<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { errorMessage } from '@/lib/http'
import { createTicket } from '@/services/tickets'
import { ArrowLeft } from 'lucide-vue-next'

const router = useRouter()

const title = ref('')
const description = ref('')
const location = ref('')
const assetLabel = ref('')
const files = ref<File[]>([])
const submitting = ref(false)
const error = ref('')

function pickFiles(event: Event) {
  const input = event.target as HTMLInputElement
  files.value = Array.from(input.files ?? [])
}

async function submit() {
  if (submitting.value) return

  submitting.value = true
  error.value = ''

  try {
    const ticket = await createTicket({
      title: title.value.trim(),
      description: description.value.trim(),
      location: location.value.trim(),
      asset_label: assetLabel.value.trim(),
      files: files.value,
    })

    await router.push({ name: 'ticket-detail', params: { id: ticket.id } })
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <main class="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
    <RouterLink
      to="/"
      class="inline-flex items-center gap-1.5 text-sm text-ink-600 hover:text-ink-950"
    >
      <ArrowLeft class="size-4" aria-hidden="true" />
      กลับรายการใบแจ้งซ่อม
    </RouterLink>
    <h1 class="mt-4 text-2xl font-semibold text-ink-950">แจ้งซ่อม</h1>
    <p class="hint mt-1">บอกว่าอะไรเสียและอยู่ที่ไหน ทีมไอทีจะรับเรื่องตามลำดับความเร่งด่วน</p>

    <form class="panel mt-6" @submit.prevent="submit">
      <fieldset :disabled="submitting" class="space-y-5">
        <div>
          <label for="ticket-title" class="label"
            >หัวข้อ <span class="text-vermilion-700" aria-hidden="true">*</span></label
          >
          <input
            id="ticket-title"
            v-model="title"
            required
            maxlength="200"
            class="field mt-1.5"
            placeholder="เช่น เครื่องพิมพ์ชั้น 3 กระดาษติด"
          />
        </div>

        <div>
          <label for="ticket-description" class="label"
            >รายละเอียด <span class="text-vermilion-700" aria-hidden="true">*</span></label
          >
          <textarea
            id="ticket-description"
            v-model="description"
            required
            rows="5"
            class="field mt-1.5"
            placeholder="อาการที่พบ เกิดตั้งแต่เมื่อไร และลองทำอะไรไปแล้ว"
          ></textarea>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
          <div>
            <label for="ticket-location" class="label">สถานที่</label>
            <input
              id="ticket-location"
              v-model="location"
              maxlength="255"
              class="field mt-1.5"
              placeholder="อาคาร ชั้น ห้อง"
            />
          </div>
          <div>
            <label for="ticket-asset" class="label">รหัสอุปกรณ์</label>
            <input
              id="ticket-asset"
              v-model="assetLabel"
              maxlength="100"
              class="field mt-1.5"
              placeholder="ดูจากสติกเกอร์บนเครื่อง"
            />
          </div>
        </div>

        <div>
          <label for="ticket-files" class="label">ไฟล์แนบ</label>
          <p id="ticket-files-hint" class="hint mt-0.5">
            สูงสุด 3 ไฟล์ ไฟล์ละไม่เกิน 2MB · JPG, PNG, WebP, PDF, MP4, WebM
          </p>
          <input
            id="ticket-files"
            type="file"
            multiple
            accept=".jpg,.jpeg,.png,.webp,.pdf,.mp4,.webm"
            aria-describedby="ticket-files-hint"
            class="peer sr-only"
            @change="pickFiles"
          />
          <label
            for="ticket-files"
            class="btn mt-2 cursor-pointer peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-vermilion-600"
          >
            เลือกไฟล์
          </label>
          <ul v-if="files.length" class="mt-2 space-y-1 text-sm">
            <li
              v-for="(file, index) in files"
              :key="index"
              class="flex justify-between gap-4 break-all"
            >
              <span>{{ file.name }}</span>
              <span class="code shrink-0 text-ink-500">{{ Math.ceil(file.size / 1024) }} KB</span>
            </li>
          </ul>
          <p v-else class="hint mt-2">ยังไม่ได้เลือกไฟล์</p>
        </div>

        <p v-if="error" role="alert" class="banner-error">{{ error }}</p>

        <div class="flex items-center justify-end gap-3 border-t border-ink-100 pt-5">
          <RouterLink to="/" class="btn">ยกเลิก</RouterLink>
          <button type="submit" class="btn btn-primary">
            {{ submitting ? 'กำลังส่ง...' : 'ส่งเรื่อง' }}
          </button>
        </div>
      </fieldset>
    </form>
  </main>
</template>
