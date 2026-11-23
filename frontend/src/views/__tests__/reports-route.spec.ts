import { afterEach, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import router from '@/router'
import { http } from '@/lib/http'
import type { AuthUser } from '@/services/auth'
const adapter = http.defaults.adapter
const actors = [
  { role: 'user', is_active: true, expected: 'home' },
  { role: 'junior_technician', is_active: true, expected: 'home' },
  { role: 'senior_technician', is_active: true, expected: 'home' },
  { role: 'it_manager', is_active: false, expected: 'home' },
  { role: 'it_manager', is_active: true, expected: 'manager-reports' },
] satisfies { role: AuthUser['role']; is_active: boolean; expected: string }[]
afterEach(() => {
  http.defaults.adapter = adapter
})
it.each(actors)('guards /reports for $role active=$is_active', async (actor) => {
  setActivePinia(createPinia())
  http.defaults.adapter = async (config) => ({
    data: {
      data: {
        id: 9,
        name: 'Example',
        email: 'example@example.test',
        role: actor.role,
        is_active: actor.is_active,
        department: null,
      },
    },
    status: 200,
    statusText: 'OK',
    headers: {},
    config,
  })
  await router.push('/')
  await router.push('/reports')
  expect(router.currentRoute.value.name).toBe(actor.expected)
})
