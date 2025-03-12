<template>
  <div class="fixed top-0 w-full z-10 pl-4">
    <div class="bg-bg1 rounded-bl-2xl h-[68px] px-6 shadow-md">
      <div class="flex items-center justify-between h-full">
        <NuxtLink to="/">
          <div class="flex items-center space-x-2 text-xl text-primary">
            <i class="fa fa-leaf"></i>
            <h1 class="font-extrabold">MINT</h1>
          </div>
        </NuxtLink>
        <div class="max-w-md grow">
          <OInput
            id="search"
            placeholder="Search"
          />
        </div>
        <div class="flex gap-2">
          <button
            v-for="color in colors"
            @click="changeTheme(color)"
            class="w-6 h-6 rounded-full cursor-pointer"
            :class="getStyle(color)"
          >
            <i class="fa fa-circle"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { OInput } from '@owlint/feathers-vue'

const colors = ['pink', 'indigo', 'emerald']

const currentTheme = ref('indigo')

const getStyle = (color: string) => {
  switch (color) {
    case 'pink':
      return currentTheme.value === 'pink'
        ? 'bg-pink-500 text-white'
        : 'text-pink-500 hover:bg-pink-300 hover:text-white'
    case 'indigo':
      return currentTheme.value === 'indigo'
        ? 'bg-indigo-500 text-white'
        : 'text-indigo-500 hover:bg-indigo-300 hover:text-white'
    case 'emerald':
      return currentTheme.value === 'emerald'
        ? 'bg-emerald-500 text-white'
        : 'text-emerald-500 hover:bg-emerald-300 hover:text-white'
    case 'dark':
      return currentTheme.value === 'dark'
        ? 'bg-gray-500 text-white'
        : 'text-gray-500 hover:bg-gray-300 hover:text-white'
    default:
      return 'text-gray-500'
  }
}

// switch data theme
const changeTheme = (theme: string) => {
  currentTheme.value = theme
  document.documentElement.setAttribute('data-theme', theme)
}
</script>
