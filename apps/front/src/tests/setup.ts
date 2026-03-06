import { config } from '@vue/test-utils'

// Provide $t as a global mock for all component tests.
// Components using $t in templates will get the fallback string or the key.
config.global.mocks = {
  $t: (key: string, fallback?: string) => fallback || key,
}
