<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { isAxiosError } from 'axios'
import TicketHistory from '@/components/TicketHistory.vue'
import TicketInformation from '@/components/TicketInformation.vue'
import TicketHandoff from '@/components/TicketHandoff.vue'
import TicketEquipment from '@/components/TicketEquipment.vue'
import TicketResolution from '@/components/TicketResolution.vue'
import TicketManagement from '@/components/TicketManagement.vue'
import TrackingStrip from '@/components/TrackingStrip.vue'
import PriorityBadge from '@/components/PriorityBadge.vue'
import { ArrowLeft, Download } from 'lucide-vue-next'
import { levelLabels, passedStops, statusLabels } from '@/lib/ticketLabels'
import { useRoute, useRouter } from 'vue-router'
import { errorMessage } from '@/lib/http'
import { useAuthStore } from '@/stores/auth'
import {
  addTicketWorkLog,
  cancelTicket,
  claimTicket,
  getTicket,
  type Ticket,
  type EquipmentRequest,
  type TicketEvent,
} from '@/services/tickets'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const ticketId = computed(() => Number(route.params.id))
// Ignore responses from an earlier route, including in-flight actions.
let revision = 0
const ticket = ref<Ticket | null>(null)
const loading = ref(true)
const busy = ref(false)
const historyRevision = ref(0)
// Stops proven by the history page and by approved equipment requests the viewer can see.
const historyPassed = ref<ReadonlySet<number>>(new Set())
const equipmentApproved = ref(false)
const passed = computed<ReadonlySet<number>>(() =>
  equipmentApproved.value ? new Set([...historyPassed.value, 3]) : historyPassed.value,
)
function trackHistory(events: TicketEvent[]) {
  historyPassed.value = passedStops(events)
}
function trackEquipment(requests: EquipmentRequest[]) {
  equipmentApproved.value = requests.some((request) => request.status === 'approved')
}
const handoffCompleted = ref(false)
const error = ref('')
const workLogBody = ref('')
const workLogFeedback = ref<{ type: 'success' | 'error'; message: string } | null>(null)
const dateFormat = new Intl.DateTimeFormat('th-TH', {
  dateStyle: 'medium',
  timeStyle: 'short',
  timeZone: 'Asia/Bangkok',
})
function formatDate(value: string | null | undefined) {
  if (!value) return '-'
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : dateFormat.format(date)
}

const canCancel = computed(
  () =>
    auth.user?.role === 'user' &&
    ticket.value?.requester?.id === auth.user.id &&
    ticket.value?.status === 'new',
)

const canClaim = computed(() => {
  const user = auth.user
  const current = ticket.value
  if (!user || !current) return false

  const level =
    user.role === 'junior_technician'
      ? 'junior'
      : user.role === 'senior_technician'
        ? 'senior'
        : null

  return (
    level !== null &&
    current.support_level === level &&
    ['new', 'escalated', 'returned'].includes(current.status) &&
    (!current.assignee || current.assignee.id === user.id)
  )
})

const canAddWorkLog = computed(() => {
  const user = auth.user
  return (
    user?.is_active === true &&
    (user.role === 'junior_technician' || user.role === 'senior_technician') &&
    ticket.value?.assignee?.id === user.id &&
    ticket.value.status === 'in_progress'
  )
})

watch(
  ticketId,
  async (id, _previousId, onCleanup) => {
    const currentRevision = ++revision
    onCleanup(() => {
      if (revision === currentRevision) revision++
    })

    ticket.value = null
    historyPassed.value = new Set()
    equipmentApproved.value = false
    loading.value = true
    busy.value = false
    error.value = ''
    handoffCompleted.value = false
    workLogBody.value = ''
    workLogFeedback.value = null

    try {
      const result = await getTicket(id)
      if (revision === currentRevision) ticket.value = result
    } catch (e) {
      if (revision === currentRevision) error.value = errorMessage(e)
    } finally {
      if (revision === currentRevision) loading.value = false
    }
  },
  { immediate: true },
)

async function cancel() {
  if (!ticket.value || !canCancel.value || busy.value) return
  const id = ticket.value.id
  const reason = window.prompt('เหตุผลที่ยกเลิก')
  if (!reason?.trim()) return

  await run(() => cancelTicket(id, reason.trim()))
}

async function claim() {
  if (!ticket.value || !canClaim.value) return
  const id = ticket.value.id
  await run(() => claimTicket(id))
}

