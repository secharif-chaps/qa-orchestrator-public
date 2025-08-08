<template>
  <div class="space-y-6">
    <!-- Header Section -->
    <div class="text-center">
      <h2 class="text-2xl font-bold text-text-1 mb-2">Your Modules</h2>
      <p class="text-text-2 text-sm">
        Unlock powerful features to supercharge your business intelligence
      </p>
    </div>

    <!-- Modules Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div
        v-for="module in modules"
        :key="module.name"
        :class="[
          'relative rounded-xl border transition-all duration-300 hover:scale-[1.02] cursor-pointer',
          'p-6 min-h-[200px] flex flex-col justify-between',
          module.unlocked
            ? 'bg-gradient-to-br from-primary/10 to-purple-500/10 border-primary/20 hover:shadow-lg hover:shadow-primary/20'
            : 'bg-gray-50 dark:bg-gray-800/50 border-gray-200 dark:border-gray-700 hover:shadow-md',
        ]"
      >
        <!-- Status Badge -->
        <div class="absolute top-3 right-3">
          <div
            v-if="module.unlocked"
            class="flex items-center gap-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 text-xs px-2 py-1 rounded-full"
          >
            <i class="fa-solid fa-check text-[10px]"></i>
            <span>Active</span>
          </div>
          <div
            v-else
            :class="[
              'text-xs px-2 py-1 rounded-full',
              module.status === 'contact-sales'
                ? 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300'
                : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400',
            ]"
          >
            {{ module.status === 'contact-sales' ? 'Pro Feature' : 'Coming Soon' }}
          </div>
        </div>

        <!-- Module Icon -->
        <div class="flex-1">
          <div
            :class="[
              'w-12 h-12 rounded-lg flex items-center justify-center mb-4',
              module.unlocked
                ? 'bg-gradient-to-br from-primary to-purple-500 text-white'
                : 'bg-gray-200 dark:bg-gray-700 text-gray-400',
            ]"
          >
            <i :class="[module.icon, 'text-lg']"></i>
          </div>

          <!-- Module Info -->
          <h3
            :class="[
              'font-semibold text-lg mb-2',
              module.unlocked ? 'text-text-1' : 'text-gray-500 dark:text-gray-400',
            ]"
          >
            {{ module.name }}
          </h3>
          <p
            :class="[
              'text-sm leading-relaxed',
              module.unlocked ? 'text-text-2' : 'text-gray-400 dark:text-gray-500',
            ]"
          >
            {{ module.description }}
          </p>
        </div>

        <!-- Action Buttons -->
        <div class="mt-4">
          <div v-if="module.unlocked" class="grid grid-cols-2 gap-2">
            <RouterLink
              to="/search"
              class="bg-gradient-to-r from-primary to-purple-500 text-white py-2 px-3 rounded-lg font-medium text-center text-sm transition-all duration-200 hover:shadow-lg hover:shadow-primary/25 transform hover:-translate-y-0.5 flex items-center justify-center"
            >
              <i class="fa-solid fa-magnifying-glass mr-1"></i>
              Search
            </RouterLink>
            <RouterLink
              to="/companies"
              class="bg-gradient-to-r from-primary to-purple-500 text-white py-2 px-3 rounded-lg font-medium text-center text-sm transition-all duration-200 hover:shadow-lg hover:shadow-primary/25 transform hover:-translate-y-0.5 flex items-center justify-center"
            >
              <i class="fa-solid fa-building mr-1"></i>
              Companies
            </RouterLink>
          </div>
          <button
            v-else-if="module.status === 'contact-sales'"
            class="w-full border border-orange-300 dark:border-orange-700 text-orange-600 dark:text-orange-400 py-2 px-4 rounded-lg font-medium transition-all duration-200 hover:bg-orange-50 dark:hover:bg-orange-900/20"
          >
            <i class="fa-solid fa-envelope mr-2"></i>
            Contact Sales
          </button>
          <button
            v-else
            disabled
            class="w-full border border-gray-300 dark:border-gray-600 text-gray-400 dark:text-gray-500 py-2 px-4 rounded-lg font-medium cursor-not-allowed"
          >
            <i class="fa-solid fa-clock mr-2"></i>
            Coming Soon
          </button>
        </div>
      </div>
    </div>

    <!-- Upgrade Section -->
    <!-- <div class="text-center p-6 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
      <h3 class="font-semibold text-text-1 mb-2">Ready to unlock more potential?</h3>
      <p class="text-text-2 text-sm mb-4">
        Get access to all modules and supercharge your business intelligence workflow
      </p>
      <button class="bg-gradient-to-r from-primary to-purple-500 text-white py-2 px-6 rounded-lg font-medium transition-all duration-200 hover:shadow-lg hover:shadow-primary/25 transform hover:-translate-y-0.5">
        <i class="fa-solid fa-rocket mr-2"></i>
        Upgrade Plan
      </button>
    </div> -->
  </div>
</template>

<script setup lang="ts">
interface Module {
  name: string
  description: string
  icon: string
  unlocked: boolean
  status: 'contact-sales' | 'coming-soon'
}

const modules: Module[] = [
  {
    name: 'Screen',
    description: 'Deep company intelligence and comprehensive business screening',
    icon: 'fa-solid fa-magnifying-glass',
    unlocked: true,
    status: 'contact-sales', // This won't be used since it's unlocked
  },
  {
    name: 'Target',
    description: 'AI-powered market watch with smart alerts and monitoring',
    icon: 'fa-solid fa-bullseye',
    unlocked: false,
    status: 'contact-sales',
  },
  {
    name: 'Explore',
    description: 'Interactive knowledge graph for data visualization and discovery',
    icon: 'fa-solid fa-project-diagram',
    unlocked: false,
    status: 'coming-soon',
  },
  {
    name: 'Stream',
    description: 'Automated insights delivery through newsletters and reports',
    icon: 'fa-solid fa-rss',
    unlocked: false,
    status: 'coming-soon',
  },
]
</script>
