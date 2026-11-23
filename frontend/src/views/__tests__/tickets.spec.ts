import { afterEach, describe, expect, it } from 'vitest'
import type { InternalAxiosRequestConfig } from 'axios'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { http } from '@/lib/http'
import { useAuthStore } from '@/stores/auth'
import type { AuthUser } from '@/services/auth'
import type { Ticket } from '@/services/tickets'
import HomeView from '@/views/HomeView.vue'
import TicketDetailView from '@/views/TicketDetailView.vue'

const originalAdapter = http.defaults.adapter
const wrappers: VueWrapper[] = []

afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
  http.defaults.adapter = originalAdapter
})

function makeTicket(id: number, fields: Partial<Ticket> = {}): Ticket {
  return {
    id,
    ticket_no: `RPR-${id}`,
    title: `Repair ${id}`,
    description: 'Screen is blank',
    location: null,
    asset_label: null,
    status: 'new',
    support_level: 'junior',
    priority: 'normal',
    requester: { id: 1, name: 'Requester' },
    department: { id: 1, code: 'FIN', name: 'Finance' },
    assignee: null,
    created_at: '2026-11-23T00:00:00Z',
    ...fields,
  }
}

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

async function openDetail(actor = user()) {
  const pinia = createPinia()
  useAuthStore(pinia).user = actor
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/tickets/:id', name: 'ticket-detail', component: TicketDetailView },
      { path: '/', component: HomeView },
      { path: '/tickets/new', name: 'ticket-create', component: { template: '<div />' } },
    ],
  })
  await router.push('/tickets/1')
  await router.isReady()
  const wrapper = mount(TicketDetailView, { global: { plugins: [pinia, router] } })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, router }
}

function reply(data: unknown, config: InternalAxiosRequestConfig) {
  return { data, status: 200, statusText: 'OK', headers: {}, config }
}

describe('ticket detail', () => {
  it('lets a technician claim a queued ticket assigned to them', async () => {
    http.defaults.adapter = async (config) =>
      reply(
        {
          data: makeTicket(1, {
            assignee: { id: 9, name: 'Technician' },
            status: config.method === 'post' ? 'in_progress' : 'new',
          }),
        },
        config,
      )
    const { wrapper } = await openDetail()
    const claim = wrapper.findAll('button').find((button) => button.text() === 'รับงาน')
    expect(claim, 'Assigned technician needs a claim button').toBeDefined()
    await claim?.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('in_progress')
    expect(wrapper.findAll('button').some((button) => button.text() === 'รับงาน')).toBe(false)
  })

  it('does not offer a senior a junior queue ticket even when they can view it', async () => {
    http.defaults.adapter = async (config) =>
      reply({ data: makeTicket(1, { status: 'returned' }) }, config)
    const { wrapper } = await openDetail(user('senior_technician'))
    expect(wrapper.findAll('button').some((button) => button.text() === 'รับงาน')).toBe(false)
  })

  it('does not offer a ticket assigned to another technician', async () => {
    http.defaults.adapter = async (config) =>
      reply({ data: makeTicket(1, { assignee: { id: 10, name: 'Other' } }) }, config)
    const { wrapper } = await openDetail()
    expect(wrapper.findAll('button').some((button) => button.text() === 'รับงาน')).toBe(false)
  })

  it('loads the new ticket when the route parameter changes on the same component', async () => {
    const requested: string[] = []
    http.defaults.adapter = async (config) => {
      const path = new URL(http.getUri(config), 'http://localhost').pathname
      requested.push(path)
      return reply({ data: makeTicket(path.endsWith('/2') ? 2 : 1) }, config)
    }
    const { wrapper, router } = await openDetail()
    await router.push('/tickets/2')
    await flushPromises()
    expect(wrapper.get('h1').text()).toContain('RPR-2')
    const claim = wrapper.findAll('button').find((button) => button.text() === 'รับงาน')
    await claim?.trigger('click')
    await flushPromises()
    expect(requested).toContain('/api/v1/tickets/2/claim')
    expect(requested).not.toContain('/api/v1/tickets/1/claim')
  })

  it('does not replace the new route with a late claim response from the old ticket', async () => {
    let releaseClaim = () => {}
    const claimResponse = new Promise<void>((resolve) => {
      releaseClaim = resolve
    })
    http.defaults.adapter = async (config) => {
      if (config.method === 'post') {
        await claimResponse
        return reply({ data: makeTicket(1, { status: 'in_progress' }) }, config)
      }
      return reply({ data: makeTicket(config.url?.endsWith('/2') ? 2 : 1) }, config)
    }
    const { wrapper, router } = await openDetail()
    const claim = wrapper.findAll('button').find((button) => button.text() === 'รับงาน')
    expect(claim).toBeDefined()
    await claim?.trigger('click')
    await router.push('/tickets/2')
    await flushPromises()
    expect(wrapper.get('h1').text()).toContain('RPR-2')
    releaseClaim()
    await flushPromises()
    expect(wrapper.get('h1').text()).toContain('RPR-2')
  })

  it('ignores a response from an earlier route that arrives late', async () => {
    let releaseFirst = () => {}
    const firstResponse = new Promise<void>((resolve) => {
      releaseFirst = resolve
    })
    http.defaults.adapter = async (config) => {
      const id = config.url?.endsWith('/2') ? 2 : 1
      if (id === 1) await firstResponse
      return reply({ data: makeTicket(id) }, config)
    }
    const { wrapper, router } = await openDetail()
    await router.push('/tickets/2')
    await flushPromises()
    const beforeLateResponse = wrapper.text()
    releaseFirst()
    await flushPromises()
    expect(beforeLateResponse).toContain('RPR-2')
    expect(wrapper.get('h1').text()).toContain('RPR-2')
  })
})

