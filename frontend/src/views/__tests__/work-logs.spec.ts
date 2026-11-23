import { afterEach, describe, expect, it } from 'vitest'
import { AxiosError, type InternalAxiosRequestConfig } from 'axios'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { http } from '@/lib/http'
import { useAuthStore } from '@/stores/auth'
import type { AuthUser } from '@/services/auth'
import type { Ticket } from '@/services/tickets'
import TicketDetailView from '@/views/TicketDetailView.vue'

const originalAdapter = http.defaults.adapter
const wrappers: VueWrapper[] = []
afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
  http.defaults.adapter = originalAdapter
})

function user(role: AuthUser['role'] = 'junior_technician'): AuthUser {
  return {
    id: 9,
    name: 'Technician',
    email: 'technician@example.test',
    role,
    is_active: true,
    department: { id: 1, code: 'IT', name: 'IT' },
  }
}
function ticket(id: number, fields: Partial<Ticket> = {}): Ticket {
  return {
    id,
    ticket_no: `RPR-${id}`,
    title: 'Screen repair',
    description: 'Blank screen',
    location: null,
    asset_label: null,
    status: 'in_progress',
    support_level: 'junior',
    priority: 'normal',
    requester: { id: 1, name: 'Requester' },
    department: { id: 1, code: 'FIN', name: 'Finance' },
    assignee: { id: 9, name: 'Technician' },
    created_at: '2026-11-23T03:00:00Z',
    ...fields,
  }
}
function note() {
  return {
    id: 701,
    ticket_id: 1,
    kind: 'work_log',
    audience: 'junior',
    body: 'ตรวจสายแล้ว',
    author_role: 'junior_technician',
    author: { id: 9, name: 'Technician' },
    created_at: '2026-11-23T03:00:00Z',
  }
}
function reply(data: unknown, config: InternalAxiosRequestConfig) {
  return { data, status: 200, statusText: 'OK', headers: {}, config }
}
function api(
  fields: Partial<Ticket> = {},
  post?: (config: InternalAxiosRequestConfig) => Promise<void>,
) {
  const posts: { url: string | undefined; data: unknown }[] = []
  let saved = false
  http.defaults.adapter = async (config) => {
    if (config.url?.endsWith('/events'))
      return reply(
        {
          data: saved
            ? [
                {
                  id: 801,
                  type: 'note_added',
                  label: 'บันทึกการซ่อม',
                  actor_role: 'junior_technician',
                  actor: { id: 9, name: 'Technician' },
                  from_status: 'in_progress',
                  to_status: 'in_progress',
                  from_level: 'junior',
                  to_level: 'junior',
                  from_assignee_id: 9,
                  to_assignee_id: 9,
                  information_request_id: null,
                  note: note(),
                  metadata: {},
                  created_at: '2026-11-23T03:00:00Z',
                },
              ]
            : [],
          meta: { current_page: 1, last_page: 1, per_page: 20, total: saved ? 1 : 0 },
        },
        config,
      )
    if (config.method === 'post') {
      posts.push({ url: config.url, data: config.data })
      await post?.(config)
      saved = true
      return { ...reply({ data: note() }, config), status: 201, statusText: 'Created' }
    }
    return reply({ data: ticket(config.url?.endsWith('/2') ? 2 : 1, fields) }, config)
  }
  return posts
}
async function open(actor = user()) {
  const pinia = createPinia()
  useAuthStore(pinia).user = actor
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/tickets/:id', name: 'ticket-detail', component: TicketDetailView },
      { path: '/', component: { template: '<div />' } },
    ],
  })
  await router.push('/tickets/1')
  await router.isReady()
  const wrapper = mount(TicketDetailView, { global: { plugins: [pinia, router] } })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, router }
}

