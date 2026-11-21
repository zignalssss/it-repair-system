import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { isAxiosError } from 'axios'
import * as authApi from '@/services/auth'
import type { AuthUser } from '@/services/auth'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)

  const isAuthenticated = computed(() => user.value !== null)

  async function fetchUser() {
    try {
      user.value = await authApi.getCurrentUser()
    } catch (error) {
      if (
        isAxiosError(error) &&
        (error.response?.status === 401 || error.response?.status === 403)
      ) {
        user.value = null
        return
      }

      throw error
    }
  }

  async function login(email: string, password: string) {
    user.value = await authApi.login(email, password)
  }

  async function logout() {
    try {
      await authApi.logout()
    } catch (error) {
      if (!isAxiosError(error) || error.response?.status !== 401) {
        throw error
      }
    }

    user.value = null
  }

  return {
    user,
    isAuthenticated,
    fetchUser,
    login,
    logout,
  }
})