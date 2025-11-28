import type { ModuleName } from '@/types/tokens'

export type ModuleStatus = 'enabled' | 'soon' | 'unavailable'
export type ModuleColor = 'purple' | 'green' | 'orange' | 'blue'

export interface ModuleDisplayConfig {
  name: ModuleName
  labelKey: string
  icon: string
  status: ModuleStatus
  color: ModuleColor
}

// Module display configuration
// This maps internal module names to i18n keys, icons, and colors
export const MODULE_CONFIG: Record<ModuleName, Omit<ModuleDisplayConfig, 'status' | 'name'>> = {
  screen: {
    labelKey: 'common.modules.screen',
    icon: 'fa fa-building',
    color: 'indigo',
  },
  target: {
    labelKey: 'common.modules.target',
    icon: 'fa fa-file-alt',
    color: 'orange',
  },
  explore: {
    labelKey: 'common.modules.explore',
    icon: 'fa fa-map',
    color: 'almond',
  },
  stream: {
    labelKey: 'common.modules.stream',
    icon: 'fa fa-stream',
    color: 'yellow',
  },
}

export const getModuleDisplayConfig = (
  name: ModuleName,
  enabled: boolean,
  comingSoon: boolean = false,
): ModuleDisplayConfig => {
  const config = MODULE_CONFIG[name]

  let status: ModuleStatus
  if (comingSoon) {
    status = 'soon'
  } else if (enabled) {
    status = 'enabled'
  } else {
    status = 'unavailable'
  }

  return {
    name,
    ...config,
    status,
  }
}
