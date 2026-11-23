import { afterEach, describe, expect, it } from 'vitest'
import { defineComponent, h, ref, type Component } from 'vue'
import { createPinia } from 'pinia'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { AxiosError, type InternalAxiosRequestConfig } from 'axios'
import { createRouter, createMemoryHistory } from 'vue-router'
import TicketResolution from '@/components/TicketResolution.vue'
import TicketManagement from '@/components/TicketManagement.vue'
import ManagerReportsView from '@/views/ManagerReportsView.vue'
import TicketDetailView from '@/views/TicketDetailView.vue'
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
function user(role: AuthUser['role'] = 'senior_technician'): AuthUser {
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
function page(data: unknown[] = [], current_page = 1, last_page = 1) {
  return { data, meta: { current_page, last_page, per_page: 20, total: data.length } }
}
function reply(data: unknown, c: InternalAxiosRequestConfig) {
  return { data, status: 200, statusText: 'OK', headers: {}, config: c }
}
function invalid(c: InternalAxiosRequestConfig, errors: Record<string, string[]>) {
  return new AxiosError('invalid', 'ERR_BAD_REQUEST', c, undefined, {
    ...reply({ errors }, c),
    status: 422,
  })
}
async function open(component: Component, initial = ticket(), actor = user()) {
  const current = ref(initial),
    busy = ref(false),
    updates: Ticket[] = []
  const pinia = createPinia()
  useAuthStore(pinia).user = actor
  const Host = defineComponent({
    setup: () => () =>
      h(component, {
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
  return { wrapper, current, busy, updates, pinia }
}
const approved = {
  id: 20,
  ticket_id: 1,
  status: 'approved',
  items: [{ id: 30, name: 'RAM', quantity: 2 }],
}
describe('resolution', () => {
  it('resolves without parts even when equipment lookup fails', async () => {
    const posts: unknown[] = []
    http.defaults.adapter = async (c) => {
      if (c.method === 'post') {
        posts.push({ url: c.url, data: JSON.parse(c.data) })
        return reply({ data: ticket({ status: 'resolved' }) }, c)
      }
      throw new Error('offline')
    }
    const { wrapper, updates } = await open(TicketResolution)
    await wrapper.get('#resolution-summary').setValue('  Repaired  ')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(posts).toEqual([
      { url: '/api/v1/tickets/1/resolve', data: { summary: 'Repaired', parts: [] } },
    ])
    expect(updates[0]?.status).toBe('resolved')
  })
  it('selects approved item and sends exact part reference', async () => {
    let body: unknown
    http.defaults.adapter = async (c) => {
      if (c.method === 'post') {
        body = JSON.parse(c.data)
        return reply({ data: ticket({ status: 'resolved' }) }, c)
      }
      return reply(page([approved]), c)
    }
    const { wrapper } = await open(TicketResolution)
    await wrapper.get('[data-test="add-part"]').trigger('click')
    await wrapper.get('#part-source-0').setValue('30')
    await wrapper.get('#part-quantity-0').setValue('2')
    await wrapper.get('#resolution-summary').setValue('Done')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(body).toEqual({
      summary: 'Done',
      parts: [{ name: 'RAM', quantity: 2, equipment_request_item_id: 30 }],
    })
  })
  it('blocks combined quantities above the approved quantity', async () => {
    let posts = 0
    http.defaults.adapter = async (c) => {
      if (c.method === 'post') posts++
      return reply(page([approved]), c)
    }
    const { wrapper } = await open(TicketResolution)
    await wrapper.get('#resolution-summary').setValue('Done')
    for (let i = 0; i < 2; i++) {
      await wrapper.get('[data-test="add-part"]').trigger('click')
      await wrapper.get(`#part-source-${i}`).setValue('30')
      await wrapper.get(`#part-quantity-${i}`).setValue('2')
    }
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(posts).toBe(0)
    expect(wrapper.text()).toContain('เกิน')
  })
  it('retains resolution draft and nested errors on422', async () => {
    http.defaults.adapter = async (c) => {
      if (c.method === 'post') throw invalid(c, { 'parts.0.quantity': ['จำนวนเกินอนุมัติ'] })
      return reply(page(), c)
    }
    const { wrapper } = await open(TicketResolution)
    await wrapper.get('#resolution-summary').setValue('Keep')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.get<HTMLTextAreaElement>('#resolution-summary').element.value).toBe('Keep')
    expect(wrapper.text()).toContain('จำนวนเกินอนุมัติ')
  })
  it.each(['user', 'it_manager'] satisfies AuthUser['role'][])(
    'never fetches for %s',
    async (role) => {
      let calls = 0
      http.defaults.adapter = async (c) => {
        calls++
        return reply(page(), c)
      }
      const { wrapper } = await open(TicketResolution, ticket(), user(role))
      expect(calls).toBe(0)
      expect(wrapper.find('form').exists()).toBe(false)
    },
  )
  it.each([
    'new',
    'waiting_info',
    'waiting_equipment_approval',
    'resolved',
    'cancelled',
  ] satisfies Ticket['status'][])('hides resolve while %s', async (status) => {
    http.defaults.adapter = async (c) => reply(page(), c)
    const { wrapper } = await open(TicketResolution, ticket({ status }))
    expect(wrapper.find('form').exists()).toBe(false)
  })
})
describe('management', () => {
  it('sends PATCH assignment and keeps waiting status', async () => {
    const calls: unknown[] = []
    http.defaults.adapter = async (c) => {
      if (c.method === 'patch') {
        calls.push({ url: c.url, data: JSON.parse(c.data) })
        return reply(
          {
            data: ticket({
              status: 'waiting_info',
              assignee: { id: 10, name: 'Junior' },
              support_level: 'junior',
            }),
          },
          c,
        )
      }
      return reply(page([{ ...user('junior_technician'), id: 10 }]), c)
    }
    const { wrapper, updates } = await open(
      TicketManagement,
      ticket({ status: 'waiting_info' }),
      user('it_manager'),
    )
    await wrapper.get('#assignment-technician').setValue('10')
    await wrapper.get('#assignment-reason').setValue('  Continue  ')
    await wrapper.get('form[aria-label="มอบหมายงาน"]').trigger('submit')
    await flushPromises()
    expect(calls).toEqual([
      { url: '/api/v1/tickets/1/assignment', data: { assignee_id: 10, reason: 'Continue' } },
    ])
    expect(updates[0]?.status).toBe('waiting_info')
  })
  it('updates priority using exact PATCH JSON', async () => {
    let body: unknown
    http.defaults.adapter = async (c) => {
      if (c.method === 'patch') {
        body = { url: c.url, data: JSON.parse(c.data) }
        return reply({ data: ticket({ priority: 'critical' }) }, c)
      }
      return reply(page(), c)
    }
    const { wrapper } = await open(TicketManagement, ticket(), user('it_manager'))
    await wrapper.get('#ticket-priority').setValue('critical')
    await wrapper.get('form[aria-label="ความเร่งด่วน"]').trigger('submit')
    await flushPromises()
    expect(body).toEqual({ url: '/api/v1/tickets/1/priority', data: { priority: 'critical' } })
  })
  it.each(['user', 'junior_technician', 'senior_technician'] satisfies AuthUser['role'][])(
    'does not list technicians for %s',
    async (role) => {
      let calls = 0
      http.defaults.adapter = async (c) => {
        calls++
        return reply(page(), c)
      }
      const { wrapper } = await open(TicketManagement, ticket(), user(role))
      expect(calls).toBe(0)
      expect(wrapper.find('form').exists()).toBe(false)
    },
  )
  it.each(['resolved', 'cancelled'] satisfies Ticket['status'][])(
    'hides manager mutations on %s',
    async (status) => {
      http.defaults.adapter = async (c) => reply(page(), c)
      const { wrapper } = await open(TicketManagement, ticket({ status }), user('it_manager'))
      expect(wrapper.find('form').exists()).toBe(false)
    },
  )
})
const report = {
  period: { from: '2026-11-01', to: '2026-11-23', timezone: 'Asia/Bangkok' },
  generated_at: '2026-11-23T03:00:00Z',
  tickets_created_in_period: {
    total: 5,
    by_current_status: [{ status: 'new', count: 3 }],
    by_current_priority: [{ priority: 'high', count: 2 }],
  },
  current_open_workload: { total: 8, assigned: 6, unassigned: 2 },
  technicians: [
    {
      user_id: 9,
      name: 'Tech',
      current_role: 'senior_technician',
      is_active: true,
      period_activity: { claim_count: 4, claimed_ticket_count: 3, resolved_ticket_count: 2 },
      current_open_assigned: 6,
    },
  ],
}
async function openReport(actor = user('it_manager')) {
  const pinia = createPinia()
  useAuthStore(pinia).user = actor
  const wrapper = mount(ManagerReportsView, {
    global: { plugins: [pinia], stubs: { RouterLink: true } },
  })
  wrappers.push(wrapper)
  await flushPromises()
  return wrapper
}
describe('reports', () => {
  it('uses inclusive date query and distinguishes current from period metrics', async () => {
    const calls: unknown[] = []
    http.defaults.adapter = async (c) => {
      calls.push(c.params)
      return reply({ data: report }, c)
    }
    const wrapper = await openReport()
    await wrapper.get('#report-from').setValue('2026-11-01')
    await wrapper.get('#report-to').setValue('2026-11-23')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(calls).toContainEqual({ from: '2026-11-01', to: '2026-11-23' })
    expect(wrapper.text()).toContain('งานเปิดอยู่ปัจจุบัน')
    expect(wrapper.text()).toContain('ครั้งที่รับงาน')
    expect(wrapper.text()).toContain('ใบที่รับงานไม่ซ้ำ')
  })
  it.each(['user', 'junior_technician', 'senior_technician'] satisfies AuthUser['role'][])(
    'never fetches report for %s',
    async (role) => {
      let calls = 0
      http.defaults.adapter = async (c) => {
        calls++
        return reply({ data: report }, c)
      }
      await openReport(user(role))
      expect(calls).toBe(0)
    },
  )
  it('blocks reversed date ranges', async () => {
    let calls = 0
    http.defaults.adapter = async (c) => {
      calls++
      return reply({ data: report }, c)
    }
    const wrapper = await openReport()
    const before = calls
    await wrapper.get('#report-from').setValue('2026-11-24')
    await wrapper.get('#report-to').setValue('2026-11-23')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(calls).toBe(before)
    expect(wrapper.text()).toContain('วันที่')
  })
})
describe('PDF permission', () => {
  it.each(['it_manager', 'user'] satisfies AuthUser['role'][])(
    'exposes native PDF download only for manager (%s)',
    async (role) => {
      http.defaults.adapter = async (c) =>
        c.url?.endsWith('/1') ? reply({ data: ticket() }, c) : reply(page(), c)
      const pinia = createPinia()
      useAuthStore(pinia).user = user(role)
      const router = createRouter({
        history: createMemoryHistory(),
        routes: [{ path: '/tickets/:id', component: TicketDetailView }],
      })
      await router.push('/tickets/1')
      const wrapper = mount(TicketDetailView, { global: { plugins: [pinia, router] } })
      wrappers.push(wrapper)
      await flushPromises()
      expect(wrapper.find('a[href="/api/v1/reports/tickets/1/pdf"]').exists()).toBe(
        role === 'it_manager',
      )
    },
  )
})

describe('remaining feature edge cases', () => {
  it('loads approved item pages and allows a manual part with null reference', async () => {
    const queries: unknown[] = []
    let posted: unknown
    http.defaults.adapter = async (c) => {
      if (c.method === 'post') {
        posted = JSON.parse(c.data)
        return reply({ data: ticket({ status: 'resolved' }) }, c)
      }
      queries.push(c.params)
      return reply(
        page(
          c.params?.page === 1
            ? [approved]
            : [{ ...approved, id: 21, items: [{ id: 31, name: 'SSD', quantity: 1 }] }],
          c.params?.page,
          2,
        ),
        c,
      )
    }
    const { wrapper } = await open(TicketResolution)
    await wrapper.get('[data-test="approved-more"]').trigger('click')
    await flushPromises()
    expect(queries).toEqual([
      { page: 1, status: 'approved' },
      { page: 2, status: 'approved' },
    ])
    await wrapper.get('[data-test="add-part"]').trigger('click')
    expect(wrapper.get('#part-source-0').text()).toContain('SSD')
    await wrapper.get('#part-name-0').setValue('  Cable  ')
    await wrapper.get('#resolution-summary').setValue('Done')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(posted).toEqual({
      summary: 'Done',
      parts: [{ name: 'Cable', quantity: 1, equipment_request_item_id: null }],
    })
  })
  it('ignores a late resolution response after ticket changes and blocks duplicates', async () => {
    let release = () => {},
      posts = 0
    const wait = new Promise<void>((r) => (release = r))
    http.defaults.adapter = async (c) => {
      if (c.method === 'post') {
        posts++
        await wait
        return reply({ data: ticket({ status: 'resolved' }) }, c)
      }
      return reply(page(), c)
    }
    const { wrapper, current, updates, busy } = await open(TicketResolution)
    await wrapper.get('#resolution-summary').setValue('Done')
    await wrapper.get('form').trigger('submit')
    await wrapper.get('form').trigger('submit')
    expect(posts).toBe(1)
    expect(busy.value).toBe(true)
    current.value = ticket({ id: 8 })
    await flushPromises()
    await wrapper.get('#resolution-summary').setValue('New draft')
    release()
    await flushPromises()
    expect(updates).toEqual([])
    expect(wrapper.get<HTMLTextAreaElement>('#resolution-summary').element.value).toBe('New draft')
    expect(busy.value).toBe(false)
  })
  it.each([
    { assignee: null },
    {
      pending_information_request: {
        id: 3,
        requested_at: '2026-11-23',
        question: {
          id: 1,
          ticket_id: 1,
          kind: 'information_request',
          audience: 'requester',
          body: 'Photo?',
          author_role: 'senior_technician',
          created_at: null,
        },
      },
    },
  ] satisfies Partial<Ticket>[])(
    'does not allow unresolved request context to close',
    async (fields) => {
      http.defaults.adapter = async (c) => reply(page(), c)
      const { wrapper } = await open(TicketResolution, ticket(fields))
      expect(wrapper.find('form').exists()).toBe(false)
    },
  )
  it('retains manager assignment draft on422 and supports technician pagination', async () => {
    let failList = true
    const queries: unknown[] = []
    http.defaults.adapter = async (c) => {
      if (c.method === 'patch') throw invalid(c, { assignee_id: ['ช่างถูกปิดใช้งานแล้ว'] })
      queries.push(c.params)
      if (failList) {
        failList = false
        throw new Error('offline')
      }
      return reply(
        page(
          [{ ...user('junior_technician'), id: c.params?.page === 2 ? 10 : 9 }],
          c.params?.page,
          2,
        ),
        c,
      )
    }
    const { wrapper } = await open(TicketManagement, ticket(), user('it_manager'))
    await wrapper
      .findAll('button')
      .find((b) => b.text() === 'โหลดรายชื่ออีกครั้ง')
      ?.trigger('click')
    await flushPromises()
    await wrapper.get('[data-test="technicians-next"]').trigger('click')
    await flushPromises()
    expect(queries).toEqual([{ page: 1 }, { page: 1 }, { page: 2 }])
    await wrapper.get('#assignment-technician').setValue('10')
    await wrapper.get('#assignment-reason').setValue('Keep reason')
    await wrapper.get('form[aria-label="มอบหมายงาน"]').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('ช่างถูกปิดใช้งานแล้ว')
    expect(wrapper.get<HTMLTextAreaElement>('#assignment-reason').element.value).toBe('Keep reason')
  })
  it('blocks duplicate manager commands while busy and ignores late updates', async () => {
    let release = () => {},
      patches = 0
    const wait = new Promise<void>((r) => (release = r))
    http.defaults.adapter = async (c) => {
      if (c.method === 'patch') {
        patches++
        await wait
        return reply({ data: ticket({ priority: 'critical' }) }, c)
      }
      return reply(page(), c)
    }
    const { wrapper, current, updates, busy } = await open(
      TicketManagement,
      ticket(),
      user('it_manager'),
    )
    await wrapper.get('#ticket-priority').setValue('critical')
    await wrapper.get('form[aria-label="ความเร่งด่วน"]').trigger('submit')
    await wrapper.get('form[aria-label="ความเร่งด่วน"]').trigger('submit')
    expect(patches).toBe(1)
    current.value = ticket({ id: 8 })
    await flushPromises()
    release()
    await flushPromises()
    expect(updates).toEqual([])
    expect(busy.value).toBe(false)
  })
  it('keeps successful report visible and clearly marks it after a failed request', async () => {
    let fail = false
    http.defaults.adapter = async (c) => {
      if (fail) throw new Error('offline')
      return reply({ data: report }, c)
    }
    const wrapper = await openReport()
    expect(wrapper.text()).toContain('Tech')
    fail = true
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('ผลสำเร็จครั้งก่อน')
    expect(wrapper.text()).toContain('Tech')
  })
  it('ignores an older report that completes after the newest range', async () => {
    let release = () => {},
      calls = 0
    const wait = new Promise<void>((r) => (release = r))
    http.defaults.adapter = async (c) => {
      calls++
      if (calls === 1) {
        await wait
        return reply(
          {
            data: {
              ...report,
              tickets_created_in_period: { ...report.tickets_created_in_period, total: 99 },
            },
          },
          c,
        )
      }
      return reply({ data: report }, c)
    }
    const wrapper = await openReport()
    await wrapper.get('#report-from').setValue('2026-11-01')
    await wrapper.get('#report-to').setValue('2026-11-23')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    release()
    await flushPromises()
    expect(wrapper.text()).toContain('ทั้งหมด 5 ใบ')
    expect(wrapper.text()).not.toContain('ทั้งหมด 99 ใบ')
  })
  it('shows zero report values and no technicians without inventing averages', async () => {
    http.defaults.adapter = async (c) =>
      reply(
        {
          data: {
            ...report,
            tickets_created_in_period: { total: 0, by_current_status: [], by_current_priority: [] },
            current_open_workload: { total: 0, assigned: 0, unassigned: 0 },
            technicians: [],
          },
        },
        c,
      )
    const wrapper = await openReport()
    expect(wrapper.text()).toContain('ทั้งหมด 0 ใบ')
    expect(wrapper.text()).toContain('ไม่มีข้อมูลช่าง')
    expect(wrapper.text()).not.toContain('SLA')
  })
  it('invalid range cancels an earlier response from replacing validation feedback', async () => {
    let release = () => {}
    const wait = new Promise<void>((r) => (release = r))
    http.defaults.adapter = async (c) => {
      await wait
      return reply({ data: report }, c)
    }
    const wrapper = await openReport()
    await wrapper.get('#report-from').setValue('2026-11-24')
    await wrapper.get('#report-to').setValue('2026-11-23')
    await wrapper.get('form').trigger('submit')
    release()
    await flushPromises()
    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
    expect(wrapper.find('section[aria-label="ผลรายงาน"]').exists()).toBe(false)
  })
})
