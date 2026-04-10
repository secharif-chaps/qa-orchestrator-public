<template>
  <div ref="triggerRef" class="relative inline-block">
    <!-- Trigger slot -->
    <div @click="toggle">
      <slot name="trigger" :is-open="isOpen" />
    </div>

    <!-- Teleport dropdown to body to escape overflow constraints -->
    <Teleport to="body">
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
          ref="dropdownRef"
          class="border-primary-lighter-stroke shadow-3 fixed z-50 overflow-hidden rounded-sm border bg-white"
          :class="widthClass"
          :style="dropdownStyle"
        >
          <div class="p-2" @click="props.closeOnSelect && close()">
            <slot name="content" :close="close" />
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'

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
const triggerRef = ref<HTMLElement | null>(null)
const dropdownRef = ref<HTMLElement | null>(null)
const dropdownPosition = ref({ top: 0, left: 0 })

// Calculate dropdown position based on trigger element
const updatePosition = () => {
  if (!triggerRef.value || !dropdownRef.value) return

  const rect = triggerRef.value.getBoundingClientRect()
  const dropdownWidth = getDropdownWidth()
  const dropdownHeight = dropdownRef.value.offsetHeight

  // Horizontal positioning
  let left = props.align === 'right' ? rect.right - dropdownWidth : rect.left

  const viewportWidth = window.innerWidth
  if (left + dropdownWidth > viewportWidth - 8) {
    left = viewportWidth - dropdownWidth - 8
  }
  if (left < 8) {
    left = 8
  }

  // Vertical positioning with flip
  let top = rect.bottom + 8
  const viewportHeight = window.innerHeight

  if (top + dropdownHeight > viewportHeight - 8) {
    top = rect.top - dropdownHeight - 8

    if (top < 8) {
      top = 8
      dropdownRef.value.style.maxHeight = `${viewportHeight - 16}px`
    } else {
      dropdownRef.value.style.maxHeight = ''
    }
  } else {
    dropdownRef.value.style.maxHeight = '' // reset if enough space
  }

  dropdownPosition.value = {
    top,
    left,
  }
}

// Get dropdown width in pixels based on width prop
const getDropdownWidth = (): number => {
  const widths: Record<string, number> = {
    auto: 192, // min-w-[12rem]
    sm: 192, // w-48
    md: 224, // w-56
    lg: 256, // w-64
    xl: 320, // w-80
    full: triggerRef.value?.offsetWidth ?? 224,
  }
  return widths[props.width]
}

// Computed style for dropdown position
const dropdownStyle = computed(() => ({
  top: `${dropdownPosition.value.top}px`,
  left: `${dropdownPosition.value.left}px`,
}))

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

// Update position when dropdown opens
watch(isOpen, async (newValue) => {
  if (newValue) {
    await nextTick()
    updatePosition()
  }
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
  if (!props.backdrop && isOpen.value) {
    const target = event.target as Node
    const clickedTrigger = triggerRef.value?.contains(target)
    const clickedDropdown = dropdownRef.value?.contains(target)
    if (!clickedTrigger && !clickedDropdown) {
      close()
    }
  }
}

// Keyboard handler
const handleKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape' && isOpen.value) {
    close()
  }
}

// Reposition on scroll/resize
const handleScrollResize = () => {
  if (isOpen.value) {
    updatePosition()
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  document.addEventListener('keydown', handleKeydown)
  window.addEventListener('scroll', handleScrollResize, true)
  window.addEventListener('resize', handleScrollResize)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  document.removeEventListener('keydown', handleKeydown)
  window.removeEventListener('scroll', handleScrollResize, true)
  window.removeEventListener('resize', handleScrollResize)
})

// Expose methods for parent components
defineExpose({
  close,
  open,
  toggle,
})
</script>
