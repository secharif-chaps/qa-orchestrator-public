/**
 * Types for user account management (sessions, activity events)
 */

export interface Session {
  id: string
  ipAddress: string
  startedAt: string
  lastAccess: string
  clients: Record<string, string>
  isCurrent: boolean
}

export interface SessionsResponse {
  sessions: Session[]
  currentSessionId: string | null
}

export type ActivityEventType = 'login' | 'security' | 'update'

export interface ActivityEvent {
  id: string
  type: string
  displayType: ActivityEventType
  icon: string
  title: string
  description: string
  ipAddress: string | null
  timestamp: string
}

export interface ActivityEventsResponse {
  events: ActivityEvent[]
  page: number
  size: number
  hasMore: boolean
}

export interface ActivityEventsParams {
  page?: number
  size?: number
  eventType?: 'login' | 'security' | 'profile' | 'all'
}
