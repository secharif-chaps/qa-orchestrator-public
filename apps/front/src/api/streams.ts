import { apiClient } from './client'
import type { PaginatedResponse } from '@/types/pagination'
import type {
  StreamRead,
  StreamCreate,
  StreamUpdate,
  StreamStatusUpdate,
  DeliveryRead,
  DispatchResponse,
  TestConnectionRequest,
  TestConnectionResponse,
  EventSourceGroup,
} from '@/types/stream'

export const getEventTypes = async () => {
  const response = await apiClient.get<EventSourceGroup[]>('/event-types')
  return response
}

export const getStreamsByFolder = async (
  folderId: string,
  filters: { page: number; per_page: number },
) => {
  const params = new URLSearchParams({
    page: filters.page.toString(),
    per_page: filters.per_page.toString(),
  })
  const response = await apiClient.get<PaginatedResponse<StreamRead>>(
    `/folders/${folderId}/streams?${params.toString()}`,
  )
  return response
}

export const createStream = async (folderId: string, data: StreamCreate) => {
  return apiClient.post<StreamRead>(`/folders/${folderId}/streams`, data, { silent: true })
}

export const getStreamById = async (streamId: string) => {
  const response = await apiClient.get<StreamRead>(`/streams/${streamId}`)
  return response
}

export const updateStream = async (streamId: string, data: StreamUpdate) => {
  return apiClient.patch<StreamRead>(`/streams/${streamId}`, data, { silent: true })
}

export const deleteStream = async (streamId: string) => {
  await apiClient.delete(`/streams/${streamId}`, { silent: true })
}

export const updateStreamStatus = async (streamId: string, data: StreamStatusUpdate) => {
  return apiClient.patch<StreamRead>(`/streams/${streamId}/status`, data, { silent: true })
}

export const getDeliveries = async (
  streamId: string,
  filters: { page: number; per_page: number },
) => {
  const params = new URLSearchParams({
    page: filters.page.toString(),
    per_page: filters.per_page.toString(),
  })
  const response = await apiClient.get<PaginatedResponse<DeliveryRead>>(
    `/streams/${streamId}/deliveries?${params.toString()}`,
  )
  return response
}

export const dispatchStream = async (streamId: string) => {
  return apiClient.post<DispatchResponse>(`/streams/${streamId}/dispatch`, {}, { silent: true })
}

export const testConnection = async (data: TestConnectionRequest) => {
  const response = await apiClient.post<TestConnectionResponse>('/test-connection', data)
  return response
}
