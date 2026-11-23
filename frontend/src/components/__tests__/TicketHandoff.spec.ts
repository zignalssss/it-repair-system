import { afterEach, describe, expect, it } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { createPinia } from 'pinia'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { createRouter, createMemoryHistory, RouterView } from 'vue-router'
import { AxiosError, type InternalAxiosRequestConfig } from 'axios'
import TicketHandoff from '@/components/TicketHandoff.vue'
import TicketDetailView from '@/views/TicketDetailView.vue'
import HomeView from '@/views/HomeView.vue'
import { useAuthStore } from '@/stores/auth'
import { http } from '@/lib/http'
import type { AuthUser } from '@/services/auth'
import type { Ticket } from '@/services/tickets'

const originalAdapter = http.defaults.adapter
const wrappers: VueWrapper[] = []
afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
  http.defaults.adapter = originalAdapter
})
function user(role: AuthUser['role'] = 'junior_technician'): AuthUser {
  return {
    id: 9,
    name: 'Actor',
    email: 'actor@example.test',
    role,
    is_active: true,
    department: { id: 1, code: 'IT', name: 'IT' },
  }
}
function ticket(fields: Partial<Ticket> = {}): Ticket {
  return {
    id: 1,
    ticket_no: 'RPR-1',
    title: 'Monitor',
    description: 'Blank screen',
    location: null,
    asset_label: null,
    status: 'in_progress',
    support_level: 'junior',
    priority: 'normal',
    requester: { id: 7, name: 'Owner' },
    department: { id: 1, code: 'FIN', name: 'Finance' },
    assignee: { id: 9, name: 'Tech' },
    created_at: '2026-11-23T03:00:00Z',
    ...fields,
  }
}
function transferred(senior = false) {
  return ticket({
    status: senior ? 'returned' : 'escalated',
    support_level: senior ? 'junior' : 'senior',
    assignee: null,
  })
}
function reply(data: unknown, config: InternalAxiosRequestConfig) {
  return { data, status: 200, statusText: 'OK', headers: {}, config }
}
async function open(initial = ticket(), actor = user()) {
  const current = ref(initial),
    busy = ref(false),
    completions: Ticket[] = []
  const pinia = createPinia()
  useAuthStore(pinia).user = actor
  const Host = defineComponent({
    setup: () => () =>
      h(TicketHandoff, {
        ticket: current.value,
        busy: busy.value,
        'onUpdate:busy': (value: boolean) => {
          busy.value = value
        },
        onCompleted: (value: Ticket) => {
          completions.push(value)
        },
      }),
  })
  const wrapper = mount(Host, { global: { plugins: [pinia] } })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, current, busy, completions }
}

describe('TicketHandoff eligibility', () => {
  it.each([
    { role: 'junior_technician', level: 'junior', button: 'ส่งต่อช่างอาวุโส' },
    { role: 'senior_technician', level: 'senior', button: 'ส่งกลับช่างระดับต้น' },
  ] satisfies { role: AuthUser['role']; level: Ticket['support_level']; button: string }[])(
    'offers the correct handoff to $role',
    async ({ role, level, button }) => {
      const { wrapper } = await open(ticket({ support_level: level }), user(role))
      expect(wrapper.get('button[type="submit"]').text()).toBe(button)
      expect(wrapper.get('textarea').attributes('maxlength')).toBe('10000')
    },
  )
  it.each([
    { name: 'User', actor: user('user'), fields: {} },
    { name: 'Manager', actor: user('it_manager'), fields: {} },
    { name: 'inactive', actor: { ...user(), is_active: false }, fields: {} },
    { name: 'other assignee', actor: user(), fields: { assignee: { id: 10, name: 'Other' } } },
    { name: 'new ticket', actor: user(), fields: { status: 'new' } },
    { name: 'waiting ticket', actor: user(), fields: { status: 'waiting_info' } },
    { name: 'Junior on senior level', actor: user(), fields: { support_level: 'senior' } },
    { name: 'Senior on junior level', actor: user('senior_technician'), fields: {} },
  ] satisfies { name: string; actor: AuthUser; fields: Partial<Ticket> }[])(
    'hides the form for $name',
    async ({ actor, fields }) => {
      const { wrapper } = await open(ticket(fields), actor)
      expect(wrapper.find('form').exists()).toBe(false)
    },
  )
})