describe('work log form', () => {
  it.each(['junior_technician', 'senior_technician'] satisfies AuthUser['role'][])(
    'allows the assigned %s to write a log',
    async (role) => {
      api({ support_level: role === 'senior_technician' ? 'senior' : 'junior' })
      const { wrapper } = await open(user(role))
      expect(wrapper.find('textarea').exists()).toBe(true)
      expect(wrapper.get('label[for="work-log-body"]').text()).toBe('รายละเอียดการซ่อม')
    },
  )

  it.each([
    { name: 'user', actor: user('user'), fields: {} },
    { name: 'manager', actor: user('it_manager'), fields: {} },
    { name: 'inactive technician', actor: { ...user(), is_active: false }, fields: {} },
    { name: 'another assignee', actor: user(), fields: { assignee: { id: 10, name: 'Other' } } },
    { name: 'waiting ticket', actor: user(), fields: { status: 'waiting_info' } },
    { name: 'resolved ticket', actor: user(), fields: { status: 'resolved' } },
  ] satisfies { name: string; actor: AuthUser; fields: Partial<Ticket> }[])(
    'hides the form for $name',
    async ({ actor, fields }) => {
      api(fields)
      const { wrapper } = await open(actor)
      expect(wrapper.find('textarea').exists()).toBe(false)
    },
  )

  it('rejects whitespace without sending a request', async () => {
    const posts = api()
    const { wrapper } = await open()
    await wrapper.get('textarea').setValue('  \n ')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(posts).toHaveLength(0)
    expect(wrapper.get('[role="alert"]').text()).toContain('กรุณาระบุรายละเอียดการซ่อม')
  })

  it('posts trimmed JSON, retains the ticket, clears the draft and refreshes history', async () => {
    const posts = api()
    const { wrapper } = await open()
    await wrapper.get('textarea').setValue('  ตรวจสายแล้ว  ')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(posts).toEqual([{ url: '/api/v1/tickets/1/work-logs', data: '{"body":"ตรวจสายแล้ว"}' }])
    expect(wrapper.get('h1').text()).toContain('RPR-1')
    expect(wrapper.text()).toContain('in_progress')
    expect(wrapper.get('textarea').element.value).toBe('')
    expect(wrapper.get('ol').text()).toContain('ตรวจสายแล้ว')
    expect(wrapper.get('[role="status"]').text()).toContain('บันทึกการซ่อมแล้ว')
  })

  it('prevents duplicate submits while a request is pending', async () => {
    let release = () => {}
    const pending = new Promise<void>((resolve) => {
      release = resolve
    })
    const posts = api({}, async () => pending)
    const { wrapper } = await open()
    await wrapper.get('textarea').setValue('ตรวจสายแล้ว')
    await wrapper.get('form').trigger('submit')
    await wrapper.get('form').trigger('submit')
    expect(posts).toHaveLength(1)
    expect(wrapper.get('fieldset').attributes('disabled')).toBeDefined()
    release()
    await flushPromises()
    expect(wrapper.get('fieldset').attributes('disabled')).toBeUndefined()
  })

  it('keeps the draft and field validation error on failure and lets the user retry', async () => {
    let attempts = 0
    const posts = api({}, async (config) => {
      if (++attempts === 1)
        throw new AxiosError('Validation failed', 'ERR_BAD_REQUEST', config, undefined, {
          ...reply(
            {
              message: 'The given data was invalid.',
              errors: { body: ['รายละเอียดเกินความยาวที่กำหนด'] },
            },
            config,
          ),
          status: 422,
        })
    })
    const { wrapper } = await open()
    await wrapper.get('textarea').setValue('ตรวจสายแล้ว')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.get('textarea').element.value).toBe('ตรวจสายแล้ว')
    expect(wrapper.get('[role="alert"]').text()).toContain('รายละเอียดเกินความยาวที่กำหนด')
    expect(posts).toHaveLength(1)
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(posts).toHaveLength(2)
    expect(wrapper.get('textarea').element.value).toBe('')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it.each(['success', 'error'])(
    'ignores late %s from an old route without clearing the new draft',
    async (outcome) => {
      let release = () => {}
      const pending = new Promise<void>((resolve) => {
        release = resolve
      })
      api({}, async () => {
        await pending
        if (outcome === 'error') throw new Error('old request failed')
      })
      const { wrapper, router } = await open()
      await wrapper.get('textarea').setValue('ร่างใบเก่า')
      await wrapper.get('form').trigger('submit')
      await router.push('/tickets/2')
      await flushPromises()
      expect(wrapper.get('textarea').element.value).toBe('')
      await wrapper.get('textarea').setValue('ร่างใบใหม่')
      release()
      await flushPromises()
      expect(wrapper.get('h1').text()).toContain('RPR-2')
      expect(wrapper.get('textarea').element.value).toBe('ร่างใบใหม่')
      expect(wrapper.find('[role="alert"]').exists()).toBe(false)
      expect(wrapper.text()).not.toContain('บันทึกการซ่อมแล้ว')
    },
  )
})
