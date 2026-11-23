import { http } from '@/lib/http'
import type { AuthUser } from '@/services/auth'
import type { Ticket, TicketPagination, TicketStatus } from '@/services/tickets'

export interface ResolutionInput {
  summary: string
  parts: { name: string; quantity: number; equipment_request_item_id: number | null }[]
}
export async function resolveTicket(id: number, input: ResolutionInput): Promise<Ticket> {
  const response = await http.post<{ data: Ticket }>(`/api/v1/tickets/${id}/resolve`, input)
  return response.data.data
}
export interface TechnicianPage {
  data: AuthUser[]
  meta: TicketPagination
}
export async function listTechnicians(page = 1): Promise<TechnicianPage> {
  const response = await http.get<TechnicianPage>('/api/v1/technicians', { params: { page } })
  if (!Array.isArray(response.data.data)) throw new Error('รูปแบบรายชื่อช่างไม่ถูกต้อง')
  return response.data
}
export async function assignTicket(
  id: number,
  assignee_id: number,
  reason: string | null,
): Promise<Ticket> {
  const response = await http.patch<{ data: Ticket }>(`/api/v1/tickets/${id}/assignment`, {
    assignee_id,
    reason,
  })
  return response.data.data
}
export async function updateTicketPriority(
  id: number,
  priority: Ticket['priority'],
): Promise<Ticket> {
  const response = await http.patch<{ data: Ticket }>(`/api/v1/tickets/${id}/priority`, {
    priority,
  })
  return response.data.data
}
export interface ManagerReport {
  period: { from: string; to: string; timezone: string }
  generated_at: string
  tickets_created_in_period: {
    total: number
    by_current_status: { status: TicketStatus; count: number }[]
    by_current_priority: { priority: Ticket['priority']; count: number }[]
  }
  current_open_workload: { total: number; assigned: number; unassigned: number }
  technicians: {
    user_id: number
    name: string
    current_role: AuthUser['role']
    is_active: boolean
    period_activity: {
      claim_count: number
      claimed_ticket_count: number
      resolved_ticket_count: number
    }
    current_open_assigned: number
  }[]
}
export async function getManagerReport(from: string, to: string): Promise<ManagerReport> {
  const response = await http.get<{ data: ManagerReport }>('/api/v1/reports/summary', {
    params: { from, to },
  })
  return response.data.data
}
