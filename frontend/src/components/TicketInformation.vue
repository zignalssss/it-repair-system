<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { isAxiosError } from 'axios'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/lib/http'
import {
  requestTicketInformation,
  respondTicketInformation,
  type PendingInformationRequest,
  type Ticket,
} from '@/services/tickets'

const props = defineProps<{ ticket: Ticket }>()
const busy = defineModel<boolean>('busy', { required: true })
const emit = defineEmits<{ updated: [ticket: Ticket] }>()
const auth = useAuthStore()
const body = ref('')
const files = ref<File[]>([])
const fileInput = ref<HTMLInputElement | null>(null)
const feedback = ref<{ type: 'success' | 'error'; message: string } | null>(null)
let revision = 0
let ownedRequest: number | null = null

type FormMode ={ kind: 'ask' } | { kind: 'respond'; request: PendingInformationRequest } | { kind: 'readonly' }
const pending = computed(() => props.ticket.pending_information_request)
const mode = computed<FormMode>(() => {
  const user = auth.user
  const ticket = props.ticket
  if (!user?.is_active) return { kind: 'readonly' }
  if (
    (user.role === 'junior_technician' || user.role === 'senior_technician') &&
    ticket.assignee?.id === user.id &&
    ticket.status === 'in_progress' &&
    !pending.value
  ) {
    return { kind: 'ask' }
  }
  if (
    user.role === 'user' &&
    ticket.requester?.id === user.id &&
    user.department !== null &&
    ticket.department != null &&
    user.department.id === ticket.department.id &&
    ticket.status === 'waiting_info' &&
    pending.value
  ) {
    return { kind: 'respond', request: pending.value }
  }
  return { kind: 'readonly' }
})

function clearFiles() {
  files.value = []
  if (fileInput.value) fileInput.value.value = ''
}
watch(
  [() => props.ticket.id, () => pending.value?.id],
  () => {
    revision++
    if (ownedRequest !== null) {
      busy.value = false
      ownedRequest = null
    }
    body.value = ''
    clearFiles()
    feedback.value = null
  },
  { immediate: true },
)
onUnmounted(() => {
  revision++
})

function pickFiles(event: Event) {
  if (event.target instanceof HTMLInputElement) files.value = Array.from(event.target.files ?? [])
}

