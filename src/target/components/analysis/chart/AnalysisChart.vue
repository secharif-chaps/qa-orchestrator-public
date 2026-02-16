<template>
  <div class="space-y-6">
    <div class="flex items-center gap-6">
      <p class="font-medium">{{ t('watch_files.analysis.graph.legend') }}</p>
      <div class="flex items-center gap-3 text-xs">
        <div class="bg-almond-400 h-2 w-7.5 rounded" />
        <span>{{ t('watch_files.analysis.graph.legend.event.basic') }}</span>
      </div>
      <div class="flex items-center gap-3 text-xs">
        <div class="h-2 w-7.5 overflow-hidden rounded">
          <div
            class="bg-almond-400 h-1 w-32 -translate-x-1.5 -translate-y-6 -rotate-24"
          />
          <div
            class="bg-almond-500 h-1 w-32 -translate-x-1.5 -translate-y-6 -rotate-24"
          />
          <div
            class="bg-almond-400 h-1 w-32 -translate-x-1.5 -translate-y-6 -rotate-24"
          />
          <div
            class="bg-almond-500 h-1 w-32 -translate-x-1.5 -translate-y-6 -rotate-24"
          />
          <div
            class="bg-almond-400 h-1 w-32 -translate-x-1.5 -translate-y-6 -rotate-24"
          />
        </div>
        <span>{{ t('watch_files.analysis.graph.legend.event.starred') }}</span>
      </div>
    </div>
    <div v-if="isLoading">
      <div
        class="border-sage-100 bg-sage-100 flex h-[60vh] items-center justify-center rounded-2xl border shadow-md"
      >
        <Icon
          icon="fa-chart-bar"
          class="text-sage-700 animate-bounce text-9xl"
        />
      </div>
    </div>
    <div
      v-show="!isLoading"
      ref="chartBox"
      class="border-sage-100 flex items-center gap-6 rounded-2xl border p-6 shadow-md"
    >
      <Button
        class="shrink-0"
        variant="secondary"
        size="sm"
        icon="fa-chevron-left"
        :disabled="!canMoveLeft"
        @click="moveChart('left')"
      />

      <div class="h-[60vh] flex-1 basis-full">
        <canvas id="analysis-chart" ref="chart"></canvas>
      </div>
      <Button
        class="shrink-0"
        variant="secondary"
        size="sm"
        icon="fa-chevron-right"
        :disabled="!canMoveRight"
        @click="moveChart('right')"
      />
    </div>
    <Drawer
      v-model="displayDrawer"
      :title="
        selectedEvent
          ? t('watch_files.analysis.event.title', {
              from: d(new Date(selectedEvent.start), 'eventDate'),
            })
          : ''
      "
      icon="fa-bullhorn"
      to="#watchfile-layout"
    >
      <AnalysisEventViewer v-if="selectedEvent" :link="selectedEvent.link" />
    </Drawer>
  </div>
</template>

