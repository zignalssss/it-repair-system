<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { isAxiosError } from 'axios'
import TicketHistory from '@/components/TicketHistory.vue'
import TicketInformation from '@/components/TicketInformation.vue'
import TicketHandoff from '@/components/TicketHandoff.vue'
import TicketEquipment from '@/components/TicketEquipment.vue'
import TicketResolution from '@/components/TicketResolution.vue'
import TicketManagement from '@/components/TicketManagement.vue'
import { useRoute, useRouter } from 'vue-router'
import { errorMessage } from '@/lib/http'
import { useAuthStore } from '@/stores/auth'
import {
  addTicketWorkLog,
  cancelTicket,
  claimTicket,
  getTicket,
  type Ticket,
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
const handoffCompleted = ref(false)
const error = ref('')
const workLogBody = ref('')
const workLogFeedback = ref<{ type: 'success' | 'error'; message: string } | null>(null)

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
  <main class="mx-auto max-w-xl p-6">
    <RouterLink to="/" class="text-sm underline">← กลับ</RouterLink>

    <p v-if="loading" class="mt-4">กำลังโหลด...</p>

    <div v-else-if="handoffCompleted" class="mt-4 space-y-3">
      <p role="status" class="text-sm text-green-800">ส่งต่องานสำเร็จแล้ว กำลังกลับหน้ารายการ</p>
      <RouterLink :to="{ name: 'home' }" class="text-sm underline">กลับรายการใบแจ้งซ่อม</RouterLink>
    </div>

    <template v-else-if="ticket">
      <h1 class="mt-4 text-xl font-semibold">{{ ticket.ticket_no }} · {{ ticket.title }}</h1>

      <dl class="mt-4 grid grid-cols-[8rem_1fr] gap-2">
        <dt>สถานะ</dt>
        <dd>{{ ticket.status }}</dd>
        <dt>ความสำคัญ</dt>
        <dd>{{ ticket.priority }}</dd>
        <dt>ระดับ</dt>
        <dd>{{ ticket.support_level }}</dd>
        <dt>ผู้แจ้ง</dt>
        <dd>{{ ticket.requester?.name ?? '-' }}</dd>
        <dt>แผนก</dt>
        <dd>{{ ticket.department?.name ?? '-' }}</dd>
        <dt>ผู้รับผิดชอบ</dt>
        <dd>{{ ticket.assignee?.name ?? 'ยังไม่มี' }}</dd>
        <dt>สถานที่</dt>
        <dd>{{ ticket.location ?? '-' }}</dd>
        <dt>รหัสอุปกรณ์</dt>
        <dd>{{ ticket.asset_label ?? '-' }}</dd>
        <dt>รายละเอียด</dt>
        <dd class="whitespace-pre-line">{{ ticket.description }}</dd>
      </dl>

      <div class="mt-6 flex gap-2">
        <button v-if="canCancel" :disabled="busy" class="border px-4 py-2" @click="cancel">
          ยกเลิกใบงาน
        </button>
        <button v-if="canClaim" :disabled="busy" class="border px-4 py-2" @click="claim">
          รับงาน
        </button>
      </div>

      <form
        v-if="canAddWorkLog"
        class="mt-8 border-t border-stone-200 pt-6"
        aria-label="บันทึกการซ่อม"
        @submit.prevent="saveWorkLog"
      >
        <h2 class="text-lg font-semibold">บันทึกการซ่อม</h2>
        <fieldset :disabled="busy" class="mt-4 space-y-3">
          <label for="work-log-body" class="block text-sm font-medium">รายละเอียดการซ่อม</label>
          <textarea
            id="work-log-body"
            v-model="workLogBody"
            required
            maxlength="10000"
            rows="4"
            :aria-describedby="workLogFeedback ? 'work-log-feedback' : undefined"
            class="block w-full border border-stone-300 p-2 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-60"
          ></textarea>
          <p
            v-if="workLogFeedback"
            id="work-log-feedback"
            :role="workLogFeedback.type === 'error' ? 'alert' : 'status'"
            :class="workLogFeedback.type === 'error' ? 'text-red-700' : 'text-green-800'"
            class="text-sm"
          >
            {{ workLogFeedback.message }}
          </p>
          <button
            type="submit"
            class="border bg-stone-900 px-4 py-2 text-sm text-white focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50"
          >
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
      />

      <TicketResolution :key="ticket.id" :ticket="ticket" v-model:busy="busy" @updated="updateTicket" />
      <TicketManagement :key="ticket.id" :ticket="ticket" v-model:busy="busy" @updated="updateTicket" />

      <section v-if="ticket.status === 'resolved'" aria-label="ผลการปิดงาน" class="mt-8 border-t border-stone-200 pt-6">
        <h2 class="text-lg font-semibold">ปิดงานแล้ว</h2>
        <p class="mt-2 text-sm">ผู้ปิดงาน {{ ticket.resolved_by?.name ?? '-' }} · {{ ticket.resolved_at ?? '-' }}</p>
        <h3 class="mt-3 font-medium">อะไหล่ที่ใช้จริง</h3>
        <ul v-if="ticket.parts_used?.length" class="mt-2 text-sm"><li v-for="part in ticket.parts_used" :key="part.id">{{ part.name }} · จำนวน {{ part.quantity }}</li></ul>
        <p v-else class="mt-2 text-sm">ไม่ได้บันทึกอะไหล่ที่ใช้จริง</p>
      </section>
      <a v-if="auth.user?.role === 'it_manager' && auth.user.is_active" :href="`/api/v1/reports/tickets/${ticket.id}/pdf`" download class="mt-6 inline-block border px-3 py-2 text-sm">ดาวน์โหลดใบแจ้งซ่อม PDF</a>

      <TicketHistory :key="`${ticket.id}-${historyRevision}`" :ticket-id="ticket.id" />
    </template>

    <p v-if="error" role="alert" class="mt-4 text-red-700">{{ error }}</p>
  </main>
</template>
