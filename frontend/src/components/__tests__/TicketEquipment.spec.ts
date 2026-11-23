import { afterEach, describe, expect, it } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { createPinia } from 'pinia'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { AxiosError, type InternalAxiosRequestConfig } from 'axios'
import TicketEquipment from '@/components/TicketEquipment.vue'
import TicketDetailView from '@/views/TicketDetailView.vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { http } from '@/lib/http'
import type { AuthUser } from '@/services/auth'
import type { Ticket } from '@/services/tickets'
const originalAdapter = http.defaults.adapter
const wrappers: VueWrapper[] = []
afterEach(() => {
  wrappers.splice(0).forEach((w) => w.unmount())
  http.defaults.adapter = originalAdapter
})
function actor(role: AuthUser['role'] = 'senior_technician'): AuthUser {
  return {
    id: 9,
    name: 'Tech',
    email: 'tech@example.test',
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
    description: 'Broken',
    location: null,
    asset_label: null,
    status: 'in_progress',
    support_level: 'senior',
    priority: 'normal',
    assignee: { id: 9, name: 'Tech' },
    created_at: '2026-11-23T03:00:00Z',
    ...fields,
  }
}
const request = {
  id: 2,
  ticket_id: 1,
  status: 'pending',
  items: [{ id: 3, name: 'RAM', quantity: 2, estimated_unit_price: '1200.25' }],
  estimated_total: '2400.50',
  reason: 'Replace RAM',
}
function page(data: unknown[] = [], current_page = 1, last_page = 1) {
  return { data, meta: { current_page, last_page, per_page: 20, total: data.length } }
}
function reply(data: unknown, config: InternalAxiosRequestConfig) {
  return { data, status: 200, statusText: 'OK', headers: {}, config }
}
async function open(initial = ticket(), user = actor()) {
  const current = ref(initial),
    busy = ref(false),
    updates: Ticket[] = []
  const pinia = createPinia()
  useAuthStore(pinia).user = user
  const Host = defineComponent({
    setup: () => () =>
      h(TicketEquipment, {
        ticket: current.value,
        busy: busy.value,
        'onUpdate:busy': (v: boolean) => (busy.value = v),
        onUpdated: (v: Ticket) => {
          updates.push(v)
          current.value = v
        },
      }),
  })
  const wrapper = mount(Host, { global: { plugins: [pinia] } })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, current, busy, updates }
}
async function fill(wrapper: VueWrapper) {
  await wrapper.get('#equipment-reason').setValue('  Replace RAM  ')
  await wrapper.get('#equipment-name-0').setValue(' RAM ')
  await wrapper.get('#equipment-quantity-0').setValue('2')
  await wrapper.get('#equipment-price-0').setValue('1200.25')
}
describe('equipment permissions and list', () => {
  it.each(['user', 'junior_technician'] satisfies AuthUser['role'][])(
    'never fetches for unauthorized %s',
    async (role) => {
      let calls = 0
      http.defaults.adapter = async (c) => {
        calls++
        return reply(page(), c)
      }
      const { wrapper } = await open(ticket({ assignee: null }), actor(role))
      expect(calls).toBe(0)
      expect(wrapper.find('section').exists()).toBe(false)
    },
  )
  it('shows redacted junior items without inventing a zero price', async () => {
    http.defaults.adapter = async (c) =>
      reply(
        page([
          { id: 2, ticket_id: 1, status: 'approved', items: [{ id: 3, name: 'RAM', quantity: 2 }] },
        ]),
        c,
      )
    const { wrapper } = await open(ticket(), actor('junior_technician'))
    expect(wrapper.text()).toContain('RAM')
    expect(wrapper.text()).toContain('ไม่เปิดเผยราคา')
    expect(wrapper.find('form').exists()).toBe(false)
  })
  it('paginates and retries a failed list', async () => {
    let fail = true
    const pages: unknown[] = []
    http.defaults.adapter = async (c) => {
      pages.push(c.params?.page)
      if (fail) {
        fail = false
        throw new Error('offline')
      }
      return reply(page([], c.params?.page, 2), c)
    }
    const { wrapper } = await open()
    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
    await wrapper.get('[data-test="equipment-reload"]').trigger('click')
    await flushPromises()
    await wrapper.get('[data-test="equipment-next"]').trigger('click')
    await flushPromises()
    expect(pages).toEqual([1, 1, 2])
    expect(wrapper.text()).toContain('หน้า 2 / 2')
  })
})
describe('equipment mutations', () => {
  it('posts exact decimal-string JSON then refreshes Ticket and list', async () => {
    const posts: unknown[] = []
    http.defaults.adapter = async (c) => {
      if (c.method === 'post') {
        posts.push(JSON.parse(c.data))
        return reply({ data: request }, c)
      }
      if (c.url === '/api/v1/tickets/1')
        return reply({ data: ticket({ status: 'waiting_equipment_approval' }) }, c)
      return reply(page(posts.length ? [request] : []), c)
    }
    const { wrapper, updates, busy } = await open()
    await fill(wrapper)
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(posts).toEqual([
      {
        reason: 'Replace RAM',
        items: [{ name: 'RAM', quantity: 2, estimated_unit_price: '1200.25' }],
      },
    ])
    expect(updates[0]?.status).toBe('waiting_equipment_approval')
    expect(busy.value).toBe(false)
    expect(wrapper.find('#equipment-reason').exists()).toBe(false)
    expect(wrapper.text()).toContain('2400.50')
  })
  it.each(['approved', 'rejected'] as const)(
    'manager sends %s and resumes current Ticket',
    async (status) => {
      const posts: unknown[] = []
      http.defaults.adapter = async (c) => {
        if (c.method === 'post') {
          posts.push({ url: c.url, body: JSON.parse(c.data) })
          return reply({ data: { ...request, status } }, c)
        }
        if (c.url === '/api/v1/tickets/1') return reply({ data: ticket() }, c)
        return reply(page([{ ...request, status: posts.length ? status : 'pending' }]), c)
      }
      const { wrapper, updates } = await open(
        ticket({ status: 'waiting_equipment_approval' }),
        actor('it_manager'),
      )
      if (status === 'rejected')
        await wrapper.get('#equipment-decision-2').setValue('  Budget unavailable  ')
      await wrapper.get(`[data-test="equipment-${status}-2"]`).trigger('click')
      await flushPromises()
      expect(posts).toEqual([
        {
          url: '/api/v1/tickets/1/equipment-requests/2/decision',
          body: { status, reason: status === 'approved' ? null : 'Budget unavailable' },
        },
      ])
      expect(updates[0]?.status).toBe('in_progress')
    },
  )
  it('requires a nonblank rejection reason', async () => {
    let posts = 0
    http.defaults.adapter = async (c) => {
      if (c.method === 'post') posts++
      return reply(page([request]), c)
    }
    const { wrapper } = await open(
      ticket({ status: 'waiting_equipment_approval' }),
      actor('it_manager'),
    )
    await wrapper.get('[data-test="equipment-rejected-2"]').trigger('click')
    await flushPromises()
    expect(posts).toBe(0)
    expect(wrapper.get('[role="alert"]').text()).toContain('เหตุผล')
  })
  it('retains draft and displays nested 422 validation errors', async () => {
    http.defaults.adapter = async (c) => {
      if (c.method === 'post')
        throw new AxiosError('invalid', 'ERR_BAD_REQUEST', c, undefined, {
          ...reply({ errors: { 'items.0.name': ['ชื่ออุปกรณ์ไม่ถูกต้อง'] } }, c),
          status: 422,
        })
      return reply(page(), c)
    }
    const { wrapper } = await open()
    await fill(wrapper)
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.get<HTMLInputElement>('#equipment-name-0').element.value).toBe(' RAM ')
    expect(wrapper.get('[role="alert"]').text()).toContain('ชื่ออุปกรณ์ไม่ถูกต้อง')
  })
  it('does not retry POST when refresh failed and keeps other forms busy', async () => {
    let posts = 0,
      fail = true
    http.defaults.adapter = async (c) => {
      if (c.method === 'post') {
        posts++
        return reply({ data: request }, c)
      }
      if (c.url === '/api/v1/tickets/1') {
        if (fail) throw new Error('offline')
        return reply({ data: ticket({ status: 'waiting_equipment_approval' }) }, c)
      }
      return reply(page(posts ? [request] : []), c)
    }
    const { wrapper, busy, updates } = await open()
    await fill(wrapper)
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(busy.value).toBe(true)
    expect(wrapper.text()).toContain('บันทึกสำเร็จแล้ว')
    await wrapper.get('form').trigger('submit')
    expect(posts).toBe(1)
    fail = false
    await wrapper.get('[data-test="equipment-refresh"]').trigger('click')
    await flushPromises()
    expect(posts).toBe(1)
    expect(updates).toHaveLength(1)
    expect(busy.value).toBe(false)
  })
  it('blocks duplicate POST and ignores late completion after changing ticket', async () => {
    let release = () => {}
    const wait = new Promise<void>((r) => (release = r))
    let posts = 0
    http.defaults.adapter = async (c) => {
      if (c.method === 'post') {
        posts++
        await wait
        return reply({ data: request }, c)
      }
      return reply(page(), c)
    }
    const { wrapper, current, busy, updates } = await open()
    await fill(wrapper)
    await wrapper.get('form').trigger('submit')
    await wrapper.get('form').trigger('submit')
    expect(posts).toBe(1)
    expect(busy.value).toBe(true)
    current.value = ticket({ id: 8 })
    await flushPromises()
    release()
    await flushPromises()
    expect(updates).toHaveLength(0)
    expect(busy.value).toBe(false)
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })
})

