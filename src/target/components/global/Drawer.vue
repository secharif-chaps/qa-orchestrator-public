<template>
  <Teleport defer :to="to">
    <Transition
      enter-active-class="transition-opacity duration-300 ease-in-out"
      leave-active-class="transition-opacity duration-300 ease-in-out"
      enter-from-class="opacity-0 backdrop-blur-none"
      leave-to-class="opacity-0 backdrop-blur-none"
    >
      <div
        v-if="isOpen"
        class="bg-sage-950/40 absolute inset-0 z-[19] backdrop-blur-xs"
        @click="handleOverlayClick"
      ></div>
    </Transition>

    <Transition
      :enter-active-class="drawerTransitionClasses.enterActive"
      :leave-active-class="drawerTransitionClasses.leaveActive"
      :enter-from-class="drawerTransitionClasses.enterFrom"
      :leave-to-class="drawerTransitionClasses.leaveTo"
    >
      <div
        v-if="isOpen"
        class="absolute inset-y-0 z-[19] flex max-h-screen w-3/4 flex-col rounded bg-white shadow-xl sm:w-1/3 sm:min-w-[30rem]"
        :class="[
          { 'top-0 left-0 h-full rounded-bl-none': position === 'left' },
          { 'top-0 right-0 h-full rounded-br-none': position === 'right' },
        ]"
      >
        <div v-if="showHeader" class="p-6">
          <slot name="header">
            <div class="flex justify-between gap-3">
              <div class="flex items-center gap-2">
                <Icon v-if="icon" class="text-lg" :icon="icon" />
                <h3 class="text-base font-medium">
                  {{ title }}
                </h3>
              </div>

              <Button
                v-if="showCloseButton"
                size="sm"
                icon="fa-xmark"
                variant="tertiary"
                @click="close"
              />
            </div>
          </slot>
          <slot name="subheader" />
        </div>

        <div class="relative flex-1">
          <slot />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { Button, Icon } from '@owlint/feathers-vue'
import { computed, onMounted, onUnmounted, watch, type RendererElement } from 'vue'

type DrawerPosition = 'left' | 'right'

interface Props {
  position?: DrawerPosition
  title?: string
  showHeader?: boolean
  icon?: string
  showCloseButton?: boolean
  closeOnOverlay?: boolean
  closeOnEscape?: boolean
  to?: string | RendererElement | null
}

interface Emits {
  (e: 'open' | 'close'): void
}

const {
  position = 'right',
  showHeader = true,
  showCloseButton = true,
  closeOnOverlay = true,
  closeOnEscape = true,
  to = undefined,
  title = undefined,
  icon = undefined,
} = defineProps<Props>()
const emit = defineEmits<Emits>()

const isOpen = defineModel<boolean>()

const drawerTransitionClasses = computed(() => {
  const baseClasses = {
    enterActive: 'transition-transform duration-300 ease-in-out',
    leaveActive: 'transition-transform duration-300 ease-in-out',
  }

  const transformClasses = {
    left: '-translate-x-full',
    right: 'translate-x-full',
  }

  return {
    ...baseClasses,
    enterFrom: transformClasses[position],
    leaveTo: transformClasses[position],
  }
})

const close = () => {
  isOpen.value = false
  emit('close')
}

const open = () => {
  isOpen.value = true
  emit('open')
}

const handleOverlayClick = () => {
  if (closeOnOverlay) {
    close()
  }
}

const handleEscapeKey = (event: KeyboardEvent) => {
  if (event.key === 'Escape' && closeOnEscape && isOpen.value) {
    close()
  }
}

watch(isOpen, (newValue) => {
  if (newValue) {
    document.body.style.overflow = 'hidden'
  } else {
    document.body.style.overflow = ''
  }
})

onMounted(() => {
  document.addEventListener('keydown', handleEscapeKey)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleEscapeKey)
  document.body.style.overflow = ''
})

defineExpose({
  open,
  close,
})
</script>
