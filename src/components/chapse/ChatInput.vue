<template>
  <div class="relative">
    <!-- Context Badges Row (always visible to allow adding companies) -->
    <div class="flex items-center gap-2 px-4 py-2 border-t border-sage-700 flex-wrap">
      <span class="text-xs text-sage-400">
        {{ $t('chapse.context', 'Context:') }}
      </span>
      <ContextBadge
        v-for="company in companyContext"
        :key="company.id"
        :context="{ type: 'company', id: company.id, name: company.name }"
        dismissible
        @dismiss="$emit('remove-context', company.id)"
      />
      <CompanyContextSelector
        v-if="canAddMoreCompanies"
        :context-company-ids="companyContext.map((c: { id: any }) => c.id)"
        :context-count="companyContext.length"
        :max-companies="3"
        :show-limit="true"
        position="top-left"
        @select="$emit('add-context', $event)"
      />
      <span v-else class="text-xs text-sage-500">
        {{ $t('chapse.maxCompanies', '(max 3)') }}
      </span>
    </div>

    <!-- Input Area -->
    <div class="p-4">
      <div class="relative">
        <textarea
          ref="textareaRef"
          v-model="message"
          :placeholder="placeholder"
          :disabled="disabled"
          class="w-full min-h-[80px] max-h-[200px] p-4 pr-14 bg-sage-900 rounded-xl text-sm text-sage-100 placeholder-sage-500 resize-none focus:outline-none focus:ring-2 focus:ring-primary/50 disabled:opacity-50"
          @keydown.enter.ctrl.prevent="handleSend"
          @keydown.enter.meta.prevent="handleSend"
          @input="autoResize"
        ></textarea>

        <!-- Send Button -->
        <Button
          variant="primary"
          icon="fa fa-paper-plane"
          size="sm"
          class="absolute right-3 bottom-3"
          :disabled="!canSend && !loading"
          :loading="loading"
          @click="handleSend"
        />
      </div>

      <!-- Helper Text -->
      <p class="mt-2 text-xs text-sage-500">
        <kbd class="px-1.5 py-0.5 bg-sage-800 rounded text-sage-400">Ctrl</kbd>
        +
        <kbd class="px-1.5 py-0.5 bg-sage-800 rounded text-sage-400">Enter</kbd>
        {{ $t('chapse.toSend', 'to send') }}
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import { Button } from '@owlint/feathers-vue'
import ContextBadge from './ContextBadge.vue'
import CompanyContextSelector from './CompanyContextSelector.vue'
import type { CompanyContext } from '@/stores/chapse'

interface Props {
  /** Initial message value */
  modelValue?: string
  /** Placeholder text */
  placeholder?: string
  /** Loading state (sending message) */
  loading?: boolean
  /** Disabled state */
  disabled?: boolean
  /** Company context array */
  companyContext?: CompanyContext[]
  /** Whether more companies can be added */
  canAddMoreCompanies?: boolean
}

const {
  canAddMoreCompanies,
  companyContext,
  disabled,
  loading,
  modelValue = '',
  placeholder = '',
} = defineProps<Props>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
  send: [message: string]
  'add-context': [company: CompanyContext]
  'remove-context': [companyId: number]
}>()

// Refs
const textareaRef = ref<HTMLTextAreaElement | null>(null)

// Local message state (two-way binding)
const message = computed({
  get: () => modelValue,
  set: (value: string) => emit('update:modelValue', value),
})

// Computed
const canSend = computed(() => {
  return message.value.trim().length > 0 && !loading && !disabled
})

// Methods
const handleSend = () => {
  if (!canSend.value) return
  if (loading) return

  emit('send', message.value.trim())
  message.value = ''

  // Reset textarea height
  nextTick(() => {
    if (textareaRef.value) {
      textareaRef.value.style.height = 'auto'
    }
  })
}

const autoResize = () => {
  if (!textareaRef.value) return

  // Reset height to auto to get the correct scrollHeight
  textareaRef.value.style.height = 'auto'
  // Set to scrollHeight (capped by max-height in CSS)
  textareaRef.value.style.height = `${Math.min(textareaRef.value.scrollHeight, 200)}px`
}

// Focus the textarea
const focus = () => {
  textareaRef.value?.focus()
}

// Expose methods
defineExpose({
  focus,
})

// Auto-resize on initial value
watch(
  () => modelValue,
  () => {
    nextTick(() => {
      autoResize()
    })
  },
  { immediate: true },
)
</script>
