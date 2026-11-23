<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { isAxiosError } from 'axios'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/lib/http'
import { getManagerReport, type ManagerReport } from '@/services/management'
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
  <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
    <RouterLink :to="{ name: 'home' }" class="text-sm underline">กลับรายการใบแจ้งซ่อม</RouterLink>
    <h1 class="mt-4 text-2xl font-semibold">รายงานสถิติการแจ้งซ่อม</h1>
    <p v-if="!allowed" role="alert" class="mt-4 text-red-700">เฉพาะ IT Manager ที่เปิดใช้งานเท่านั้น</p>
    <template v-else>
      <p class="mt-3 text-sm text-stone-600">
        ช่วงวันที่นับตาม Asia/Bangkok รวมวันเริ่มและวันสิ้นสุด ส่วนภาระงานเปิดอยู่ใช้สถานะปัจจุบันทุกช่วงเวลา
      </p>
      <form
        class="mt-5 flex flex-wrap items-end gap-3"
        aria-label="ช่วงวันที่รายงาน"
        @submit.prevent="load"
      >
        <div>
          <label for="report-from" class="block text-sm">วันที่เริ่ม</label
          ><input
            id="report-from"
            v-model="from"
            type="date"
            required
            class="mt-1 border border-stone-300 p-2"
          />
        </div>
        <div>
          <label for="report-to" class="block text-sm">วันที่สิ้นสุด</label
          ><input
            id="report-to"
            v-model="to"
            type="date"
            required
            class="mt-1 border border-stone-300 p-2"
          />
        </div>
        <button type="submit" class="border bg-stone-900 px-3 py-2 text-white">แสดงรายงาน</button>
      </form>
      <p v-if="state.kind === 'loading'" role="status" class="mt-4">กำลังโหลดรายงาน...</p>
      <p v-if="state.kind === 'error'" role="alert" class="mt-4 text-sm text-red-700">
        {{ state.message }}
      </p>
      <section v-if="report" aria-label="ผลรายงาน" class="mt-6 space-y-6">
        <p class="text-sm">
          ข้อมูลที่แสดงของช่วง {{ report.period.from }} ถึง {{ report.period.to }} ·
          {{ report.period.timezone
          }}<span v-if="state.kind !== 'idle'"> · เป็นผลสำเร็จครั้งก่อน ยังไม่ใช่ผลของคำขอล่าสุด</span>
        </p>
        <p class="text-xs text-stone-500">สร้างข้อมูลเมื่อ {{ report.generated_at }}</p>
        <section>
          <h2 class="text-lg font-semibold">ใบงานที่สร้างในช่วงวันที่เลือก</h2>
          <p class="mt-2">ทั้งหมด {{ report.tickets_created_in_period.total }} ใบ</p>
          <div class="mt-3 grid gap-4 sm:grid-cols-2">
            <div>
              <h3 class="font-medium">แบ่งตามสถานะปัจจุบันของใบที่สร้างในช่วงนี้</h3>
              <ul class="mt-2 text-sm">
                <li
                  v-for="row in report.tickets_created_in_period.by_current_status"
                  :key="row.status"
                >
                  {{ row.status }} · {{ row.count }} ใบ
                </li>
              </ul>
            </div>
            <div>
              <h3 class="font-medium">แบ่งตามความเร่งด่วนปัจจุบัน</h3>
              <ul class="mt-2 text-sm">
                <li
                  v-for="row in report.tickets_created_in_period.by_current_priority"
                  :key="row.priority"
                >
                  {{ row.priority }} · {{ row.count }} ใบ
                </li>
              </ul>
            </div>
          </div>
        </section>
        <section class="border-t border-stone-200 pt-4">
          <h2 class="text-lg font-semibold">งานเปิดอยู่ปัจจุบัน</h2>
          <p class="mt-2 text-sm">ไม่นับเฉพาะช่วงวันที่เลือก และไม่ใช่ยอดคงค้างย้อนหลัง ณ วันสิ้นสุด</p>
          <dl class="mt-3 flex flex-wrap gap-6">
            <div>
              <dt>ทั้งหมด</dt>
              <dd>{{ report.current_open_workload.total }}</dd>
            </div>
            <div>
              <dt>มีผู้รับผิดชอบ</dt>
              <dd>{{ report.current_open_workload.assigned }}</dd>
            </div>
            <div>
              <dt>ยังไม่มอบหมาย</dt>
              <dd>{{ report.current_open_workload.unassigned }}</dd>
            </div>
          </dl>
        </section>
        <section class="border-t border-stone-200 pt-4">
          <h2 class="text-lg font-semibold">กิจกรรมช่าง</h2>
          <p class="mt-2 text-sm">
            ครั้งที่รับงานอาจมากกว่าใบที่รับงานไม่ซ้ำ ส่วนปิดงานนับตามผู้ปิดและวันที่ปิด ไม่ใช่วันที่สร้าง
          </p>
          <p v-if="!report.technicians.length" class="mt-3">ไม่มีข้อมูลช่าง</p>
          <div v-else class="mt-3 overflow-x-auto">
            <table class="w-full text-left text-sm">
              <thead>
                <tr>
                  <th class="p-2">ช่าง / บทบาทปัจจุบัน</th>
                  <th class="p-2">ครั้งที่รับงาน</th>
                  <th class="p-2">ใบที่รับงานไม่ซ้ำ</th>
                  <th class="p-2">ปิดในช่วงนี้</th>
                  <th class="p-2">งานเปิดที่รับผิดชอบปัจจุบัน</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="technician in report.technicians"
                  :key="technician.user_id"
                  class="border-t border-stone-200"
                >
                  <td class="p-2">
                    {{ technician.name }}<br />{{ technician.current_role }} ·
                    {{ technician.is_active ? 'เปิดใช้งาน' : 'ปิดใช้งาน' }}
                  </td>
                  <td class="p-2">{{ technician.period_activity.claim_count }}</td>
                  <td class="p-2">{{ technician.period_activity.claimed_ticket_count }}</td>
                  <td class="p-2">{{ technician.period_activity.resolved_ticket_count }}</td>
                  <td class="p-2">{{ technician.current_open_assigned }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </section>
    </template>
  </main>
</template>
