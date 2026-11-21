<script setup lang="ts">
import { computed, ref } from 'vue'
import { isAxiosError } from 'axios'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

interface ApiError {
  message?: string
  errors?: Record<string, string[]>
}

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const submitting = ref(false)
const errorMessage = ref('')

const connectionMessage = computed(() =>
  route.query.unavailable === '1'
    ? 'ตรวจสอบสถานะการเข้าสู่ระบบไม่ได้ กรุณาตรวจสอบการเชื่อมต่อแล้วลองใหม่'
    : '',
)

async function submit() {
  if (submitting.value) return

  submitting.value = true
  errorMessage.value = ''

  try {
    await auth.login(email.value.trim(), password.value)
    password.value = ''

    await router.replace({ name: 'home' })
  } catch (error) {
    if (isAxiosError<ApiError>(error)) {
      const status = error.response?.status

      if (status === 422) {
        errorMessage.value =
          error.response?.data.errors?.email?.[0] ??
          error.response?.data.errors?.password?.[0] ??
          'กรุณาตรวจสอบข้อมูลที่กรอก'
      } else if (status === 429) {
        errorMessage.value = 'ลองเข้าสู่ระบบบ่อยเกินไป กรุณารอสักครู่'
      } else if (status === 419) {
        errorMessage.value = 'Session หมดอายุ กรุณาลองเข้าสู่ระบบอีกครั้ง'
      } else {
        errorMessage.value = 'เชื่อมต่อระบบไม่ได้ กรุณาลองใหม่'
      }
    } else {
      errorMessage.value = 'เกิดข้อผิดพลาด กรุณาลองใหม่'
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <main>
    <h1>เข้าสู่ระบบแจ้งซ่อม</h1>

    <p v-if="connectionMessage" role="status">
      {{ connectionMessage }}
    </p>

    <form @submit.prevent="submit">
      <fieldset :disabled="submitting">
        <div>
          <label for="email">อีเมล</label>
          <input
            id="email"
            v-model="email"
            type="email"
            autocomplete="username"
            required
          />
        </div>

        <div>
          <label for="password">รหัสผ่าน</label>
          <input
            id="password"
            v-model="password"
            type="password"
            autocomplete="current-password"
            required
          />
        </div>

        <p v-if="errorMessage" role="alert">
          {{ errorMessage }}
        </p>

        <button type="submit">
          {{ submitting ? 'กำลังเข้าสู่ระบบ...' : 'เข้าสู่ระบบ' }}
        </button>
      </fieldset>
    </form>
  </main>
</template>