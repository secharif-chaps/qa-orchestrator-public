import type { JsonLdResource } from '@target/types/jsonld'

export interface User extends JsonLdResource {
  '@id': string
  '@type': 'User'
  id: string
  email: string
  firstName?: string
  lastName?: string
  defaultThumbnail: string
  displayName: string
}
