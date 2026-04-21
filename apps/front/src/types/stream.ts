/**
 * Stream types for multi-channel event distribution.
 *
 * Streams deliver folder events (company created, task completed, etc.)
 * to external channels like Teams, Slack, or generic webhooks.
 */

// Channel types — matches backend ChannelType enum
export type ChannelType = 'teams' | 'slack_webhook' | 'webhook'

// Stream execution modes
export type StreamMode = 'live' | 'recurrence'

// Stream lifecycle statuses
export type StreamStatus = 'draft' | 'active' | 'paused' | 'archived'

// Delivery statuses for individual event deliveries
export type DeliveryStatus = 'pending' | 'delivered' | 'failed' | 'skipped'

// --- Channel config shapes ---

/**
 * Microsoft Teams Power Automate workflow configuration.
 */
export interface TeamsConfig {
  workflow_url: string
}

/**
 * Slack incoming webhook configuration.
 */
export interface SlackWebhookConfig {
  webhook_url: string
}

/**
 * Generic HTTP webhook configuration.
 */
export interface WebhookConfig {
  url: string
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  headers?: Record<string, string>
  secret?: string | null
}

/**
 * Maps each channel type to its configuration shape.
 *
 * Allows type-safe constraints like `ChannelConfigMap['teams']` → `TeamsConfig`.
 * Used by `StreamCreate` (via `StreamChannel`) and `StreamUpdate<T>` to ensure
 * `channel_config` matches the actual `channel_type`.
 */
export interface ChannelConfigMap {
  teams: TeamsConfig
  slack_webhook: SlackWebhookConfig
  webhook: WebhookConfig
}

// Union of all channel configs (kept as a convenience alias)
export type ChannelConfig = ChannelConfigMap[ChannelType]

/**
 * Discriminated pair of {channel_type, channel_config}: picking a channel_type
 * automatically narrows channel_config to its matching shape.
 */
export type StreamChannel = {
  [K in ChannelType]: { channel_type: K; channel_config: ChannelConfigMap[K] }
}[ChannelType]

/**
 * Discriminated schedule: cron_expression is required in recurrence mode and
 * forbidden in live mode.
 */
export type StreamSchedule =
  | { mode: 'live'; cron_expression?: null }
  | { mode: 'recurrence'; cron_expression: string }

// --- API request/response shapes ---

/**
 * Full stream response from the API.
 */
export interface StreamRead {
  id: number
  name: string
  description: string | null
  folder_id: string
  channel_type: ChannelType
  channel_config: ChannelConfig
  mode: StreamMode
  cron_expression: string | null
  status: StreamStatus
  organization_id: string
  owner_id: string
  owner_username: string | null
  subscribed_events: string[]
  created_at: string
  updated_at: string
}

/**
 * Request body for creating a stream.
 *
 * Discriminated on two axes:
 *   - `channel_type` constrains `channel_config` to its matching shape
 *   - `mode` decides whether `cron_expression` is required
 */
export type StreamCreate = {
  name: string
  description?: string | null
  subscribed_events?: string[]
} & StreamChannel &
  StreamSchedule

/**
 * Request body for updating a stream (all fields optional).
 * Note: channel_type and mode are NOT updatable.
 *
 * Generic parameter `T` lets callers constrain `channel_config` to the stream's
 * actual channel type: `StreamUpdate<'teams'>` forbids sending a SlackConfig.
 * Defaults to the full union for callers that don't know the type statically.
 */
export interface StreamUpdate<T extends ChannelType = ChannelType> {
  name?: string | null
  description?: string | null
  channel_config?: ChannelConfigMap[T] | null
  cron_expression?: string | null
  subscribed_events?: string[] | null
}

/**
 * Request body for updating stream status.
 */
export interface StreamStatusUpdate {
  status: StreamStatus
}

/**
 * Individual delivery record for a stream event.
 */
export interface DeliveryRead {
  id: number
  stream_id: number
  event_id: number
  status: DeliveryStatus
  error_message: string | null
  response_metadata: Record<string, unknown> | null
  delivered_at: string | null
  created_at: string
  attempt_count: number
  next_retry_at: string | null
}

// --- Event types catalog ---

/**
 * Single event type entry.
 */
export interface EventTypeEntry {
  type: string
  label: string
}

/**
 * Group of event types from a source module.
 */
export interface EventSourceGroup {
  source: string
  label: string
  available: boolean
  events: EventTypeEntry[]
}

// --- Test connection ---

/**
 * Request to test a channel connection.
 */
export interface TestConnectionRequest {
  channel_type: ChannelType
  channel_config: ChannelConfig
}

/**
 * Response from testing a channel connection.
 */
export interface TestConnectionResponse {
  success: boolean
  error?: string | null
}

// --- Dispatch ---

/**
 * Response from manual dispatch.
 */
export interface DispatchResponse {
  dispatched_count: number
  failed_count: number
}

// --- Builders ---

/**
 * Narrow a flat form payload into a type-safe {@link StreamCreate}.
 *
 * The form state carries a union `channel_type: ChannelType` which TypeScript
 * can't assign to a discriminated `StreamChannel`. This helper performs the
 * runtime narrowing once, at the form/API boundary.
 */
export const buildStreamCreate = (input: {
  name: string
  description: string | null
  channel_type: ChannelType
  channel_config: ChannelConfig
  mode: StreamMode
  cron_expression?: string | null
  subscribed_events: string[]
}): StreamCreate => {
  const base = {
    name: input.name,
    description: input.description,
    subscribed_events: input.subscribed_events,
  }

  const channel: StreamChannel =
    input.channel_type === 'teams'
      ? { channel_type: 'teams', channel_config: input.channel_config as TeamsConfig }
      : input.channel_type === 'slack_webhook'
        ? {
            channel_type: 'slack_webhook',
            channel_config: input.channel_config as SlackWebhookConfig,
          }
        : { channel_type: 'webhook', channel_config: input.channel_config as WebhookConfig }

  const schedule: StreamSchedule =
    input.mode === 'recurrence'
      ? { mode: 'recurrence', cron_expression: input.cron_expression ?? '' }
      : { mode: 'live' }

  return { ...base, ...channel, ...schedule }
}
