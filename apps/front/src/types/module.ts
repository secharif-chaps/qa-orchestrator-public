export interface ModuleAction {
  name: string
  label: string
  icon: string
  color: 'indigo' | 'cherry' | 'yellow' | 'cyan'
  route?: string
  externalUrl?: string
}