describe('TicketHandoff request', () => {
  it.each([false, true])(
    'posts trimmed JSON and returns the transferred Ticket, senior=%s',
    async (senior) => {
      const posts: { url: string | undefined; body: unknown }[] = []
      http.defaults.adapter = async (config) => {
        posts.push({ url: config.url, body: config.data })
        return reply({ data: transferred(senior) }, config)
      }
      const { wrapper, completions } = await open(
        ticket({ support_level: senior ? 'senior' : 'junior' }),
        user(senior ? 'senior_technician' : 'junior_technician'),
      )
      await wrapper.get('textarea').setValue('  ตรวจสายแล้ว\nขอตรวจเพิ่ม  ')
      await wrapper.get('form').trigger('submit')
      await flushPromises()
      expect(posts).toEqual([
        {
          url: senior ? '/api/v1/tickets/1/return-to-junior' : '/api/v1/tickets/1/escalate',
          body: '{"body":"ตรวจสายแล้ว\\nขอตรวจเพิ่ม"}',
        },
      ])
      expect(completions[0]?.status).toBe(senior ? 'returned' : 'escalated')
      expect(completions[0]?.assignee).toBeNull()
      await wrapper.get('form').trigger('submit')
      await flushPromises()
      expect(posts).toHaveLength(1)
    },
  )
  it('rejects whitespace without a network call', async () => {
    let posts = 0
    http.defaults.adapter = async (config) => {
      posts++
      return reply({ data: transferred() }, config)
    }
    const { wrapper } = await open()
    await wrapper.get('textarea').setValue('  \n ')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(posts).toBe(0)
    expect(wrapper.get('[role="alert"]').text()).toContain('กรุณาระบุ')
  })
  it('keeps the draft and shows a 422 body error before retrying', async () => {
    let count = 0
    http.defaults.adapter = async (config) => {
      if (++count === 1)
        throw new AxiosError('Invalid', 'ERR_BAD_REQUEST', config, undefined, {
          ...reply({ message: 'Invalid', errors: { body: ['กรุณาอธิบายคำแนะนำให้ครบ'] } }, config),
          status: 422,
        })
      return reply({ data: transferred() }, config)
    }
    const { wrapper } = await open()
    await wrapper.get('textarea').setValue('ตรวจสายแล้ว')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.get('textarea').element.value).toBe('ตรวจสายแล้ว')
    expect(wrapper.get('[role="alert"]').text()).toContain('กรุณาอธิบายคำแนะนำให้ครบ')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(count).toBe(2)
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })
  it('blocks a repeated submit while pending', async () => {
    let release = () => {}
    const pending = new Promise<void>((resolve) => {
      release = resolve
    })
    let posts = 0
    http.defaults.adapter = async (config) => {
      posts++
      await pending
      return reply({ data: transferred() }, config)
    }
    const { wrapper, busy } = await open()
    await wrapper.get('textarea').setValue('ตรวจเพิ่ม')
    await wrapper.get('form').trigger('submit')
    await wrapper.get('form').trigger('submit')
    expect(posts).toBe(1)
    expect(busy.value).toBe(true)
    release()
    await flushPromises()
    expect(busy.value).toBe(false)
  })
  it.each(['success', 'error'])(
    'ignores a late %s after ticket context changes',
    async (outcome) => {
      let release = () => {}
      const pending = new Promise<void>((resolve) => {
        release = resolve
      })
      http.defaults.adapter = async (config) => {
        await pending
        if (outcome === 'error') throw new Error('old error')
        return reply({ data: transferred() }, config)
      }
      const { wrapper, current, completions, busy } = await open()
      await wrapper.get('textarea').setValue('ร่างเก่า')
      await wrapper.get('form').trigger('submit')
      current.value = ticket({ id: 2, ticket_no: 'RPR-2' })
      await flushPromises()
      await wrapper.get('textarea').setValue('ร่างใหม่')
      release()
      await flushPromises()
      expect(wrapper.get('textarea').element.value).toBe('ร่างใหม่')
      expect(wrapper.find('[role="alert"]').exists()).toBe(false)
      expect(completions).toHaveLength(0)
      expect(busy.value).toBe(false)
    },
  )
})

