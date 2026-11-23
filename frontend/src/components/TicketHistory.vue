<script setup lang="ts">
import { onUnmounted, ref, watch } from 'vue'
import { errorMessage } from '@/lib/http'
import {
  listTicketEvents,
  type Ticket,
  type TicketEvent,
  type TicketPagination,
  type TicketStatus,
} from '@/services/tickets'

const props = defineProps<{ ticketId: number }>()

type HistoryState =
  | { status: 'loading' }
  | { status: 'error'; page: number; message: string }
  | { status: 'ready'; events: TicketEvent[]; meta: TicketPagination }

const state = ref<HistoryState>({ status: 'loading' })
let revision = 0

const statusLabels: Record<TicketStatus, string> = {
  new: 'รอรับเรื่อง',
  in_progress: 'กำลังดำเนินการ',
  waiting_info: 'รอข้อมูลเพิ่มเติม',
  escalated: 'ส่งต่อช่างอาวุโส',
  returned: 'ส่งกลับช่างระดับต้น',
  waiting_equipment_approval: 'รออนุมัติอุปกรณ์',
  resolved: 'แก้ไขเสร็จสิ้น',
  cancelled: 'ยกเลิก',
}
const priorityLabels: Record<Ticket['priority'], string> = {
  normal: 'ปกติ',
  high: 'สำคัญ',
  critical: 'วิกฤต',
}
const dateFormat = new Intl.DateTimeFormat('th-TH', {
  dateStyle: 'medium',
  timeStyle: 'short',
  timeZone: 'Asia/Bangkok',
})

watch(
  () => props.ticketId,
  () => loadPage(1),
  { immediate: true },
)
onUnmounted(() => {
  revision++
})

async function loadPage(page: number) {
  const currentRevision = ++revision
  state.value = { status: 'loading' }

  try {
    const result = await listTicketEvents(props.ticketId, page)
    if (currentRevision === revision) {
      state.value = { status: 'ready', events: result.data, meta: result.meta }
    }
  } catch (error) {
    if (currentRevision === revision) {
      state.value = { status: 'error', page, message: errorMessage(error) }
    }
  }
}
</script>

<template>
  <section class="mt-8 border-t border-stone-200 pt-6" aria-label="ประวัติการดำเนินการ">
    <h2 class="text-lg font-semibold">ประวัติการดำเนินการ</h2>

    <p v-if="state.status === 'loading'" role="status" class="mt-4 text-sm text-stone-600">
      กำลังโหลดประวัติ...
    </p>
    <div v-else-if="state.status === 'error'" class="mt-4">
      <p role="alert" class="text-sm text-red-700">{{ state.message }}</p>
      <button
        type="button"
        aria-label="ลองโหลดประวัติอีกครั้ง"
        class="mt-3 border px-3 py-1.5 text-sm focus-visible:outline-2 focus-visible:outline-offset-2"
        @click="loadPage(state.page)"
      >
        ลองใหม่
      </button>
    </div>
    <template v-else>
      <p v-if="state.events.length === 0" class="mt-4 text-sm text-stone-600">ยังไม่มีประวัติที่คุณดูได้</p>
      <ol v-else class="mt-4 space-y-5 border-l border-stone-300 pl-4">
        <li v-for="event in state.events" :key="event.id" class="break-words">
          <h3 class="font-medium">{{ event.label }}</h3>
          <p class="mt-1 text-xs text-stone-600">
            {{ event.actor?.name ?? 'ไม่ระบุผู้ดำเนินการ' }}
            ·
            <time :datetime="event.created_at">{{
              dateFormat.format(new Date(event.created_at))
            }}</time>
          </p>
          <p
            v-if="event.from_status !== event.to_status && event.to_status"
            class="mt-2 text-sm text-stone-700"
          >
            สถานะ: {{ event.from_status ? statusLabels[event.from_status] : 'เริ่มต้น' }} →
            {{ statusLabels[event.to_status] }}
          </p>
          <p v-if="event.note?.body" class="mt-2 whitespace-pre-line text-sm">
            {{ event.note.body }}
          </p>
          <p v-if="event.metadata.reason" class="mt-2 whitespace-pre-line text-sm">
            เหตุผล: {{ event.metadata.reason }}
          </p>
          <p v-if="event.metadata.from_priority && event.metadata.to_priority" class="mt-2 text-sm">
            ความเร่งด่วน: {{ priorityLabels[event.metadata.from_priority] }} →
            {{ priorityLabels[event.metadata.to_priority] }}
          </p>
          <p v-if="event.metadata.parts_count !== undefined" class="mt-2 text-sm">
            อะไหล่ที่ใช้จริง {{ event.metadata.parts_count }} รายการ
          </p>
          <ul v-if="event.note?.attachments?.length" class="mt-3 space-y-2 text-sm">
            <li v-for="file in event.note.attachments" :key="file.id">
              <a
                :href="file.download_url"
                :download="file.original_name"
                class="break-all underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-offset-2"
                >{{ file.original_name }}</a
              >
              <span class="ml-2 text-xs text-stone-500"
                >{{ Math.ceil(file.size_bytes / 1024) }} KB</span
              >
            </li>
          </ul>
        </li>
      </ol>
      <nav
        v-if="state.meta.last_page > 1"
        aria-label="เปลี่ยนหน้าประวัติ"
        class="mt-5 flex flex-wrap items-center justify-between gap-3 text-sm"
      >
        <p>หน้า {{ state.meta.current_page }} จาก {{ state.meta.last_page }}</p>
        <div class="flex gap-2">
          <button
            type="button"
            aria-label="ประวัติหน้าก่อนหน้า"
            :disabled="state.meta.current_page <= 1"
            class="border px-3 py-1.5 focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-40"
            @click="loadPage(state.meta.current_page - 1)"
          >
            ก่อนหน้า
          </button>
          <button
            type="button"
            aria-label="ประวัติหน้าถัดไป"
            :disabled="state.meta.current_page >= state.meta.last_page"
            class="border px-3 py-1.5 focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-40"
            @click="loadPage(state.meta.current_page + 1)"
          >
            ถัดไป
          </button>
        </div>
      </nav>
    </template>
  </section>
</template>
