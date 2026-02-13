<template>
  <div class="flex flex-col gap-3">
    <div
      v-if="showDateType"
      class="flex flex-col items-start gap-1 border-b border-gray-200 pb-3"
    >
      <ORadio
        id="publication-radio"
        v-model="selectedDateType"
        :value="FilterDates.PUBLICATION"
        name="date-radio"
        :label="t('watch_files.filters.type.dates.publication')"
      />
      <ORadio
        id="collection-radio"
        v-model="selectedDateType"
        :value="FilterDates.COLLECT"
        name="date-radio"
        :label="t('watch_files.filters.type.dates.collection')"
      />
    </div>
    <div class="max-w-72 space-y-1">
      <Label id="dates-period-filter">
        {{ t('watch_files.filters.type.dates.period.label') }}
      </Label>
      <Select
        id="dates-period-filter"
        v-model="selectedPeriod"
        :placeholder="t('watch_files.filters.type.dates.period.placeholder')"
        :options="periodsOptions"
      >
        <template #items="{ options }">
          <SelectItem v-for="option in options" :key="option" :option="option">
            <ORadio :id="option" v-model="selectedPeriod" :value="option" />
            <span class="of:flex of:items-center of:gap-2">
              <span>{{
                t(`watch_files.filters.type.dates.period.${option}`)
              }}</span>
            </span>
          </SelectItem>
        </template>
      </Select>
    </div>
    <div class="space-y-1">
      <Label id="dates-range-filter">
        {{ t('watch_files.filters.type.dates.range.label') }}
      </Label>
      <DateRangePicker
        id="dates-range-filter"
        v-model="datesPicker"
        class="z-50"
      />
    </div>
    <Button
      v-if="datesFilterCount"
      class="w-fit"
      variant="tertiary"
      size="sm"
      icon="fa-rotate-left"
      @click="handleReset"
    >
      {{
        t('watch_files.filters.type.reset', {
          name: t('watch_files.filters.type.dates'),
        })
      }}
    </Button>
  </div>
</template>

<script lang="ts" setup>
import {
  Button,
  DateRangePicker,
  Label,
  ORadio,
  Select,
  SelectItem,
} from '@owlint/feathers-vue';
import type { DateRange } from 'reka-ui';
import { computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { DatesPeriod, FilterDates } from '~/types/filter';

const { t } = useI18n();

interface Props {
  datesFilterCount?: number;
  showDateType?: boolean;
}

const { datesFilterCount = 0, showDateType = false } = defineProps<Props>();

const datesPicker = defineModel<DateRange | null>('datesPicker', {
  default: () => ({ start: undefined, end: undefined }),
});

const selectedPeriod = defineModel<DatesPeriod | undefined>('selectedPeriod');
const selectedDateType = defineModel<FilterDates | undefined>(
  'selectedDateType',
);

const emit = defineEmits<{
  reset: [];
}>();

const periodsOptions = computed(() => [
  DatesPeriod.LAST_WEEK,
  DatesPeriod.LAST_MONTH,
  DatesPeriod.LAST_3_MONTH,
]);

onMounted(() => {
  if (!selectedDateType.value && showDateType) {
    selectedDateType.value = FilterDates.PUBLICATION;
  }
});

watch(selectedPeriod, (value) => {
  if (value) {
    datesPicker.value = { start: undefined, end: undefined } as DateRange;
  }
});

watch(datesPicker, (value) => {
  if (value && (value.end || value.start)) {
    selectedPeriod.value = undefined;
  }
});

const handleReset = () => {
  emit('reset');
};
</script>

<style>
/* Temporary fix waiting the fix from Vuellar */
div[data-reka-popper-content-wrapper] {
  z-index: 20 !important;
}
</style>
