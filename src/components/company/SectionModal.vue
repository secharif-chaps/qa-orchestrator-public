<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="modelValue"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
        @click.self="close"
      >
        <div
          class="bg-base-100 rounded-card border border-primary-stroke shadow-shadow-3 w-full max-w-6xl max-h-[90vh] overflow-hidden flex flex-col"
        >
          <!-- Header -->
          <div
            class="flex items-center justify-between p-6 border-b border-primary-stroke bg-base-200"
          >
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center">
                <i :class="[sectionConfig?.icon, 'text-primary-light-content text-lg']"></i>
              </div>
              <div>
                <h2 class="text-xl font-semibold">{{ sectionConfig?.name }}</h2>
                <p class="text-sm text-primary-light-content">
                  {{ sectionConfig?.description }}
                </p>
              </div>
            </div>
            <Button variant="tertiary" icon="fa fa-times" icon-only size="lg" @click="close" />
          </div>

          <!-- Content -->
          <div class="flex-1 overflow-y-auto p-6">
            <component :is="sectionComponent" v-if="sectionComponent" />
            <div v-else class="text-center text-primary-light-content py-12">
              <i class="fas fa-exclamation-triangle text-4xl mb-4"></i>
              <p>Section non disponible</p>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, watch, defineAsyncComponent } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import Button from '@/components/ui/Button.vue'
import type { TaskType } from '@/types/task'

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

interface SectionConfig {
  name: string
  description: string
  icon: string
  component: any
}

interface Props {
  modelValue: boolean
  section?: TaskType | null
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'update:section': [value: TaskType | null]
}>()

const router = useRouter()
const route = useRoute()

// Section configurations
const sections: Record<TaskType, SectionConfig> = {
  profile: {
    name: "Profil de l'entreprise",
    description: 'Informations générales et présence digitale',
    icon: 'fas fa-building',
    component: ProfilePage,
  },
  timeline: {
    name: 'Activités & Événements',
    description: 'Historique et moments clés',
    icon: 'fas fa-calendar-days',
    component: TimelinePage,
  },
  products: {
    name: 'Produits & Services',
    description: 'Catalogue et gamme de produits',
    icon: 'fas fa-box',
    component: ProductsPage,
  },
  team: {
    name: 'Équipe & Management',
    description: 'Organigramme et membres clés',
    icon: 'fas fa-users',
    component: TeamPage,
  },
  jobs: {
    name: "Offres d'emploi",
    description: 'Recrutement et opportunités',
    icon: 'fas fa-briefcase',
    component: JobsPage,
  },
  press: {
    name: 'Presse & Médias',
    description: 'Articles et communiqués',
    icon: 'fas fa-newspaper',
    component: PressPage,
  },
  digital: {
    name: 'Présence digitale',
    description: 'Réseaux sociaux et stratégie numérique',
    icon: 'fas fa-globe',
    component: ProfilePage, // Included in profile
  },
  csr: {
    name: 'Responsabilité sociale',
    description: 'RSE et développement durable',
    icon: 'fas fa-leaf',
    component: CsrPage,
  },
}

const sectionConfig = computed(() => {
  if (!props.section) return null
  return sections[props.section]
})

const sectionComponent = computed(() => {
  return sectionConfig.value?.component || null
})

const close = () => {
  emit('update:modelValue', false)
  emit('update:section', null)

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
      emit('update:section', newSection as TaskType)
      emit('update:modelValue', true)
    } else if (!newSection && props.modelValue) {
      emit('update:modelValue', false)
      emit('update:section', null)
    }
  },
  { immediate: true },
)

// Watch for modal close to update URL
watch(
  () => props.modelValue,
  (isOpen) => {
    if (!isOpen) {
      const query = { ...route.query }
      delete query.section
      if (JSON.stringify(query) !== JSON.stringify(route.query)) {
        router.replace({ query })
      }
    }
  },
)
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

.modal-enter-active .bg-base-100,
.modal-leave-active .bg-base-100 {
  transition: transform 0.3s ease;
}

.modal-enter-from .bg-base-100,
.modal-leave-to .bg-base-100 {
  transform: scale(0.95);
}
</style>
