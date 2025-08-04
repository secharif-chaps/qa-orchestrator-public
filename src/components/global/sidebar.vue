<template>
  <div class="w-[86px] bg-primary dark:bg-sidebar h-screen fixed text-white pt-24 pb-4 px-2">
    <div class="flex flex-col justify-between h-full">
      <div class="space-y-4">
        <div
          v-for="button in buttons"
          :key="button.label"
          class="w-full py-2 text-center space-y-2"
        >
          <RouterLink :to="button.to" class="block">
            <div class="group cursor-pointer space-y-1 text-white dark:text-bg2">
              <div
                class="relative mx-auto flex h-10 w-10 items-center justify-center rounded-md"
                :class="[
                  isActive(button.to)
                    ? 'bg-white dark:bg-bg2/20 text-primary dark:text-bg2'
                    : 'group-hover:bg-primary group-hover:text-white dark:group-hover:bg-primary/20 dark:group-hover:text-primary-content',
                ]"
              >
                <i
                  :class="['fa-jelly-duo', button.icon, isActive(button.to) ? 'scale-[115%]' : '']"
                  class="fa-fw text-lg transition-transform group-hover:scale-[115%]"
                  aria-hidden="true"
                />
              </div>
              <div
                class="dark:text-bg2 text-center text-[0.65rem] leading-3 font-normal tracking-wide"
              >
                {{ button.label }}
              </div>
            </div>
          </RouterLink>
        </div>
      </div>

      <div class="pt-4 space-y-4">
        <div
          v-for="button in actions"
          :key="button.label"
          class="w-full py-2 text-center space-y-2"
        >
          <RouterLink :to="button.to" class="block">
            <div class="group cursor-pointer space-y-1 text-white dark:text-bg2">
              <div
                class="relative mx-auto flex h-10 w-10 items-center justify-center rounded-md"
                :class="[
                  isActive(button.to)
                    ? 'bg-white text-primary dark:text-bg2'
                    : 'group-hover:bg-primary group-hover:text-white dark:group-hover:bg-bg2/20 dark:group-hover:text-bg2',
                ]"
              >
                <i
                  :class="['fa-solid', button.icon, isActive(button.to) ? 'scale-[115%]' : '']"
                  class="fa-fw text-lg transition-transform group-hover:scale-[115%]"
                  aria-hidden="true"
                />
              </div>
              <div class="text-center text-[0.65rem] leading-3 font-normal tracking-wide">
                {{ button.label }}
              </div>
            </div>
          </RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { useAuthStore } from '@/stores/auth'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'

const { t } = useI18n()

const { userRoles } = useAuthStore()

const buttons = computed(() => {
  const baseButtons = [
    { icon: 'fa fa-home', label: t('sidebar.home'), active: true, to: '/' },
    { icon: 'fa fa-search', label: t('sidebar.search'), active: true, to: '/search' },
    {
      icon: 'fa fa-folder',
      label: t('sidebar.cards'),
      to: '/companies',
    },
  ]

  // Add workspace button if user has admin.workspaces role
  if (userRoles.includes('admin.workspaces')) {
    baseButtons.push({
      icon: 'fa fa-users',
      label: t('sidebar.workspaces'),
      active: true,
      to: '/workspaces',
    })
  }

  return baseButtons
})

const actions = computed(() => [
  { icon: 'fa fa-cog', label: t('sidebar.settings'), to: '/settings' },
  { icon: 'fa fa-question', label: t('sidebar.help'), to: '/help' },
])

const route = useRoute()

const isActive = (to: string) => {
  return to === route.path
}
</script>
