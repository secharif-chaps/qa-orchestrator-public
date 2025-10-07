<template>
  <!-- Source from sourced value -->
  <div
    class="relative inline-block group"
    v-if="
      sourcedValue && getSourcedSource(sourcedValue) && getSourcedSource(sourcedValue) !== 'N/A'
    "
  >
    <!-- LLM Source (non-clickable) -->
    <div
      v-if="isLLMSource(getSourcedSource(sourcedValue))"
      class="text-primary-light-content/50 cursor-help"
    >
      <i class="fa fa-info-circle text-sm"></i>
    </div>

    <!-- Regular URL Source (clickable) -->
    <a
      v-else
      :href="getSourcedSource(sourcedValue) || ''"
      target="_blank"
      class="text-primary-light-content/50 hover:text-primary-light-content transition-colors duration-200"
    >
      <i class="fa fa-info-circle text-sm"></i>
    </a>

    <div
      class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10"
      :class="isLLMSource(getSourcedSource(sourcedValue)) ? 'w-64' : 'whitespace-nowrap'"
    >
      <div
        class="bg-gray-800 text-white text-xs rounded px-2 py-1 max-w-xs"
        :class="isLLMSource(getSourcedSource(sourcedValue)) ? 'break-words' : 'break-all overflow-hidden text-ellipsis'"
      >
        <template v-if="isLLMSource(getSourcedSource(sourcedValue))">
          Cette information est issue de la base de connaissances du modèle de langage (LLM).
          Un LLM s'appuie sur un vaste ensemble de textes analysés lors de son entraînement, et ne consulte pas de sources externes en temps réel.
        </template>
        <template v-else>
          {{ getSourcedSource(sourcedValue) }}
        </template>
      </div>
      <div
        class="w-2 h-2 bg-gray-800 transform rotate-45 absolute -bottom-1 left-1/2 -translate-x-1/2"
      ></div>
    </div>
  </div>

  <!-- Direct source prop -->
  <div class="relative inline-block group" v-else-if="source">
    <!-- LLM Source (non-clickable) -->
    <div
      v-if="isLLMSource(source)"
      class="text-primary-light-content/50 cursor-help"
    >
      <i class="fa fa-info-circle text-xs"></i>
    </div>

    <!-- Regular URL Source (clickable) -->
    <a
      v-else
      :href="source"
      target="_blank"
      class="text-primary-light-content/50 hover:text-primary-light-content transition-colors duration-200"
    >
      <i class="fa fa-info-circle text-xs"></i>
    </a>

    <div
      class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10"
      :class="isLLMSource(source) ? 'w-64' : 'whitespace-nowrap'"
    >
      <div
        class="bg-gray-800 text-white text-xs rounded px-2 py-1 max-w-xs"
        :class="isLLMSource(source) ? 'break-words' : 'break-all overflow-hidden text-ellipsis'"
      >
        <template v-if="isLLMSource(source)">
          Cette information est issue de la base de connaissances du modèle de langage (LLM).
          Un LLM s'appuie sur un vaste ensemble de textes analysés lors de son entraînement, et ne consulte pas de sources externes en temps réel.
        </template>
        <template v-else>
          {{ source }}
        </template>
      </div>
      <div
        class="w-2 h-2 bg-gray-800 transform rotate-45 absolute -bottom-1 left-1/2 -translate-x-1/2"
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
  return cleanedSource.includes('anthropic.com') ||
         cleanedSource.includes('mistral.ai') ||
         cleanedSource.includes('claude.ai')
}
</script>
