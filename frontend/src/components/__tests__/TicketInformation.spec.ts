import { afterEach, describe, expect, it } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { createPinia } from 'pinia'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { AxiosError, type InternalAxiosRequestConfig } from 'axios'
import TicketInformation from '@/components/TicketInformation.vue'
import TicketDetailView from '@/views/TicketDetailView.vue'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { http } from '@/lib/http'
import type { AuthUser } from '@/services/auth'
import type { Ticket, PendingInformationRequest } from '@/services/tickets'

const originalAdapter = http.defaults.adapter
const wrappers: VueWrapper[] = []
afterEach(() => {
  wrappers.splice(0).forEach((w) => w.unmount())
  http.defaults.adapter = originalAdapter
})
function user(role: AuthUser['role'] = 'junior_technician'): AuthUser {
  return {
    id: 9,
    name: 'Actor',
    email: 'actor@example.test',
    role,
    is_active: true,
    department: { id: 1, code: 'FIN', name: 'Finance' },
  }
}
function pending(id = 51): PendingInformationRequest {
  return {
    id,
    requested_at: '2026-11-23T03:00:00Z',
    question: {
      id: 101,
      ticket_id: 1,
      kind: 'info_question',
      audience: 'requester',
      body: 'ขอภาพหน้าจอ',
      author_role: 'junior_technician',
      author: { id: 8, name: 'ช่าง' },
      created_at: '2026-11-23T03:00:00Z',
    },
  }
}
function ticket(fields: Partial<Ticket> = {}): Ticket {
  return {
    id: 1,
    ticket_no: 'RPR-1',
    title: 'Monitor',
    description: 'Blank',
    location: null,
    asset_label: null,
    status: 'in_progress',
    support_level: 'junior',
    priority: 'normal',
    requester: { id: 9, name: 'Owner' },
    department: { id: 1, code: 'FIN', name: 'Finance' },
    assignee: { id: 9, name: 'Tech' },
    created_at: '2026-11-23T03:00:00Z',
    pending_information_request: null,
    ...fields,
  }
}
function reply(data: unknown, config: InternalAxiosRequestConfig) {
  return { data, status: 200, statusText: 'OK', headers: {}, config }
}
async function open(initial = ticket(), actor = user()) {
  const current = ref(initial),
    busy = ref(false),
    updates: Ticket[] = []
  const pinia = createPinia()
  useAuthStore(pinia).user = actor
  const Host = defineComponent({
    setup: () => () =>
      h(TicketInformation, {
        ticket: current.value,
        busy: busy.value,
        'onUpdate:busy': (value: boolean) => {
          busy.value = value
        },
        onUpdated: (value: Ticket) => {
          updates.push(value)
          current.value = value
        },
      }),
  })
  const wrapper = mount(Host, { global: { plugins: [pinia] } })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, current, busy, updates }
}
function choose(wrapper: VueWrapper, files: File[]) {
  const input = wrapper.get('input[type="file"]')
  Object.defineProperty(input.element, 'files', { configurable: true, value: files })
  return input.trigger('change')
}

