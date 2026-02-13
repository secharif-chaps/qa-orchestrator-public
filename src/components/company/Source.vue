<template>
  <!-- Source from sourced value -->
  <div
    class="group relative inline-block"
    v-if="
      sourcedValue && getSourcedSource(sourcedValue) && getSourcedSource(sourcedValue) !== 'N/A'
    "
  >
    <!-- LLM Source (non-clickable) -->
    <div v-if="isLLMSource(getSourcedSource(sourcedValue))" class="text-secondary/50 cursor-help">
      <i class="fa fa-info-circle text-sm"></i>
    </div>

    <!-- Regular URL Source (clickable) -->
    <a
      v-else
      :href="getSourcedSource(sourcedValue) || ''"
      target="_blank"
      class="text-secondary/50 hover:text-secondary transition-colors duration-200"
    >
      <i class="fa fa-info-circle text-sm"></i>
    </a>

    <div
      class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 -translate-x-1/2 transform opacity-0 transition-opacity duration-200 group-hover:opacity-100"
      :class="isLLMSource(getSourcedSource(sourcedValue)) ? 'w-64' : 'whitespace-nowrap'"
    >
      <div
        class="max-w-xs rounded bg-gray-800 px-2 py-1 text-xs text-white"
        :class="
          isLLMSource(getSourcedSource(sourcedValue))
            ? 'break-words'
            : 'overflow-hidden break-all text-ellipsis'
        "
      >
        <template v-if="isLLMSource(getSourcedSource(sourcedValue))">
          Cette information est issue de la base de connaissances du modèle de langage (LLM). Un LLM
          s'appuie sur un vaste ensemble de textes analysés lors de son entraînement, et ne consulte
          pas de sources externes en temps réel.
        </template>
        <template v-else>
          {{ getSourcedSource(sourcedValue) }}
        </template>
      </div>
      <div
        class="absolute -bottom-1 left-1/2 h-2 w-2 -translate-x-1/2 rotate-45 transform bg-gray-800"
      ></div>
    </div>
  </div>

  <!-- Direct source prop -->
  <div class="group relative inline-block" v-else-if="source">
    <!-- LLM Source (non-clickable) -->
    <div v-if="isLLMSource(source)" class="text-secondary/50 cursor-help">
      <i class="fa fa-info-circle text-xs"></i>
    </div>

    <!-- Regular URL Source (clickable) -->
    <a
      v-else
      :href="source"
      target="_blank"
      class="text-secondary/50 hover:text-secondary transition-colors duration-200"
    >
      <i class="fa fa-info-circle text-xs"></i>
    </a>

    <div
      class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 -translate-x-1/2 transform opacity-0 transition-opacity duration-200 group-hover:opacity-100"
      :class="isLLMSource(source) ? 'w-64' : 'whitespace-nowrap'"
    >
      <div
        class="max-w-xs rounded bg-gray-800 px-2 py-1 text-xs text-white"
        :class="isLLMSource(source) ? 'break-words' : 'overflow-hidden break-all text-ellipsis'"
      >
        <template v-if="isLLMSource(source)">
          Cette information est issue de la base de connaissances du modèle de langage (LLM). Un LLM
          s'appuie sur un vaste ensemble de textes analysés lors de son entraînement, et ne consulte
          pas de sources externes en temps réel.
        </template>
        <template v-else>
          {{ source }}
        </template>
      </div>
      <div
        class="absolute -bottom-1 left-1/2 h-2 w-2 -translate-x-1/2 rotate-45 transform bg-gray-800"
      ></div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { getSourcedSource } from '@/components/helpers/sourcedValues'
import type { SourcedValue } from '@/types/company'

interface Props {
  sourcedValue?: SourcedValue<string>
  source?: string
}

defineProps<Props>()

// Check if the source is an LLM (Mistral or Claude)
function isLLMSource(source: string | undefined): boolean {
  if (!source) return false
  const normalizedSource = source.toLowerCase().trim()

  // Direct name matches
  if (normalizedSource === 'mistral' || normalizedSource === 'claude') {
    return true
  }

  // URL-based detection
  // Remove protocol and www, then check domain
  const cleanedSource = normalizedSource
    .replace(/^https?:\/\//, '')
    .replace(/^www\./, '')
    .split('/')[0] // Get just the domain

  // Check for Anthropic (Claude) or Mistral domains
  return (
    cleanedSource.includes('anthropic.com') ||
    cleanedSource.includes('mistral.ai') ||
    cleanedSource.includes('claude.ai')
  )
}
</script>
