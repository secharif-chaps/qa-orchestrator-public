/**
 * Pinia Colada queries for user account management
 */

import { defineQueryOptions } from '@pinia/colada'
import { getSessions, getActivityEvents } from '@/api/account'
import type { ActivityEventsParams } from '@/types/account'

export const ACCOUNT_QUERY_KEYS = {
  root: ['account'] as const,
  sessions: () => [...ACCOUNT_QUERY_KEYS.root, 'sessions'] as const,
  events: (params: ActivityEventsParams) => [
    ...ACCOUNT_QUERY_KEYS.root,
    'events',
    params.page ?? 1,
    params.size ?? 20,
    params.eventType ?? 'all',
  ] as const,
}

/**
 * Query for fetching user sessions
 */
export const sessionsQuery = defineQueryOptions(() => ({
  key: ACCOUNT_QUERY_KEYS.sessions(),
  query: () => getSessions(),
}))

/**
 * Query for fetching activity events with pagination
 */
export const activityEventsQuery = defineQueryOptions(
  ({ params }: { params: ActivityEventsParams }) => ({
    key: ACCOUNT_QUERY_KEYS.events(params),
    query: () => getActivityEvents(params),
  }),
)
