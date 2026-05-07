<template>
  <Dropdown align="right" width="md">
    <template #trigger>
      <Button variant="tertiary" :icon="triggerIcon" icon-right="fa-chevron-down">
        {{ triggerLabel }}
      </Button>
    </template>
    <template #content>
      <DropdownItem v-for="option in options" :key="option.value" @click="setSortBy(option.value)">
        <i
          class="fa-solid fa-check w-4 text-xs"
          :class="{ 'opacity-0': sortBy !== option.value }"
          aria-hidden="true"
        ></i>
        <span>{{ option.label }}</span>
      </DropdownItem>
      <DropdownDivider />
      <DropdownItem @click="setSortOrder('asc')">
        <i
          class="fa-solid fa-check w-4 text-xs"
          :class="{ 'opacity-0': sortOrder !== 'asc' }"
          aria-hidden="true"
        ></i>
        <span>{{ t('common.sort.asc') }}</span>
      </DropdownItem>
      <DropdownItem @click="setSortOrder('desc')">
        <i
          class="fa-solid fa-check w-4 text-xs"
          :class="{ 'opacity-0': sortOrder !== 'desc' }"
          aria-hidden="true"
        ></i>
        <span>{{ t('common.sort.desc') }}</span>
      </DropdownItem>
    </template>
  </Dropdown>
</template>

<script setup lang="ts">
import Dropdown from '@/components/ui/Dropdown.vue'
import DropdownDivider from '@/components/ui/DropdownDivider.vue'
import DropdownItem from '@/components/ui/DropdownItem.vue'
import { Button } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

export interface SortOption {
  value: string
  label: string
}

interface Props {
  options: SortOption[]
  buttonLabel?: string
}

const { options, buttonLabel = undefined } = defineProps<Props>()

const sortBy = defineModel<string>('sortBy', { required: true })
const sortOrder = defineModel<'asc' | 'desc'>('sortOrder', { required: true })

const { t } = useI18n()

const triggerLabel = computed(() => buttonLabel ?? t('common.sort.label'))
const triggerIcon = computed(() =>
  sortOrder.value === 'asc' ? 'fa-sort-amount-asc' : 'fa-sort-amount-desc',
)

const setSortBy = (value: string) => {
  sortBy.value = value
}

const setSortOrder = (value: 'asc' | 'desc') => {
  sortOrder.value = value
}
</script>
