<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { errorMessage } from '@/lib/http'
import { createTicket } from '@/services/tickets'

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
  <main class="mx-auto max-w-xl p-6">
    <RouterLink to="/" class="text-sm underline">← กลับ</RouterLink>
    <h1 class="mt-4 text-xl font-semibold">แจ้งซ่อม</h1>

    <form class="mt-6 space-y-4" @submit.prevent="submit">
      <fieldset :disabled="submitting" class="space-y-4">
        <label class="block">
          หัวข้อ *
          <input v-model="title" required maxlength="200" class="block w-full border p-2" />
        </label>

        <label class="block">
          รายละเอียด *
          <textarea v-model="description" required rows="4" class="block w-full border p-2"></textarea>
        </label>

        <label class="block">
          สถานที่
          <input v-model="location" maxlength="255" class="block w-full border p-2" />
        </label>

        <label class="block">
          รหัสอุปกรณ์
          <input v-model="assetLabel" maxlength="100" class="block w-full border p-2" />
        </label>

        <label class="block">
          ไฟล์แนบ (สูงสุด 3 ไฟล์, ไฟล์ละไม่เกิน 2MB)
          <input
            type="file"
            multiple
            accept=".jpg,.jpeg,.png,.webp,.pdf,.mp4,.webm"
            class="block"
            @change="pickFiles"
          />
        </label>

        <p v-if="error" role="alert" class="text-red-700">{{ error }}</p>

        <button type="submit" class="border bg-stone-900 px-4 py-2 text-white">
          {{ submitting ? 'กำลังส่ง...' : 'ส่งเรื่อง' }}
        </button>
      </fieldset>
    </form>
  </main>
</template>