describe('equipment guardrails', () => {
  it.each(['-1', '1.234', '10000000000', '1e2', ''])(
    'rejects invalid price %s without POST',
    async (price) => {
      let posts = 0
      http.defaults.adapter = async (c) => {
        if (c.method === 'post') posts++
        return reply(page(), c)
      }
      const { wrapper } = await open()
      await fill(wrapper)
      await wrapper.get('#equipment-price-0').setValue(price)
      await wrapper.get('form').trigger('submit')
      await flushPromises()
      expect(posts).toBe(0)
      expect(wrapper.find('[role="alert"]').exists()).toBe(true)
    },
  )
  it.each([
    { fields: { status: 'waiting_info' }, user: actor() },
    { fields: { support_level: 'junior' }, user: actor() },
    { fields: { assignee: { id: 99, name: 'Other' } }, user: actor() },
    { fields: {}, user: { ...actor(), is_active: false } },
  ] satisfies { fields: Partial<Ticket>; user: AuthUser }[])(
    'hides creation outside assigned active senior work',
    async ({ fields, user }) => {
      http.defaults.adapter = async (c) => reply(page(), c)
      const { wrapper } = await open(ticket(fields), user)
      expect(wrapper.find('#equipment-reason').exists()).toBe(false)
    },
  )
  it('hides creation while a pending equipment request exists', async () => {
    http.defaults.adapter = async (c) => reply(page([request]), c)
    const { wrapper } = await open()
    expect(wrapper.find('#equipment-reason').exists()).toBe(false)
  })
  it('adds and removes equipment rows with accessible names', async () => {
    http.defaults.adapter = async (c) => reply(page(), c)
    const { wrapper } = await open()
    await wrapper
      .findAll('button')
      .find((b) => b.text().includes('เพิ่มรายการ'))
      ?.trigger('click')
    expect(wrapper.find('#equipment-name-1').exists()).toBe(true)
    await wrapper
      .findAll('button')
      .find((b) => b.text() === 'ลบรายการที่ 2')
      ?.trigger('click')
    expect(wrapper.find('#equipment-name-1').exists()).toBe(false)
  })
  it('updates parent Ticket, history, and disables other forms until refresh finishes', async () => {
    let posted = false,
      events = 0,
      release = () => {}
    const wait = new Promise<void>((r) => (release = r))
    http.defaults.adapter = async (c) => {
      if (c.url?.endsWith('/events')) {
        events++
        return reply(page(), c)
      }
      if (c.method === 'post') {
        posted = true
        return reply({ data: request }, c)
      }
      if (c.url?.endsWith('/equipment-requests')) return reply(page(posted ? [request] : []), c)
      if (posted) await wait
      return reply(
        { data: ticket({ status: posted ? 'waiting_equipment_approval' : 'in_progress' }) },
        c,
      )
    }
    const pinia = createPinia()
    useAuthStore(pinia).user = actor()
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/tickets/:id', component: TicketDetailView }],
    })
    await router.push('/tickets/1')
    await router.isReady()
    const wrapper = mount(TicketDetailView, { global: { plugins: [pinia, router] } })
    wrappers.push(wrapper)
    await flushPromises()
    await fill(wrapper)
    await wrapper.get('form[aria-label="ขออุปกรณ์"]').trigger('submit')
    await flushPromises()
    expect(
      wrapper.get('form[aria-label="บันทึกการซ่อม"] fieldset').attributes('disabled'),
    ).toBeDefined()
    expect(events).toBe(1)
    release()
    await flushPromises()
    expect(wrapper.text()).toContain('waiting_equipment_approval')
    expect(wrapper.find('form[aria-label="บันทึกการซ่อม"]').exists()).toBe(false)
    expect(events).toBe(2)
  })
})

