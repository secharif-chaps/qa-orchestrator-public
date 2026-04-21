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
// Note: 'translation' is now a feature flag, not a module (see feature-flags config)
export const MODULE_CONFIG: Record<ModuleName, Omit<ModuleDisplayConfig, 'status' | 'name'>> = {
  screen: {
    labelKey: 'common.modules.screen',
    icon: 'fa-regular fa-buildings',
    color: 'indigo',
  },
  target: {
    labelKey: 'common.modules.target',
    icon: 'fa-regular fa-file-lines',
    color: 'cherry',
  },
  explore: {
    labelKey: 'common.modules.explore',
    icon: 'fa-regular fa-chart-network',
    color: 'almond',
  },
  stream: {
    labelKey: 'common.modules.stream',
    icon: 'fa-solid fa-paper-plane',
    color: 'cyan',
  },
}

// Default config for unknown modules
const DEFAULT_MODULE_CONFIG: Omit<ModuleDisplayConfig, 'status' | 'name'> = {
  labelKey: 'common.modules.unknown',
  icon: 'fa-solid fa-cube',
  color: 'sage',
}

export const getModuleDisplayConfig = (name: ModuleName, enabled: boolean): ModuleDisplayConfig => {
  const config = MODULE_CONFIG[name] || DEFAULT_MODULE_CONFIG

  return {
    name,
    ...config,
    status: enabled ? 'enabled' : 'unavailable',
  }
}
