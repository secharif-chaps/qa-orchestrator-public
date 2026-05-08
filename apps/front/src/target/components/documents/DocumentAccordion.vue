<template>
  <div>
    <div v-if="status === 'pending'" class="w-full animate-pulse overflow-hidden rounded">
      <div class="flex h-8 items-center bg-gray-200 px-2 text-xs">
        {{ pendingTitleResolved }}
      </div>
      <div class="h-32 bg-gray-100" />
    </div>
    <InformationMessage
      v-else-if="status === 'failed'"
      :title="errorTitleResolved"
      color="error"
      width="full"
    />
    <Accordion.Root v-else default-value="content" collapsible>
      <Accordion.Item
        v-slot="{ open }"
        class="bg-sage-50 overflow-hidden rounded border border-gray-300 px-3.5 py-4.5"
        value="content"
      >
        <Accordion.Header class="flex overflow-hidden">
          <Accordion.Trigger
            class="group flex flex-1 cursor-default items-center justify-between text-sm leading-none outline-none"
          >
            <div class="flex items-center gap-1.5">
              <span class="font-bold">
                {{ title }}
              </span>
            </div>
            <div class="flex gap-2">
              <Icon
                :icon="open ? 'fa-chevron-up' : 'fa-chevron-down'"
                aria-label="Expand/Collapse"
                class="text-2xl"
              />
            </div>
          </Accordion.Trigger>
        </Accordion.Header>
        <Accordion.Content
          class="data-[state=open]:animate-slideDown data-[state=closed]:animate-slideUp"
        >
          <div class="space-y-1 pt-2">
            <div v-if="subtitle || $slots.subtitle" class="text-xs text-gray-800">
              <slot name="subtitle">
                {{ subtitle }}
              </slot>
            </div>
            <ul v-if="content" class="text-gray-950">
              <li>{{ content }}</li>
            </ul>
          </div>
        </Accordion.Content>
      </Accordion.Item>
    </Accordion.Root>
  </div>
</template>

<script lang="ts" setup>
import { Icon } from '@owlint/feathers-vue'
import type { AiValidationStatus, SummaryStatusType } from '@target/types/document'
import { Accordion } from 'reka-ui/namespaced'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import InformationMessage from '../global/InformationMessage.vue'

const { t } = useI18n()

interface Props {
  title: string
  subtitle?: string
  content?: string
  status?: SummaryStatusType | AiValidationStatus
  /** Pre-translated text shown while the content is loading. */
  errorTitle?: string
  /** Pre-translated text shown when loading is in progress. */
  pendingTitle?: string
}

const props = withDefaults(defineProps<Props>(), {
  subtitle: undefined,
  content: undefined,
  status: 'completed',
  errorTitle: undefined,
  pendingTitle: undefined,
})

const errorTitleResolved = computed(() => props.errorTitle ?? t('common.errors.generic'))
const pendingTitleResolved = computed(() => props.pendingTitle ?? t('common.action.loading'))
</script>
