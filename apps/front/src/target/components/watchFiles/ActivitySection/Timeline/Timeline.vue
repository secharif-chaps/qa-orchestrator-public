<template>
  <div class="relative flex h-full overflow-y-auto pl-6">
    <TimelineSkeleton v-if="isLoading" />

    <template v-else>
      <!-- Vertical line -->
      <div
        ref="lineRef"
        class="bg-sage-200 absolute top-3 left-6 z-10 w-0.5"
        :style="{ height: lineHeight + 'px' }"
      ></div>
      <div ref="timelineRef" class="relative pl-10">
        <template v-for="(day, dayIndex) in days" :key="day.date">
          <!-- Date header -->
          <div class="relative mb-6">
            <Tag size="sm" class="relative left-6 z-10 -ml-10 inline-block -translate-x-1/2">
              {{ d(day.date, 'short') }}
            </Tag>
          </div>

          <!-- Activities for this day -->
          <template v-for="(activity, activityIndex) in day.activities" :key="activity.id">
            <div
              class="relative mb-4 flex gap-2"
              :class="{
                'last-event':
                  dayIndex === days.length - 1 && activityIndex === day.activities.length - 1,
              }"
            >
              <div
                class="text-sage-950 z-10 -ml-10 flex size-8 shrink-0 -translate-x-1/2 items-center justify-center rounded-full border-2 border-white"
                :class="activity.color"
              >
                <Icon :icon="activity.icon" class="size-4 shrink-0" />
              </div>
              <div class="flex flex-col gap-1 pt-1">
                <div class="text-sm font-bold text-gray-700">
                  {{ activity.time }}
                </div>
                <div
                  v-if="typeof activity.message === 'string'"
                  v-sanitize-html="activity.message"
                  class="text-sm leading-relaxed text-gray-700"
                />
                <div v-else class="text-sm leading-relaxed text-gray-700">
                  <TimelineItemSourceDescription
                    v-if="activity.message.dataType === 'Source'"
                    :activity="(activity.message as SourceActivityDescription).activity"
                  />
                  <TimelineItemWatchFileDescription
                    v-else
                    :activity="(activity.message as WatchFileActivityDescription).activity"
                  />
                </div>
                <Button
                  v-if="activity.button"
                  size="sm"
                  icon="fa-eye"
                  variant="tertiary"
                  class="w-fit"
                  @click="activity.button.action"
                >
                  {{ activity.button.text }}
                </Button>
              </div>
            </div>
          </template>

          <!-- Pagination button -->
          <div
            v-if="dayIndex === days.length - 1 && hasNextPage && !isLoading"
            class="relative mb-4 flex gap-2"
          >
            <div class="flex -translate-x-1/2 justify-start">
              <Button
                size="sm"
                variant="tertiary"
                :loading="isLoadingMore"
                class="w-fit"
                @click="emit('loadMore')"
              >
                {{ t('target.watchFiles.activity.history.see_more_actions') }}
              </Button>
            </div>
          </div>
        </template>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { Button, Icon, Tag } from '@owlint/feathers-vue'
import TimelineSkeleton from '@target/components/skeletons/TimelineSkeleton.vue'
import type {
  SourceActivityDescription,
  TimelineProps,
  WatchFileActivityDescription,
} from '@target/types/timeline'
import { nextTick, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import TimelineItemSourceDescription from './TimelineItemSourceDescription.vue'
import TimelineItemWatchFileDescription from './TimelineItemWatchFileDescription.vue'

const { days = [], isLoading } = defineProps<TimelineProps>()

const emit = defineEmits<{
  loadMore: []
}>()

const { t, d } = useI18n()

const timelineRef = ref<HTMLElement>()
const lineRef = ref<HTMLElement>()
const lineHeight = ref(0)

const updateLineHeight = () => {
  if (timelineRef.value) {
    const lastElement = timelineRef.value.querySelector('.last-event') as HTMLElement

    if (lastElement) {
      // Get the container and last element positions
      const containerRect = timelineRef.value.getBoundingClientRect()
      const lastElementRect = lastElement.getBoundingClientRect()

      // Calculate the position of the last element relative to the container
      const lastElementTop = lastElementRect.top - containerRect.top
      // The line stops at 1/3 of the height of the last element (to make sure it is behind)
      lineHeight.value = lastElementTop + lastElementRect.height / 3
    } else {
      // Fallback if no last element - use the full height of the container
      lineHeight.value = timelineRef.value.scrollHeight
    }
  }
}

onMounted(() => {
  if (timelineRef.value) {
    updateLineHeight()
  }
})

watch(
  () => days,
  async () => {
    if (!isLoading) {
      await nextTick()
      updateLineHeight()
    }
  },
  { deep: true },
)

watch(
  () => isLoading,
  async (newIsLoading, oldIsLoading) => {
    if (oldIsLoading && !newIsLoading) {
      await nextTick()
      updateLineHeight()
    }
  },
)
</script>
