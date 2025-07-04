<template>
  <div class="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg transition-all duration-300">
    <div class="flex justify-between items-start">
      <div>
        <h3 class="text-lg font-semibold text-primary">
          {{ job.title }}
          <Source :sourced-value="job.title" />
        </h3>
        <div class="mt-2 space-y-2">
          <div class="flex items-center text-sm text-slate-600 dark:text-slate-400">
            <i class="fa fa-map-marker w-4"></i>
            {{ job.location }}
          </div>
          <div class="flex items-center text-sm text-slate-600 dark:text-slate-400">
            <i class="fa fa-building w-4"></i>
            {{ job.department }}
          </div>
          <div class="flex items-center text-sm text-slate-600 dark:text-slate-400">
            <i class="fa fa-calendar w-4"></i>
            Posted: {{ job.posted_date }}
          </div>
        </div>
      </div>
    </div>

    <div class="mt-4">
      <h4 class="font-medium mb-2">Description</h4>
      <p class="text-sm text-slate-600 dark:text-slate-400">
        {{ job.description }}
      </p>
    </div>

    <div class="mt-4">
      <h4 class="font-medium mb-2">Requirements</h4>
      <p class="text-sm text-slate-600 dark:text-slate-400">
        {{ job.requirements }}
      </p>
    </div>
  </div>
</template>

<script lang="ts" setup>
defineProps<{
  job: {
    title: any
    location: any
    department: any
    posted_date: any
    description: any
    requirements: any
  }
}>()
</script>
