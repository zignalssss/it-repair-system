import { http } from '@/lib/http'
import type { UserRole } from '@/services/auth'

export type TicketStatus =
  | 'new'
  | 'in_progress'
  | 'waiting_info'
  | 'escalated'
  | 'returned'
  | 'waiting_equipment_approval'
  | 'resolved'
  | 'cancelled'

export interface Ticket {
  id: number
  ticket_no: string
  title: string
  description: string
  location: string | null
  asset_label: string | null
  status: TicketStatus
  support_level: 'junior' | 'senior'
  priority: 'normal' | 'high' | 'critical'
  requester?: { id: number; name: string } | null
  department?: { id: number; code: string; name: string } | null
  assignee?: { id: number; name: string } | null
  created_at: string
  pending_information_request?: PendingInformationRequest | null
}

export interface NewTicket {
  title: string
  description: string
  location: string
  asset_label: string
  files: File[]
}

export interface TicketPagination {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface TicketPage {
  data: Ticket[]
  meta: TicketPagination
}

export async function listTickets(page = 1): Promise<TicketPage> {
  const response = await http.get<TicketPage>('/api/v1/tickets', {
    params: { page },
  })

  return response.data
}

export async function getTicket(id: number) {
  const response = await http.get<{ data: Ticket }>(`/api/v1/tickets/${id}`)

  return response.data.data
}

export async function createTicket(input: NewTicket) {
  const form = new FormData()
  form.append('title', input.title)
  form.append('description', input.description)
  if (input.location) form.append('location', input.location)
  if (input.asset_label) form.append('asset_label', input.asset_label)
  for (const file of input.files) form.append('files[]', file)

  const response = await http.post<{ data: Ticket }>('/api/v1/tickets', form)

  return response.data.data
}

export async function cancelTicket(id: number, reason: string) {
  const response = await http.post<{ data: Ticket }>(`/api/v1/tickets/${id}/cancel`, {
    reason,
  })

  return response.data.data
}

export async function claimTicket(id: number) {
  const response = await http.post<{ data: Ticket }>(`/api/v1/tickets/${id}/claim`)

  return response.data.data
}

export interface TicketAttachment {
  id: number
  original_name: string
  mime_type: string
  size_bytes: number
  download_url: string
}

export interface TicketNote {
  id: number
  ticket_id: number
  kind: string
  audience: string
  body: string | null
  author_role: UserRole
  author?: { id: number; name: string } | null
  attachments?: TicketAttachment[]
  created_at: string | null
}

export interface TicketEvent {
  id: number
  type: string
  label: string
  actor_role: UserRole
  actor?: { id: number; name: string } | null
  from_status: TicketStatus | null
  to_status: TicketStatus | null
  from_level: Ticket['support_level'] | null
  to_level: Ticket['support_level'] | null
  from_assignee_id: number | null
  to_assignee_id: number | null
  information_request_id: number | null
  equipment_request_id?: number | null
  note?: TicketNote | null
  metadata: {
    ticket_no?: string
    reason?: string | null
    from_priority?: Ticket['priority']
    to_priority?: Ticket['priority']
    parts_count?: number
  }
  created_at: string
}

export interface TicketEventPage {
  data: TicketEvent[]
  meta: TicketPagination
}

export async function listTicketEvents(ticketId: number, page = 1): Promise<TicketEventPage> {
  const response = await http.get<TicketEventPage>(`/api/v1/tickets/${ticketId}/events`, {
    params: { page },
  })

  return response.data
}

export async function addTicketWorkLog(ticketId: number, body: string): Promise<TicketNote> {
  const response = await http.post<{ data: TicketNote }>(`/api/v1/tickets/${ticketId}/work-logs`, {
    body,
  })

  return response.data.data
}

export interface PendingInformationRequest {
  id: number
  requested_at: string
  question: TicketNote
}

export async function requestTicketInformation(ticketId: number, body: string): Promise<Ticket> {
  const response = await http.post<{ data: Ticket }>(
    `/api/v1/tickets/${ticketId}/information-requests`,
    { body },
  )
  return response.data.data
}

export async function respondTicketInformation(
  ticketId: number,
  requestId: number,
  input: { body: string; files: File[] },
): Promise<Ticket> {
  const form = new FormData()
  const body = input.body.trim()

  if (body) 
    form.append('body', body)
  for (const file of input.files) 
    form.append('files[]', file)

  const response = await http.post<{ data: Ticket }>(
    `/api/v1/tickets/${ticketId}/information-requests/${requestId}/respond`,
    form,
  )
  return response.data.data
}

export async function escalateTicket(ticketId: number, body: string): Promise<Ticket> {
  const response = await http.post<{ data: Ticket }>(`/api/v1/tickets/${ticketId}/escalate`, {
    body,
  })
  return response.data.data
}

export async function returnTicketToJunior(ticketId: number, body: string): Promise<Ticket> {
  const response = await http.post<{ data: Ticket }>(
    `/api/v1/tickets/${ticketId}/return-to-junior`,
    { body },
  )
  return response.data.data
}

export type EquipmentStatus = 'pending' | 'approved' | 'rejected'
export interface EquipmentRequest {
  id: number
  ticket_id: number
  status: EquipmentStatus
  ticket_status?: TicketStatus
  items: { id: number; name: string; quantity: number; estimated_unit_price?: string }[]
  reason?: string
  estimated_total?: string | null
  requested_by_id?: number
  requested_at?: string
  decision_reason?: string | null
  decided_at?: string | null
  decided_by_id?: number | null
}
export interface EquipmentPage {
  data: EquipmentRequest[]
  meta: TicketPagination
}
export interface NewEquipmentRequest {
  reason: string
  items: { name: string; quantity: number; estimated_unit_price: string }[]
}
export async function listEquipmentRequests(ticketId: number, page = 1): Promise<EquipmentPage> {
  const response = await http.get<EquipmentPage>(`/api/v1/tickets/${ticketId}/equipment-requests`, {
    params: { page },
  })
  if (!Array.isArray(response.data.data)) throw new Error('รูปแบบรายการอุปกรณ์ไม่ถูกต้อง')
  return response.data
}
export async function requestTicketEquipment(ticketId: number, input: NewEquipmentRequest) {
  const response = await http.post<{ data: EquipmentRequest }>(
    `/api/v1/tickets/${ticketId}/equipment-requests`, input,
  )
  return response.data.data
}
export async function decideTicketEquipment(
  ticketId: number, requestId: number, status: Exclude<EquipmentStatus, 'pending'>, reason: string | null,
) {
  const response = await http.post<{ data: EquipmentRequest }>(
    `/api/v1/tickets/${ticketId}/equipment-requests/${requestId}/decision`, { status, reason },
  )
  return response.data.data
}
