export type ModuleName = 'screen' | 'target' | 'explore' | 'stream'

export interface ModuleConfig {
  name: ModuleName
  enabled: boolean
  token_count: number
  created_at: string
  updated_at: string
}

export interface ModulesResponse {
  modules: ModuleConfig[]
}

export interface ModuleTokenResponse {
  module: ModuleName
  token_count: number
  enabled: boolean
}

export interface TokenUpdateRequest {
  enabled?: boolean
  token_count?: number
}

export interface AddTokensRequest {
  tokens: number
}

export interface InsufficientTokensError {
  error: 'insufficient_tokens'
  message: string
  current_tokens: number
  required_tokens: number
  module: ModuleName
}