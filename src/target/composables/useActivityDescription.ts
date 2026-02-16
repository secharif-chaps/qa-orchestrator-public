import type { WatchFileActivity } from '~/types/watchFile';
import type { SourceActivity } from '~/types/source';
import type {
  WatchFileActivityDescription,
  SourceActivityDescription,
} from '~/types/timeline';

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
    };
  };

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
    };
  };

  return {
    createWatchFileActivityDescription,
    createSourceActivityDescription,
  };
}
