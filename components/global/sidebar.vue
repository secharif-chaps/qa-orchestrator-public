<template>
  <div class="w-[86px] bg-primary h-screen fixed text-white pt-24 pb-4 px-2">
    <div class="flex flex-col justify-between h-full">
      <div class="space-y-4">
        <div
          v-for="button in buttons"
          :key="button.label"
          class="w-full py-2 text-center space-y-2 pb-6"
        >
          <NuxtLink :to="button.to" class="block">
            <div class="group cursor-pointer space-y-1 text-white">
              <div
                class="relative mx-auto flex h-10 w-10 items-center justify-center rounded-md"
                :class="[
                  isActive(button.to)
                    ? 'bg-white text-indigo-600'
                    : 'group-hover:bg-indigo-700 group-hover:text-white'
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
          </NuxtLink>
        </div>
      </div>

      <div class="pt-4 space-y-4">
        <div
          v-for="button in actions"
          :key="button.label"
          class="w-full py-2 text-center space-y-2 pb-6"
        >
          <NuxtLink :to="button.to" class="block">
            <div class="group cursor-pointer space-y-1 text-white">
              <div
                class="relative mx-auto flex h-10 w-10 items-center justify-center rounded-md"
                :class="[
                  isActive(button.to)
                    ? 'bg-white text-indigo-600'
                    : 'group-hover:bg-indigo-700 group-hover:text-white'
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
          </NuxtLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const buttons = computed(() => [
  { icon: 'fa fa-search', label: t('sidebar.search'), active: true, to: '/search' },
  {
    icon: 'fa fa-folder-open',
    label: t('sidebar.cards'),
    chip: nbCards.value || '0',
    to: '/cards'
  }
])

const actions = computed(() => [
  { icon: 'fa fa-cog', label: t('sidebar.settings'), to: '/settings' },
  { icon: 'fa fa-question', label: t('sidebar.help'), to: '/help' }
])

const route = useRoute()

const isActive = (to: string) => {
  return to === route.path
}

const companyStore = useCompanyStore()

const nbCards = computed(() => companyStore.getCompanyList.length)
</script>
