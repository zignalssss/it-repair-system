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
  <main class="grid min-h-dvh lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
    <section
      class="relative flex flex-col justify-between overflow-hidden bg-stone-950 px-6 py-6 text-stone-100 sm:px-10 lg:py-12"
    >
      <div class="flex items-center gap-3">
        <span
          class="relative inline-flex items-center rounded-sm bg-signal-500 py-1 pr-2.5 pl-5 font-mono text-xs font-semibold tracking-wider text-stone-950"
        >
          <span class="absolute left-1.5 size-1.5 rounded-full bg-stone-950" aria-hidden="true"></span>
          IT
        </span>
        <span class="text-sm font-medium text-stone-300">ระบบแจ้งซ่อม</span>
      </div>

      <div class="hidden lg:block">
        <p class="max-w-sm text-4xl leading-tight font-semibold text-balance text-white">
          อุปกรณ์มีปัญหา แจ้งทีมไอทีได้ที่นี่
        </p>

        <ol class="mt-10 max-w-sm border-t border-stone-800">
          <li class="flex items-baseline gap-5 border-b border-stone-800 py-4">
            <span class="font-mono text-xs text-signal-500">01</span>
            <span class="text-stone-300">พนักงานแจ้งปัญหา</span>
          </li>
          <li class="flex items-baseline gap-5 border-b border-stone-800 py-4">
            <span class="font-mono text-xs text-signal-500">02</span>
            <span class="text-stone-300">ช่างไอทีรับเรื่องและดำเนินการ</span>
          </li>
          <li class="flex items-baseline gap-5 border-b border-stone-800 py-4">
            <span class="font-mono text-xs text-signal-500">03</span>
            <span class="text-stone-300">ติดตามสถานะจนปิดงาน</span>
          </li>
        </ol>
      </div>

      <p class="hidden font-mono text-xs text-stone-500 lg:block">สำหรับพนักงานและทีมไอทีภายในองค์กร</p>
    </section>

    <!-- Form -->
    <section class="flex items-center justify-center px-4 py-12 sm:px-10">
      <div class="w-full max-w-sm">
        <h1 class="text-2xl font-semibold text-stone-950">เข้าสู่ระบบแจ้งซ่อม</h1>
        <p class="mt-1.5 text-sm text-stone-500">ใช้อีเมลและรหัสผ่านที่องค์กรออกให้</p>

        <p
          v-if="connectionMessage"
          role="status"
          class="mt-6 border-l-2 border-signal-500 bg-signal-50 px-3 py-2.5 text-sm text-signal-700"
        >
          {{ connectionMessage }}
        </p>

        <form class="mt-8" @submit.prevent="submit">
          <fieldset :disabled="submitting" class="space-y-5">
            <div>
              <label for="email" class="mb-1.5 block text-sm font-medium text-stone-800">อีเมล</label>
              <input
                id="email"
                v-model="email"
                type="email"
                autocomplete="username"
                required
                class="block h-11 w-full rounded-md border border-stone-300 bg-white px-3 text-base text-stone-950 transition-colors outline-none hover:border-stone-400 focus:border-stone-950 focus:ring-3 focus:ring-signal-500/35 disabled:bg-stone-50 disabled:text-stone-500 sm:text-sm"
              />
            </div>

            <div>
              <label for="password" class="mb-1.5 block text-sm font-medium text-stone-800">รหัสผ่าน</label>
              <input
                id="password"
                v-model="password"
                type="password"
                autocomplete="current-password"
                required
                class="block h-11 w-full rounded-md border border-stone-300 bg-white px-3 text-base text-stone-950 transition-colors outline-none hover:border-stone-400 focus:border-stone-950 focus:ring-3 focus:ring-signal-500/35 disabled:bg-stone-50 disabled:text-stone-500 sm:text-sm"
              />
            </div>

            <p
              v-if="errorMessage"
              role="alert"
              class="border-l-2 border-red-600 bg-red-50 px-3 py-2.5 text-sm text-red-800"
            >
              {{ errorMessage }}
            </p>

            <button
              type="submit"
              class="h-11 w-full rounded-md bg-stone-950 px-4 text-sm font-semibold text-white transition-colors hover:bg-stone-800 focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-signal-500/60 active:bg-stone-900 disabled:cursor-wait disabled:bg-stone-600"
            >
              {{ submitting ? 'กำลังเข้าสู่ระบบ...' : 'เข้าสู่ระบบ' }}
            </button>
          </fieldset>
        </form>
      </div>
    </section>
  </main>
</template>