describe('equipment stale responses and pagination', () => {
  it.each(['success', 'error'])(
    'ignores stale list %s after route context changes',
    async (outcome) => {
      let release = () => {}
      const wait = new Promise<void>((r) => (release = r))
      http.defaults.adapter = async (c) => {
        if (c.url?.includes('/tickets/1/')) {
          await wait
          if (outcome === 'error') throw new Error('old request')
          return reply(page([request]), c)
        }
        return reply(page(), c)
      }
      const { wrapper, current } = await open()
      current.value = ticket({ id: 8 })
      await flushPromises()
      release()
      await flushPromises()
      expect(wrapper.text()).not.toContain('RAM')
      expect(wrapper.find('[role="alert"]').exists()).toBe(false)
      expect(wrapper.find('#equipment-reason').exists()).toBe(true)
    },
  )
  it('keeps request creation hidden on history pages and restores it only on newest page', async () => {
    http.defaults.adapter = async (c) => reply(page([], c.params?.page, 2), c)
    const { wrapper } = await open()
    expect(wrapper.find('#equipment-reason').exists()).toBe(true)
    await wrapper.get('[data-test="equipment-next"]').trigger('click')
    await flushPromises()
    expect(wrapper.find('#equipment-reason').exists()).toBe(false)
    await wrapper.get('nav button').trigger('click')
    await flushPromises()
    expect(wrapper.find('#equipment-reason').exists()).toBe(true)
  })
  it('does not allow removing the only equipment row', async () => {
    http.defaults.adapter = async (c) => reply(page(), c)
    const { wrapper } = await open()
    const remove = wrapper.findAll('button').find((b) => b.text() === 'ลบรายการที่ 1')
    expect(remove?.attributes('disabled')).toBeDefined()
    await remove?.trigger('click')
    expect(wrapper.find('#equipment-name-0').exists()).toBe(true)
  })
})
