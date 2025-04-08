<template>
  <Transition
    enter-active-class="transform ease-out duration-300 transition"
    enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
    enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
    leave-active-class="transition ease-in duration-100"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-if="show"
      class="fixed z-50 bottom-0 right-0 mb-4 mr-4 max-w-sm w-full bg-white rounded-lg shadow-lg overflow-hidden"
    >
      <div class="p-4">
        <div class="flex items-start">
          <div class="flex-shrink-0">
            <i
              :class="[
                'fas text-lg',
                type === 'success' ? 'fa-check-circle text-green-400' : 'fa-exclamation-circle text-red-400'
              ]"
            ></i>
          </div>
          <div class="ml-3 w-0 flex-1 pt-0.5">
            <p class="text-sm font-medium text-gray-900">
              {{ title }}
            </p>
            <p class="mt-1 text-sm text-gray-500">
              {{ message }}
            </p>
          </div>
          <div class="ml-4 flex-shrink-0 flex">
            <button
              class="inline-flex text-gray-400 hover:text-gray-500 focus:outline-none"
              @click="close"
            >
              <span class="sr-only">Fermer</span>
              <i class="fas fa-times"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'

const props = defineProps<{
  title: string
  message: string
  type: 'success' | 'error'
  duration?: number
}>()

const show = ref(true)

const close = () => {
  show.value = false
}

onMounted(() => {
  if (props.duration) {
    setTimeout(() => {
      close()
    }, props.duration)
  }
})
</script> 