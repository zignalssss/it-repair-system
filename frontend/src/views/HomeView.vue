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
  <main v-if="auth.user">
    <h1>ระบบแจ้งซ่อม</h1>

    <dl>
      <dt>ชื่อ</dt>
      <dd>{{ auth.user.name }}</dd>

      <dt>อีเมล</dt>
      <dd>{{ auth.user.email }}</dd>

      <dt>บทบาท</dt>
      <dd>{{ auth.user.role }}</dd>

      <dt>แผนก</dt>
      <dd>{{ auth.user.department?.name ?? 'ไม่มีแผนก' }}</dd>
    </dl>

    <p v-if="errorMessage" role="alert">
      {{ errorMessage }}
    </p>

    <button :disabled="loggingOut" @click="handleLogout">
      {{ loggingOut ? 'กำลังออกจากระบบ...' : 'ออกจากระบบ' }}
    </button>
  </main>
</template>