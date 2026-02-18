import { useWatchFileAnalysisStore } from '@target/stores/watchFileAnalysis'
import { RouteNames } from '@target/types/route-names'
import { storeToRefs } from 'pinia'
import type { NavigationGuardWithThis } from 'vue-router'
import { useRouter } from 'vue-router'

const radarMiddleware: NavigationGuardWithThis<undefined> = (to) => {
  const router = useRouter()
  const watchFileAnalysisStore = useWatchFileAnalysisStore()
  const { selectedView } = storeToRefs(watchFileAnalysisStore)

  if (to.name === RouteNames.WATCH_FILES_RADAR) {
    return router.push({
      name: selectedView.value,
      params: to.params,
      query: to.query,
    })
  }
}

export default radarMiddleware
