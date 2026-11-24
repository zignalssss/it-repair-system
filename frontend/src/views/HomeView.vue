<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Plus } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { errorMessage as apiErrorMessage } from '@/lib/http'
import { statusLabels } from '@/lib/ticketLabels'
import TrackingStrip from '@/components/TrackingStrip.vue'
import PriorityBadge from '@/components/PriorityBadge.vue'
import { listTickets, type Ticket, type TicketPagination } from '@/services/tickets'

const auth = useAuthStore()
const route = useRoute()
const handoffMessage = computed(() => {
  if (route.query.handoff === 'escalated') return 'ส่งต่อช่างอาวุโสแล้ว งานอยู่ในคิวรอรับเรื่อง'
  if (route.query.handoff === 'returned') return 'ส่งกลับช่างระดับต้นแล้ว งานอยู่ในคิวรอรับเรื่อง'
  return ''
})

const tickets = ref<Ticket[]>([])
const ticketsLoading = ref(false)
const pagination = ref<TicketPagination | null>(null)
const ticketsError = ref('')

const dateFormat = new Intl.DateTimeFormat('th-TH', {
  day: 'numeric',
  month: 'short',
  hour: '2-digit',
  minute: '2-digit',
  timeZone: 'Asia/Bangkok',
})

onMounted(() => loadTickets())

async function loadTickets(page = 1) {
  if (ticketsLoading.value) return

  ticketsLoading.value = true
  ticketsError.value = ''

  try {
    const result = await listTickets(page)
    tickets.value = result.data
    pagination.value = result.meta
  } catch (e) {
    ticketsError.value = apiErrorMessage(e)
  } finally {
    ticketsLoading.value = false
  }
}

function formatDate(value: string) {
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? '' : dateFormat.format(date)
}

function statusTone(status: Ticket['status']) {
  if (status === 'resolved') return 'text-scan-700'
  if (status === 'cancelled') return 'text-ink-500'
  if (status === 'waiting_info' || status === 'waiting_equipment_approval') return 'text-kraft-700'
  return 'text-ink-950'
}
</script>

