import axios, { isAxiosError } from 'axios'

export const http = axios.create({
  baseURL: '/',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
  },
})

export function errorMessage(error: unknown) {
  if (isAxiosError<{ message?: string }>(error) && error.response?.data?.message) {
    return error.response.data.message
  }

  return 'เกิดข้อผิดพลาด กรุณาลองใหม่'
}
