<template>
  <div>
    <label class="mb-2 block text-sm font-medium">
      {{ $t('folder.form.color') }}
    </label>
    <div class="relative">
      <button
        type="button"
        class="bg-base-200 border-primary-stroke hover:border-primary/50 flex w-full items-center gap-3 rounded-lg border px-4 py-3 transition-colors"
        @click="showDropdown = !showDropdown"
      >
        <div class="h-6 w-6 rounded-lg" :class="getColorPreviewClasses()"></div>
        <span class="flex-1 text-left">{{ t(`folder.form.colors.${selectedColor}`) }}</span>
        <i class="fas fa-chevron-down text-secondary"></i>
      </button>

      <!-- Backdrop -->
      <div v-if="showDropdown" class="fixed inset-0 z-40" @click="showDropdown = false"></div>

      <!-- Color Grid Dropdown -->
      <div
        v-if="showDropdown"
        class="bg-base-100 border-primary-stroke absolute top-full z-50 mt-2 rounded-lg border p-4 shadow-lg"
      >
        <div class="grid grid-cols-6 gap-2">
          <button
            v-for="color in availableColors"
            :key="color"
            type="button"
            class="h-10 w-10 rounded-lg border-2 transition-all hover:scale-110"
            :class="[
              getColorClasses(color),
              selectedColor === color ? 'border-primary' : 'border-transparent',
            ]"
            @click="selectColor(color)"
            :title="t(`folder.form.colors.${color}`)"
          ></button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Props {
  modelValue: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const showDropdown = ref(false)

const selectedColor = computed({
  get: () => props.modelValue,
  set: (value: string) => emit('update:modelValue', value),
})

// Available colors (Tailwind 400 intensity)
const availableColors = [
  'red',
  'orange',
  'amber',
  'yellow',
  'lime',
  'green',
  'emerald',
  'teal',
  'cyan',
  'sky',
  'blue',
  'indigo',
  'violet',
  'purple',
  'fuchsia',
  'pink',
  'rose',
  'gray',
]

const selectColor = (color: string) => {
  selectedColor.value = color
  showDropdown.value = false
}

const getColorClasses = (color: string) => {
  const colorMap: Record<string, string> = {
    red: 'bg-red-400',
    orange: 'bg-orange-400',
    amber: 'bg-amber-400',
    yellow: 'bg-yellow-400',
    lime: 'bg-lime-400',
    green: 'bg-green-400',
    emerald: 'bg-emerald-400',
    teal: 'bg-teal-400',
    cyan: 'bg-cyan-400',
    sky: 'bg-sky-400',
    blue: 'bg-blue-400',
    indigo: 'bg-indigo-400',
    violet: 'bg-violet-400',
    purple: 'bg-purple-400',
    fuchsia: 'bg-fuchsia-400',
    pink: 'bg-pink-400',
    rose: 'bg-rose-400',
    gray: 'bg-gray-400',
  }
  return colorMap[color] || colorMap.blue
}

const getColorPreviewClasses = () => {
  return getColorClasses(selectedColor.value || 'blue')
}
</script>
