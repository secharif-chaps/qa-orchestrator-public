<template>
  <div>
    <label class="mb-2 block text-sm font-medium">
      {{ $t('common.folder.form.icon') }}
    </label>
    <div class="relative">
      <button
        type="button"
        class="bg-base-200 border-primary-stroke hover:border-primary/50 flex w-full items-center gap-3 rounded-lg border px-4 py-3 transition-colors"
        @click="showDropdown = !showDropdown"
      >
        <div
          :class="getSelectedIconColorClasses()"
          class="flex h-10 w-10 items-center justify-center rounded-lg"
        >
          <i :class="selectedIcon" class="text-lg"></i>
        </div>
        <span class="flex-1 text-left">{{ getIconDisplayName(selectedIcon) }}</span>
        <i class="fas fa-chevron-down text-secondary"></i>
      </button>

      <!-- Backdrop -->
      <div v-if="showDropdown" class="fixed inset-0 z-40" @click="showDropdown = false"></div>

      <!-- Icon Grid Dropdown -->
      <div
        v-if="showDropdown"
        class="bg-base-100 border-primary-stroke absolute top-full z-50 mt-2 rounded-lg border p-4 shadow-lg"
      >
        <div class="grid grid-cols-5 gap-2">
          <button
            v-for="icon in availableIcons"
            :key="icon.class"
            type="button"
            class="hover:bg-base-200 flex h-12 w-12 items-center justify-center rounded-lg border-2 transition-all"
            :class="
              selectedIcon === icon.class ? 'border-primary bg-primary/10' : 'border-transparent'
            "
            @click="selectIcon(icon.class)"
            :title="icon.name"
          >
            <i :class="icon.class" class="text-secondary text-lg"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'

interface Props {
  modelValue?: string
  color?: string
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: 'fa-jelly-duo fa-folder',
  color: 'blue',
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const showDropdown = ref(false)

const selectedIcon = computed({
  get: () => props.modelValue,
  set: (value: string) => emit('update:modelValue', value),
})

// Available icons (25 FontAwesome jelly-duo icons)
const availableIcons = [
  { class: 'fa-jelly-duo fa-folder', name: 'Folder' },
  { class: 'fa-jelly-duo fa-fire', name: 'Fire' },
  { class: 'fa-jelly-duo fa-cloud', name: 'Cloud' },
  { class: 'fa-jelly-duo fa-music', name: 'Music' },
  { class: 'fa-jelly-duo fa-heart', name: 'Heart' },
  { class: 'fa-jelly-duo fa-star', name: 'Star' },
  { class: 'fa-jelly-duo fa-bomb', name: 'Bomb' },
  { class: 'fa-jelly-duo fa-droplet', name: 'Droplet' },
  { class: 'fa-jelly-duo fa-user', name: 'User' },
  { class: 'fa-jelly-duo fa-suitcase', name: 'Suitcase' },
  { class: 'fa-jelly-duo fa-bookmark', name: 'Bookmark' },
  { class: 'fa-jelly-duo fa-tree', name: 'Tree' },
  { class: 'fa-jelly-duo fa-lightbulb', name: 'Lightbulb' },
  { class: 'fa-jelly-duo fa-paper-plane', name: 'Paper Plane' },
  { class: 'fa-jelly-duo fa-shield', name: 'Shield' },
  { class: 'fa-jelly-duo fa-crown', name: 'Crown' },
  { class: 'fa-jelly-duo fa-leaf', name: 'Leaf' },
  { class: 'fa-jelly-duo fa-snowflake', name: 'Snowflake' },
  { class: 'fa-jelly-duo fa-sun', name: 'Sun' },
  { class: 'fa-jelly-duo fa-moon', name: 'Moon' },
  { class: 'fa-jelly-duo fa-bolt', name: 'Bolt' },
  { class: 'fa-jelly-duo fa-compass', name: 'Compass' },
  { class: 'fa-jelly-duo fa-flag', name: 'Flag' },
  { class: 'fa-jelly-duo fa-gift', name: 'Gift' },
  { class: 'fa-jelly-duo fa-camera', name: 'Camera' },
]

const selectIcon = (iconClass: string) => {
  selectedIcon.value = iconClass
  showDropdown.value = false
}

const getIconDisplayName = (iconClass: string) => {
  return availableIcons.find((icon) => icon.class === iconClass)?.name || 'Folder'
}

const getSelectedIconColorClasses = () => {
  const color = props.color || 'blue'
  const colorMap: Record<string, string> = {
    red: 'bg-red-100 dark:bg-red-900/20 text-red-600 dark:text-red-400',
    orange: 'bg-orange-100 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400',
    amber: 'bg-amber-100 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400',
    yellow: 'bg-yellow-100 dark:bg-yellow-900/20 text-yellow-600 dark:text-yellow-400',
    lime: 'bg-lime-100 dark:bg-lime-900/20 text-lime-600 dark:text-lime-400',
    green: 'bg-green-100 dark:bg-green-900/20 text-green-600 dark:text-green-400',
    emerald: 'bg-emerald-100 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400',
    teal: 'bg-teal-100 dark:bg-teal-900/20 text-teal-600 dark:text-teal-400',
    cyan: 'bg-cyan-100 dark:bg-cyan-900/20 text-cyan-600 dark:text-cyan-400',
    sky: 'bg-sky-100 dark:bg-sky-900/20 text-sky-600 dark:text-sky-400',
    blue: 'bg-blue-100 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400',
    indigo: 'bg-indigo-100 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400',
    violet: 'bg-violet-100 dark:bg-violet-900/20 text-violet-600 dark:text-violet-400',
    purple: 'bg-purple-100 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400',
    fuchsia: 'bg-fuchsia-100 dark:bg-fuchsia-900/20 text-fuchsia-600 dark:text-fuchsia-400',
    pink: 'bg-pink-100 dark:bg-pink-900/20 text-pink-600 dark:text-pink-400',
    rose: 'bg-rose-100 dark:bg-rose-900/20 text-rose-600 dark:text-rose-400',
    gray: 'bg-gray-100 dark:bg-gray-900/20 text-gray-600 dark:text-gray-400',
  }
  return colorMap[color] || colorMap.blue
}
</script>
