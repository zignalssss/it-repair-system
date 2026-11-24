<script setup lang="ts">
import { onUnmounted, ref, watch } from 'vue'
import { errorMessage } from '@/lib/http'
import { priorityLabels, statusLabels } from '@/lib/ticketLabels'
import { listTicketEvents, type TicketEvent, type TicketPagination } from '@/services/tickets'

const props = defineProps<{ ticketId: number }>()
const emit = defineEmits<{ loaded: [events: TicketEvent[]] }>()

type HistoryState =
  | { status: 'loading' }
  | { status: 'error'; page: number; message: string }
  | { status: 'ready'; events: TicketEvent[]; meta: TicketPagination }

const state = ref<HistoryState>({ status: 'loading' })
let revision = 0

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
      emit('loaded', result.data)
    }
  } catch (error) {
    if (currentRevision === revision) {
      state.value = { status: 'error', page, message: errorMessage(error) }
    }
  }
}
</script>

<template>
  <section class="panel" aria-label="ประวัติการดำเนินการ">
    <h2 class="panel-title">ประวัติการดำเนินการ</h2>

    <p v-if="state.status === 'loading'" role="status" class="hint mt-4">กำลังโหลดประวัติ...</p>
    <div v-else-if="state.status === 'error'" class="mt-4">
      <p role="alert" class="note-error">{{ state.message }}</p>
      <button
        type="button"
        aria-label="ลองโหลดประวัติอีกครั้ง"
        class="btn mt-3"
        @click="loadPage(state.page)"
      >
        ลองใหม่
      </button>
    </div>
    <template v-else>
      <p v-if="state.events.length === 0" class="hint mt-4">ยังไม่มีประวัติที่คุณดูได้</p>
      <ol v-else class="scans mt-4">
        <li v-for="event in state.events" :key="event.id" class="scan break-words">
          <h3 class="text-sm font-semibold text-ink-950">{{ event.label }}</h3>
          <p class="mt-0.5 text-xs text-ink-600">
            {{ event.actor?.name ?? 'ไม่ระบุผู้ดำเนินการ' }}
            ·
            <time class="code" :datetime="event.created_at">{{
              dateFormat.format(new Date(event.created_at))
            }}</time>
          </p>
          <p
            v-if="event.from_status !== event.to_status && event.to_status"
            class="mt-2 text-sm text-ink-800"
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
              <span class="code ml-2 text-xs text-ink-500"
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
            class="btn"
            @click="loadPage(state.meta.current_page - 1)"
          >
            ก่อนหน้า
          </button>
          <button
            type="button"
            aria-label="ประวัติหน้าถัดไป"
            :disabled="state.meta.current_page >= state.meta.last_page"
            class="btn"
            @click="loadPage(state.meta.current_page + 1)"
          >
            ถัดไป
          </button>
        </div>
      </nav>
    </template>
  </section>
</template>

<style scoped>
.scans {
  position: relative;
}
.scan {
  position: relative;
  padding: 0 0 1.25rem 1.5rem;
}
.scan::before {
  content: '';
  position: absolute;
  top: 0.75rem;
  bottom: -0.25rem;
  left: 0.3125rem;
  width: 2px;
  background: var(--color-ink-200);
}
.scan:last-child::before {
  display: none;
}
.scan::after {
  content: '';
  position: absolute;
  top: 0.375rem;
  left: 0;
  width: 0.75rem;
  height: 0.75rem;
  border: 2px solid var(--color-ink-900);
  border-radius: 999px;
  background: #fff;
}
.scan:last-child::after {
  background: var(--color-ink-900);
  box-shadow:
    0 0 0 2px #fff,
    0 0 0 4px var(--color-ink-900);
}
</style>
