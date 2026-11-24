<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { isAxiosError } from 'axios'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/lib/http'
import { getManagerReport, type ManagerReport } from '@/services/management'
import { priorityLabels, roleLabels, statusLabels } from '@/lib/ticketLabels'
const generatedFormat = new Intl.DateTimeFormat('th-TH', {
  dateStyle: 'medium',
  timeStyle: 'medium',
  timeZone: 'Asia/Bangkok',
})
const dayFormat = new Intl.DateTimeFormat('th-TH', { dateStyle: 'medium', timeZone: 'UTC' })
function formatDay(value: string) {
  const date = new Date(value + 'T00:00:00Z')
  return Number.isNaN(date.getTime()) ? value : dayFormat.format(date)
}
function formatGenerated(value: string) {
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : generatedFormat.format(date)
}
function share(count: number, total: number) {
  return total > 0 && count > 0 ? `${Math.max(2, Math.round((count / total) * 100))}%` : '0%'
}
const auth = useAuthStore()
const allowed = computed(() => auth.user?.is_active === true && auth.user.role === 'it_manager')
const today = new Intl.DateTimeFormat('en-CA', {
  timeZone: 'Asia/Bangkok',
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
}).format(new Date())
const from = ref(today.slice(0, 7) + '-01'),
  to = ref(today)
