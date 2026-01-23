<template>
  <div class="space-y-6">
    <!-- Modules Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
      <Card
        v-for="module in visibleModules"
        :key="module.name"
        hoverable
        clickable
        :disabled="module.soon"
        class="flex flex-col"
      >
        <!-- Card Content - grows to fill space -->
        <div class="flex-1 flex flex-col gap-4">
          <div class="flex items-start space-x-3">
            <!-- Avatar -->
            <Badge
              :color="module.unlocked ? module.color : 'sage'"
              :icon="module.icon"
              variant="secondary"
            />

            <!-- Title and Secondary Text -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between gap-2">
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">
                  {{ module.name }}
                </h3>
                <div>
                  <Tag
                    :intent="
                      module.unlocked
                        ? 'success'
                        : module.status === 'contact-sales'
                          ? 'warning'
                          : 'neutral'
                    "
                    :label="
                      module.unlocked
                        ? $t('home.modules.status.active', 'Active')
                        : module.status === 'contact-sales'
                          ? $t('home.modules.status.proFeature', 'Pro Feature')
                          : $t('home.modules.status.comingSoon', 'Coming Soon')
                    "
                    size="xs"
                  />
                </div>
              </div>
              <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ module.category }}
              </p>
            </div>
          </div>
          <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
            {{ module.description }}
          </p>
        </div>

        <!-- Action Buttons - stuck to bottom -->
        <div class="mt-4 flex items-center justify-between">
          <div class="flex space-x-2">
            <Button
              v-if="module.status === 'available'"
              variant="secondary"
              intent="warning"
              size="sm"
              :label="$t('home.modules.actions.companyScreen', 'Create a Screen')"
              icon="fa-solid fa-search"
              @click="$router.push('/companies/create')"
            />
            <Button
              v-if="module.status === 'contact-sales'"
              variant="secondary"
              intent="warning"
              size="sm"
              :label="$t('home.modules.actions.contactSales', 'Contact Sales')"
              icon="fa-solid fa-envelope"
              @click="handleContactSales(module)"
            />
            <Button
              v-else-if="module.status === 'coming-soon'"
              variant="secondary"
              size="sm"
              :label="$t('home.modules.status.comingSoon', 'Coming Soon')"
              icon="fa-solid fa-clock"
              disabled
            />
            <Button
              v-else-if="module.status === 'external'"
              variant="secondary"
              size="sm"
              :label="$t('home.modules.actions.open', 'Open')"
              icon="fa-solid fa-external-link"
              :disabled="!module.externalUrl"
              @click="handleOpenExternal(module)"
            />
          </div>
        </div>
      </Card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { Tag, Button, Badge } from '@owlint/feathers-vue'
import Card from '../ui/Card.vue'
import type { FeatureFlagConfig } from '@/types/feature-flags'

interface Module {
  name: string
  description: string
  category: string
  icon: string
  unlocked: boolean
  soon: boolean
  status: 'contact-sales' | 'coming-soon' | 'available' | 'external'
  favorite: boolean
  color: "indigo" | "sage" | "almond" | "yellow" | "pink" | "cherry" | "cyan" | undefined
  externalUrl?: string | null
}

interface Props {
  featureFlags?: FeatureFlagConfig[]
}

const props = withDefaults(defineProps<Props>(), {
  featureFlags: () => [],
})

const router = useRouter()
const { t } = useI18n()

// Find the discover feature flag
const discoverFlag = computed(() =>
  props.featureFlags.find((f) => f.flag === 'discover'),
)

// Check if discover is enabled and has a URL
const isDiscoverEnabled = computed(() => discoverFlag.value?.enabled ?? false)
const discoverUrl = computed(() => (discoverFlag.value?.config?.url as string) || null)

// Static modules (non-feature-flag modules)
const staticModules = computed<Module[]>(() => [
  {
    name: t('home.modules.screen.name', 'Screen'),
    description: t(
      'home.modules.screen.description',
      'Deep company intelligence and comprehensive business screening with advanced analytics',
    ),
    category: t('home.modules.screen.category', 'Business Intelligence'),
    icon: 'fa-solid fa-magnifying-glass',
    unlocked: true,
    soon: false,
    status: 'available',
    favorite: false,
    color: 'indigo',
  },
  {
    name: t('home.modules.target.name', 'Target'),
    description: t(
      'home.modules.target.description',
      'AI-powered market watch with smart alerts and comprehensive monitoring tools',
    ),
    category: t('home.modules.target.category', 'Market Analysis'),
    icon: 'fa-solid fa-bullseye',
    unlocked: false,
    soon: false,
    status: 'contact-sales',
    favorite: true,
    color: 'cherry',
  },
  {
    name: t('home.modules.explore.name', 'Explore'),
    description: t(
      'home.modules.explore.description',
      'Interactive knowledge graph for advanced data visualization and discovery',
    ),
    category: t('home.modules.explore.category', 'Cartography'),
    icon: 'fa-solid fa-project-diagram',
    unlocked: false,
    soon: true,
    status: 'coming-soon',
    favorite: false,
    color: 'almond',
  },
])

// Dynamic Discover module - always visible, but status depends on feature flag
const discoverModule = computed<Module>(() => {
  // When feature flag is disabled: show as "Pro Feature" with "Contact Sales" button
  // When feature flag is enabled: show as "Active" with "Open" button
  if (!isDiscoverEnabled.value) {
    return {
      name: t('home.modules.discover.name', 'Discover'),
      description: t('home.modules.discover.description', 'Share strategic insights'),
      category: t('home.modules.discover.category', 'Search Data'),
      icon: 'fa-solid fa-rss',
      unlocked: false,
      soon: false,
      status: 'contact-sales',
      favorite: false,
      color: 'yellow',
    }
  }

  return {
    name: t('home.modules.discover.name', 'Discover'),
    description: t('home.modules.discover.description', 'Share strategic insights'),
    category: t('home.modules.discover.category', 'Search Data'),
    icon: 'fa-solid fa-rss',
    unlocked: true,
    soon: false,
    status: 'external',
    favorite: false,
    color: 'yellow',
    externalUrl: discoverUrl.value,
  }
})

// Combine static modules with dynamic discover module
const visibleModules = computed<Module[]>(() => {
  return [...staticModules.value, discoverModule.value]
})

const handleOpenExternal = (module: Module) => {
  if (module.externalUrl) {
    window.open(module.externalUrl, '_blank')
  }
}

const handleContactSales = (module: Module) => {
  // Implement contact sales functionality
  console.log('Contact sales for:', module.name)
}
</script>
