<template>
  <div class="form-group">
    <label
      v-if="label"
      :for="id"
      class="mb-1 block text-sm font-bold text-slate-500 dark:text-indigo-500"
      :class="{
        'after:ml-0.5 after:text-red-400 after:content-[\'*\'] dark:after:text-red-700': required,
      }"
    >
      {{ label }}
    </label>
    <textarea
      :id="id"
      v-model="model"
      v-auto-resize="autoResize ? maxHeight || '300px' : false"
      :placeholder="placeholder"
      :required="required"
      :readonly="readonly"
      :disabled="disabled"
      :rows="rows"
      :autofocus="autofocus"
      :tabindex="tabindex"
      class="p-xl block w-full appearance-none overflow-y-auto rounded-xl border border-slate-300 bg-white text-sm font-medium text-slate-700 transition-colors duration-150 ease-in-out focus:outline-none"
      :class="{
        'border-red-500 focus:border-red-500': error,
        'focus:border-primary': !error && !readonly,
        'resize-none': !resizeable,
        [inputClass || '']: true,
      }"
    />
    <p v-if="error" class="mt-1 text-sm text-red-600">
      {{ error }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { type DirectiveBinding, watch } from 'vue'

interface Props {
  id: string
  label?: string
  placeholder?: string
  required?: boolean
  readonly?: boolean
  disabled?: boolean
  resizeable?: boolean
  error?: string
  rows?: number
  maxHeight?: string
  inputClass?: string
  autocorrect?: boolean
  autoResize?: boolean
  autofocus?: boolean
  tabindex?: string
}

// Set default value for autoResize
const {
  autoResize = true,
  label = '',
  placeholder = '',
  error = '',
  rows = undefined,
  maxHeight = undefined,
  inputClass = '',
  tabindex = undefined,
} = defineProps<Props>()

const model = defineModel<string>({ required: true })
// Auto-resize directive
const vAutoResize = {
  mounted: (el: HTMLTextAreaElement, binding: DirectiveBinding<string | false>) => {
    if (binding.value === false) return

    const maxHeight = binding.value || '300px'

    const resize = () => {
      el.style.height = 'auto'
      const newHeight = Math.min(el.scrollHeight, parseInt(maxHeight))
      el.style.height = `${newHeight}px`
    }

    watch(model, resize)
    resize() // Initial resize

    // Store the resize function in a WeakMap for cleanup
    resizeHandlers.set(el, resize)
  },
  unmounted: (el: HTMLTextAreaElement) => {
    const handler = resizeHandlers.get(el)
    if (handler) {
      el.removeEventListener('input', handler)
      resizeHandlers.delete(el)
    }
  },
}

// WeakMap to store resize handlers
const resizeHandlers = new WeakMap<HTMLTextAreaElement, () => void>()
</script>