async function submit() {
  const action = mode.value
  if (busy.value || action.kind === 'readonly') return
  const text = body.value.trim()
  if (!text && (action.kind === 'ask' || files.value.length === 0)) {
    feedback.value = {
      type: 'error',
      message:
        action.kind === 'ask' ? 'กรุณาระบุข้อมูลที่ต้องการเพิ่มเติม' : 'กรุณากรอกข้อมูลหรือแนบไฟล์อย่างน้อยหนึ่งไฟล์',
    }
    return
  }
  if (files.value.length > 3) {
    feedback.value = { type: 'error', message: 'แนบได้ไม่เกิน 3 ไฟล์' }
    return
  }
  if (files.value.some((file) => file.size > 2097152)) {
    feedback.value = { type: 'error', message: 'ไฟล์แต่ละไฟล์ต้องไม่เกิน 2 MB' }
    return
  }
  const ticketId = props.ticket.id
  const currentRevision = ++revision
  ownedRequest = currentRevision
  busy.value = true
  feedback.value = null
  try {
    const updated =
      action.kind === 'ask'
        ? await requestTicketInformation(ticketId, text)
        : await respondTicketInformation(ticketId, action.request.id, {
            body: text,
            files: [...files.value],
          })
    if (revision === currentRevision) {
      body.value = ''
      clearFiles()
      feedback.value = {
        type: 'success',
        message: action.kind === 'ask' ? 'ขอข้อมูลเพิ่มเติมแล้ว' : 'ส่งข้อมูลเพิ่มเติมแล้ว',
      }
      emit('updated', updated)
    }
  } catch (error) {
    if (revision === currentRevision) {
      const errors =
        isAxiosError<{ errors?: Record<string, string[]> }>(error) && error.response?.status === 422
          ? error.response.data.errors
          : undefined
      const fieldError =
        errors &&
        Object.entries(errors).find(
          ([key]) => key === 'body' || key === 'files' || key.startsWith('files.'),
        )?.[1]?.[0]
      feedback.value = { type: 'error', message: fieldError || errorMessage(error) }
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
  <section
    v-if="mode.kind !== 'readonly' || pending || feedback"
    class="mt-8 border-t border-stone-200 pt-6"
    aria-label="ข้อมูลเพิ่มเติม"
  >
    <div v-if="pending && ticket.status === 'waiting_info'" class="space-y-2">
      <h2 class="text-lg font-semibold">รอข้อมูลเพิ่มเติม</h2>
      <p class="text-sm text-stone-600">
        {{ pending.question.author?.name ?? 'ช่างผู้รับผิดชอบ' }} ขอข้อมูลเพิ่มเติม
      </p>
      <p class="whitespace-pre-line text-sm">{{ pending.question.body }}</p>
      <p v-if="mode.kind === 'readonly'" class="text-sm text-stone-600">
        รอเจ้าของใบแจ้งซ่อมส่งข้อมูลเพิ่มเติม
      </p>
    </div>
    <form v-if="mode.kind === 'ask'" aria-label="ขอข้อมูลเพิ่มเติม" @submit.prevent="submit">
      <h2 class="text-lg font-semibold">ขอข้อมูลเพิ่มเติม</h2>
      <fieldset :disabled="busy" class="mt-4 space-y-3">
        <label for="info-question-body" class="block text-sm font-medium">ข้อมูลที่ต้องการเพิ่มเติม</label>
        <textarea
          id="info-question-body"
          v-model="body"
          required
          maxlength="10000"
          rows="4"
          :aria-describedby="feedback ? 'info-feedback' : undefined"
          class="block w-full border border-stone-300 p-2 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-60"
        ></textarea>
        <button
          type="submit"
          class="border bg-stone-900 px-4 py-2 text-sm text-white focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50"
        >
          {{ busy ? 'กำลังส่ง...' : 'ขอข้อมูลเพิ่มเติม' }}
        </button>
      </fieldset>
    </form>
    <form
      v-else-if="mode.kind === 'respond'"
      class="mt-4"
      aria-label="ตอบข้อมูลเพิ่มเติม"
      @submit.prevent="submit"
    >
      <h3 class="font-medium">ตอบข้อมูลเพิ่มเติม</h3>
      <fieldset :disabled="busy" class="mt-3 space-y-3">
        <label for="info-response-body" class="block text-sm font-medium">ข้อมูลเพิ่มเติม</label>
        <textarea
          id="info-response-body"
          v-model="body"
          maxlength="10000"
          rows="4"
          :aria-describedby="feedback ? 'info-feedback' : undefined"
          class="block w-full border border-stone-300 p-2 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-60"
        ></textarea>
        <label for="info-response-files" class="block text-sm font-medium">ไฟล์แนบ</label>
        <p class="text-xs text-stone-600">
          กรอกข้อความหรือแนบไฟล์ สูงสุด 3 ไฟล์ ไฟล์ละไม่เกิน 2 MB รองรับ JPG, PNG, WebP, PDF, MP4 และ WebM
        </p>
        <input
          id="info-response-files"
          ref="fileInput"
          type="file"
          multiple
          accept=".jpg,.jpeg,.png,.webp,.pdf,.mp4,.webm"
          class="block w-full text-sm focus-visible:outline-2 focus-visible:outline-offset-2"
          @change="pickFiles"
        />
        <ul v-if="files.length" class="space-y-1 text-sm">
          <li v-for="(file, index) in files" :key="index" class="break-all">
            {{ file.name }} · {{ Math.ceil(file.size / 1024) }} KB
          </li>
        </ul>
        <button
          v-if="files.length"
          type="button"
          class="border px-3 py-1 text-sm focus-visible:outline-2 focus-visible:outline-offset-2"
          @click="clearFiles"
        >
          ล้างไฟล์แนบ
        </button>
        <div>
          <button
            type="submit"
            class="border bg-stone-900 px-4 py-2 text-sm text-white focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50"
          >
            {{ busy ? 'กำลังส่ง...' : 'ตอบข้อมูลเพิ่มเติม' }}
          </button>
        </div>
      </fieldset>
    </form>
    <p
      v-if="feedback"
      id="info-feedback"
      :role="feedback.type === 'error' ? 'alert' : 'status'"
      :class="feedback.type === 'error' ? 'text-red-700' : 'text-green-800'"
      class="mt-3 text-sm"
    >
      {{ feedback.message }}
    </p>
  </section>
</template>