describe('ticket list pagination', () => {
  it('navigates to the second page and back using API pagination metadata', async () => {
    http.defaults.adapter = async (config) => {
      const page = Number(
        new URL(http.getUri(config), 'http://localhost').searchParams.get('page') ?? 1,
      )
      return reply(
        {
          data:
            page === 1 ? Array.from({ length: 15 }, (_, i) => makeTicket(i + 1)) : [makeTicket(16)],
          links: {
            first: '/api/v1/tickets?page=1',
            last: '/api/v1/tickets?page=2',
            prev: page === 1 ? null : '/api/v1/tickets?page=1',
            next: page === 1 ? '/api/v1/tickets?page=2' : null,
          },
          meta: {
            current_page: page,
            last_page: 2,
            per_page: 15,
            total: 16,
            from: page === 1 ? 1 : 16,
            to: page === 1 ? 15 : 16,
            path: '/api/v1/tickets',
            links: [],
          },
        },
        config,
      )
    }
    const pinia = createPinia()
    useAuthStore(pinia).user = user('user')
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', component: HomeView },
        { path: '/tickets/new', name: 'ticket-create', component: { template: '<div />' } },
        { path: '/tickets/:id', name: 'ticket-detail', component: TicketDetailView },
      ],
    })
    await router.push('/')
    await router.isReady()
    const wrapper = mount(HomeView, { global: { plugins: [pinia, router] } })
    wrappers.push(wrapper)
    await flushPromises()
    expect(wrapper.text()).toContain('RPR-1')
    expect(wrapper.text()).not.toContain('RPR-16')
    const next = wrapper.find('button[aria-label="หน้าถัดไป"]')
    expect(next.exists(), 'The list needs pagination controls').toBe(true)
    await next.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('RPR-16')
    expect(wrapper.text()).not.toContain('RPR-15')
    expect(wrapper.get('button[aria-label="หน้าถัดไป"]').attributes('disabled')).toBeDefined()
    await wrapper.get('button[aria-label="หน้าก่อนหน้า"]').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('RPR-15')
    expect(wrapper.get('button[aria-label="หน้าก่อนหน้า"]').attributes('disabled')).toBeDefined()
  })
})
