<template>
  <div class="gap-4xl text-neutral-black-font mt-xl relative flex items-start">
    <!-- Date indicator -->
    <div class="pt-lg w-20 text-right">
      <Tag v-if="isToday" size="sm" intent="accent" variant="primary">
        {{ date }}
      </Tag>
      <Tag v-else size="sm" color="sage">
        {{ date }}
      </Tag>
    </div>

    <!-- Timeline line -->
    <div class="bg-primary-lighter-stroke absolute top-0 left-30 h-[calc(100%+24px)] w-px" />

    <!-- Timeline dot -->
    <div class="relative">
      <Bullet v-if="isToday" intent="accent" class="absolute top-6 -left-2" />
      <Bullet v-else color="sage" class="absolute top-6 -left-2" />
    </div>

    <!-- Event content -->
    <div class="gap-lg flex flex-1 flex-col">
      <slot />
    </div>
  </div>
</template>

<script lang="ts" setup>
import { Bullet, Tag } from '@owlint/feathers-vue'

interface Props {
  date: string
  isToday?: boolean
}

defineProps<Props>()
</script>
