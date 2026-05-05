<template>
  <div class="relative">
    <!-- Context Badges Row (always visible to allow adding companies) -->
    <div class="border-sage-300 dark:border-sage-700 flex flex-wrap items-center gap-2 px-4 py-2">
      <span class="text-sage-900 dark:text-sage-300 text-xs">
        {{ $t('common.sidebar.chapse.context') }}
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
      <span v-else class="text-sage-500 text-xs">
        {{ $t('screen.chapse.maxCompanies') }}
      </span>
    </div>

    <!-- Input Area -->
    <div class="p-4">
      <div class="relative">
        <Textarea
          id="chat-input"
          ref="textareaComponentRef"
          v-model="message"
          :placeholder="placeholder"
          :disabled="disabled"
          :rows="3"
          class="max-h-50 min-h-20 w-full pr-14"
          @keydown.enter.ctrl.prevent="handleSend"
          @keydown.enter.meta.prevent="handleSend"
        />

        <!-- Send Button -->
        <Button
          variant="accent"
          icon="fa-send"
          size="sm"
          class="absolute right-3 bottom-4"
          :disabled="!canSend && !loading"
          :loading="loading"
          @click="handleSend"
        />
      </div>

      <!-- Helper Text -->
      <p class="text-sage-900 dark:text-sage-300 mt-2 text-xs">
        <kbd
          class="bg-sage-300 dark:bg-sage-800 text-sage-950 dark:text-sage-200 rounded px-1.5 py-0.5"
          >{{ $t('common.keyboard.ctrl') }}</kbd
        >
        +
        <kbd
          class="bg-sage-300 dark:bg-sage-800 text-sage-950 dark:text-sage-200 rounded px-1.5 py-0.5"
          >{{ $t('common.keyboard.enter') }}</kbd
        >
        {{ $t('common.sidebar.chapse.toSend') }}
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { CompanyContext } from '@/stores/chapse'
import { Button, Textarea } from '@owlint/feathers-vue'
import { computed, ref, watch } from 'vue'
import CompanyContextSelector from './CompanyContextSelector.vue'
import ContextBadge from './ContextBadge.vue'

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
  companyContext = [],
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

// Local message state (two-way binding)
const message = computed({
  get: () => modelValue,
  set: (value: string) => emit('update:modelValue', value),
})

// Computed
const canSend = computed(() => {
  return message.value.trim().length > 0 && !loading && !disabled
})

const textareaComponentRef = ref<InstanceType<typeof Textarea> | null>(null)

const autoResize = () => {
  const el = textareaComponentRef.value?.textareaRef
  if (!el) return
  el.style.height = 'auto'
  el.style.height = `${Math.min(el.scrollHeight, 200)}px`
}

watch(message, () => {
  autoResize()
})

const handleSend = () => {
  if (!canSend.value) return
  if (loading) return

  emit('send', message.value.trim())
  message.value = ''
}

const focus = () => {
  textareaComponentRef.value?.focus()
}

defineExpose({
  focus,
})
</script>