<template>
  <main v-if="auth.user" class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <p v-if="handoffMessage" role="status" class="banner-ok mb-6">{{ handoffMessage }}</p>

    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold text-ink-950">
          {{ auth.user.role === 'user' ? 'ใบแจ้งซ่อมของฉัน' : 'คิวงานซ่อม' }}
        </h1>
        <p class="hint mt-1">
          <template v-if="pagination"
            >ทั้งหมด <span class="code">{{ pagination.total }}</span> รายการ ·
          </template>
          {{ auth.user.department?.name ?? 'ไม่มีแผนก' }}
        </p>
      </div>
      <RouterLink
        v-if="auth.user.role === 'user'"
        :to="{ name: 'ticket-create' }"
        class="btn btn-primary"
      >
        <Plus class="size-4" aria-hidden="true" />
        แจ้งซ่อม
      </RouterLink>
    </div>

    <section
      aria-label="รายการใบแจ้งซ่อม"
      class="mt-6 overflow-hidden rounded border border-ink-200 bg-white"
    >
      <p v-if="ticketsLoading && !tickets.length" role="status" class="hint px-5 py-10 text-center">
        กำลังโหลดใบแจ้งซ่อม...
      </p>
      <div v-else-if="ticketsError" class="px-5 py-10 text-center">
        <p role="alert" class="note-error">{{ ticketsError }}</p>
        <button type="button" class="btn mt-3" @click="loadTickets(pagination?.current_page ?? 1)">
          โหลดอีกครั้ง
        </button>
      </div>
      <div v-else-if="tickets.length === 0" class="px-5 py-12 text-center">
        <p class="font-medium text-ink-950">ยังไม่มีใบแจ้งซ่อม</p>
        <p class="hint mt-1">
          {{
            auth.user.role === 'user'
              ? 'เมื่ออุปกรณ์มีปัญหา กด “แจ้งซ่อม” เพื่อส่งเรื่องให้ทีมไอที'
              : 'ไม่มีงานในคิวตอนนี้'
          }}
        </p>
      </div>

      <table v-else class="w-full text-left text-sm" :class="{ 'opacity-60': ticketsLoading }">
        <thead class="border-b border-ink-200 bg-ink-50 text-xs text-ink-600">
          <tr>
            <th scope="col" class="px-4 py-2.5 font-medium">เลขที่</th>
            <th scope="col" class="px-4 py-2.5 font-medium">หัวข้อ</th>
            <th scope="col" class="hidden px-4 py-2.5 font-medium md:table-cell">เส้นทาง</th>
            <th scope="col" class="hidden px-4 py-2.5 font-medium sm:table-cell">สถานะ</th>
            <th scope="col" class="hidden px-4 py-2.5 font-medium sm:table-cell">ความเร่งด่วน</th>
            <th scope="col" class="hidden px-4 py-2.5 font-medium lg:table-cell">ผู้แจ้ง</th>
            <th scope="col" class="hidden px-4 py-2.5 font-medium xl:table-cell">ผู้รับผิดชอบ</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="ticket in tickets"
            :key="ticket.id"
            class="border-b border-ink-100 align-top transition-colors last:border-0 hover:bg-ink-100 hover:shadow-[inset_0_0_0_1px_var(--color-ink-300)] focus-within:bg-ink-100 focus-within:shadow-[inset_0_0_0_1px_var(--color-ink-300)]"
          >
            <td class="px-4 py-3 whitespace-nowrap">
              <RouterLink
                :to="{ name: 'ticket-detail', params: { id: ticket.id } }"
                class="code font-semibold text-ink-950 underline decoration-ink-300 hover:decoration-ink-900"
              >
                {{ ticket.ticket_no }}
              </RouterLink>
              <p class="code mt-0.5 text-xs text-ink-500">{{ formatDate(ticket.created_at) }}</p>
            </td>
            <td class="min-w-48 px-4 py-3">
              <RouterLink
                :to="{ name: 'ticket-detail', params: { id: ticket.id } }"
                class="font-medium break-words text-ink-950 hover:underline"
              >
                {{ ticket.title }}
              </RouterLink>
              <p
                v-if="ticket.location || ticket.asset_label"
                class="mt-0.5 text-xs break-words text-ink-600"
              >
                {{ [ticket.location, ticket.asset_label].filter(Boolean).join(' · ') }}
              </p>
              <div class="mt-2 flex flex-wrap items-center gap-2 sm:hidden">
                <span class="text-xs font-semibold" :class="statusTone(ticket.status)">
                  {{ statusLabels[ticket.status] ?? ticket.status }}
                </span>
                <PriorityBadge :priority="ticket.priority" />
              </div>
              <div class="mt-2.5 md:hidden"><TrackingStrip :ticket="ticket" /></div>
            </td>
            <td class="hidden px-4 py-4 md:table-cell"><TrackingStrip :ticket="ticket" /></td>
            <td
              class="hidden px-4 py-3 font-medium whitespace-nowrap sm:table-cell"
              :class="statusTone(ticket.status)"
            >
              {{ statusLabels[ticket.status] ?? ticket.status }}
            </td>
            <td class="hidden px-4 py-3 sm:table-cell">
              <PriorityBadge :priority="ticket.priority" />
            </td>
            <td class="hidden px-4 py-3 lg:table-cell">
              <p class="text-ink-950">{{ ticket.requester?.name ?? '-' }}</p>
              <p v-if="ticket.department" class="text-xs text-ink-600">
                {{ ticket.department.name }}
              </p>
            </td>
            <td
              class="hidden px-4 py-3 xl:table-cell"
              :class="ticket.assignee ? 'text-ink-950' : 'text-ink-500'"
            >
              {{ ticket.assignee?.name ?? 'ยังไม่มี' }}
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <ul v-if="tickets.length" aria-label="ความหมายของจุดบนเส้นทาง" class="legend mt-3 hidden md:flex">
      <li><i data-state="done"></i>ผ่านแล้ว</li>
      <li><i data-state="active"></i>อยู่ที่นี่ กำลังดำเนินการ</li>
      <li><i data-state="queued"></i>รอช่างรับเรื่อง</li>
      <li><i data-state="waiting"></i>รอผู้แจ้งหรือผู้อนุมัติ</li>
      <li><i data-state="closed"></i>ปิดงาน</li>
      <li><i data-state="void"></i>ยกเลิก</li>
    </ul>

    <nav
      v-if="pagination && pagination.last_page > 1"
      aria-label="เปลี่ยนหน้ารายการใบแจ้งซ่อม"
      class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm"
    >
      <p aria-live="polite" class="hint">
        หน้า <span class="code">{{ pagination.current_page }}</span> จาก
        <span class="code">{{ pagination.last_page }}</span>
      </p>
      <div class="flex gap-2">
        <button
          type="button"
          aria-label="หน้าก่อนหน้า"
          :disabled="ticketsLoading || pagination.current_page <= 1"
          class="btn"
          @click="loadTickets(pagination.current_page - 1)"
        >
          ก่อนหน้า
        </button>
        <button
          type="button"
          aria-label="หน้าถัดไป"
          :disabled="ticketsLoading || pagination.current_page >= pagination.last_page"
          class="btn"
          @click="loadTickets(pagination.current_page + 1)"
        >
          ถัดไป
        </button>
      </div>
    </nav>
  </main>
</template>

<style scoped>
.legend {
  flex-wrap: wrap;
  gap: 0.375rem 1.25rem;
  font-size: 0.75rem;
  color: var(--color-ink-600);
}
.legend li {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.legend i {
  position: relative;
  width: 0.75rem;
  height: 0.75rem;
  border: 2px solid var(--color-ink-900);
  border-radius: 999px;
  background: #fff;
}
.legend [data-state='done'] {
  background: var(--color-ink-900);
}
.legend [data-state='active'] {
  background: var(--color-ink-900);
  box-shadow:
    0 0 0 2px #fff,
    0 0 0 3.5px var(--color-ink-900);
}
.legend [data-state='queued'] {
  box-shadow:
    0 0 0 2px #fff,
    0 0 0 3px var(--color-ink-300);
}
.legend [data-state='waiting'] {
  border-color: var(--color-kraft-500);
  background: var(--color-kraft-500);
}
.legend [data-state='closed'] {
  border-color: var(--color-scan-600);
  background: var(--color-scan-600);
}
.legend [data-state='void'] {
  border-color: var(--color-ink-300);
}
.legend [data-state='void']::after {
  content: '';
  position: absolute;
  inset: 50% -3px auto;
  height: 2px;
  background: var(--color-ink-500);
  transform: rotate(-40deg);
}
</style>
