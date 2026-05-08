<template>
  <div>
    <label class="mb-2 block text-sm font-medium">
      {{ $t('common.folder.form.color') }}
    </label>
    <div class="relative">
      <button
        type="button"
        class="bg-primary-lightest border-primary-lighter-stroke hover:border-primary/50 flex w-full items-center gap-3 rounded-sm border px-4 py-3 transition-colors"
        @click="showDropdown = !showDropdown"
      >
        <div class="h-6 w-6 rounded-sm" :class="getColorPreviewClasses()"></div>
        <span class="flex-1 text-left">{{ colorLabelMap[selectedColor] }}</span>
        <i class="fas fa-chevron-down text-neutral-black-font"></i>
      </button>

      <!-- Backdrop -->
      <div v-if="showDropdown" class="fixed inset-0 z-40" @click="showDropdown = false"></div>

      <!-- Color Grid Dropdown -->
      <div
        v-if="showDropdown"
        class="border-primary-lighter-stroke absolute top-full z-50 mt-2 rounded-sm border bg-white p-4 shadow-lg"
      >
        <div class="grid grid-cols-6 gap-2">
          <button
            v-for="color in availableColors"
            :key="color"
            type="button"
            class="h-10 w-10 rounded-sm border-2 transition-all hover:scale-110"
            :class="[
              getColorClasses(color),
              selectedColor === color ? 'border-primary' : 'border-transparent',
            ]"
            :title="colorLabelMap[color]"
            @click="selectColor(color)"
          ></button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Props {
  modelValue?: string
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: 'blue',
})

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

const colorLabelMap = computed<Record<string, string>>(() => ({
  red: t('common.folder.form.colors.red'),
  orange: t('common.folder.form.colors.orange'),
  amber: t('common.folder.form.colors.amber'),
  yellow: t('common.folder.form.colors.yellow'),
  lime: t('common.folder.form.colors.lime'),
  green: t('common.folder.form.colors.green'),
  emerald: t('common.folder.form.colors.emerald'),
  teal: t('common.folder.form.colors.teal'),
  cyan: t('common.folder.form.colors.cyan'),
  sky: t('common.folder.form.colors.sky'),
  blue: t('common.folder.form.colors.blue'),
  indigo: t('common.folder.form.colors.indigo'),
  violet: t('common.folder.form.colors.violet'),
  purple: t('common.folder.form.colors.purple'),
  fuchsia: t('common.folder.form.colors.fuchsia'),
  pink: t('common.folder.form.colors.pink'),
  rose: t('common.folder.form.colors.rose'),
  gray: t('common.folder.form.colors.gray'),
}))

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
