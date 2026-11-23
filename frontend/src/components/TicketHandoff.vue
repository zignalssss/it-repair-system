<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { isAxiosError } from 'axios'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/lib/http'
import { escalateTicket, returnTicketToJunior, type Ticket } from '@/services/tickets'

const props = defineProps<{ ticket: Ticket }>()
const busy = defineModel<boolean>('busy', { required: true })
const emit = defineEmits<{ completed: [ticket: Ticket] }>()
const auth = useAuthStore()
const body = ref('')
const feedback = ref<{ type: 'error' | 'success'; message: string } | null>(null)
let revision = 0
let ownedRequest: number | null = null

type FormMode = 'none' | 'escalate' | 'return'
const mode = computed<FormMode>(() => {
  const user = auth.user
  const ticket = props.ticket
  if (!user?.is_active || ticket.assignee?.id !== user.id || ticket.status !== 'in_progress')
    return 'none'
  if (user.role === 'junior_technician' && ticket.support_level === 'junior') return 'escalate'
  if (user.role === 'senior_technician' && ticket.support_level === 'senior') return 'return'
  return 'none'
})
const buttonLabel = computed(() => (mode.value === 'escalate' ? 'ส่งต่อช่างอาวุโส' : 'ส่งกลับช่างระดับต้น'))

watch(
  [
    () => props.ticket.id,
    () => props.ticket.status,
    () => props.ticket.support_level,
    () => props.ticket.assignee?.id,
    () => auth.user?.id,
    () => auth.user?.role,
    () => auth.user?.is_active,
  ],
  () => {
    revision++
    if (ownedRequest !== null) {
      busy.value = false
      ownedRequest = null
    }
    body.value = ''
    feedback.value = null
  },
  { immediate: true },
)
onUnmounted(() => {
  revision++
})

async function submit() {
  const action = mode.value
  if (busy.value || action === 'none' || feedback.value?.type === 'success') return
  const text = body.value.trim()
  if (!text) {
    feedback.value = { type: 'error', message: 'กรุณาระบุเหตุผลหรือคำแนะนำในการส่งต่องาน' }
    return
  }
  const ticketId = props.ticket.id
  const currentRevision = ++revision
  ownedRequest = currentRevision
  busy.value = true
  feedback.value = null
  try {
    const updated =
      action === 'escalate'
        ? await escalateTicket(ticketId, text)
        : await returnTicketToJunior(ticketId, text)
    if (revision === currentRevision) {
      body.value = ''
      feedback.value = { type: 'success', message: 'ส่งต่องานสำเร็จแล้ว' }
      emit('completed', updated)
    }
  } catch (error) {
    if (revision === currentRevision) {
      const validationMessage =
        isAxiosError<{ errors?: { body?: string[] } }>(error) && error.response?.status === 422
          ? error.response.data.errors?.body?.[0]
          : undefined
      feedback.value = { type: 'error', message: validationMessage ?? errorMessage(error) }
    }
  } finally {
    if (revision === currentRevision) {
      ownedRequest = null
      busy.value = false
    }
  }
}
</script>

<template>
  <form
    v-if="mode !== 'none'"
    aria-label="ส่งต่องาน"
    class="mt-8 border-t border-stone-200 pt-6"
    @submit.prevent="submit"
  >
    <h2 class="text-lg font-semibold">{{ buttonLabel }}</h2>
    <fieldset :disabled="busy || feedback?.type === 'success'" class="mt-4 space-y-3">
      <label for="handoff-body" class="block text-sm font-medium">{{
        mode === 'escalate' ? 'เหตุผลและรายละเอียดที่ส่งต่อ' : 'คำแนะนำให้ช่างระดับต้น'
      }}</label>
      <textarea
        id="handoff-body"
        v-model="body"
        required
        maxlength="10000"
        rows="4"
        :aria-describedby="feedback ? 'handoff-feedback' : undefined"
        class="block w-full border border-stone-300 p-2 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-60"
      ></textarea>
      <button
        type="submit"
        class="border bg-stone-900 px-4 py-2 text-sm text-white focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50"
      >
        {{ busy ? 'กำลังส่งต่อ...' : buttonLabel }}
      </button>
    </fieldset>
    <p
      v-if="feedback"
      id="handoff-feedback"
      :role="feedback.type === 'error' ? 'alert' : 'status'"
      :class="feedback.type === 'error' ? 'text-red-700' : 'text-green-800'"
      class="mt-3 text-sm"
    >
      {{ feedback.message }}
    </p>
  </form>
</template>
