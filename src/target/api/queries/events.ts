import { defineQueryOptions } from '@pinia/colada';
import { getEventsGraph } from '../events';
import type { LocationQueryRaw } from 'vue-router';

export const EVENTS_QUERY_KEYS = {
  root: ['events'] as const,
  graph: (watchFileId: string, filters?: LocationQueryRaw) => {
    // Serialize filters to ensure stable key comparison
    const filtersKey = filters
      ? JSON.stringify(
          Object.keys(filters)
            .sort()
            .reduce(
              (acc, key) => {
                acc[key] = filters[key];
                return acc;
              },
              {} as Record<string, unknown>,
            ),
        )
      : 'no-filters';
    return [
      ...EVENTS_QUERY_KEYS.root,
      'graph',
      watchFileId,
      filtersKey,
    ] as const;
  },
};

export const getEventsGraphQuery = defineQueryOptions(
  ({
    watchFileId,
    filters,
  }: {
    watchFileId: string;
    filters?: LocationQueryRaw;
  }) => ({
    key: EVENTS_QUERY_KEYS.graph(watchFileId, filters),
    query: () => getEventsGraph(watchFileId, filters),
    enabled: !!watchFileId,
  }),
);
