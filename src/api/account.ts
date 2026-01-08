/**
 * API functions for user account management (sessions, activity events)
 *
 * This module handles self-service account operations, distinct from
 * user.ts which handles admin operations on other users.
 */

import { apiClient } from './client'
import type {
  SessionsResponse,
  ActivityEventsResponse,
  ActivityEventsParams,
} from '@/types/account'

/**
 * Get all active sessions for the current user
 */
export async function getSessions(): Promise<SessionsResponse> {
  const response = await apiClient.get<{
    sessions: Array<{
      id: string
      ip_address: string
      started_at: string
      last_access: string
      clients: Record<string, string>
      is_current: boolean
    }>
    current_session_id: string | null
  }>('/users/me/sessions')

  // Transform snake_case to camelCase
  return {
    sessions: response.sessions.map((session) => ({
      id: session.id,
      ipAddress: session.ip_address,
      startedAt: session.started_at,
      lastAccess: session.last_access,
      clients: session.clients,
      isCurrent: session.is_current,
    })),
    currentSessionId: response.current_session_id,
  }
}

/**
 * Revoke a specific session
 * @param sessionId - The session ID to revoke
 */
export async function revokeSession(sessionId: string): Promise<void> {
  await apiClient.delete(`/users/me/sessions/${sessionId}`)
}

/**
 * Revoke all sessions (sign out all devices)
 * @param keepCurrent - If true, keeps the current session active (default: true)
 */
export async function revokeAllSessions(keepCurrent: boolean = true): Promise<void> {
  const params = new URLSearchParams()
  params.append('keep_current', keepCurrent.toString())
  await apiClient.delete(`/users/me/sessions?${params.toString()}`)
}

/**
 * Get activity events (security log) for the current user
 * @param params - Pagination and filter parameters
 */
export async function getActivityEvents(
  params: ActivityEventsParams = {},
): Promise<ActivityEventsResponse> {
  const queryParams = new URLSearchParams()

  if (params.page) {
    queryParams.append('page', params.page.toString())
  }
  if (params.size) {
    queryParams.append('size', params.size.toString())
  }
  if (params.eventType && params.eventType !== 'all') {
    queryParams.append('event_type', params.eventType)
  }

  const queryString = queryParams.toString()
  const url = queryString ? `/users/me/events?${queryString}` : '/users/me/events'

  const response = await apiClient.get<{
    events: Array<{
      id: string
      type: string
      display_type: 'login' | 'security' | 'update'
      icon: string
      title: string
      description: string
      ip_address: string | null
      timestamp: string
    }>
    page: number
    size: number
    has_more: boolean
  }>(url)

  // Transform snake_case to camelCase
  return {
    events: response.events.map((event) => ({
      id: event.id,
      type: event.type,
      displayType: event.display_type,
      icon: event.icon,
      title: event.title,
      description: event.description,
      ipAddress: event.ip_address,
      timestamp: event.timestamp,
    })),
    page: response.page,
    size: response.size,
    hasMore: response.has_more,
  }
}
