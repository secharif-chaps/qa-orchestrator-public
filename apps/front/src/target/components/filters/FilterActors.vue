<template>
  <div class="flex flex-col gap-3">
    <Searchbar
      v-if="actors?.length > 5"
      id="searchbar-filter-actors"
      v-model="searchInput"
      :placeholder="t('watch_files.filters.type.actors.placeholder')"
      size="sm"
    />
    <div v-if="actors.length" class="flex flex-col items-start gap-1.5">
      <Checkbox
        v-for="{ actor, count } in displayedActors"
        :id="actor?.id"
        :key="actor?.id"
        v-model="selectedActors"
        :value="actor"
        name="filter-actors"
      >
        <label v-if="actor" :for="actor.id" class="flex items-center gap-2 pl-2">
          <Logo
            :domain="actor.primaryDomain ?? ''"
            :alt="actor.label"
            :name="actor.label"
            :width="20"
            :height="20"
            class="shrink-0 rounded-full object-contain"
          />
          <span>{{ actor.label }}</span>
          <span class="text-gray-800"> ({{ count }}) </span>
        </label>
      </Checkbox>
    </div>
    <div v-else>
      <p class="text-sm text-gray-800">
        {{ t('watch_files.filters.empty') }}
      </p>
    </div>
    <Button
      v-if="filteredActors.length > 5"
      class="self-start"
      variant="tertiary"
      @click="displayAllActors = !displayAllActors"
    >
      {{ t(`watch_files.filters.type.actors.see.${displayAllActors ? 'less' : 'more'}`) }}
    </Button>
    <Button
      v-if="selectedActors.length"
      class="w-fit"
      variant="tertiary"
      size="sm"
      icon="fa-rotate-left"
      @click="handleReset"
    >
      {{
        t('watch_files.filters.type.reset', {
          name: t('watch_files.filters.type.actors'),
        })
      }}
    </Button>
  </div>
</template>

<script lang="ts" setup>
import { Button, Checkbox, Searchbar } from '@owlint/feathers-vue'
import Logo from '@target/components/global/Logo.vue'
import type { Actor, ActorFacet } from '@target/types/facet'
import { watchDebounced } from '@vueuse/core'
import { computed, ref, watchEffect } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Props {
  actors: ActorFacet[]
}

const props = defineProps<Props>()

const selectedActors = defineModel<Actor[]>({ required: true })

const displayAllActors = ref(false)
const searchActor = ref('')
const searchInput = ref(searchActor.value)

const filteredActors = computed(() =>
  props.actors.filter(({ actor }) => {
    if (!actor || !actor.label) return false
    const isSelected = selectedActors.value.some((storedActor) => storedActor.id === actor.id)

    return (
      actor.label.toLocaleLowerCase().includes(searchActor.value.toLocaleLowerCase()) || isSelected
    )
  }),
)

const slicedActors = computed(() => filteredActors.value.slice(0, 5))

const displayedActors = computed(() =>
  displayAllActors.value ? filteredActors.value : slicedActors.value,
)

const handleReset = () => {
  selectedActors.value = []
}

watchEffect(() => {
  if (searchActor.value === '') {
    searchInput.value = ''
  }
})

watchDebounced(
  searchInput,
  (newval) => {
    searchActor.value = newval
  },
  { debounce: 500, maxWait: 1000 },
)
</script>
