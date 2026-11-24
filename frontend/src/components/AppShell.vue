<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { LogOut } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { roleLabels } from '@/lib/ticketLabels'

const auth = useAuthStore()
const router = useRouter()
const loggingOut = ref(false)
const logoutError = ref('')

async function handleLogout() {
  if (loggingOut.value) return

  loggingOut.value = true
  logoutError.value = ''

  try {
    await auth.logout()
    await router.replace({ name: 'login' })
  } catch {
    logoutError.value = 'ออกจากระบบไม่สำเร็จ กรุณาลองใหม่'
  } finally {
    loggingOut.value = false
  }
}
</script>

<template>
  <div class="app min-h-dvh">
    <header class="bg-ink-900 text-ink-100">
      <div class="mx-auto flex h-14 max-w-7xl items-center gap-6 px-4 sm:px-6 lg:px-8">
        <RouterLink
          :to="{ name: 'home' }"
          class="flex shrink-0 items-center gap-2.5 font-semibold text-white"
        >
          <svg viewBox="0 0 24 24" class="size-6" aria-hidden="true" fill="none" stroke-width="2">
            <circle cx="9" cy="12" r="7.5" stroke="var(--color-vermilion-600)" />
            <path d="M3.5 10.5h19M3.5 13.5h19" stroke="currentColor" stroke-linecap="round" />
          </svg>
          <span class="hidden sm:inline">ระบบแจ้งซ่อม</span>
        </RouterLink>

        <nav aria-label="เมนูหลัก" class="flex h-full items-stretch gap-1 text-sm">
          <RouterLink :to="{ name: 'home' }" exact-active-class="nav-on" class="nav-link">
            ใบแจ้งซ่อม
          </RouterLink>
          <RouterLink
            v-if="auth.user?.role === 'it_manager' && auth.user.is_active"
            :to="{ name: 'manager-reports' }"
            active-class="nav-on"
            class="nav-link"
          >
            รายงาน
          </RouterLink>
        </nav>

        <div v-if="auth.user" class="ml-auto flex min-w-0 items-center gap-3 text-sm">
          <div class="hidden min-w-0 text-right leading-tight md:block">
            <p class="truncate font-medium text-white">{{ auth.user.name }}</p>
            <p class="truncate text-xs text-ink-300">
              {{ auth.user.department?.name ?? 'ไม่มีแผนก' }}
            </p>
          </div>
          <span
            class="shrink-0 rounded-[3px] bg-ink-800 px-2 py-1 text-xs font-medium text-white ring-1 ring-ink-700"
          >
            {{ roleLabels[auth.user.role] ?? auth.user.role }}
          </span>
          <button
            type="button"
            :disabled="loggingOut"
            :aria-label="loggingOut ? 'กำลังออกจากระบบ...' : 'ออกจากระบบ'"
            class="inline-flex size-9 shrink-0 items-center justify-center rounded text-ink-300 transition-colors hover:bg-ink-800 hover:text-white disabled:cursor-wait disabled:opacity-60"
            @click="handleLogout"
          >
            <LogOut class="size-4" aria-hidden="true" />
          </button>
        </div>
      </div>
    </header>

    <div v-if="logoutError" class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
      <p role="alert" class="banner-error">{{ logoutError }}</p>
    </div>

    <slot />
  </div>
</template>

<style scoped>
.nav-link {
  display: flex;
  align-items: center;
  border-bottom: 3px solid transparent;
  padding: 3px 0.75rem 0;
  color: var(--color-ink-300);
  transition: color 120ms ease-out;
}
.nav-link:hover {
  color: #fff;
}
.nav-on {
  border-color: var(--color-vermilion-600);
  color: #fff;
  font-weight: 500;
}
</style>