async function saveWorkLog() {
  if (busy.value || !canAddWorkLog.value || !ticket.value) return

  const body = workLogBody.value.trim()
  if (!body) {
    workLogFeedback.value = { type: 'error', message: 'กรุณาระบุรายละเอียดการซ่อม' }
    return
  }

  const id = ticket.value.id
  const currentRevision = revision
  busy.value = true
  workLogFeedback.value = null

  try {
    await addTicketWorkLog(id, body)
    if (revision === currentRevision) {
      workLogBody.value = ''
      workLogFeedback.value = { type: 'success', message: 'บันทึกการซ่อมแล้ว' }
      historyRevision.value++
    }
  } catch (error) {
    if (revision === currentRevision) {
      const validationMessage =
        isAxiosError<{ errors?: { body?: string[] } }>(error) && error.response?.status === 422
          ? error.response.data.errors?.body?.[0]
          : undefined
      workLogFeedback.value = { type: 'error', message: validationMessage ?? errorMessage(error) }
    }
  } finally {
    if (revision === currentRevision) busy.value = false
  }
}

async function completeHandoff(updated: Ticket) {
  const currentRevision = revision
  handoffCompleted.value = true
  error.value = ''
  try {
    await router.replace({ name: 'home', query: { handoff: updated.status } })
  } catch {
    if (revision === currentRevision) {
      error.value = 'ส่งต่อสำเร็จแล้ว แต่เปิดหน้ารายการไม่สำเร็จ กรุณากดกลับรายการ'
    }
  }
}

function updateTicket(updated: Ticket) {
  ticket.value = updated
  historyRevision.value++
}

async function run(action: () => Promise<Ticket>) {
  if (busy.value) return

  const currentRevision = revision
  busy.value = true
  error.value = ''

  try {
    const result = await action()
    if (revision === currentRevision) {
      updateTicket(result)
    }
  } catch (e) {
    if (revision === currentRevision) error.value = errorMessage(e)
  } finally {
    if (revision === currentRevision) busy.value = false
  }
}
</script>