describe('handoff Detail navigation', () => {
  it.each([false, true])(
    'leaves the old ticket without forbidden history reloads, senior=%s',
    async (senior) => {
      const pinia = createPinia()
      useAuthStore(pinia).user = user(senior ? 'senior_technician' : 'junior_technician')
      let posted = false,
        oldHistoryReads = 0,
        postCount = 0,
        releasePost = () => {},
        release = () => {}
      const postPending = new Promise<void>((resolve) => {
        releasePost = resolve
      })
      const navigating = new Promise<void>((resolve) => {
        release = resolve
      })
      http.defaults.adapter = async (config) => {
        if (config.url?.endsWith('/events')) {
          oldHistoryReads++
          return reply(
            { data: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } },
            config,
          )
        }
        if (config.method === 'post') {
          postCount++
          await postPending
          posted = true
          return reply({ data: transferred(senior) }, config)
        }
        if (config.url === '/api/v1/tickets')
          return reply(
            { data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } },
            config,
          )
        return reply({ data: ticket({ support_level: senior ? 'senior' : 'junior' }) }, config)
      }
      const router = createRouter({
        history: createMemoryHistory(),
        routes: [
          { path: '/', name: 'home', component: HomeView },
          { path: '/tickets/:id', name: 'ticket-detail', component: TicketDetailView },
        ],
      })
      router.beforeEach(async (to) => {
        if (to.name === 'home' && posted) await navigating
      })
      await router.push('/tickets/1')
      await router.isReady()
      const wrapper = mount(RouterView, { global: { plugins: [pinia, router] } })
      wrappers.push(wrapper)
      await flushPromises()
      expect(oldHistoryReads).toBe(1)
      const form = wrapper.get('form[aria-label="ส่งต่องาน"]')
      await form.get('textarea').setValue('ตรวจเพิ่ม')
      await form.trigger('submit')
      await flushPromises()
      expect(
        wrapper.get('form[aria-label="บันทึกการซ่อม"] fieldset').attributes('disabled'),
      ).toBeDefined()
      await wrapper.get('form[aria-label="บันทึกการซ่อม"] textarea').setValue('Must not send')
      await wrapper.get('form[aria-label="บันทึกการซ่อม"]').trigger('submit')
      expect(postCount).toBe(1)
      releasePost()
      await flushPromises()
      expect(wrapper.find('form').exists()).toBe(false)
      expect(wrapper.find('ol').exists()).toBe(false)
      expect(wrapper.text()).toContain('ส่งต่องานสำเร็จแล้ว')
      expect(oldHistoryReads).toBe(1)
      release()
      await flushPromises()
      expect(router.currentRoute.value.name).toBe('home')
      expect(router.currentRoute.value.query.handoff).toBe(senior ? 'returned' : 'escalated')
      expect(wrapper.get('[role="status"]').text()).toContain(
        senior ? 'ส่งกลับช่างระดับต้นแล้ว' : 'ส่งต่อช่างอาวุโสแล้ว',
      )
      expect(oldHistoryReads).toBe(1)
    },
  )
  it('keeps completed handoff visible without restoring write forms if navigation fails', async () => {
    const pinia = createPinia()
    useAuthStore(pinia).user = user()
    let posted = false
    http.defaults.adapter = async (config) => {
      if (config.url?.endsWith('/events'))
        return reply(
          { data: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } },
          config,
        )
      if (config.method === 'post') {
        posted = true
        return reply({ data: transferred() }, config)
      }
      return reply({ data: ticket() }, config)
    }
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', name: 'home', component: HomeView },
        { path: '/tickets/:id', name: 'ticket-detail', component: TicketDetailView },
      ],
    })
    router.onError(() => {})
    router.beforeEach((to) => {
      if (to.name === 'home' && posted) throw new Error('navigation failed')
    })
    await router.push('/tickets/1')
    await router.isReady()
    const wrapper = mount(RouterView, { global: { plugins: [pinia, router] } })
    wrappers.push(wrapper)
    await flushPromises()
    const form = wrapper.get('form[aria-label="ส่งต่องาน"]')
    await form.get('textarea').setValue('ตรวจเพิ่ม')
    await form.trigger('submit')
    await flushPromises()
    expect(wrapper.find('form').exists()).toBe(false)
    expect(wrapper.get('[role="alert"]').text()).toContain('ส่งต่อสำเร็จแล้ว แต่เปิดหน้ารายการไม่สำเร็จ')
    expect(wrapper.get('[role="status"]').text()).toContain('ส่งต่องานสำเร็จแล้ว')
    expect(wrapper.find('a[href="/"]').exists()).toBe(true)
  })

  it('ignores an unknown success hint on Home', async () => {
    http.defaults.adapter = async (config) =>
      reply({ data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } }, config)
    const pinia = createPinia()
    useAuthStore(pinia).user = user()
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/', name: 'home', component: HomeView }],
    })
    await router.push('/?handoff=made-up')
    await router.isReady()
    const wrapper = mount(HomeView, { global: { plugins: [pinia, router] } })
    wrappers.push(wrapper)
    await flushPromises()
    expect(wrapper.find('[role="status"]').exists()).toBe(false)
  })
})
