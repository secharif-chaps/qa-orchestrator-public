<template>
  <Transition
    enter-active-class="transition-all duration-300 ease-out"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="transition-all duration-200 ease-in"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-if="isOpen"
      class="fixed inset-0 z-50 flex items-end justify-end pr-3"
      @click="$emit('close')"
    >
      <!-- Overlay background -->
      <div
        class="absolute inset-0 bg-black transition-opacity duration-300"
        style="opacity: 0.8"
      ></div>

      <!-- Modal container -->
      <div
        class="relative z-10 flex h-[90%] w-[95%] flex-col rounded-t-lg bg-white shadow-2xl"
        @click.stop
      >
        <!-- Button bar - positioned above modal -->
        <div
          class="absolute -top-12 right-0 left-0 z-20 flex h-8 flex-col justify-end bg-transparent"
          @click="$emit('close')"
        >
          <div class="flex justify-end">
            <div class="rounded bg-white">
              <Button variant="secondary" icon="fa-xmark" @click="$emit('close')" />
            </div>
          </div>
        </div>

        <!-- Content - takes remaining space -->
        <Transition
          mode="out-in"
          enter-active-class="transition-all duration-400 ease-out"
          enter-from-class="opacity-0 translate-y-2"
          enter-to-class="opacity-100 translate-y-0"
          leave-active-class="transition-all duration-300 ease-in"
          leave-from-class="opacity-100 translate-y-0"
          leave-to-class="opacity-0 translate-y-2"
        >
          <DocumentViewerSkeleton v-if="isLoading" :key="'skeleton'" />
          <div v-else-if="error" :key="'error'" class="flex flex-1 items-center justify-center">
            <ErrorMessage :title="$t('target.document.viewer.error.title')" :fill="true" />
          </div>
          <div v-else :key="'content'" class="flex flex-1 overflow-hidden p-6">
            <!-- Left Pane - Main Content with Header -->
            <div class="flex flex-1 flex-col overflow-hidden rounded-2xl shadow-2xl">
              <!-- Header -->
              <div class="shrink-0 px-6 pt-4">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-4">
                    <Logo
                      :domain="document?.source?.primaryDomain"
                      :alt="document?.source?.name"
                      class="h-6 w-6 rounded-full"
                    />
                    <h1 class="text-xl font-semibold text-gray-900">
                      {{ document?.title }}
                    </h1>
                  </div>
                </div>
              </div>

              <!-- Content Area -->
              <div class="flex-1 overflow-hidden p-4">
                <!-- PDF Viewer -->
                <div v-if="isPdf" class="h-full">
                  <object
                    :data="pdfData"
                    type="application/pdf"
                    class="h-full w-full rounded-lg border-0"
                  />
                </div>

                <!-- HTML Content -->
                <div v-else class="scrollable h-full pb-10">
                  <div
                    v-sanitize-html="document?.content"
                    class="prose prose-lg pb-6 leading-relaxed text-gray-700"
                  ></div>
                </div>

                <DocumentViewerActionButtons :document="document" />
              </div>
            </div>

            <!-- Right Pane - Information Sidebar -->
            <div class="scrollable w-[30%] shrink-0">
              <DocumentDetail :document="document" />
            </div>
          </div>
        </Transition>
      </div>
    </div>
  </Transition>
</template>

<script setup lang="ts">
import { Button } from '@owlint/feathers-vue'
import DocumentDetail from '@target/components/documents/DocumentDetail.vue'
import ErrorMessage from '@target/components/global/ErrorMessage.vue'
import Logo from '@target/components/global/Logo.vue'
import DocumentViewerSkeleton from '@target/components/skeletons/DocumentViewerSkeleton.vue'
import type { Document } from '@target/types/document'
import { computed, onMounted, onUnmounted } from 'vue'
import DocumentViewerActionButtons from './DocumentViewerActionButtons.vue'

interface Props {
  isOpen: boolean
  document?: Document
  isLoading?: boolean
  error?: Error
}

const { isOpen, document = undefined, isLoading = false, error = undefined } = defineProps<Props>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const isPdf = computed(() => document?.type?.toLowerCase() === 'pdf')

const pdfData = computed(() => {
  if (!document?.content) return ''
  return `data:application/pdf;base64,${document.content}`
})

const handleKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape' && isOpen) {
    event.preventDefault()
    event.stopPropagation()
    emit('close')
  }
}

onMounted(() => {
  window.document.addEventListener('keydown', handleKeydown, true)
  // Prevent body scroll when modal is open
  window.document.body.style.overflow = 'hidden'
})

onUnmounted(() => {
  window.document.removeEventListener('keydown', handleKeydown, true)
  // Restore body scroll
  window.document.body.style.overflow = ''
})
</script>
