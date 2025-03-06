<template>
  <div class="w-[86px] bg-primary h-screen fixed text-white pt-24 pb-4 px-4">
    <div class="flex flex-col justify-between h-full">
      <div class="space-y-4 divide-y divide-emerald-400">
        <div
          v-for="button in buttons"
          :key="button.label"
          class="w-full py-2 text-center space-y-2 pb-6"
        >
          <NuxtLink :to="button.to">
            <button class="space-y-2 group cursor-pointer">
              <div>
                <span
                  :class="[isActive(button.to) ? 'bg-white text-primary' : '']"
                  class="rounded p-1.5 text-lg group-hover:bg-white group-hover:text-primary"
                >
                  <i :class="[button.icon, 'fa-fw']"></i>
                </span>
              </div>

              <p class="font-bold text-xs">{{ button.label }}</p>

              <span
                v-if="button.chip"
                class="bg-emerald-500 text-emerald-100 rounded px-2 py-1"
                >{{ button.chip }}</span
              >
            </button>
          </NuxtLink>
        </div>
      </div>

      <div
        class="border-t border-emerald-400 pt-4 space-y-4 divide-y divide-emerald-400"
      >
        <div
          v-for="button in actions"
          :key="button.label"
          class="w-full py-2 text-center space-y-2 pb-6"
        >
          <NuxtLink :to="button.to">
            <button class="space-y-2 cursor-pointer group">
              <div class="space-y-2">
                <div>
                  <span
                    :class="[
                      isActive(button.to) ? 'bg-white icon-emerald-500' : '',
                    ]"
                    class="rounded p-1.5 text-lg group-hover:bg-white group-hover:text-primary"
                  >
                    <i :class="[button.icon, 'fa-fw']"></i>
                  </span>
                </div>

                <p class="font-bold text-xs">{{ button.label }}</p>
              </div>

              <span
                v-if="button.chip"
                class="bg-emerald-500 text-emerald-100 rounded px-2 py-1"
                >{{ button.chip }}</span
              >
            </button>
          </NuxtLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
const buttons = computed(() => [
  { icon: 'fa fa-search', label: 'Search', active: true, to: '/search' },
  {
    icon: 'fa fa-folder-open',
    label: 'Cards',
    chip: nbCards.value || '0',
    to: '/cards',
  },
])

const actions = ref([
  { icon: 'fa fa-cog', label: 'Settings', to: '/settings' },
  { icon: 'fa fa-question', label: 'Help', to: '/help' },
])

const route = useRoute()

const isActive = (to: string) => {
  return to === route.path
}

const companyStore = useCompanyStore()

const nbCards = computed(() => companyStore.getCompanyList.length)
</script>
