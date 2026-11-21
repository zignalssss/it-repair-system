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
  <main class="flex min-h-screen items-center justify-center bg-slate-100 px-4 py-12">
    <div class="w-full max-w-sm">
      <div class="mb-6 text-center">
        <div
          class="mx-auto mb-4 flex size-12 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-sm"
          aria-hidden="true"
        >
          <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085"
            />
          </svg>
        </div>
        <h1 class="text-2xl font-semibold text-slate-900">เข้าสู่ระบบแจ้งซ่อม</h1>
      </div>

      <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
        <p
          v-if="connectionMessage"
          role="status"
          class="mb-5 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200"
        >
          {{ connectionMessage }}
        </p>

        <form @submit.prevent="submit">
          <fieldset :disabled="submitting" class="space-y-5">
            <div>
              <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">อีเมล</label>
              <input
                id="email"
                v-model="email"
                type="email"
                autocomplete="username"
                required
                class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-base text-slate-900 shadow-xs placeholder:text-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none disabled:bg-slate-50 disabled:text-slate-500 sm:text-sm"
              />
            </div>

            <div>
              <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">รหัสผ่าน</label>
              <input
                id="password"
                v-model="password"
                type="password"
                autocomplete="current-password"
                required
                class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-base text-slate-900 shadow-xs placeholder:text-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none disabled:bg-slate-50 disabled:text-slate-500 sm:text-sm"
              />
            </div>

            <p
              v-if="errorMessage"
              role="alert"
              class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200"
            >
              {{ errorMessage }}
            </p>

            <button
              type="submit"
              class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:bg-indigo-400"
            >
              {{ submitting ? 'กำลังเข้าสู่ระบบ...' : 'เข้าสู่ระบบ' }}
            </button>
          </fieldset>
        </form>
      </div>
    </div>
  </main>
</template>