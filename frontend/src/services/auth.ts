import { http } from '@/lib/http'

export type UserRole =
  | 'user'
  | 'junior_technician'
  | 'senior_technician'
  | 'it_manager'

export interface AuthUser {
  id: number
  name: string
  email: string
  role: UserRole
  is_active: boolean
  department: {
    id: number
    code: string
    name: string
  } | null
}

interface UserResponse {
  data: AuthUser
}

export async function login(email: string, password: string) {
  await http.get('/sanctum/csrf-cookie')

  const response = await http.post<UserResponse>('/api/v1/login', {
    email,
    password,
  })

  return response.data.data
}

export async function getCurrentUser() {
  const response = await http.get<UserResponse>('/api/v1/me')

  return response.data.data
}

export async function logout() {
  await http.post('/api/v1/logout')
}