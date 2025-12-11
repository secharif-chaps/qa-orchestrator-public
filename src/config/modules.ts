import type { ModuleName } from '@/types/tokens'

export type ModuleStatus = 'enabled' | 'unavailable'
// Vuellar Tag colors
export type ModuleColor = 'sage' | 'almond' | 'pink' | 'indigo' | 'yellow' | 'cherry' | 'cyan'

export interface ModuleDisplayConfig {
  name: ModuleName
  labelKey: string
  icon: string
  status: ModuleStatus
  color: ModuleColor
}

// Module display configuration using Vuellar Tag colors
export const MODULE_CONFIG: Record<ModuleName, Omit<ModuleDisplayConfig, 'status' | 'name'>> = {
  screen: {
    labelKey: 'common.modules.screen',
    icon: 'fa-solid fa-magnifying-glass',
    color: 'indigo',
  },
  target: {
    labelKey: 'common.modules.target',
    icon: 'fa-solid fa-bullseye',
    color: 'cherry',
  },
  explore: {
    labelKey: 'common.modules.explore',
    icon: 'fa-solid fa-project-diagram',
    color: 'almond',
  },
  stream: {
    labelKey: 'common.modules.stream',
    icon: 'fa-solid fa-rss',
    color: 'yellow',
  },
}

export const getModuleDisplayConfig = (
  name: ModuleName,
  enabled: boolean,
): ModuleDisplayConfig => {
  const config = MODULE_CONFIG[name]

  return {
    name,
    ...config,
    status: enabled ? 'enabled' : 'unavailable',
  }
}
