import type { ModuleName } from '@/types/tokens'

export type ModuleStatus = 'enabled' | 'soon' | 'unavailable'
export type ModuleColor = 'purple' | 'green' | 'orange' | 'blue'

export interface ModuleDisplayConfig {
  name: ModuleName
  label: string
  icon: string
  status: ModuleStatus
  color: ModuleColor
}

// Module display configuration
// This maps internal module names to display names, icons, and colors
export const MODULE_CONFIG: Record<ModuleName, Omit<ModuleDisplayConfig, 'status' | 'name'>> = {
  screen: {
    label: 'Fiche entreprise',
    icon: 'fa fa-building',
    color: 'indigo',
  },
  target: {
    label: 'Veille',
    icon: 'fa fa-file-alt',
    color: 'orange',
  },
  explore: {
    label: 'Cartographie',
    icon: 'fa fa-map',
    color: 'almond',
  },
  stream: {
    label: 'Stream',
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
