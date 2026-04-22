<template>
  <Modal
    v-model:display-modal="modelValue"
    :title="sectionConfig?.name"
    :icon="sectionConfig?.icon"
    size="7xl"
    @close="close"
  >
    <template #description>
      {{ sectionConfig?.description }}

      <div class="max-h-[70vh] overflow-x-hidden overflow-y-auto">
        <component :is="sectionComponent" v-if="sectionComponent" />
        <div v-else-if="section" class="text-neutral-black-font py-12 text-center">
          <i class="fas fa-exclamation-triangle mb-4 text-4xl"></i>
          <p>{{ t('screen.company.sections.notAvailable') }}</p>
        </div>
      </div>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import type { TaskType } from '@/types/task'
import { Modal } from '@owlint/feathers-vue'
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
