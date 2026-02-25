<template>
  <div class="flex h-full flex-col items-center justify-center py-12">
    <div>
      <Icon icon="fa-folder" class="text-3xl text-gray-600" />
    </div>
    <h3 class="text-md mb-6 text-center font-medium text-gray-700">
      {{ t('watch_files.activity.sources.empty.title') }}
    </h3>
    <Button
      icon="fa-plus"
      :label="t('watch_files.activity.sources.empty.add_button')"
      @click="onAddSource"
    />
  </div>
</template>

<script setup lang="ts">
import { Button, Icon } from '@owlint/feathers-vue'
import { RouteNames } from '@target/types/route-names'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const { t } = useI18n()
const router = useRouter()
const props = defineProps<{
  watchFileId?: string
}>()

const onAddSource = async () => {
  await router.push({
    name: RouteNames.WATCH_FILES_SCOPE,
    params: { id: props.watchFileId! },
  })
}
</script>