<template>
  <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
    <RouterLink
      to="/"
      class="inline-flex items-center gap-1.5 text-sm text-ink-600 hover:text-ink-950"
    >
      <ArrowLeft class="size-4" aria-hidden="true" />
      กลับรายการใบแจ้งซ่อม
    </RouterLink>

    <p v-if="loading" role="status" class="hint mt-8">กำลังโหลดใบแจ้งซ่อม...</p>

    <div v-else-if="handoffCompleted" class="panel space-y-3">
      <p role="status" class="note-ok">ส่งต่องานสำเร็จแล้ว กำลังกลับหน้ารายการ</p>
      <RouterLink :to="{ name: 'home' }" class="text-sm underline">กลับรายการใบแจ้งซ่อม</RouterLink>
    </div>

    <template v-else-if="ticket">
      <header class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
          <h1 class="text-2xl font-semibold break-words text-ink-950">
            <span class="code block text-sm font-semibold text-ink-600">{{
              ticket.ticket_no
            }}</span>
            {{ ticket.title }}
          </h1>
          <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
            <span class="font-semibold">{{ statusLabels[ticket.status] ?? ticket.status }}</span>
            <PriorityBadge :priority="ticket.priority" />
            <span class="text-ink-600">{{
              levelLabels[ticket.support_level] ?? ticket.support_level
            }}</span>
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button
            v-if="canClaim"
            type="button"
            :disabled="busy"
            class="btn btn-primary"
            @click="claim"
          >
            รับงาน
          </button>
          <button v-if="canCancel" type="button" :disabled="busy" class="btn" @click="cancel">
            ยกเลิกใบงาน
          </button>
          <a
            v-if="auth.user?.role === 'it_manager' && auth.user.is_active"
            :href="`/api/v1/reports/tickets/${ticket.id}/pdf`"
            download
            class="btn"
          >
            <Download class="size-4" aria-hidden="true" />
            ดาวน์โหลดใบแจ้งซ่อม PDF
          </a>
        </div>
      </header>

      <p v-if="error" role="alert" class="banner-error mt-4">{{ error }}</p>

      <div class="panel">
        <TrackingStrip :ticket="ticket" :passed="passed" large />
      </div>

      <div
        class="grid items-start gap-x-6 lg:grid-cols-[minmax(0,1fr)_24rem] lg:grid-rows-[auto_1fr]"
      >
        <div class="min-w-0 lg:col-start-1 lg:row-start-1">
          <section class="panel" aria-label="รายละเอียดใบแจ้งซ่อม">
            <h2 class="panel-title">รายละเอียด</h2>
            <p class="mt-2 text-sm leading-relaxed break-words whitespace-pre-line text-ink-950">
              {{ ticket.description }}
            </p>
            <dl
              class="mt-5 grid gap-x-6 gap-y-3 border-t border-ink-100 pt-4 text-sm sm:grid-cols-2"
            >
              <div>
                <dt class="hint">ผู้แจ้ง</dt>
                <dd class="font-medium">{{ ticket.requester?.name ?? '-' }}</dd>
              </div>
              <div>
                <dt class="hint">แผนก</dt>
                <dd class="font-medium">{{ ticket.department?.name ?? '-' }}</dd>
              </div>
              <div>
                <dt class="hint">ผู้รับผิดชอบ</dt>
                <dd class="font-medium">{{ ticket.assignee?.name ?? 'ยังไม่มี' }}</dd>
              </div>
              <div>
                <dt class="hint">แจ้งเมื่อ</dt>
                <dd class="code">{{ formatDate(ticket.created_at) }}</dd>
              </div>
              <div>
                <dt class="hint">สถานที่</dt>
                <dd class="font-medium break-words">{{ ticket.location ?? '-' }}</dd>
              </div>
              <div>
                <dt class="hint">รหัสอุปกรณ์</dt>
                <dd class="code break-all">{{ ticket.asset_label ?? '-' }}</dd>
              </div>
            </dl>
          </section>

          <section v-if="ticket.status === 'resolved'" aria-label="ผลการปิดงาน" class="panel">
            <h2 class="panel-title text-scan-700">ปิดงานแล้ว</h2>
            <p class="mt-2 text-sm">
              ผู้ปิดงาน {{ ticket.resolved_by?.name ?? '-' }} ·
              <span class="code">{{ formatDate(ticket.resolved_at) }}</span>
            </p>
            <h3 class="mt-4 text-sm font-semibold">อะไหล่ที่ใช้จริง</h3>
            <ul v-if="ticket.parts_used?.length" class="mt-2 divide-y divide-ink-100 text-sm">
              <li
                v-for="part in ticket.parts_used"
                :key="part.id"
                class="flex justify-between gap-4 py-1.5"
              >
                <span class="break-words">{{ part.name }}</span>
                <span class="shrink-0"
                  >จำนวน <span class="code">{{ part.quantity }}</span></span
                >
              </li>
            </ul>
            <p v-else class="hint mt-2">ไม่ได้บันทึกอะไหล่ที่ใช้จริง</p>
          </section>
        </div>

        <aside class="min-w-0 lg:sticky lg:top-4 lg:col-start-2 lg:row-span-2 lg:row-start-1">
          <TicketHistory
            :key="`${ticket.id}-${historyRevision}`"
            :ticket-id="ticket.id"
            @loaded="trackHistory"
          />
        </aside>

        <div class="min-w-0 lg:col-start-1 lg:row-start-2">
          <form
            v-if="canAddWorkLog"
            class="panel"
            aria-label="บันทึกการซ่อม"
            @submit.prevent="saveWorkLog"
          >
            <h2 class="panel-title">บันทึกการซ่อม</h2>
            <fieldset :disabled="busy" class="mt-3 space-y-3">
              <label for="work-log-body" class="label">รายละเอียดการซ่อม</label>
              <textarea
                id="work-log-body"
                v-model="workLogBody"
                required
                maxlength="10000"
                rows="4"
                :aria-describedby="workLogFeedback ? 'work-log-feedback' : undefined"
                class="field"
              ></textarea>
              <p
                v-if="workLogFeedback"
                id="work-log-feedback"
                :role="workLogFeedback.type === 'error' ? 'alert' : 'status'"
                :class="workLogFeedback.type === 'error' ? 'note-error' : 'note-ok'"
              >
                {{ workLogFeedback.message }}
              </p>
              <button type="submit" class="btn btn-primary">
                {{ busy ? 'กำลังบันทึก...' : 'บันทึกการซ่อม' }}
              </button>
            </fieldset>
          </form>

          <TicketInformation
            :key="ticket.id"
            :ticket="ticket"
            v-model:busy="busy"
            @updated="updateTicket"
          />
          <TicketHandoff
            :key="ticket.id"
            :ticket="ticket"
            v-model:busy="busy"
            @completed="completeHandoff"
          />
          <TicketEquipment
            :key="ticket.id"
            :ticket="ticket"
            v-model:busy="busy"
            @updated="updateTicket"
            @loaded="trackEquipment"
          />
          <TicketResolution
            :key="ticket.id"
            :ticket="ticket"
            v-model:busy="busy"
            @updated="updateTicket"
          />
          <TicketManagement
            :key="ticket.id"
            :ticket="ticket"
            v-model:busy="busy"
            @updated="updateTicket"
          />
        </div>
      </div>
    </template>

    <p v-if="error && (!ticket || handoffCompleted)" role="alert" class="banner-error mt-6">
      {{ error }}
    </p>
  </main>
</template>
