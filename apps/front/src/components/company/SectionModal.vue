<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="modelValue"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm transition-all duration-300"
        @click.self="close"
      >
        <div
          class="rounded-card border-primary-lighter-stroke shadow-3 flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden border bg-white"
        >
          <!-- Header -->
          <div
            class="border-primary-lighter-stroke bg-primary-lightest flex items-center justify-between border-b p-6"
          >
            <div class="flex items-center gap-3">
              <div class="bg-primary/10 flex h-12 w-12 items-center justify-center rounded-full">
                <i :class="[sectionConfig?.icon, 'text-neutral-black-font text-lg']"></i>
              </div>
              <div>
                <h2 class="text-xl font-semibold">{{ sectionConfig?.name }}</h2>
                <p class="text-neutral-black-font text-sm">
                  {{ sectionConfig?.description }}
                </p>
              </div>
            </div>
            <Button variant="tertiary" icon="fa fa-times" icon-only size="lg" @click="close" />
          </div>

          <!-- Content -->
          <div class="flex-1 overflow-x-hidden overflow-y-auto p-6">
            <component :is="sectionComponent" v-if="sectionComponent" />
            <div v-else class="text-neutral-black-font py-12 text-center">
              <i class="fas fa-exclamation-triangle mb-4 text-4xl"></i>
              <p>{{ t('screen.company.sections.notAvailable') }}</p>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import type { TaskType } from '@/types/task'
import { Button } from '@owlint/feathers-vue'
import { computed, defineAsyncComponent, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

const { t } = useI18n()

// Lazy load section components
const ProfilePage = defineAsyncComponent(
  () => import('@/pages/folders/[folderId]/companies/[companyId]/profile.vue'),
)
const TimelinePage = defineAsyncComponent(
  () => import('@/pages/folders/[folderId]/companies/[companyId]/timeline.vue'),
)
const ProductsPage = defineAsyncComponent(
  () => import('@/pages/folders/[folderId]/companies/[companyId]/products.vue'),
)
const TeamPage = defineAsyncComponent(
  () => import('@/pages/folders/[folderId]/companies/[companyId]/team.vue'),
)
const JobsPage = defineAsyncComponent(
  () => import('@/pages/folders/[folderId]/companies/[companyId]/jobs.vue'),
)
const PressPage = defineAsyncComponent(
  () => import('@/pages/folders/[folderId]/companies/[companyId]/press.vue'),
)
const CsrPage = defineAsyncComponent(
  () => import('@/pages/folders/[folderId]/companies/[companyId]/csr.vue'),
)
const CorporateStructurePage = defineAsyncComponent(
  () => import('@/pages/folders/[folderId]/companies/[companyId]/corporate-structure.vue'),
)
const SanctionsPage = defineAsyncComponent(
  () => import('@/pages/folders/[folderId]/companies/[companyId]/sanctions.vue'),
)
const FinancialPage = defineAsyncComponent(
  () => import('@/pages/folders/[folderId]/companies/[companyId]/financial.vue'),
)

interface SectionConfig {
  name: string
  description: string
  icon: string
  component: ReturnType<typeof defineAsyncComponent>
}

const section = defineModel<TaskType | null>('section', { required: false })
const modelValue = defineModel<boolean>()

const router = useRouter()
const route = useRoute()

// Section configurations
const sections: Record<TaskType, SectionConfig> = {
  profile: {
    name: t('screen.company.analysisCards.profile.title'),
    description: t('screen.company.analysisCards.profile.description'),
    icon: 'fas fa-building',
    component: ProfilePage,
  },
  timeline: {
    name: t('screen.company.analysisCards.timeline.title'),
    description: t('screen.company.analysisCards.timeline.description'),
    icon: 'fas fa-calendar-days',
    component: TimelinePage,
  },
  products: {
    name: t('screen.company.analysisCards.products.title'),
    description: t('screen.company.analysisCards.products.description'),
    icon: 'fas fa-box',
    component: ProductsPage,
  },
  team: {
    name: t('screen.company.analysisCards.team.title'),
    description: t('screen.company.analysisCards.team.description'),
    icon: 'fas fa-users',
    component: TeamPage,
  },
  jobs: {
    name: t('screen.company.analysisCards.jobs.title'),
    description: t('screen.company.analysisCards.jobs.description'),
    icon: 'fas fa-briefcase',
    component: JobsPage,
  },
  press: {
    name: t('screen.company.analysisCards.press.title'),
    description: t('screen.company.analysisCards.press.description'),
    icon: 'fas fa-newspaper',
    component: PressPage,
  },
  digital: {
    name: t('screen.company.onlinePresence.title'),
    description: t('screen.company.onlinePresence.socialMedia'),
    icon: 'fas fa-globe',
    component: ProfilePage, // Included in profile
  },
  csr: {
    name: t('screen.company.analysisCards.csr.title'),
    description: t('screen.company.analysisCards.csr.description'),
    icon: 'fas fa-leaf',
    component: CsrPage,
  },
  corporate_structure: {
    name: t('screen.company.analysisCards.corporateStructure.title'),
    description: t('screen.company.analysisCards.corporateStructure.description'),
    icon: 'fas fa-sitemap',
    component: CorporateStructurePage,
  },
  sanctions: {
    name: t('screen.company.analysisCards.sanctions.title'),
    description: t('screen.company.analysisCards.sanctions.description'),
    icon: 'fas fa-shield-halved',
    component: SanctionsPage,
  },
  financial: {
    name: t('screen.company.analysisCards.financial.title'),
    description: t('screen.company.analysisCards.financial.description'),
    icon: 'fas fa-chart-line',
    component: FinancialPage,
  },
}

const sectionConfig = computed(() => {
  if (!section.value) return null
  return sections[section.value]
})

const sectionComponent = computed(() => {
  return sectionConfig.value?.component || null
})

const close = () => {
  modelValue.value = false
  section.value = null

  // Remove query param from URL
  const query = { ...route.query }
  delete query.section
  router.replace({ query })
}

// Watch for URL changes to sync modal state
watch(
  () => route.query.section,
  (newSection) => {
    if (newSection && typeof newSection === 'string') {
      section.value = newSection as TaskType
      modelValue.value = true
    } else if (!newSection && modelValue.value) {
      modelValue.value = false
      section.value = null
    }
  },
  { immediate: true },
)

// Watch for modal close to update URL
watch(modelValue, (isOpen) => {
  if (!isOpen) {
    const query = { ...route.query }
    delete query.section
    if (JSON.stringify(query) !== JSON.stringify(route.query)) {
      router.replace({ query })
    }
  }
})
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-active .bg-white,
.modal-leave-active .bg-white {
  transition: transform 0.3s ease;
}

.modal-enter-from .bg-white,
.modal-leave-to .bg-white {
  transform: scale(0.95);
}
</style>
