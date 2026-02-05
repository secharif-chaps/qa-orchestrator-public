export interface DataSourceConfig {
  source: string
  enabled: boolean
  api_key_masked: string | null
  enabled_at: string | null
  updated_at: string | null
}

export interface DataSourceInfo {
  source: string
  name: string
  description: string
  logo: string
}
