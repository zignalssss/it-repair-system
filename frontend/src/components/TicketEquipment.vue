<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { isAxiosError } from 'axios'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/lib/http'
import {
  decideTicketEquipment,
  getTicket,
  listEquipmentRequests,
  requestTicketEquipment,
  type EquipmentPage,
  type EquipmentRequest,
  type EquipmentStatus,
  type NewEquipmentRequest,
  type Ticket,
} from '@/services/tickets'
const props = defineProps<{ ticket: Ticket }>()
const busy = defineModel<boolean>('busy', { required: true })
const emit = defineEmits<{ updated: [ticket: Ticket] }>()
const auth = useAuthStore()
type ListState =
  | { kind: 'loading'; page: number }
  | { kind: 'error'; page: number; message: string }
  | { kind: 'ready'; result: EquipmentPage }
type MutationState =
  | { kind: 'idle' }
  | { kind: 'posting' }
  | { kind: 'refreshing' }
  | { kind: 'refresh-error'; message: string }
const list = ref<ListState>({ kind: 'loading', page: 1 })
const mutation = ref<MutationState>({ kind: 'idle' })
const reason = ref('')
const items = ref([{ name: '', quantity: 1, estimated_unit_price: '' }])
const decisionReasons = ref<Record<number, string>>({})
const feedback = ref<{ type: 'error' | 'success'; message: string } | null>(null)
let revision = 0
let listRevision = 0
let ownsBusy = false
const canView = computed(() => {
  const u = auth.user
  return (
    !!u?.is_active &&
    (u.role === 'senior_technician' ||
      u.role === 'it_manager' ||
      (u.role === 'junior_technician' && props.ticket.assignee?.id === u.id))
  )
})
const canCreate = computed(
  () =>
    auth.user?.role === 'senior_technician' &&
    canView.value &&
    props.ticket.assignee?.id === auth.user.id &&
    props.ticket.support_level === 'senior' &&
    props.ticket.status === 'in_progress' &&
    !props.ticket.pending_information_request &&
    list.value.kind === 'ready' &&
    list.value.result.meta.current_page === 1 &&
    !list.value.result.data.some((r) => r.status === 'pending'),
)
function canDecide(request: EquipmentRequest) {
  return (
    auth.user?.role === 'it_manager' &&
    canView.value &&
    request.status === 'pending' &&
    props.ticket.status === 'waiting_equipment_approval' &&
    !!props.ticket.assignee
  )
}
function releaseBusy() {
  if (ownsBusy) {
    busy.value = false
    ownsBusy = false
  }
}
function resetDraft() {
  reason.value = ''
  items.value = [{ name: '', quantity: 1, estimated_unit_price: '' }]
  decisionReasons.value = {}
}
watch(
  [
    () => props.ticket.id,
    () => auth.user?.id,
    () => auth.user?.role,
    () => auth.user?.is_active,
    canView,
  ],
  () => {
    revision++
    listRevision++
    releaseBusy()
    mutation.value = { kind: 'idle' }
    feedback.value = null
    resetDraft()
    if (canView.value) void load(1)
  },
  { immediate: true },
)
onUnmounted(() => {
  revision++
  listRevision++
  releaseBusy()
})
async function load(page: number) {
  const current = ++listRevision,
    context = revision,
    id = props.ticket.id
  list.value = { kind: 'loading', page }
  try {
    const result = await listEquipmentRequests(id, page)
    if (context === revision && current === listRevision) list.value = { kind: 'ready', result }
  } catch (error) {
    if (context === revision && current === listRevision)
      list.value = { kind: 'error', page, message: errorMessage(error) }
  }
}
function validationError(error: unknown) {
  if (
    isAxiosError<{ errors?: Record<string, string[]> }>(error) &&
    error.response?.status === 422
  ) {
    const fields = error.response.data.errors
    if (fields)
      return Object.entries(fields)
        .map(([field, messages]) => `${field}: ${messages.join(' ')}`)
        .join('\n')
  }
  return errorMessage(error)
}
async function refreshAfterWrite(context = revision) {
  if (mutation.value.kind === 'refreshing') return
  mutation.value = { kind: 'refreshing' }
  const id = props.ticket.id
  try {
    const updated = await getTicket(id)
    const result = await listEquipmentRequests(id, 1)
    if (context !== revision) return
    listRevision++
    list.value = { kind: 'ready', result }
    resetDraft()
    mutation.value = { kind: 'idle' }
    feedback.value = { type: 'success', message: 'บันทึกสำเร็จแล้ว และอัปเดตสถานะล่าสุดแล้ว' }
    emit('updated', updated)
    releaseBusy()
  } catch (error) {
    if (context === revision)
      mutation.value = {
        kind: 'refresh-error',
        message: `บันทึกสำเร็จแล้ว แต่โหลดสถานะล่าสุดไม่สำเร็จ: ${errorMessage(error)}`,
      }
  }
}
async function write(action: () => Promise<EquipmentRequest>) {
  if (busy.value || mutation.value.kind !== 'idle') return
  const context = revision
  ownsBusy = true
  busy.value = true
  mutation.value = { kind: 'posting' }
  feedback.value = null
  try {
    await action()
    if (context !== revision) return
    await refreshAfterWrite(context)
  } catch (error) {
    if (context === revision) {
      mutation.value = { kind: 'idle' }
      feedback.value = { type: 'error', message: validationError(error) }
      releaseBusy()
    }
  }
}
async function create() {
  if (!canCreate.value || busy.value || mutation.value.kind !== 'idle') return
  const input: NewEquipmentRequest = {
    reason: reason.value.trim(),
    items: items.value.map((i) => ({
      name: i.name.trim(),
      quantity: Number(i.quantity),
      estimated_unit_price: i.estimated_unit_price.trim(),
    })),
  }
  const invalid = input.items.some(
    (i) =>
      !i.name ||
      i.name.length > 200 ||
      !Number.isInteger(i.quantity) ||
      i.quantity < 1 ||
      i.quantity > 1000 ||
      !/^\d+(\.\d{1,2})?$/.test(i.estimated_unit_price) ||
      Number(i.estimated_unit_price) > 9999999999.99,
  )
  if (
    !input.reason ||
    input.reason.length > 5000 ||
    input.items.length < 1 ||
    input.items.length > 20 ||
    invalid
  ) {
    feedback.value = {
      type: 'error',
      message:
        'กรุณาระบุเหตุผลและรายการให้ครบ จำนวน 1–1000 และราคาไม่ติดลบ ทศนิยมไม่เกิน 2 ตำแหน่ง (สูงสุด 9999999999.99)',
    }
    return
  }
  const id = props.ticket.id
  await write(() => requestTicketEquipment(id, input))
}
async function decide(request: EquipmentRequest, status: Exclude<EquipmentStatus, 'pending'>) {
  if (!canDecide(request) || busy.value || mutation.value.kind !== 'idle') return
  const text = decisionReasons.value[request.id]?.trim() ?? ''
  if ((status === 'rejected' && !text) || text.length > 2000) {
    feedback.value = { type: 'error', message: 'กรุณาระบุเหตุผลที่ปฏิเสธ (ไม่เกิน 2000 ตัวอักษร)' }
    return
  }
  const id = props.ticket.id
  await write(() => decideTicketEquipment(id, request.id, status, text || null))
}
const statuses: Record<EquipmentStatus, string> = {
  pending: 'รออนุมัติ',
  approved: 'อนุมัติแล้ว',
  rejected: 'ปฏิเสธแล้ว',
}
</script>
<template>
  <section v-if="canView" aria-label="คำขออุปกรณ์" class="mt-8 border-t border-stone-200 pt-6">
    <h2 class="text-lg font-semibold">อุปกรณ์และอะไหล่ที่ขอ</h2>
    <p class="mt-2 text-sm text-stone-600">
      การอนุมัติเป็นการอนุญาตให้จัดหา ยังไม่ใช่การบันทึกอะไหล่ที่ใช้จริงหรือปิดงาน
    </p>
    <p v-if="list.kind === 'loading'" role="status" class="mt-3 text-sm">กำลังโหลดคำขออุปกรณ์...</p>
    <div v-else-if="list.kind === 'error'" class="mt-3">
      <p role="alert" class="text-sm text-red-700">{{ list.message }}</p>
      <button
        data-test="equipment-reload"
        type="button"
        :disabled="busy"
        class="mt-2 border px-3 py-2 text-sm disabled:opacity-50"
        @click="load(list.page)"
      >
        โหลดรายการอีกครั้ง
      </button>
    </div>
    <template v-else>
      <p v-if="!list.result.data.length" class="mt-3 text-sm">ยังไม่มีคำขออุปกรณ์ในหน้านี้</p>
      <article
        v-for="request in list.result.data"
        :key="request.id"
        class="mt-4 border border-stone-200 p-4"
      >
        <h3 class="font-medium">คำขอ #{{ request.id }} · {{ statuses[request.status] }}</h3>
        <p v-if="request.reason !== undefined" class="mt-2 whitespace-pre-line text-sm">
          เหตุผล: {{ request.reason }}
        </p>
        <ul class="mt-2 space-y-2 text-sm">
          <li v-for="item in request.items" :key="item.id" class="break-words">
            {{ item.name }} · จำนวน {{ item.quantity }} ·
            {{
              item.estimated_unit_price !== undefined
                ? `ราคาประมาณต่อหน่วย ${item.estimated_unit_price} บาท`
                : 'ไม่เปิดเผยราคา'
            }}
          </li>
        </ul>
        <p
          v-if="request.estimated_total !== undefined && request.estimated_total !== null"
          class="mt-2 text-sm"
        >
          รวมประมาณ {{ request.estimated_total }} บาท
        </p>
        <p v-if="request.decision_reason" class="mt-2 whitespace-pre-line text-sm">
          เหตุผลการพิจารณา: {{ request.decision_reason }}
        </p>
        <fieldset v-if="canDecide(request)" :disabled="busy" class="mt-3 space-y-2">
          <label :for="`equipment-decision-${request.id}`" class="block text-sm"
            >เหตุผลการพิจารณา (จำเป็นเมื่อปฏิเสธ)</label
          >
          <textarea
            :id="`equipment-decision-${request.id}`"
            v-model="decisionReasons[request.id]"
            maxlength="2000"
            rows="3"
            class="block w-full border border-stone-300 p-2 text-sm"
          ></textarea>
          <div class="flex gap-2">
            <button
              :data-test="`equipment-approved-${request.id}`"
              type="button"
              class="border bg-stone-900 px-3 py-2 text-sm text-white disabled:opacity-50"
              @click="decide(request, 'approved')"
            >
              อนุมัติอุปกรณ์
            </button>
            <button
              :data-test="`equipment-rejected-${request.id}`"
              type="button"
              class="border px-3 py-2 text-sm disabled:opacity-50"
              @click="decide(request, 'rejected')"
            >
              ปฏิเสธอุปกรณ์
            </button>
          </div>
        </fieldset>
      </article>
      <nav
        v-if="list.result.meta.last_page > 1"
        aria-label="หน้าคำขออุปกรณ์"
        class="mt-4 flex items-center gap-3 text-sm"
      >
        <button
          type="button"
          :disabled="busy || list.result.meta.current_page <= 1"
          class="border px-3 py-2 disabled:opacity-50"
          @click="load(list.result.meta.current_page - 1)"
        >
          ก่อนหน้า
        </button>
        <span>หน้า {{ list.result.meta.current_page }} / {{ list.result.meta.last_page }}</span>
        <button
          data-test="equipment-next"
          type="button"
          :disabled="busy || list.result.meta.current_page >= list.result.meta.last_page"
          class="border px-3 py-2 disabled:opacity-50"
          @click="load(list.result.meta.current_page + 1)"
        >
          ถัดไป
        </button>
      </nav>
    </template>
    <form v-if="canCreate" aria-label="ขออุปกรณ์" class="mt-5 space-y-3" @submit.prevent="create">
      <h3 class="font-medium">ร้องขออุปกรณ์หรืออะไหล่</h3>
      <fieldset :disabled="busy" class="space-y-3">
        <label for="equipment-reason" class="block text-sm">เหตุผลที่ต้องใช้</label>
        <textarea
          id="equipment-reason"
          v-model="reason"
          required
          maxlength="5000"
          rows="3"
          class="block w-full border border-stone-300 p-2 text-sm"
        ></textarea>
        <fieldset
          v-for="(item, index) in items"
          :key="index"
          class="space-y-2 border border-stone-200 p-3"
        >
          <legend class="text-sm">รายการที่ {{ index + 1 }}</legend>
          <label :for="`equipment-name-${index}`" class="block text-sm">ชื่ออุปกรณ์</label>
          <input
            :id="`equipment-name-${index}`"
            v-model="item.name"
            required
            maxlength="200"
            class="block w-full border border-stone-300 p-2 text-sm"
          />
          <label :for="`equipment-quantity-${index}`" class="block text-sm">จำนวน</label>
          <input
            :id="`equipment-quantity-${index}`"
            v-model.number="item.quantity"
            type="number"
            required
            min="1"
            max="1000"
            step="1"
            class="block w-full border border-stone-300 p-2 text-sm"
          />
          <label :for="`equipment-price-${index}`" class="block text-sm"
            >ราคาประมาณต่อหน่วย (บาท)</label
          >
          <input
            :id="`equipment-price-${index}`"
            v-model="item.estimated_unit_price"
            type="text"
            inputmode="decimal"
            required
            class="block w-full border border-stone-300 p-2 text-sm"
          />
          <button
            type="button"
            :disabled="items.length <= 1"
            class="border px-3 py-2 text-sm disabled:opacity-50"
            @click="items.splice(index, 1)"
          >
            ลบรายการที่ {{ index + 1 }}
          </button>
        </fieldset>
        <button
          type="button"
          :disabled="items.length >= 20"
          class="border px-3 py-2 text-sm disabled:opacity-50"
          @click="items.push({ name: '', quantity: 1, estimated_unit_price: '' })"
        >
          เพิ่มรายการ (สูงสุด 20)
        </button>
        <button
          type="submit"
          class="ml-2 border bg-stone-900 px-3 py-2 text-sm text-white disabled:opacity-50"
        >
          ส่งคำขออุปกรณ์
        </button>
      </fieldset>
    </form>
    <p
      v-if="feedback"
      :role="feedback.type === 'error' ? 'alert' : 'status'"
      class="mt-3 whitespace-pre-line text-sm"
      :class="feedback.type === 'error' ? 'text-red-700' : 'text-green-800'"
    >
      {{ feedback.message }}
    </p>
    <p
      v-if="mutation.kind === 'posting' || mutation.kind === 'refreshing'"
      role="status"
      class="mt-3 text-sm"
    >
      {{ mutation.kind === 'posting' ? 'กำลังบันทึก...' : 'บันทึกสำเร็จแล้ว กำลังโหลดสถานะล่าสุด...' }}
    </p>
    <div v-if="mutation.kind === 'refresh-error'" class="mt-3 space-y-2">
      <p role="alert" class="text-sm text-red-700">{{ mutation.message }}</p>
      <button
        data-test="equipment-refresh"
        type="button"
        class="border px-3 py-2 text-sm"
        @click="refreshAfterWrite()"
      >
        โหลดสถานะล่าสุดอีกครั้ง (ไม่ส่งคำขอซ้ำ)
      </button>
    </div>
  </section>
</template>