describe('information requests', () => {
  it.each(['junior_technician', 'senior_technician'] satisfies AuthUser['role'][])(
    'lets the assigned %s request information',
    async (role) => {
      const { wrapper } = await open(ticket(), user(role))
      expect(wrapper.find('form[aria-label="ขอข้อมูลเพิ่มเติม"]').exists()).toBe(true)
    },
  )
  it.each([
    { name: 'user', actor: user('user'), fields: {} },
    { name: 'manager', actor: user('it_manager'), fields: {} },
    { name: 'inactive', actor: { ...user(), is_active: false }, fields: {} },
    { name: 'another assignee', actor: user(), fields: { assignee: { id: 44, name: 'Other' } } },
    {
      name: 'waiting',
      actor: user(),
      fields: { status: 'waiting_info', pending_information_request: pending() },
    },
  ] satisfies { name: string; actor: AuthUser; fields: Partial<Ticket> }[])(
    'does not offer ask for $name',
    async ({ actor, fields }) => {
      const { wrapper } = await open(ticket(fields), actor)
      expect(wrapper.find('form[aria-label="ขอข้อมูลเพิ่มเติม"]').exists()).toBe(false)
    },
  )
  it('posts JSON and updates to waiting_info with the question', async () => {
    const posts: unknown[] = []
    http.defaults.adapter = async (config) => {
      posts.push({ url: config.url, data: config.data })
      return {
        ...reply(
          { data: ticket({ status: 'waiting_info', pending_information_request: pending() }) },
          config,
        ),
        status: 201,
      }
    }
    const { wrapper, current } = await open()
    await wrapper.get('textarea').setValue('  ขอภาพหน้าจอ  ')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(posts).toEqual([
      { url: '/api/v1/tickets/1/information-requests', data: '{"body":"ขอภาพหน้าจอ"}' },
    ])
    expect(current.value.status).toBe('waiting_info')
    expect(wrapper.text()).toContain('ขอภาพหน้าจอ')
    expect(wrapper.find('form[aria-label="ขอข้อมูลเพิ่มเติม"]').exists()).toBe(false)
  })
  it('does not send whitespace questions', async () => {
    let posts = 0
    http.defaults.adapter = async (config) => {
      posts++
      return reply({}, config)
    }
    const { wrapper } = await open()
    await wrapper.get('textarea').setValue(' \n ')
    await wrapper.get('form').trigger('submit')
    expect(posts).toBe(0)
    expect(wrapper.get('[role="alert"]').text()).toContain('กรุณา')
  })
})
describe('information responses', () => {
  const waiting = () => ticket({ status: 'waiting_info', pending_information_request: pending() })
  it('shows a peer the question without the response form', async () => {
    const { wrapper } = await open(waiting(), { ...user('user'), id: 10 })
    expect(wrapper.text()).toContain('ขอภาพหน้าจอ')
    expect(wrapper.find('form[aria-label="ตอบข้อมูลเพิ่มเติม"]').exists()).toBe(false)
  })
  it.each([
    { name: 'owner', actor: user('user'), fields: {}, visible: true },
    {
      name: 'other department',
      actor: { ...user('user'), department: { id: 2, code: 'IT', name: 'IT' } },
      fields: {},
      visible: false,
    },
    {
      name: 'missing department',
      actor: { ...user('user'), department: null },
      fields: {},
      visible: false,
    },
    { name: 'technician', actor: user(), fields: {}, visible: false },
    {
      name: 'missing request',
      actor: user('user'),
      fields: { pending_information_request: null },
      visible: false,
    },
  ] satisfies { name: string; actor: AuthUser; fields: Partial<Ticket>; visible: boolean }[])(
    'response eligibility for $name',
    async ({ actor, fields, visible }) => {
      const { wrapper } = await open({ ...waiting(), ...fields }, actor)
      expect(wrapper.find('form[aria-label="ตอบข้อมูลเพิ่มเติม"]').exists()).toBe(visible)
    },
  )
  it.each(['text', 'file', 'both'])(
    'sends %s as FormData and uses the current request ID',
    async (mode) => {
      let sent: FormData | undefined
      let url: string | undefined
      http.defaults.adapter = async (config) => {
        if (config.data instanceof FormData) sent = config.data
        url = config.url
        return reply(
          {
            data: ticket({
              assignee: { id: 44, name: 'Latest' },
              pending_information_request: null,
            }),
          },
          config,
        )
      }
      const { wrapper, current } = await open(waiting(), user('user'))
      if (mode !== 'file') await wrapper.get('textarea').setValue('  จอดำ  ')
      if (mode !== 'text')
        await choose(wrapper, [new File(['image'], 'screen.png', { type: 'image/png' })])
      await wrapper.get('form').trigger('submit')
      await flushPromises()
      expect(url).toBe('/api/v1/tickets/1/information-requests/51/respond')
      expect(sent?.get('body')).toBe(mode === 'file' ? null : 'จอดำ')
      expect(sent?.getAll('files[]')).toHaveLength(mode === 'text' ? 0 : 1)
      expect(current.value.status).toBe('in_progress')
      expect(current.value.assignee?.id).toBe(44)
      expect(wrapper.find('form[aria-label="ตอบข้อมูลเพิ่มเติม"]').exists()).toBe(false)
    },
  )
  it.each(['empty', 'four files', 'too large'])('rejects %s before network', async (kind) => {
    let posts = 0
    http.defaults.adapter = async (config) => {
      posts++
      return reply({}, config)
    }
    const { wrapper } = await open(waiting(), user('user'))
    if (kind === 'four files')
      await choose(
        wrapper,
        Array.from({ length: 4 }, (_, i) => new File(['x'], `${i}.png`, { type: 'image/png' })),
      )
    if (kind === 'too large')
      await choose(wrapper, [new File([new Uint8Array(2097153)], 'big.png', { type: 'image/png' })])
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(posts).toBe(0)
    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
  })
  it('retains text and selected files on field error then clears them after success', async () => {
    let tries = 0
    http.defaults.adapter = async (config) => {
      if (++tries === 1)
        throw new AxiosError('Invalid', 'ERR_BAD_REQUEST', config, undefined, {
          ...reply({ message: 'Invalid', errors: { 'files.0': ['ไฟล์ไม่ถูกต้อง'] } }, config),
          status: 422,
        })
      return reply({ data: ticket() }, config)
    }
    const { wrapper, current } = await open(waiting(), user('user'))
    await wrapper.get('textarea').setValue('คำตอบ')
    await choose(wrapper, [new File(['x'], 'screen.png', { type: 'image/png' })])
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.get('textarea').element.value).toBe('คำตอบ')
    expect(wrapper.text()).toContain('screen.png')
    expect(wrapper.get('[role="alert"]').text()).toContain('ไฟล์ไม่ถูกต้อง')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(tries).toBe(2)
    expect(wrapper.text()).not.toContain('screen.png')
    current.value = waiting()
    await flushPromises()
    expect(wrapper.get('textarea').element.value).toBe('')
    expect(wrapper.text()).not.toContain('screen.png')
  })
  it('can clear selected files without deleting the text', async () => {
    const { wrapper } = await open(waiting(), user('user'))
    await wrapper.get('textarea').setValue('ข้อความ')
    await choose(wrapper, [new File(['x'], 'screen.png', { type: 'image/png' })])
    const clear = wrapper.findAll('button').find((b) => b.text() === 'ล้างไฟล์แนบ')
    expect(clear).toBeDefined()
    await clear?.trigger('click')
    expect(wrapper.text()).not.toContain('screen.png')
    expect(wrapper.get('textarea').element.value).toBe('ข้อความ')
    expect(wrapper.get<HTMLInputElement>('input[type="file"]').element.value).toBe('')
  })
  it('blocks a repeated submit while busy', async () => {
    let release = () => {}
    const pendingResponse = new Promise<void>((resolve) => {
      release = resolve
    })
    let posts = 0
    http.defaults.adapter = async (config) => {
      posts++
      await pendingResponse
      return reply({ data: ticket() }, config)
    }
    const { wrapper, busy } = await open(waiting(), user('user'))
    await wrapper.get('textarea').setValue('ตอบแล้ว')
    await wrapper.get('form').trigger('submit')
    await wrapper.get('form').trigger('submit')
    expect(posts).toBe(1)
    expect(busy.value).toBe(true)
    release()
    await flushPromises()
    expect(busy.value).toBe(false)
  })
  it.each(['success', 'error'])('ignores late %s after the ticket changes', async (outcome) => {
    let release = () => {}
    const pendingResponse = new Promise<void>((resolve) => {
      release = resolve
    })
    http.defaults.adapter = async (config) => {
      await pendingResponse
      if (outcome === 'error') throw new Error('late')
      return reply({ data: ticket() }, config)
    }
    const { wrapper, current, busy, updates } = await open(waiting(), user('user'))
    await wrapper.get('textarea').setValue('เก่า')
    await wrapper.get('form').trigger('submit')
    current.value = ticket({
      id: 2,
      ticket_no: 'RPR-2',
      status: 'waiting_info',
      pending_information_request: pending(52),
    })
    await flushPromises()
    await wrapper.get('textarea').setValue('ใหม่')
    release()
    await flushPromises()
    expect(updates).toHaveLength(0)
    expect(wrapper.get('textarea').element.value).toBe('ใหม่')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(busy.value).toBe(false)
  })
})

