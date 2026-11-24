import type { UserRole } from '@/services/auth'
import type { Ticket, TicketEvent, TicketStatus } from '@/services/tickets'

export const statusLabels: Record<TicketStatus, string> = {
  new: 'รอรับเรื่อง',
  in_progress: 'กำลังดำเนินการ',
  waiting_info: 'รอข้อมูลเพิ่มเติม',
  escalated: 'ส่งต่อช่างอาวุโส',
  returned: 'ส่งกลับช่างระดับต้น',
  waiting_equipment_approval: 'รออนุมัติอุปกรณ์',
  resolved: 'แก้ไขเสร็จสิ้น',
  cancelled: 'ยกเลิก',
}

export const priorityLabels: Record<Ticket['priority'], string> = {
  normal: 'ปกติ',
  high: 'สำคัญ',
  critical: 'วิกฤต',
}

export const levelLabels: Record<Ticket['support_level'], string> = {
  junior: 'ช่างระดับต้น',
  senior: 'ช่างอาวุโส',
}

export const roleLabels: Record<UserRole, string> = {
  user: 'พนักงาน',
  junior_technician: 'ช่างระดับต้น',
  senior_technician: 'ช่างอาวุโส',
  it_manager: 'ผู้จัดการฝ่ายไอที',
}

export const routeStops = ['แจ้งเรื่อง', 'ช่างระดับต้น', 'ช่างอาวุโส', 'อนุมัติอุปกรณ์', 'ปิดงาน'] as const

/** done: passed · active: being worked · queued: waiting for a technician to claim ·
 *  waiting: waiting on requester or manager · closed · void: cancelled here · idle: not reached */
export type StopState = 'done' | 'active' | 'queued' | 'waiting' | 'closed' | 'void' | 'idle'

/** Stops the visible event history proves the ticket passed through. */
export function passedStops(
  events: Pick<TicketEvent, 'from_level' | 'to_level' | 'to_status' | 'equipment_request_id'>[],
) {
  const passed = new Set<number>()
  for (const event of events) {
    if (event.from_level === 'senior' || event.to_level === 'senior') passed.add(2)
    if (event.to_status === 'waiting_equipment_approval' || event.equipment_request_id)
      passed.add(3)
  }
  return passed
}

export function routeOf(
  ticket: Pick<Ticket, 'status' | 'support_level'>,
  passed: ReadonlySet<number> = new Set(),
) {
  const level = ticket.support_level === 'senior' ? 2 : 1
  const here =
    ticket.status === 'resolved' ? 4 : ticket.status === 'waiting_equipment_approval' ? 3 : level

  const current: StopState =
    ticket.status === 'resolved'
      ? 'closed'
      : ticket.status === 'cancelled'
        ? 'void'
        : ticket.status === 'in_progress'
          ? 'active'
          : ticket.status === 'waiting_info' || ticket.status === 'waiting_equipment_approval'
            ? 'waiting'
            : 'queued'

  // Only stops up to the ticket's support level are known to be passed. Callers that have the
  // event history pass the extra stops it proves (senior visit, equipment approval).
  return routeStops.map((label, index) => ({
    label,
    state: (index === here
      ? current
      : index <= level || passed.has(index)
        ? 'done'
        : 'idle') as StopState,
  }))
}
