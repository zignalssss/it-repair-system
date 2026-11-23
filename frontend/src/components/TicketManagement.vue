<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { isAxiosError } from 'axios'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/lib/http'
import {
  assignTicket,
  listTechnicians,
  updateTicketPriority,
  type TechnicianPage,
} from '@/services/management'
import type { Ticket } from '@/services/tickets'
const props = defineProps<{ ticket: Ticket }>()
const busy = defineModel<boolean>('busy', { required: true })
const emit = defineEmits<{ updated: [ticket: Ticket] }>()
const auth = useAuthStore()
const canManage = computed(
  () =>
    auth.user?.is_active === true &&
    auth.user.role === 'it_manager' &&
    !['resolved', 'cancelled'].includes(props.ticket.status),
)
const technicians = ref<
  | { kind: 'loading'; page: number }
  | { kind: 'error'; page: number; message: string }
  | { kind: 'ready'; result: TechnicianPage }
>({ kind: 'loading', page: 1 })
const selected = ref(''),
  reason = ref(''),
  priority = ref<Ticket['priority']>(props.ticket.priority)
const feedback = ref<{ kind: 'success' | 'error'; message: string } | null>(null)
let revision = 0,
  listRevision = 0,
  ownsBusy = false
function releaseBusy() {
  if (ownsBusy) {
    busy.value = false
    ownsBusy = false
  }
}
watch(
  [() => props.ticket.id, () => auth.user?.id, canManage],
  () => {
    revision++
    listRevision++
    releaseBusy()
    selected.value = ''
    reason.value = ''
    feedback.value = null
    priority.value = props.ticket.priority
    if (canManage.value) void load(1)
  },
  { immediate: true },
)
watch(
  () => props.ticket.priority,
  (value) => (priority.value = value),
)
onUnmounted(() => {
  revision++
  listRevision++
  releaseBusy()
})
async function load(page: number) {
  const context = revision,
    request = ++listRevision
  selected.value = ''
  technicians.value = { kind: 'loading', page }
  try {
    const result = await listTechnicians(page)
    if (context === revision && request === listRevision)
      technicians.value = { kind: 'ready', result }
  } catch (error) {
    if (context === revision && request === listRevision)
      technicians.value = { kind: 'error', page, message: errorMessage(error) }
  }
}
async function run(action: () => Promise<Ticket>, message: string) {
  if (!canManage.value || busy.value) return
  const context = revision
  busy.value = true
  ownsBusy = true
  feedback.value = null
  try {
    const updated = await action()
    if (context === revision) {
      emit('updated', updated)
      feedback.value = { kind: 'success', message }
      reason.value = ''
    }
  } catch (error) {
    if (context === revision)
      feedback.value = {
        kind: 'error',
        message:
          isAxiosError<{ errors?: Record<string, string[]> }>(error) &&
          error.response?.status === 422
            ? Object.entries(error.response.data.errors ?? {})
                .map(([field, messages]) => `${field}: ${messages.join(' ')}`)
                .join('\n') || errorMessage(error)
            : errorMessage(error),
      }
  } finally {
    if (context === revision) releaseBusy()
  }
}
async function assign() {
  if (!canManage.value || busy.value) return
  const technician =
    technicians.value.kind === 'ready'
      ? technicians.value.result.data.find((t) => String(t.id) === selected.value)
      : undefined
  const text = reason.value.trim()
  if (
    !technician ||
    !technician.is_active ||
    !['junior_technician', 'senior_technician'].includes(technician.role) ||
    text.length > 2000
  ) {
    feedback.value = { kind: 'error', message: 'กรุณาเลือกช่างที่เปิดใช้งาน และเหตุผลไม่เกิน 2000 ตัวอักษร' }
    return
  }
  await run(
    () => assignTicket(props.ticket.id, technician.id, text || null),
    'มอบหมายงานแล้ว สถานะใบงานคงเดิม',
  )
}
async function savePriority() {
  if (!['normal', 'high', 'critical'].includes(priority.value)) return
  await run(() => updateTicketPriority(props.ticket.id, priority.value), 'ปรับความเร่งด่วนแล้ว')
}
</script>
<template>
  <section v-if="canManage" class="mt-8 border-t border-stone-200 pt-6" aria-label="จัดการใบงาน">
    <h2 class="text-lg font-semibold">จัดการใบงานโดย Manager</h2>
    <p class="mt-2 text-sm text-stone-600">
      การมอบหมายเปลี่ยนช่างและระดับตามบทบาทของช่าง สถานะและคำขอที่กำลังรอยังคงเดิม
    </p>
    <form aria-label="มอบหมายงาน" class="mt-4 space-y-3" @submit.prevent="assign">
      <fieldset :disabled="busy" class="space-y-3">
        <p v-if="technicians.kind === 'loading'" role="status">กำลังโหลดรายชื่อช่าง...</p>
        <div v-else-if="technicians.kind === 'error'">
          <p role="alert" class="text-sm text-red-700">{{ technicians.message }}</p>
          <button
            type="button"
            class="mt-2 border px-3 py-2 text-sm"
            @click="load(technicians.page)"
          >
            โหลดรายชื่ออีกครั้ง
          </button>
        </div>
        <template v-else>
          <label for="assignment-technician" class="block text-sm">ช่างผู้รับผิดชอบ</label>
          <select
            id="assignment-technician"
            v-model="selected"
            required
            class="block w-full border border-stone-300 p-2 text-sm"
          >
            <option value="">เลือกช่างในหน้านี้</option>
            <option
              v-for="technician in technicians.result.data"
              :key="technician.id"
              :value="String(technician.id)"
            >
              {{ technician.name }} · {{ technician.role }}
            </option>
          </select>
          <p v-if="!technicians.result.data.length" class="text-sm">ไม่มีช่างในหน้านี้</p>
          <nav
            v-if="technicians.result.meta.last_page > 1"
            aria-label="หน้ารายชื่อช่าง"
            class="flex items-center gap-3 text-sm"
          >
            <button
              type="button"
              :disabled="technicians.result.meta.current_page <= 1"
              class="border px-3 py-2 disabled:opacity-50"
              @click="load(technicians.result.meta.current_page - 1)"
            >
              ก่อนหน้า
            </button>
            <span
              >หน้า {{ technicians.result.meta.current_page }} /
              {{ technicians.result.meta.last_page }}</span
            >
            <button
              type="button"
              data-test="technicians-next"
              :disabled="technicians.result.meta.current_page >= technicians.result.meta.last_page"
              class="border px-3 py-2 disabled:opacity-50"
              @click="load(technicians.result.meta.current_page + 1)"
            >
              ถัดไป
            </button>
          </nav>
        </template>
        <label for="assignment-reason" class="block text-sm">เหตุผลการมอบหมาย (ไม่บังคับ)</label>
        <textarea
          id="assignment-reason"
          v-model="reason"
          maxlength="2000"
          rows="3"
          class="block w-full border border-stone-300 p-2 text-sm"
        ></textarea>
        <button
          type="submit"
          :disabled="technicians.kind !== 'ready' || !selected"
          class="border bg-stone-900 px-3 py-2 text-sm text-white disabled:opacity-50"
        >
          มอบหมายงาน
        </button>
      </fieldset>
    </form>
    <form aria-label="ความเร่งด่วน" class="mt-6 space-y-3" @submit.prevent="savePriority">
      <fieldset :disabled="busy" class="space-y-3">
        <label for="ticket-priority" class="block text-sm">ความเร่งด่วน</label>
        <select id="ticket-priority" v-model="priority" class="border border-stone-300 p-2 text-sm">
          <option value="normal">ปกติ</option>
          <option value="high">สูง</option>
          <option value="critical">วิกฤต</option>
        </select>
        <button type="submit" class="ml-2 border px-3 py-2 text-sm disabled:opacity-50">
          บันทึกความเร่งด่วน
        </button>
      </fieldset>
    </form>
    <p
      v-if="feedback"
      :role="feedback.kind === 'error' ? 'alert' : 'status'"
      class="mt-3 whitespace-pre-line text-sm"
      :class="feedback.kind === 'error' ? 'text-red-700' : 'text-green-800'"
    >
      {{ feedback.message }}
    </p>
  </section>
</template>
