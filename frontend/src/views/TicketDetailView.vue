<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { errorMessage } from '@/lib/http'
import { useAuthStore } from '@/stores/auth'
import { cancelTicket, claimTicket, getTicket, type Ticket } from '@/services/tickets'

const route = useRoute()
const auth = useAuthStore()

const ticketId = Number(route.params.id)
const ticket = ref<Ticket | null>(null)
const loading = ref(true)
const busy = ref(false)
const error = ref('')

const canCancel = computed(
  () =>
    auth.user?.role === 'user' &&
    ticket.value?.requester?.id === auth.user.id &&
    ticket.value?.status === 'new',
)

const canClaim = computed(
  () =>
    (auth.user?.role === 'junior_technician' || auth.user?.role === 'senior_technician') &&
    !ticket.value?.assignee &&
    ['new', 'escalated', 'returned'].includes(ticket.value?.status ?? ''),
)

onMounted(async () => {
  try {
    ticket.value = await getTicket(ticketId)
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
})

async function cancel() {
  const reason = window.prompt('เหตุผลที่ยกเลิก')
  if (!reason?.trim()) return

  await run(() => cancelTicket(ticketId, reason.trim()))
}

async function claim() {
  await run(() => claimTicket(ticketId))
}

async function run(action: () => Promise<Ticket>) {
  if (busy.value) return

  busy.value = true
  error.value = ''

  try {
    ticket.value = await action()
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    busy.value = false
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
    </template>

    <p v-if="error" role="alert" class="mt-4 text-red-700">{{ error }}</p>
  </main>
</template>
