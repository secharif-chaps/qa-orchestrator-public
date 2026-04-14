export const PROJECT_TYPES = {
  COMPANY_CARD: 'company_card',
  WATCHFILE: 'watchfile',
} as const

export type ProjectType = (typeof PROJECT_TYPES)[keyof typeof PROJECT_TYPES]

export interface ModuleAction {
  name: string
  label: string
  icon: string
  color: 'indigo' | 'cherry' | 'yellow' | 'cyan'
  route?: string
  externalUrl?: string
}
