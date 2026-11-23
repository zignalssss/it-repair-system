import { http } from '@/lib/http'

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
