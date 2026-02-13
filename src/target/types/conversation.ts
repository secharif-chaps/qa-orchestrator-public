import type { WatchFile } from './watchFile';

export enum MessageRole {
  SYSTEM = 'system',
  SYSTEM_ERROR = 'system_error',
  MODEL = 'model',
  USER = 'user',
}

export enum MessageStatus {
  PENDING = 'pending',
  SENT = 'sent',
  DELIVERED = 'delivered',
  ERROR = 'error',
}

export enum ConversationState {
  IDLE = 'idle',
  WAITING_FOR_AGENT = 'waiting_for_agent',
  AGENT_PROCESSING = 'agent_processing',
}

export interface TextContent {
  '@type': 'TextContent';
  '@id': string;
  id: string;
  content: string;
}

export interface FunctionCallContent {
  '@type': 'FunctionCallContent';
  '@id': string;
  id: string;
  functionName: string;
  arguments: Record<string, unknown>;
}

export interface Message {
  id: string;
  '@type': 'Message';
  contents: (TextContent | FunctionCallContent)[];
  role: MessageRole;
  status: MessageStatus;
  retryCount: number;
  metadata?: Record<string, unknown>;
  createdAt: string;
  loading?: boolean;
  createdBy?: {
    defaultThumbnail?: string;
  } | null;
}

// Conversation with messages
export interface Conversation {
  id: string;
  title: string;
  watchFile: WatchFile;
  metadata?: Record<string, unknown>;
  state: ConversationState;
  createdAt: string;
  updatedAt: string;
  language: 'fr' | 'en';
  messages: Message[];
}
