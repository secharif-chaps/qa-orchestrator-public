import type { SourceActivity } from '@target/types/source'
import type { SourceActivityDescription, WatchFileActivityDescription } from '@target/types/timeline'
import type { WatchFileActivity } from '@target/types/watchFile'

export function useActivityDescription() {
  const createWatchFileActivityDescription = (
    localizationKey: string,
    activity: WatchFileActivity,
    customComponent?: string,
  ): WatchFileActivityDescription => {
    return {
      dataType: 'WatchFile',
      localizationKey,
      activity,
      customComponent,
    }
  }

  const createSourceActivityDescription = (
    localizationKey: string,
    activity: SourceActivity,
    customComponent?: string,
  ): SourceActivityDescription => {
    return {
      dataType: 'Source',
      localizationKey,
      activity,
      customComponent,
    }
  }

  return {
    createWatchFileActivityDescription,
    createSourceActivityDescription,
  }
}