const report = ref<ManagerReport | null>(null)
const state = ref<{ kind: 'idle' } | { kind: 'loading' } | { kind: 'error'; message: string }>({
  kind: 'idle',
})
let revision = 0
function validDate(value: string) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false
  const date = new Date(value + 'T00:00:00Z')
  return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value
}
watch(
  [allowed, () => auth.user?.id],
  () => {
    revision++
    report.value = null
    state.value = { kind: 'idle' }
    if (allowed.value) void load()
  },
  { immediate: true },
)
onUnmounted(() => {
  revision++
})
async function load() {
  if (!allowed.value) return
  const context = ++revision
  if (!validDate(from.value) || !validDate(to.value) || from.value > to.value) {
    state.value = { kind: 'error', message: 'กรุณาระบุวันที่จริง และวันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่ม' }
    return
  }
  const start = from.value,
    end = to.value
  state.value = { kind: 'loading' }
  try {
    const result = await getManagerReport(start, end)
    if (context === revision) {
      report.value = result
      state.value = { kind: 'idle' }
    }
  } catch (error) {
    if (context === revision)
      state.value = {
        kind: 'error',
        message:
          isAxiosError<{ errors?: Record<string, string[]> }>(error) &&
          error.response?.status === 422
            ? Object.values(error.response.data.errors ?? {})
                .flat()
                .join(' ') || errorMessage(error)
            : errorMessage(error),
      }
  }
}
</script>
<template>
  <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <h1 class="text-2xl font-semibold text-ink-950">รายงานสถิติการแจ้งซ่อม</h1>
    <p v-if="!allowed" role="alert" class="banner-error mt-4">เฉพาะ IT Manager ที่เปิดใช้งานเท่านั้น</p>
    <template v-else>
      <p class="hint mt-1 max-w-3xl">
        ช่วงวันที่นับตาม Asia/Bangkok รวมวันเริ่มและวันสิ้นสุด ส่วนภาระงานเปิดอยู่ใช้สถานะปัจจุบันทุกช่วงเวลา
      </p>
      <form
        class="mt-5 flex flex-wrap items-end gap-3"
        aria-label="ช่วงวันที่รายงาน"
        @submit.prevent="load"
      >
        <div>
          <label for="report-from" class="label">วันที่เริ่ม</label>
          <input
            id="report-from"
            v-model="from"
            type="date"
            required
            class="field code mt-1.5 w-auto"
          />
        </div>
        <div>
          <label for="report-to" class="label">วันที่สิ้นสุด</label>
          <input
            id="report-to"
            v-model="to"
            type="date"
            required
            class="field code mt-1.5 w-auto"
          />
        </div>
        <button type="submit" :disabled="state.kind === 'loading'" class="btn btn-primary min-h-10">
          {{ state.kind === 'loading' ? 'กำลังโหลด...' : 'แสดงรายงาน' }}
        </button>
      </form>
      <p v-if="state.kind === 'loading'" role="status" class="hint mt-4">กำลังโหลดรายงาน...</p>
      <p v-if="state.kind === 'error'" role="alert" class="banner-error mt-4">
        {{ state.message }}
      </p>

      <section
        v-if="report"
        aria-label="ผลรายงาน"
        class="mt-6"
        :class="{ 'opacity-60': state.kind === 'loading' }"
      >
        <p class="text-sm">
          ข้อมูลช่วง <span class="code">{{ formatDay(report.period.from) }}</span> ถึง
          <span class="code">{{ formatDay(report.period.to) }}</span> · {{ report.period.timezone
          }}<span v-if="state.kind !== 'idle'" class="text-kraft-700">
            · เป็นผลสำเร็จครั้งก่อน ยังไม่ใช่ผลของคำขอล่าสุด</span
          >
        </p>
        <p class="hint mt-0.5">
          สร้างข้อมูลเมื่อ <span class="code">{{ formatGenerated(report.generated_at) }}</span>
        </p>

        <div class="grid items-start gap-x-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
          <section class="panel" aria-label="ใบงานที่สร้างในช่วงวันที่เลือก">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
              <h2 class="panel-title">ใบงานที่สร้างในช่วงวันที่เลือก</h2>
              <p class="text-sm font-semibold">
                ทั้งหมด {{ report.tickets_created_in_period.total }} ใบ
              </p>
            </div>
            <div class="mt-4 grid gap-6 sm:grid-cols-2">
              <div>
                <h3 class="hint font-medium">แบ่งตามสถานะปัจจุบันของใบที่สร้างในช่วงนี้</h3>
                <p
                  v-if="!report.tickets_created_in_period.by_current_status.length"
                  class="hint mt-2"
                >
                  ไม่มีใบงาน
                </p>
                <ul class="mt-2 space-y-2 text-sm">
                  <li
                    v-for="row in report.tickets_created_in_period.by_current_status"
                    :key="row.status"
                  >
                    <div class="flex justify-between gap-3">
                      <span>{{ statusLabels[row.status] ?? row.status }}</span>
                      <span
                        ><span class="code">{{ row.count }}</span> ใบ</span
                      >
                    </div>
                    <div class="mt-1 h-1.5 rounded-full bg-ink-100" aria-hidden="true">
                      <div
                        class="h-full rounded-full bg-ink-900"
                        :style="{ width: share(row.count, report.tickets_created_in_period.total) }"
                      ></div>
                    </div>
                  </li>
                </ul>
              </div>
              <div>
                <h3 class="hint font-medium">แบ่งตามความเร่งด่วนปัจจุบัน</h3>
                <p
                  v-if="!report.tickets_created_in_period.by_current_priority.length"
                  class="hint mt-2"
                >
                  ไม่มีใบงาน
                </p>
                <ul class="mt-2 space-y-2 text-sm">
                  <li
                    v-for="row in report.tickets_created_in_period.by_current_priority"
                    :key="row.priority"
                  >
                    <div class="flex justify-between gap-3">
                      <span>{{ priorityLabels[row.priority] ?? row.priority }}</span>
                      <span
                        ><span class="code">{{ row.count }}</span> ใบ</span
                      >
                    </div>
                    <div class="mt-1 h-1.5 rounded-full bg-ink-100" aria-hidden="true">
                      <div
                        class="h-full rounded-full"
                        :class="row.priority === 'normal' ? 'bg-ink-500' : 'bg-vermilion-600'"
                        :style="{ width: share(row.count, report.tickets_created_in_period.total) }"
                      ></div>
                    </div>
                  </li>
                </ul>
              </div>
            </div>
          </section>

          <section class="panel" aria-label="งานเปิดอยู่ปัจจุบัน">
            <h2 class="panel-title">งานเปิดอยู่ปัจจุบัน</h2>
            <p class="hint mt-1">ไม่นับเฉพาะช่วงวันที่เลือก และไม่ใช่ยอดคงค้างย้อนหลัง ณ วันสิ้นสุด</p>
            <dl class="mt-4 grid grid-cols-3 divide-x divide-ink-100 border-y border-ink-100">
              <div class="py-3 pr-3">
                <dt class="hint">ทั้งหมด</dt>
                <dd class="code text-2xl font-semibold">
                  {{ report.current_open_workload.total }}
                </dd>
              </div>
              <div class="px-3 py-3">
                <dt class="hint">มีผู้รับผิดชอบ</dt>
                <dd class="code text-2xl font-semibold">
                  {{ report.current_open_workload.assigned }}
                </dd>
              </div>
              <div class="py-3 pl-3">
                <dt class="hint">ยังไม่มอบหมาย</dt>
                <dd
                  class="code text-2xl font-semibold"
                  :class="{ 'text-vermilion-700': report.current_open_workload.unassigned > 0 }"
                >
                  {{ report.current_open_workload.unassigned }}
                </dd>
              </div>
            </dl>
          </section>
        </div>

        <section class="panel" aria-label="กิจกรรมช่าง">
          <h2 class="panel-title">กิจกรรมช่าง</h2>
          <p class="hint mt-1">
            ครั้งที่รับงานอาจมากกว่าใบที่รับงานไม่ซ้ำ ส่วนปิดงานนับตามผู้ปิดและวันที่ปิด ไม่ใช่วันที่สร้าง
          </p>
          <p v-if="!report.technicians.length" class="hint mt-4">ไม่มีข้อมูลช่าง</p>
          <div v-else class="-mx-6 mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
              <thead class="border-y border-ink-200 bg-ink-50 text-xs text-ink-600">
                <tr>
                  <th scope="col" class="px-6 py-2.5 font-medium">ช่าง / บทบาทปัจจุบัน</th>
                  <th scope="col" class="px-4 py-2.5 text-right font-medium">ครั้งที่รับงาน</th>
                  <th scope="col" class="px-4 py-2.5 text-right font-medium">ใบที่รับงานไม่ซ้ำ</th>
                  <th scope="col" class="px-4 py-2.5 text-right font-medium">ปิดในช่วงนี้</th>
                  <th scope="col" class="px-6 py-2.5 text-right font-medium">
                    งานเปิดที่รับผิดชอบปัจจุบัน
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="technician in report.technicians"
                  :key="technician.user_id"
                  class="border-b border-ink-100 last:border-0"
                >
                  <td class="px-6 py-3">
                    <p class="font-medium text-ink-950">{{ technician.name }}</p>
                    <p
                      class="text-xs"
                      :class="technician.is_active ? 'text-ink-600' : 'text-vermilion-700'"
                    >
                      {{ roleLabels[technician.current_role] ?? technician.current_role }} ·
                      {{ technician.is_active ? 'เปิดใช้งาน' : 'ปิดใช้งาน' }}
                    </p>
                  </td>
                  <td class="code px-4 py-3 text-right">
                    {{ technician.period_activity.claim_count }}
                  </td>
                  <td class="code px-4 py-3 text-right">
                    {{ technician.period_activity.claimed_ticket_count }}
                  </td>
                  <td class="code px-4 py-3 text-right">
                    {{ technician.period_activity.resolved_ticket_count }}
                  </td>
                  <td class="code px-6 py-3 text-right">{{ technician.current_open_assigned }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </section>
    </template>
  </main>
</template>
