<template>
  <Switch
    :id="`source-status-${source.id}`"
    v-model="isActive"
    @click="onSwitchToggle()"
    @keydown.enter="onSwitchToggle()"
  />
</template>

<script lang="ts" setup>
import { Switch } from '@owlint/feathers-vue'
import { useChangeSourceStatus } from '@target/api/mutations/sources'
import { SourceStatus, type Source } from '@target/types/source'
import { computed } from 'vue'

interface Props {
  source: Source
  watchFileId: string
  batchSelection?: boolean
  selectedSources?: string[]
}

interface Emits {
  'toggle-selection': [sourceId: string, isSelected: boolean]
}

const { source, watchFileId, batchSelection = false, selectedSources = [] } = defineProps<Props>()

const emit = defineEmits<Emits>()

const { changeStatus } = useChangeSourceStatus()

const isActive = computed(() => {
  if (batchSelection) {
    return selectedSources.includes(source.id)
  }
  return source.status === SourceStatus.ACTIVE
})

const onSwitchToggle = () => {
  if (batchSelection) {
    emit('toggle-selection', source.id, !isActive.value)
  } else {
    changeStatus({ source, watchFileId })
  }
}
</script>