describe('information Detail integration', () => {
  it('shares busy with work logs and updates ticket state and history after asking', async () => {
    let release = () => {}
    const response = new Promise<void>((resolve) => {
      release = resolve
    })
    let asked = false
    const posts: string[] = []
    http.defaults.adapter = async (config) => {
      if (config.method === 'post') {
        posts.push(config.url ?? '')
        await response
        asked = true
        return {
          ...reply(
            { data: ticket({ status: 'waiting_info', pending_information_request: pending() }) },
            config,
          ),
          status: 201,
        }
      }
      if (config.url?.endsWith('/events'))
        return reply(
          {
            data: asked
              ? [
                  {
                    id: 1,
                    type: 'info_requested',
                    label: 'ขอข้อมูลเพิ่มเติม',
                    actor_role: 'junior_technician',
                    actor: { id: 9, name: 'Tech' },
                    from_status: 'in_progress',
                    to_status: 'waiting_info',
                    from_level: 'junior',
                    to_level: 'junior',
                    from_assignee_id: 9,
                    to_assignee_id: 9,
                    information_request_id: 51,
                    note: pending().question,
                    metadata: {},
                    created_at: '2026-11-23T03:00:00Z',
                  },
                ]
              : [],
            meta: { current_page: 1, last_page: 1, per_page: 20, total: asked ? 1 : 0 },
          },
          config,
        )
      return reply({ data: ticket() }, config)
    }
    const pinia = createPinia()
    useAuthStore(pinia).user = user()
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', component: { template: '<div />' } },
        { path: '/tickets/:id', component: TicketDetailView },
      ],
    })
    await router.push('/tickets/1')
    await router.isReady()
    const wrapper = mount(TicketDetailView, { global: { plugins: [pinia, router] } })
    wrappers.push(wrapper)
    await flushPromises()
    await wrapper.get('form[aria-label="บันทึกการซ่อม"] textarea').setValue('draft log')
    await wrapper.get('form[aria-label="ขอข้อมูลเพิ่มเติม"] textarea').setValue('ขอภาพหน้าจอ')
    await wrapper.get('form[aria-label="ขอข้อมูลเพิ่มเติม"]').trigger('submit')
    expect(
      wrapper.get('form[aria-label="บันทึกการซ่อม"] fieldset').attributes('disabled'),
    ).toBeDefined()
    await wrapper.get('form[aria-label="บันทึกการซ่อม"]').trigger('submit')
    expect(posts).toEqual(['/api/v1/tickets/1/information-requests'])
    release()
    await flushPromises()
    expect(wrapper.text()).toContain('รอข้อมูลเพิ่มเติม')
    expect(wrapper.find('form[aria-label="บันทึกการซ่อม"]').exists()).toBe(false)
    expect(wrapper.get('ol').text()).toContain('ขอภาพหน้าจอ')
  })
})

describe('pending round context', () => {
  it.each(['success', 'error'])(
    'ignores late %s after a new question round starts on the same ticket',
    async (outcome) => {
      let release = () => {}
      const pendingResponse = new Promise<void>((resolve) => {
        release = resolve
      })
      http.defaults.adapter = async (config) => {
        await pendingResponse
        if (outcome === 'error') throw new Error('old round')
        return reply({ data: ticket() }, config)
      }
      const { wrapper, current, updates, busy } = await open(
        ticket({ status: 'waiting_info', pending_information_request: pending() }),
        user('user'),
      )
      await wrapper.get('textarea').setValue('old round')
      await wrapper.get('form').trigger('submit')
      current.value = ticket({ status: 'waiting_info', pending_information_request: pending(52) })
      await flushPromises()
      await wrapper.get('textarea').setValue('new round')
      release()
      await flushPromises()
      expect(updates).toHaveLength(0)
      expect(wrapper.get('textarea').element.value).toBe('new round')
      expect(busy.value).toBe(false)
    },
  )
})
