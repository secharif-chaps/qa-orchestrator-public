<template>
  <Accordion.Root type="multiple" :default-value :collapsible="true">
    <template v-for="item in items" :key="item.value">
      <Accordion.Item
        v-slot="{ open }"
        class="mb-3 border-b border-gray-200 p-2 pb-4"
        :value="item.value"
      >
        <Accordion.Header class="flex">
          <Accordion.Trigger
            class="group flex flex-1 cursor-default items-center justify-between text-sm leading-none outline-none"
          >
            <div class="flex items-center gap-1.5">
              <Icon v-if="item.icon" class="text-lg" :icon="item.icon" />
              <span class="font-bold">{{ item.title }}</span>
            </div>
            <div class="flex gap-2">
              <Badge v-if="item.count" variant="secondary" :number="String(item.count)" />
              <Button
                :icon="open ? 'fa-chevron-up' : 'fa-chevron-down'"
                variant="tertiary"
                aria-label="Expand/Collapse"
              />
            </div>
          </Accordion.Trigger>
        </Accordion.Header>
        <Accordion.Content
          class="data-[state=open]:animate-slideDown data-[state=closed]:animate-slideUp"
        >
          <div class="pt-3">
            <slot :name="item.value"> </slot>
          </div>
        </Accordion.Content>
      </Accordion.Item>
    </template>
  </Accordion.Root>
</template>

<script setup lang="ts">
import { Badge, Button, Icon } from '@owlint/feathers-vue'
import type { DocumentFilter } from '@target/types/filter'
import { Accordion } from 'reka-ui/namespaced'

interface Props {
  items: DocumentFilter[]
  defaultValue?: string | string[]
}

defineProps<Props>()
</script>
