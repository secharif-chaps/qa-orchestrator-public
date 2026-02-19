<template>
  <section>
    <div class="border-sage-100 space-y-4 rounded border p-6 shadow">
      <SectionListHeader
        :title="$t('watch_files.reference_subject.title')"
        :sub-title="$t('watch_files.reference_subject.sub_title')"
        :readonly="true"
        :last-update="watchFile?.referenceSubject ? watchFile?.updatedAt : undefined"
      />

      <div
        v-if="watchFile?.referenceSubject"
        class="max-h-90 min-h-20 overflow-y-auto rounded-xs p-4 text-sm"
        :class="[
          {
            'animate-pulse': loading,
          },
          watchFile?.status === 'draft' ? 'border-sage-300 border' : 'bg-sage-100',
        ]"
      >
        <div>
          <Transition name="content-fade" mode="out-in">
            <div
              :key="renderedContent"
              v-sanitize-html="renderedContent"
              class="prose prose-sm markdown-content"
            />
          </Transition>
        </div>
      </div>
      <InformationMessage
        v-else
        width="full"
        :title="$t('watch_files.reference_subject.loading')"
        :description="$t('watch_files.reference_subject.loading_subtitle')"
      />
    </div>
  </section>
</template>

<script setup lang="ts">
import InformationMessage from '@target/components/global/InformationMessage.vue'
import { useLocalized } from '@target/composables/useLocalized'
import { useMarkdown } from '@target/composables/useMarkdown'
import { useWatchFileStore } from '@target/stores/watchFile'
import type { WatchFile } from '@target/types/watchFile'
import { computed } from 'vue'
import SectionListHeader from './SectionListHeader.vue'

const watchFileStore = useWatchFileStore()
const loading = computed(() => watchFileStore.isLoading)

interface Props {
  watchFile?: WatchFile
}
const { watchFile = undefined } = defineProps<Props>()

const { getLocalizedString } = useLocalized()
const { toHtml } = useMarkdown()

const localizedReferenceSubject = computed(() => watchFile?.referenceSubject)

const localizedText = getLocalizedString(
  localizedReferenceSubject,
  watchFile?.referenceSubject?.en || '',
)

const renderedContent = toHtml(localizedText)
</script>

<style scoped>
.content-fade-enter-active,
.content-fade-leave-active {
  transition: opacity 0.3s ease;
}

.content-fade-enter-from,
.content-fade-leave-to {
  opacity: 0;
}
</style>
