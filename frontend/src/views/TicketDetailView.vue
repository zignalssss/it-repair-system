<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import TicketHistory from '@/components/TicketHistory.vue'
import { useRoute } from 'vue-router'
import { errorMessage } from '@/lib/http'
import { useAuthStore } from '@/stores/auth'
import { cancelTicket, claimTicket, getTicket, type Ticket } from '@/services/tickets'

const route = useRoute()
const auth = useAuthStore()

const ticketId = computed(() => Number(route.params.id))
// Ignore responses from an earlier route, including in-flight actions.
let revision = 0
const ticket = ref<Ticket | null>(null)
const loading = ref(true)
const busy = ref(false)
const historyRevision = ref(0)
const error = ref('')

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

async function run(action: () => Promise<Ticket>) {
  if (busy.value) return

  const currentRevision = revision
  busy.value = true
  error.value = ''

  try {
    const result = await action()
    if (revision === currentRevision) {
      ticket.value = result
      historyRevision.value++
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

      <TicketHistory :key="`${ticket.id}-${historyRevision}`" :ticket-id="ticket.id" />
    </template>

    <p v-if="error" role="alert" class="mt-4 text-red-700">{{ error }}</p>
  </main>
</template>
