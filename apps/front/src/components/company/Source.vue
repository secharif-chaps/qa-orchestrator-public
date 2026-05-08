<template>
  <!-- Source from sourced value -->
  <Tooltip
    v-if="
      sourcedValue && getSourcedSource(sourcedValue) && getSourcedSource(sourcedValue) !== 'N/A'
    "
    side="top"
    arrow
  >
    <template #default>
      <Button
        v-if="isLLMSource(getSourcedSource(sourcedValue))"
        variant="tertiary"
        size="xs"
        icon="fas fa-quote-left"
      />
      <Button
        v-else
        variant="tertiary"
        size="xs"
        icon="fas fa-quote-left"
        as="a"
        :href="getSourcedSource(sourcedValue) || ''"
        target="_blank"
      />
    </template>
    <template #tooltip>
      <template v-if="isLLMSource(getSourcedSource(sourcedValue))">
        {{ t('screen.company.source.llmDisclaimer') }}
      </template>
      <template v-else>
        {{ getSourcedSource(sourcedValue) }}
      </template>
    </template>
  </Tooltip>

  <!-- Direct source prop -->
  <Tooltip v-else-if="source" side="top" arrow>
    <template #default>
      <Button v-if="isLLMSource(source)" variant="tertiary" size="xs" icon="fas fa-quote-left" />
      <Button
        v-else
        variant="tertiary"
        size="xs"
        icon="fas fa-quote-left"
        as="a"
        :href="source"
        target="_blank"
      />
    </template>
    <template #tooltip>
      <template v-if="isLLMSource(source)">
        {{ t('screen.company.source.llmDisclaimer') }}
      </template>
      <template v-else>
        {{ source }}
      </template>
    </template>
  </Tooltip>
</template>

<script lang="ts" setup>
import { getSourcedSource } from '@/components/helpers/sourcedValues'
import type { SourcedValue } from '@/types/company'
import { Button, Tooltip } from '@owlint/feathers-vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Props {
  sourcedValue?: SourcedValue<string>
  source?: string
}

defineProps<Props>()

// Check if the source is an LLM (Mistral or Claude)
const isLLMSource = (source: string | undefined): boolean => {
  if (!source) return false
  const normalizedSource = source.toLowerCase().trim()

  if (normalizedSource === 'mistral' || normalizedSource === 'claude') {
    return true
  }

  const cleanedSource = normalizedSource
    .replace(/^https?:\/\//, '')
    .replace(/^www\./, '')
    .split('/')[0]

  return (
    cleanedSource.includes('anthropic.com') ||
    cleanedSource.includes('mistral.ai') ||
    cleanedSource.includes('claude.ai')
  )
}
</script>
