<template>
  <div ref="dropdownRef" class="relative inline-block">
    <!-- Trigger slot -->
    <div @click="toggle">
      <slot name="trigger" :is-open="isOpen" />
    </div>

    <!-- Backdrop for click-outside handling -->
    <div v-if="isOpen && backdrop" class="fixed inset-0 z-40" @click="close"></div>

    <!-- Dropdown content -->
    <Transition
      enter-active-class="transition duration-100 ease-out"
      enter-from-class="transform scale-95 opacity-0"
      enter-to-class="transform scale-100 opacity-100"
      leave-active-class="transition duration-75 ease-in"
      leave-from-class="transform scale-100 opacity-100"
      leave-to-class="transform scale-95 opacity-0"
    >
      <div
        v-if="isOpen"
        class="absolute z-50 mt-2 rounded-lg border border-primary-stroke bg-base-100 shadow-shadow-3 overflow-hidden"
        :class="[widthClass, alignmentClass]"
      >
        <div class="p-2">
          <slot name="content" :close="close" />
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'

interface Props {
  align?: 'left' | 'right'
  width?: 'auto' | 'sm' | 'md' | 'lg' | 'xl' | 'full'
  closeOnSelect?: boolean
  backdrop?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  align: 'left',
  width: 'md',
  closeOnSelect: true,
  backdrop: true,
})

const emit = defineEmits<{
  open: []
  close: []
}>()

// State
const isOpen = ref(false)
const dropdownRef = ref<HTMLElement | null>(null)

// Computed classes
const alignmentClass = computed(() => {
  return props.align === 'right' ? 'right-0' : 'left-0'
})

const widthClass = computed(() => {
  const widths = {
    auto: 'w-auto min-w-[12rem]',
    sm: 'w-48',
    md: 'w-56',
    lg: 'w-64',
    xl: 'w-80',
    full: 'w-full',
  }
  return widths[props.width]
})

// Methods
const toggle = () => {
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    emit('open')
  } else {
    emit('close')
  }
}

const close = () => {
  isOpen.value = false
  emit('close')
}

const open = () => {
  isOpen.value = true
  emit('open')
}

// Click outside handler (fallback when backdrop is disabled)
const handleClickOutside = (event: MouseEvent) => {
  if (!props.backdrop && dropdownRef.value && !dropdownRef.value.contains(event.target as Node)) {
    close()
  }
}

// Keyboard handler
const handleKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape' && isOpen.value) {
    close()
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  document.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  document.removeEventListener('keydown', handleKeydown)
})

// Expose methods for parent components
defineExpose({
  close,
  open,
  toggle,
})
</script>
