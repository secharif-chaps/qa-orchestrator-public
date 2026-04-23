<template>
  <div
    class="bg-primary-lighter border-primary-light-stroke p-xs relative overflow-hidden rounded-lg border"
    role="alert"
    :aria-labelledby="title ? 'alert-title' : undefined"
  >
    <div class="flex items-center gap-8">
      <!-- Prepend slot (left side, e.g. image) -->
      <slot name="image" />

      <!-- Content Section -->
      <div class="min-w-0 flex-1">
        <!-- Title row -->
        <div class="flex items-center justify-between">
          <h3
            id="alert-title"
            class="gap-md flex items-center text-base font-semibold text-black dark:text-white"
            :class="{ 'mb-3': $slots.default }"
          >
            <i v-if="icon" :class="icon"></i>
            <span>{{ title }}</span>
          </h3>
          <!-- Aside slot (right-aligned) -->
          <slot name="aside" />
        </div>

        <!-- Default slot for content -->
        <div v-if="$slots.default" class="dark:text-sage-300 text-sm leading-relaxed">
          <slot />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
interface Props {
  /** Title text for the alert */
  title?: string
  /** Icon class for the title */
  icon?: string
}

defineProps<Props>()
</script>
