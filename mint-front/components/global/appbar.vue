<template>
  <div class="fixed top-0 w-full z-10 pl-4">
    <div class="bg-bg1 rounded-bl-2xl h-[68px] pr-6 shadow-md">
      <div class="flex items-center justify-between h-full">
        <NuxtLink to="/">
          <div class="flex items-center space-x-2 text-xl text-primary pl-6">
            <i class="fa fa-leaf"></i>
            <h1 class="font-extrabold">MINT</h1>
          </div>
        </NuxtLink>
        <div class="max-w-md grow">
          <!-- <OInput
            id="search"
            :placeholder="t('appbar.search')"
          /> -->
        </div>
        <div class="flex items-center gap-6">
          <div>
            <Icon class="h-6 w-auto" />
          </div>
          <div class="flex gap-2">
            <button
              v-for="color in colors"
              @click="changeTheme(color)"
              class="size-6 rounded-full cursor-pointer flex items-center justify-center"
              :class="getStyle(color)"
            >
              <i class="fa fa-circle"></i>
            </button>
          </div>
          <NuxtLink 
            to="/profile"
            class="flex items-center justify-center p-2 text-gray-600 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors"
            title="Profile"
          >
            <i class="fa fa-user text-lg"></i>
          </NuxtLink>
          <button
            @click="handleLogout"
            class="flex items-center justify-center p-2 text-gray-600 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
            title="Logout"
          >
            <i class="fa fa-sign-out-alt text-lg"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
const { signOut } = useAuth()

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
    case 'chaps':
      return currentTheme.value === 'chaps'
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

// handle logout
const handleLogout = async () => {
  try {
    await signOut()
  } catch (error) {
    console.error('Logout error:', error)
  }
}
</script>
