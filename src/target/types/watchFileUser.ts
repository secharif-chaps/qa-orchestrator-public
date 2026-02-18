import type { JsonLdResource } from '@target/types/jsonld'
import type { User } from '@target/types/user'
import type { WatchFile } from '@target/types/watchFile'

export const WATCH_FILE_USER_ROLE = {
  OWNER: 'owner',
  EDITOR: 'editor',
  VIEWER: 'viewer',
} as const

export type WatchFileUserRole = (typeof WATCH_FILE_USER_ROLE)[keyof typeof WATCH_FILE_USER_ROLE]

export interface WatchFileUser extends JsonLdResource {
  '@id': string
  '@type': 'WatchFileUser'
  id: string
  user: User
  role: WatchFileUserRole
  watchFile: Partial<WatchFile>
}
