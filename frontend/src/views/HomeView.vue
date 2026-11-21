<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const loggingOut = ref(false)
const errorMessage = ref('')

async function handleLogout() {
  if (loggingOut.value) return

  loggingOut.value = true
  errorMessage.value = ''

  try {
    await auth.logout()
    await router.replace({ name: 'login' })
  } catch {
    errorMessage.value = 'ออกจากระบบไม่สำเร็จ กรุณาลองใหม่'
  } finally {
    loggingOut.value = false
  }
}
</script>

<template>
  <main v-if="auth.user" class="min-h-screen bg-slate-100">
    <header class="border-b border-slate-200 bg-white">
      <div class="mx-auto flex max-w-4xl items-center justify-between gap-4 px-4 py-4">
        <h1 class="text-lg font-semibold text-slate-900">ระบบแจ้งซ่อม</h1>

        <button
          :disabled="loggingOut"
          class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 ring-1 ring-slate-300 transition-colors hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-60"
          @click="handleLogout"
        >
          {{ loggingOut ? 'กำลังออกจากระบบ...' : 'ออกจากระบบ' }}
        </button>
      </div>
    </header>

    <div class="mx-auto max-w-4xl px-4 py-8">
      <p
        v-if="errorMessage"
        role="alert"
        class="mb-6 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200"
      >
        {{ errorMessage }}
      </p>

      <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <dl class="divide-y divide-slate-100">
          <div class="grid gap-1 px-6 py-4 sm:grid-cols-3 sm:gap-4">
            <dt class="text-sm font-medium text-slate-500">ชื่อ</dt>
            <dd class="text-sm text-slate-900 sm:col-span-2">{{ auth.user.name }}</dd>
          </div>

          <div class="grid gap-1 px-6 py-4 sm:grid-cols-3 sm:gap-4">
            <dt class="text-sm font-medium text-slate-500">อีเมล</dt>
            <dd class="break-all text-sm text-slate-900 sm:col-span-2">{{ auth.user.email }}</dd>
          </div>

          <div class="grid gap-1 px-6 py-4 sm:grid-cols-3 sm:gap-4">
            <dt class="text-sm font-medium text-slate-500">บทบาท</dt>
            <dd class="sm:col-span-2">
              <span
                class="inline-flex rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700 ring-1 ring-indigo-200"
              >
                {{ auth.user.role }}
              </span>
            </dd>
          </div>

          <div class="grid gap-1 px-6 py-4 sm:grid-cols-3 sm:gap-4">
            <dt class="text-sm font-medium text-slate-500">แผนก</dt>
            <dd class="text-sm text-slate-900 sm:col-span-2">
              {{ auth.user.department?.name ?? 'ไม่มีแผนก' }}
            </dd>
          </div>
        </dl>
      </section>
    </div>
  </main>
</template>