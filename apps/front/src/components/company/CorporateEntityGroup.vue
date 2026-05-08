<template>
  <div v-if="entities?.length" class="rounded-sm bg-white p-6">
    <h4 class="text-neutral-black-font mb-4 flex items-center gap-2 font-semibold">
      <Icon :icon />
      {{ title }}
    </h4>
    <ul class="space-y-2">
      <li
        v-for="entity in entities"
        :key="entity.name"
        class="text-neutral-black-font flex items-start gap-2"
      >
        <Icon icon="fa-circle" class="text-neutral-black-font mt-1.5 text-[6px]" />
        <div class="min-w-0 flex-1">
          <span class="text-sm font-medium">{{ entity.name }}</span>
          <span v-if="entity.country" class="text-neutral-black-font ml-1 text-xs">{{
            $t('common.inParentheses', { value: entity.country })
          }}</span>
          <Source v-if="entity.source" :source="entity.source" />
        </div>
      </li>
    </ul>
  </div>
</template>

<script lang="ts" setup>
import Source from '@/components/company/Source.vue'
import type { CorporateEntity } from '@/types/company'
import { Icon } from '@owlint/feathers-vue'

defineProps<{
  icon: string
  title: string
  entities: CorporateEntity[] | undefined
}>()
</script>
