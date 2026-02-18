import { useApi as useApiFromService } from '~/api/api'

export function useApi() {
  return useApiFromService()
}
