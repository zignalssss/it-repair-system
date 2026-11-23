<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { isAxiosError } from 'axios'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/lib/http'
import { listEquipmentRequests, type Ticket } from '@/services/tickets'
import { resolveTicket, type ResolutionInput } from '@/services/management'
const props = defineProps<{ ticket: Ticket }>()
const busy = defineModel<boolean>('busy', { required: true })
const emit = defineEmits<{ updated: [ticket: Ticket] }>()
const auth = useAuthStore()
const canResolve = computed(
  () =>
    auth.user?.is_active === true &&
    ['junior_technician', 'senior_technician'].includes(auth.user.role) &&
    props.ticket.assignee?.id === auth.user.id &&
    props.ticket.status === 'in_progress' &&
    !props.ticket.pending_information_request,
)
const summary = ref('')
const parts = ref<{ source: string; name: string; quantity: number }[]>([])
const approvedItems = ref<{ id: number; name: string; quantity: number }[]>([])
const lookup = ref<
  | { kind: 'loading'; page: number }
  | { kind: 'error'; page: number; message: string }
  | { kind: 'ready'; page: number; last: number }
>({ kind: 'loading', page: 1 })
const feedback = ref('')
let revision = 0
let lookupRevision = 0
let ownsBusy = false
function releaseBusy() {
  if (ownsBusy) {
    busy.value = false
    ownsBusy = false
  }
}
watch(
  [() => props.ticket.id, () => auth.user?.id, canResolve],
  () => {
    revision++
    lookupRevision++
    releaseBusy()
    summary.value = ''
    parts.value = []
    approvedItems.value = []
    feedback.value = ''
    if (canResolve.value) void loadApproved(1)
  },
  { immediate: true },
)
onUnmounted(() => {
  revision++
  lookupRevision++
  releaseBusy()
})
async function loadApproved(page: number) {
  const context = revision,
    request = ++lookupRevision
  lookup.value = { kind: 'loading', page }
  try {
    const result = await listEquipmentRequests(props.ticket.id, page, 'approved')
    if (context !== revision || request !== lookupRevision) return
    const loaded = result.data.filter((r) => r.status === 'approved').flatMap((r) => r.items)
    approvedItems.value = page === 1 ? loaded : [...approvedItems.value, ...loaded]
    lookup.value = { kind: 'ready', page, last: result.meta.last_page }
  } catch (error) {
    if (context === revision && request === lookupRevision)
      lookup.value = { kind: 'error', page, message: errorMessage(error) }
  }
}
async function submit() {
  if (!canResolve.value || busy.value) return
  const input: ResolutionInput = { summary: summary.value.trim(), parts: [] }
  if (!input.summary || input.summary.length > 10000 || parts.value.length > 50) {
    feedback.value = 'กรุณาระบุสรุปการซ่อมไม่เกิน 10000 ตัวอักษร'
    return
  }
  const totals = new Map<number, number>()
  for (const part of parts.value) {
    const item = part.source
      ? approvedItems.value.find((i) => String(i.id) === part.source)
      : undefined
    const name = item?.name ?? part.name.trim(),
      quantity = Number(part.quantity)
    if (
      !name ||
      name.length > 200 ||
      !Number.isInteger(quantity) ||
      quantity < 1 ||
      quantity > 1000 ||
      (part.source && !item)
    ) {
      feedback.value = 'กรุณาตรวจชื่ออะไหล่และจำนวน 1–1000 ในทุกรายการ'
      return
    }
    if (item) {
      const total = (totals.get(item.id) ?? 0) + quantity
      if (total > item.quantity) {
        feedback.value = 'จำนวนใช้จริงรวมเกินจำนวนที่อนุมัติ'
        return
      }
      totals.set(item.id, total)
    }
    input.parts.push({ name, quantity, equipment_request_item_id: item?.id ?? null })
  }
  const context = revision
  feedback.value = ''
  ownsBusy = true
  busy.value = true
  try {
    const updated = await resolveTicket(props.ticket.id, input)
    if (context === revision) {
      summary.value = ''
      parts.value = []
      emit('updated', updated)
    }
  } catch (error) {
    if (context === revision)
      feedback.value =
        isAxiosError<{ errors?: Record<string, string[]> }>(error) && error.response?.status === 422
          ? Object.entries(error.response.data.errors ?? {})
              .map(([field, messages]) => `${field}: ${messages.join(' ')}`)
              .join('\n') || errorMessage(error)
          : errorMessage(error)
  } finally {
    if (context === revision) releaseBusy()
  }
}
</script>
<template>
  <section v-if="canResolve" class="mt-8 border-t border-stone-200 pt-6" aria-label="ปิดงานซ่อม">
    <h2 class="text-lg font-semibold">แก้ไขเสร็จสิ้น</h2>
    <p class="mt-2 text-sm text-stone-600">
      บันทึกสรุปให้ผู้แจ้งอ่านได้ และระบุเฉพาะอะไหล่ที่ใช้จริง ปิดงานโดยไม่มีอะไหล่ได้
    </p>
    <form aria-label="ปิดงานซ่อม" class="mt-4 space-y-3" @submit.prevent="submit">
      <fieldset :disabled="busy" class="space-y-3">
        <label for="resolution-summary" class="block text-sm">สรุปการแก้ไข</label>
        <textarea
          id="resolution-summary"
          v-model="summary"
          required
          maxlength="10000"
          rows="4"
          class="block w-full border border-stone-300 p-2"
        ></textarea>
        <p v-if="lookup.kind === 'loading'" role="status" class="text-sm">กำลังโหลดอุปกรณ์ที่อนุมัติ...</p>
        <div v-else-if="lookup.kind === 'error'" class="text-sm">
          <p>โหลดอุปกรณ์ที่อนุมัติไม่สำเร็จ: {{ lookup.message }} ยังปิดงานโดยไม่อ้างอิงรายการได้</p>
          <button type="button" class="mt-2 border px-3 py-2" @click="loadApproved(lookup.page)">
            ลองโหลดอุปกรณ์อีกครั้ง
          </button>
        </div>
        <template v-else>
          <p v-if="!approvedItems.length" class="text-sm">ยังไม่มีอุปกรณ์ที่อนุมัติ</p>
          <button
            v-if="lookup.page < lookup.last"
            type="button"
            data-test="approved-more"
            class="border px-3 py-2 text-sm"
            @click="loadApproved(lookup.page + 1)"
          >
            โหลดอุปกรณ์ที่อนุมัติเพิ่ม
          </button>
        </template>
        <fieldset
          v-for="(part, index) in parts"
          :key="index"
          class="space-y-2 border border-stone-200 p-3"
        >
          <legend class="text-sm">อะไหล่ใช้จริง {{ index + 1 }}</legend>
          <label :for="`part-source-${index}`" class="block text-sm">อ้างอิงรายการอนุมัติ</label>
          <select
            :id="`part-source-${index}`"
            v-model="part.source"
            class="block w-full border border-stone-300 p-2 text-sm"
          >
            <option value="">อะไหล่อื่นที่ไม่ได้อ้างอิงคำขอ</option>
            <option v-for="item in approvedItems" :key="item.id" :value="String(item.id)">
              {{ item.name }} · อนุมัติ {{ item.quantity }}
            </option>
          </select>
          <label v-if="!part.source" :for="`part-name-${index}`" class="block text-sm"
            >ชื่ออะไหล่</label
          >
          <input
            v-if="!part.source"
            :id="`part-name-${index}`"
            v-model="part.name"
            required
            maxlength="200"
            class="block w-full border border-stone-300 p-2 text-sm"
          />
          <label :for="`part-quantity-${index}`" class="block text-sm">จำนวนที่ใช้</label>
          <input
            :id="`part-quantity-${index}`"
            v-model.number="part.quantity"
            type="number"
            min="1"
            max="1000"
            step="1"
            required
            class="block w-full border border-stone-300 p-2 text-sm"
          />
          <button type="button" class="border px-3 py-2 text-sm" @click="parts.splice(index, 1)">
            ลบอะไหล่ {{ index + 1 }}
          </button>
        </fieldset>
        <button
          type="button"
          data-test="add-part"
          :disabled="parts.length >= 50"
          class="border px-3 py-2 text-sm disabled:opacity-50"
          @click="parts.push({ source: '', name: '', quantity: 1 })"
        >
          เพิ่มอะไหล่ใช้จริง (สูงสุด 50)
        </button>
        <button
          type="submit"
          class="ml-2 border bg-stone-900 px-3 py-2 text-sm text-white disabled:opacity-50"
        >
          {{ busy ? 'กำลังบันทึก...' : 'ยืนยันแก้ไขเสร็จสิ้น' }}
        </button>
      </fieldset>
      <p v-if="feedback" role="alert" class="whitespace-pre-line text-sm text-red-700">
        {{ feedback }}
      </p>
    </form>
  </section>
</template>
