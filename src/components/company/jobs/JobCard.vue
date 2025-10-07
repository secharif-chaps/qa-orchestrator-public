<template>
  <div
    class="p-4 bg-primary-light border border-primary-stroke rounded-lg transition-all duration-300"
  >
    <div class="flex justify-between items-start">
      <div>
        <h3 class="text-lg font-semibold text-primary-light-content">
          {{ job.title }}
          <Source :sourced-value="job.title" />
        </h3>
        <div class="mt-2 space-y-2">
          <div v-if="job.location" class="flex items-center text-sm text-primary-light-content">
            <i class="fa fa-map-marker w-4"></i>
            {{ job.location }}
          </div>
          <div v-if="job.department" class="flex items-center text-sm text-primary-light-content">
            <i class="fa fa-building w-4"></i>
            {{ job.department }}
          </div>
          <div v-if="job.posted_date" class="flex items-center text-sm text-primary-light-content">
            <i class="fa fa-calendar w-4"></i>
            Posted: {{ job.posted_date }}
          </div>
        </div>
      </div>
    </div>

    <div class="mt-4">
      <h4 class="font-medium mb-2">Description</h4>
      <p class="text-sm text-primary-light-content">
        {{ job.description }}
      </p>
    </div>

    <div class="mt-4">
      <h4 class="font-medium mb-2">Requirements</h4>
      <p class="text-sm text-primary-light-content">
        {{ job.requirements }}
      </p>
    </div>
  </div>
</template>

<script lang="ts" setup>
import type { SourcedValue } from '@/types/company'
import Source from '../Source.vue'

defineProps<{
  job: {
    title: SourcedValue<string>
    location: SourcedValue<string>
    department: SourcedValue<string>
    posted_date: SourcedValue<string>
    description: SourcedValue<string>
    requirements: SourcedValue<string>
  }
}>()
</script>