<script lang="ts" setup>
import { Button, Icon } from '@owlint/feathers-vue';
import {
    Chart,
    registerables,
    type ChartData,
    type ChartOptions,
} from 'chart.js';
import { draw } from 'patternomaly';
import { computed, nextTick, onMounted, onUnmounted, ref, toRefs, useTemplateRef, watch, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import AnalysisEventViewer from '~/components/analysis/AnalysisEventViewer.vue';
import Drawer from '~/components/global/Drawer.vue';
import { useCssVar } from '~/composables/useCssVar';
import type { WatchFileGraphEvent } from '~/types/watchFileEvent';

Chart.register(...registerables);

let chartVal: Chart<'bar', number[], string> | null = null;

interface Props {
  watchFileId: string;
  eventsGraphData?: {
    items: WatchFileGraphEvent[];
    totalItems: number;
  };
  isLoading?: boolean;
}

const props = defineProps<Props>();

const { eventsGraphData, isLoading } = toRefs(props);
const isLoadingValue = computed(() => isLoading.value ?? false);

const chartRef = useTemplateRef('chart');
const chartBoxRef = useTemplateRef('chartBox');

const { t, d, locale } = useI18n();

const displayDrawer = ref(false);
const selectedEvent = ref();

const eventsGraph = computed(() => eventsGraphData.value?.items ?? []);
const dataLength = computed(() => eventsGraph.value.length);

const chartBoxWidth = ref(0);
const MIN_BAR_WIDTH = 80; // Minimum width per bar in pixels

const displayItems = computed(() => {
  // If width is not yet measured, use an estimation based on window
  // or wait for width to be available

  /**
   * 6 * 4 * 2 + 32 * 2 = 112
   * 6 is the padding of the chart box p-6
   * 4 is the tailwind multiplier for the padding
   * 2 is for padding-left and padding-right
   * 32 is the the size of Button size sm
   * 2 is because we have two buttons
   */
  const PADDING_BOX = 6 * 4 * 2 + 32 * 2;
  if (chartBoxWidth.value === 0) {
    // Estimation based on window width while waiting for real measurement
    const estimatedWidth =
      typeof window !== 'undefined' ? window.innerWidth - 200 : 800;
    const availableWidth = estimatedWidth - PADDING_BOX;
    const calculatedItems = Math.floor(availableWidth / MIN_BAR_WIDTH);
    return Math.max(
      1,
      Math.min(calculatedItems, eventsGraph.value.length || 14),
    );
  }
  // Calculate the number of bars that can fit in the available space
  // Subtract PADDING_BOX
  const availableWidth = chartBoxWidth.value - PADDING_BOX;
  const calculatedItems = Math.floor(availableWidth / MIN_BAR_WIDTH);
  return Math.max(1, Math.min(calculatedItems, eventsGraph.value.length));
});

// Watch for when data becomes available and draw chart
watchEffect(() => {
  if (
    !isLoadingValue.value &&
    eventsGraph.value.length > 0 &&
    chartRef.value &&
    !chartVal
  ) {
    // Wait for width to be measured if not already
    if (chartBoxWidth.value > 0) {
      nextTick(() => {
        if (chartRef.value && !chartVal) {
          drawChart(chartRef.value);
        }
      });
    }
  }
});

watch([displayItems, eventsGraph], () => {
  if (chartVal && eventsGraph.value.length > 0) {
    updateChart();
  }
});

// Redraw the chart when the real width is measured
watch(chartBoxWidth, (newWidth) => {
  if (newWidth > 0 && chartRef.value && eventsGraph.value.length > 0) {
    // If chart already exists, update it
    if (chartVal) {
      updateChart();
    } else {
      drawChart(chartRef.value);
    }
  }
});

watch(locale, () => {
  if (chartVal && eventsGraph.value.length) {
    chartVal.data.labels = labels.value;
    chartVal.update();
  }
});

const { value: almondColor400 } = useCssVar('--color-almond-400');
const { value: almondColor500 } = useCssVar('--color-almond-500');

const labels = computed(() => {
  return eventsGraph.value.map((event) => d(new Date(event.start), 'short'));
});
const datasetData = computed(() =>
  eventsGraph.value.map((event) => event.documentsCount),
);
const datasetBackgroundColor = computed(() =>
  eventsGraph.value.map((event) =>
    event.hasEvents
      ? draw('diagonal-right-left', almondColor500.value)
      : almondColor400.value,
  ),
);

const chartData = computed<ChartData<'bar', number[], string>>(() => ({
  labels: labels.value,
  datasets: [
    {
      data: datasetData.value,
      backgroundColor: datasetBackgroundColor.value,
      barPercentage: 0.9,
      borderRadius: 4,
    },
  ],
}));

const max = computed(() => {
  return Math.max(...(chartData.value.datasets[0]?.data ?? []));
});

const chartPosition = ref({ min: 0, max: 0 });

const canMoveLeft = computed(() => {
  if (eventsGraph.value.length === 0) return false;
  return chartPosition.value.min > 0;
});

const canMoveRight = computed(() => {
  if (eventsGraph.value.length === 0) return false;
  return chartPosition.value.max < dataLength.value;
});

const drawChart = (canvas: HTMLCanvasElement) => {
  if (chartVal) {
    chartVal.destroy();
  }
  const ctx = canvas.getContext('2d');
  const itemsToShow = displayItems.value;
  const options: ChartOptions<'bar'> = {
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: false,
      },
      tooltip: {
        callbacks: {
          label: function (context) {
            // Retrieve the graph item corresponding to this bar
            const eventItem = eventsGraph.value?.[context.dataIndex];
            const nbEvents = eventItem?.eventsCount ?? 0;

            const labelTexts = [
              t(
                'watch_files.analysis.graph.legend.axis.y',
                { nb: context.raw },
                Number(context.raw),
              ),
            ];

            if (nbEvents > 0) {
              labelTexts.push(
                t(
                  'watch_files.analysis.graph.legend.event.count',
                  { nb: nbEvents },
                  Number(nbEvents),
                ),
              );
            }

            return labelTexts;
          },
        },
      },
    },
    scales: {
      x: {
        grid: {
          color: '#ffffff',
        },
        ticks: {
          color: '#000000',
          textStrokeColor: '#000000',
          backdropColor: '#000000',
        },
        min: Math.max(0, dataLength.value - itemsToShow),
        max: dataLength.value,
      },
      y: {
        beginAtZero: true,
        ticks: {
          color: '#000000',
          textStrokeColor: '#000000',
          backdropColor: '#000000',
          callback: function (value: string | number) {
            return t(
              'watch_files.analysis.graph.legend.axis.y',
              { nb: value },
              Number(value),
            );
          },
          precision: 0,
        },
        max: max.value,
      },
    },
    onClick(_, elements) {
      if (!elements.length) return;

      const clickedIndex = elements[0]!.index;
      const graphData = eventsGraph.value;

      if (clickedIndex < 0 || clickedIndex >= graphData.length) return;

      const clickedEvent = graphData[clickedIndex];
      if (clickedEvent && clickedEvent.eventsCount > 0) {
        selectedEvent.value = clickedEvent;
        displayDrawer.value = true;
      }
    },
  };
  chartVal = new Chart<'bar', number[], string>(ctx!, {
    type: 'bar',
    data: chartData.value,
    options,
  });

  // Initialize position state
  const initialMin = Math.max(0, dataLength.value - itemsToShow);
  chartPosition.value = { min: initialMin, max: dataLength.value };
};

