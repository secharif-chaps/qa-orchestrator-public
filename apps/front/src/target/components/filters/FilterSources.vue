<template>
  <div class="flex flex-col gap-3">
    <Searchbar
      v-if="sources.length > 5"
      id="searchbar-filter-sources"
      v-model="searchInput"
      :placeholder="t('target.watchFiles.filters.type.sources.placeholder')"
      size="sm"
    />
    <div v-if="sources.length" class="flex flex-col items-start gap-1.5">
      <Checkbox
        v-for="{ source, count } in displayedSources"
        :id="source?.id"
        :key="source?.id"
        v-model="selectedSources"
        :value="source"
        name="filter-sources"
      >
        <label v-if="source" :for="source.id" class="flex items-center gap-2 pl-2">
          <Logo
            :domain="source.primaryDomain ?? ''"
            :alt="source.name"
            :name="source.name"
            :width="20"
            :height="20"
            class="shrink-0 rounded-full object-contain"
          />
          <span>{{ source.name }}</span>
          <span class="text-gray-800"> ({{ count }}) </span>
        </label>
      </Checkbox>
    </div>
    <div v-else>
      <p class="text-sm text-gray-800">
        {{ t('target.watchFiles.filters.empty') }}
      </p>
    </div>
    <Button
      v-if="filteredSources.length > 5"
      class="self-start"
      variant="tertiary"
      @click="displayAllSources = !displayAllSources"
    >
      {{ t(`target.watchFiles.filters.type.sources.see.${displayAllSources ? 'less' : 'more'}`) }}
    </Button>
    <Button
      v-if="selectedSources.length"
      class="w-fit"
      variant="tertiary"
      size="sm"
      icon="fa-rotate-left"
      @click="handleReset"
    >
      {{
        t('target.watchFiles.filters.type.reset', {
          name: t('target.watchFiles.filters.type.sources'),
        })
      }}
    </Button>
  </div>
</template>

<script lang="ts" setup>
import { Button, Checkbox, Searchbar } from '@owlint/feathers-vue'
import Logo from '@/components/ui/Logo.vue'
import type { Source, SourceFacet } from '@target/types/facet'
import { watchDebounced } from '@vueuse/core'
import { computed, ref, watchEffect } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Props {
  sources: SourceFacet[]
}

const { sources } = defineProps<Props>()

const selectedSources = defineModel<Source[]>({ required: true })

const displayAllSources = ref(false)
const searchSource = ref('')
const searchInput = ref(searchSource.value)

const filteredSources = computed(() =>
  sources.filter(({ source }) => {
    if (!source || !source.name) return false
    const isSelected = selectedSources.value.some((storedsource) => storedsource.id === source.id)
    return (
      source.name.toLocaleLowerCase().includes(searchSource.value.toLocaleLowerCase()) || isSelected
    )
  }),
)

const slicedSources = computed(() => filteredSources.value.slice(0, 5))

const displayedSources = computed(() =>
  displayAllSources.value ? filteredSources.value : slicedSources.value,
)

const handleReset = () => {
  selectedSources.value = []
}

watchEffect(() => {
  if (searchSource.value === '') {
    searchInput.value = ''
  }
})

watchDebounced(
  searchInput,
  (newval) => {
    searchSource.value = newval
  },
  { debounce: 500, maxWait: 1000 },
)
</script>
