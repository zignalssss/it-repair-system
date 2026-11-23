<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { errorMessage as apiErrorMessage } from '@/lib/http'
import { listTickets, type Ticket } from '@/services/tickets'

const auth = useAuthStore()
const router = useRouter()

const loggingOut = ref(false)
const errorMessage = ref('')

const tickets = ref<Ticket[]>([])
const ticketsLoading = ref(true)
const ticketsError = ref('')

onMounted(async () => {
  try {
    tickets.value = await listTickets()
  } catch (e) {
    ticketsError.value = apiErrorMessage(e)
  } finally {
    ticketsLoading.value = false
  }
})

async function handleLogout() {
  if (loggingOut.value) return

  loggingOut.value = true
  errorMessage.value = ''

  try {
    await auth.logout()
    await router.replace({ name: 'login' })
  } catch {
    errorMessage.value = 'ออกจากระบบไม่สำเร็จ กรุณาลองใหม่'
  } finally {
    loggingOut.value = false
  }
}
</script>

<template>
  <main v-if="auth.user" class="min-h-dvh">
    <header class="bg-stone-950 text-stone-100">
      <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
        <div class="flex items-center gap-3">
          <span
            class="relative inline-flex items-center rounded-sm bg-signal-500 py-1 pr-2.5 pl-5 font-mono text-xs font-semibold tracking-wider text-stone-950"
          >
            <span class="absolute left-1.5 size-1.5 rounded-full bg-stone-950" aria-hidden="true"></span>
            IT
          </span>
          <h1 class="text-sm font-medium text-stone-200">ระบบแจ้งซ่อม</h1>
        </div>

        <button
          :disabled="loggingOut"
          class="h-9 rounded-md px-3 text-sm font-medium text-stone-300 ring-1 ring-stone-700 transition-colors hover:bg-stone-800 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-signal-500 disabled:cursor-wait disabled:opacity-60"
          @click="handleLogout"
        >
          {{ loggingOut ? 'กำลังออกจากระบบ...' : 'ออกจากระบบ' }}
        </button>
      </div>
    </header>

    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 sm:py-14">
      <p
        v-if="errorMessage"
        role="alert"
        class="mb-8 border-l-2 border-red-600 bg-red-50 px-3 py-2.5 text-sm text-red-800"
      >
        {{ errorMessage }}
      </p>

      <p class="text-sm text-stone-500">สวัสดี</p>
      <p class="mt-1 text-3xl font-semibold break-words text-stone-950 sm:text-4xl">{{ auth.user.name }}</p>

      <!-- Account card, styled as a maintenance tag -->
      <section class="mt-10 max-w-xl overflow-hidden rounded-lg bg-white ring-1 ring-stone-200" aria-label="ข้อมูลบัญชี">
        <div class="flex items-center gap-2 border-b border-dashed border-stone-300 px-5 py-3">
          <span class="size-2 rounded-full bg-signal-500 ring-2 ring-signal-200" aria-hidden="true"></span>
          <span class="text-xs font-medium text-stone-500">ข้อมูลบัญชี</span>
        </div>

        <dl class="divide-y divide-stone-100">
          <div class="grid gap-1 px-5 py-4 sm:grid-cols-[8rem_1fr] sm:gap-4">
            <dt class="text-sm text-stone-500">ชื่อ</dt>
            <dd class="text-sm font-medium break-words text-stone-950">{{ auth.user.name }}</dd>
          </div>

          <div class="grid gap-1 px-5 py-4 sm:grid-cols-[8rem_1fr] sm:gap-4">
            <dt class="text-sm text-stone-500">อีเมล</dt>
            <dd class="font-mono text-sm break-all text-stone-950">{{ auth.user.email }}</dd>
          </div>

          <div class="grid gap-1 px-5 py-4 sm:grid-cols-[8rem_1fr] sm:gap-4">
            <dt class="text-sm text-stone-500">บทบาท</dt>
            <dd>
              <span
                class="inline-flex rounded-sm bg-signal-50 px-2 py-0.5 font-mono text-xs font-semibold text-signal-700 ring-1 ring-signal-200"
              >
                {{ auth.user.role }}
              </span>
            </dd>
          </div>

          <div class="grid gap-1 px-5 py-4 sm:grid-cols-[8rem_1fr] sm:gap-4">
            <dt class="text-sm text-stone-500">แผนก</dt>
            <dd class="text-sm font-medium text-stone-950">
              {{ auth.user.department?.name ?? 'ไม่มีแผนก' }}
            </dd>
          </div>
        </dl>
      </section>

      <!-- Tickets: logic first, styling later -->
      <section class="mt-10">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold">ใบแจ้งซ่อม</h2>
          <RouterLink
            v-if="auth.user.role === 'user'"
            :to="{ name: 'ticket-create' }"
            class="border bg-stone-900 px-3 py-1.5 text-sm text-white"
          >
            + แจ้งซ่อม
          </RouterLink>
        </div>

        <p v-if="ticketsLoading" class="mt-4">กำลังโหลด...</p>
        <p v-else-if="ticketsError" role="alert" class="mt-4 text-red-700">{{ ticketsError }}</p>
        <p v-else-if="tickets.length === 0" class="mt-4">ยังไม่มีใบแจ้งซ่อม</p>

        <table v-else class="mt-4 w-full text-left text-sm">
          <thead>
            <tr>
              <th class="p-2">เลขที่</th>
              <th class="p-2">หัวข้อ</th>
              <th class="p-2">สถานะ</th>
              <th class="p-2">ผู้แจ้ง</th>
              <th class="p-2">ผู้รับผิดชอบ</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ticket in tickets" :key="ticket.id" class="border-t">
              <td class="p-2">
                <RouterLink :to="{ name: 'ticket-detail', params: { id: ticket.id } }" class="underline">
                  {{ ticket.ticket_no }}
                </RouterLink>
              </td>
              <td class="p-2">{{ ticket.title }}</td>
              <td class="p-2">{{ ticket.status }}</td>
              <td class="p-2">{{ ticket.requester?.name ?? '-' }}</td>
              <td class="p-2">{{ ticket.assignee?.name ?? '-' }}</td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>
  </main>
</template>