const updateChart = () => {
  if (
    !chartVal ||
    eventsGraph.value.length === 0 ||
    !chartVal.data.datasets[0]
  ) {
    return;
  }

  // Update chart data
  chartVal.data.labels = labels.value;
  chartVal.data.datasets[0].data = datasetData.value;
  chartVal.data.datasets[0].backgroundColor = datasetBackgroundColor.value;

  // Update Y axis max
  const { scales } = chartVal.options;
  if (scales?.y) {
    scales.y.max = max.value;
  }

  // Update X axis range
  if (dataLength.value > 0 && scales?.x) {
    const itemsToShow = displayItems.value;
    const currentMax =
      scales.x.max !== undefined ? Number(scales.x.max) : dataLength.value;

    // Adjust the range to ensure it matches the number of items to display
    const newMax = Math.min(dataLength.value, currentMax);
    const newMin = Math.max(0, newMax - itemsToShow);

    scales.x.min = newMin;
    scales.x.max = newMax;
    chartPosition.value = { min: newMin, max: newMax };
  }

  chartVal.update();
};

const moveChart = (direction: 'right' | 'left') => {
  if (chartVal) {
    const itemsToShow = displayItems.value;
    const { scales } = chartVal.options;
    if (!scales) return;
    const { x } = scales;
    if (!x) return;

    if (x.min !== undefined && x.max !== undefined) {
      const currentMin = Number(x.min);
      const currentMax = Number(x.max);

      if (direction === 'right') {
        const newMin = currentMax + 1;
        const newMax = newMin + itemsToShow;

        if (newMax > dataLength.value) {
          x.min = Math.max(0, dataLength.value - itemsToShow);
          x.max = dataLength.value;
        } else {
          x.min = newMin;
          x.max = newMax;
        }
      } else {
        const newMax = currentMin - 1;
        const newMin = newMax - itemsToShow;

        if (newMin < 0) {
          x.min = 0;
          x.max = Math.min(itemsToShow, dataLength.value);
        } else {
          x.min = newMin;
          x.max = newMax;
        }
      }
    }

    chartVal.update();
    // Update position state for button disabled state
    if (x.min !== undefined && x.max !== undefined) {
      chartPosition.value = { min: Number(x.min), max: Number(x.max) };
    }
  }
};

let resizeObserver: ResizeObserver | null = null;

const updateChartBoxWidth = () => {
  if (chartBoxRef.value) {
    chartBoxWidth.value = chartBoxRef.value.clientWidth;
    if (chartVal) {
      chartVal.resize();
    }
  }
};

onMounted(() => {
  nextTick(() => {
    if (chartBoxRef.value) {
      updateChartBoxWidth();
      resizeObserver = new ResizeObserver(() => {
        updateChartBoxWidth();
      });

      resizeObserver.observe(chartBoxRef.value);
    }

    // Draw chart if data is already available
    if (
      !isLoadingValue.value &&
      eventsGraph.value.length > 0 &&
      chartRef.value &&
      !chartVal
    ) {
      drawChart(chartRef.value);
    }
  });
});

onUnmounted(() => {
  if (resizeObserver) {
    resizeObserver.disconnect();
    resizeObserver = null;
  }
  if (chartVal) {
    chartVal.destroy();
  }
});
</script>
