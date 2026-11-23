import { afterEach, describe, expect, it } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import type { InternalAxiosRequestConfig } from 'axios'
import { http } from '@/lib/http'
import TicketHistory from '@/components/TicketHistory.vue'

const originalAdapter = http.defaults.adapter
const wrappers: VueWrapper[] = []

afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
  http.defaults.adapter = originalAdapter
})

function event(id: number, body: string | null = 'ตรวจเครื่องแล้ว') {
  return {
    id,
    type: 'note_added',
    label: 'บันทึกการซ่อม',
    actor_role: 'junior_technician',
    actor: { id: 9, name: 'ช่างต้น' },
    from_status: 'in_progress',
    to_status: 'in_progress',
    from_level: 'junior',
    to_level: 'junior',
    from_assignee_id: 9,
    to_assignee_id: 9,
    information_request_id: null,
    note: {
      id: id + 100,
      ticket_id: 1,
      kind: 'work_log',
      audience: 'junior',
      body,
      author_role: 'junior_technician',
      author: { id: 9, name: 'ช่างต้น' },
      attachments: [],
      created_at: '2026-11-23T03:00:00.000Z',
    },
    metadata: {},
    created_at: '2026-11-23T03:00:00.000Z',
  }
}

function page(data: unknown[], currentPage = 1, lastPage = 1) {
  return {
    data,
    links: {
      first: '?page=1',
      last: `?page=${lastPage}`,
      prev: null,
      next: lastPage > currentPage ? `?page=${currentPage + 1}` : null,
    },
    meta: {
      current_page: currentPage,
      last_page: lastPage,
      per_page: 20,
      total: data.length,
      from: data.length ? 1 : null,
      to: data.length || null,
      path: '/api/v1/tickets/1/events',
      links: [],
    },
  }
}

function reply(data: unknown, config: InternalAxiosRequestConfig) {
  return { data, status: 200, statusText: 'OK', headers: {}, config }
}

async function openHistory(ticketId = 1) {
  const wrapper = mount(TicketHistory, { props: { ticketId } })
  wrappers.push(wrapper)
  await flushPromises()
  return wrapper
}

describe('TicketHistory', () => {
  it('renders the API event order, escaped note content, and protected attachment links', async () => {
    const first = event(1, '<img src=x onerror=alert(1)>\nภาพหน้าจอ')
    const attached = {
      ...first,
      note: {
        ...first.note,
        attachments: [
          {
            id: 5,
            original_name: 'screen.png',
            mime_type: 'image/png',
            size_bytes: 2048,
            download_url: '/api/v1/attachments/5/download',
          },
        ],
      },
    }
    http.defaults.adapter = async (config) => reply(page([attached, event(2, 'ตรวจต่อแล้ว')]), config)
    const wrapper = await openHistory()
    expect(wrapper.get('h2').text()).toBe('ประวัติการดำเนินการ')
    const entries = wrapper.findAll('ol > li')
    expect(entries).toHaveLength(2)
    expect(entries[0]?.text()).toContain('<img src=x onerror=alert(1)>')
    expect(entries[1]?.text()).toContain('ตรวจต่อแล้ว')
    expect(wrapper.find('img').exists()).toBe(false)
    const link = wrapper.get('a[href="/api/v1/attachments/5/download"]')
    expect(link.attributes('download')).toBe('screen.png')
    expect(link.text()).toContain('screen.png')
    expect(wrapper.text()).toContain('2 KB')
    expect(wrapper.get('time').attributes('datetime')).toBe('2026-11-23T03:00:00.000Z')
    expect(wrapper.text()).toContain('ช่างต้น')
  })

  it('shows returned status changes and supported metadata without rendering unknown metadata', async () => {
    const changed = {
      ...event(1),
      note: null,
      from_status: 'new',
      to_status: 'in_progress',
      metadata: {
        reason: 'เปลี่ยนผู้รับผิดชอบ',
        from_priority: 'normal',
        to_priority: 'critical',
        parts_count: 2,
        secret: 'DO-NOT-RENDER',
      },
    }
    http.defaults.adapter = async (config) => reply(page([changed]), config)
    const wrapper = await openHistory()
    expect(wrapper.text()).toContain('รอรับเรื่อง')
    expect(wrapper.text()).toContain('กำลังดำเนินการ')
    expect(wrapper.text()).toContain('เปลี่ยนผู้รับผิดชอบ')
    expect(wrapper.text()).toContain('วิกฤต')
    expect(wrapper.text()).toContain('2')
    expect(wrapper.text()).not.toContain('DO-NOT-RENDER')
  })

  it('reads subsequent pages from the API and can return to the first page', async () => {
    http.defaults.adapter = async (config) => {
      const currentPage = Number(
        new URL(http.getUri(config), 'http://localhost').searchParams.get('page') ?? 1,
      )
      return reply(
        page(
          [event(currentPage, currentPage === 1 ? 'ประวัติหน้าแรก' : 'ประวัติหน้าสอง')],
          currentPage,
          2,
        ),
        config,
      )
    }
    const wrapper = await openHistory()
    expect(wrapper.get('button[aria-label="ประวัติหน้าก่อนหน้า"]').attributes('disabled')).toBeDefined()
    await wrapper.get('button[aria-label="ประวัติหน้าถัดไป"]').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('ประวัติหน้าสอง')
    expect(wrapper.text()).not.toContain('ประวัติหน้าแรก')
    expect(wrapper.get('button[aria-label="ประวัติหน้าถัดไป"]').attributes('disabled')).toBeDefined()
    await wrapper.get('button[aria-label="ประวัติหน้าก่อนหน้า"]').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('ประวัติหน้าแรก')
  })

  it('shows an empty state when the API returns no visible events', async () => {
    http.defaults.adapter = async (config) => reply(page([]), config)
    const wrapper = await openHistory()
    expect(wrapper.text()).toContain('ยังไม่มีประวัติที่คุณดูได้')
    expect(wrapper.find('ol').exists()).toBe(false)
  })

  it('offers a retry after a failed request and displays the successful response', async () => {
    let requests = 0
    http.defaults.adapter = async (config) => {
      if (++requests === 1) throw new Error('offline')
      return reply(page([event(1, 'โหลดสำเร็จแล้ว')]), config)
    }
    const wrapper = await openHistory()
    expect(wrapper.get('[role="alert"]').text()).toContain('เกิดข้อผิดพลาด')
    await wrapper.get('button[aria-label="ลองโหลดประวัติอีกครั้ง"]').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('โหลดสำเร็จแล้ว')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('loads the new ticket from page one and ignores the late response from the old ticket', async () => {
    let releaseFirst = () => {}
    const firstResponse = new Promise<void>((resolve) => {
      releaseFirst = resolve
    })
    http.defaults.adapter = async (config) => {
      if (config.url?.includes('/tickets/1/')) {
        await firstResponse
        return reply(page([event(1, 'ข้อมูลใบเก่า')]), config)
      }
      const query = new URL(http.getUri(config), 'http://localhost').searchParams
      expect(query.get('page')).toBe('1')
      return reply(page([event(2, 'ข้อมูลใบใหม่')]), config)
    }
    const wrapper = await openHistory()
    expect(wrapper.get('[role="status"]').text()).toContain('กำลังโหลดประวัติ')
    await wrapper.setProps({ ticketId: 2 })
    await flushPromises()
    expect(wrapper.text()).toContain('ข้อมูลใบใหม่')
    releaseFirst()
    await flushPromises()
    expect(wrapper.text()).not.toContain('ข้อมูลใบเก่า')
    expect(wrapper.text()).toContain('ข้อมูลใบใหม่')
  })
})
