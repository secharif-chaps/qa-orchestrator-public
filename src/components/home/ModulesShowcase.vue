<template>
  <div class="space-y-6">
    <!-- Modules Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
      <Card
        v-for="module in modules"
        :key="module.name"
        hoverable
        clickable
        :disabled="module.soon"
      >
        <!-- Card Header with Avatar and Title -->
        <div class="flex flex-col gap-4 justify-between h-full">
          <div class="flex flex-col gap-4">
            <div class="flex items-start space-x-3">
              <!-- Avatar Badge -->
              <Badge
                variant="primary"
                :color="module.unlocked ? 'primary' : 'slate'"
                :icon="module.icon"
                size="md"
              />

              <!-- Title and Secondary Text -->
              <div class="flex-1 min-w-0">
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">
                  {{ module.name }}
                </h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                  {{ module.category }}
                </p>
              </div>
              <div>
                <Tag
                  :variant="
                    module.unlocked
                      ? 'success'
                      : module.status === 'contact-sales'
                        ? 'warning'
                        : 'slate'
                  "
                  :label="
                    module.unlocked
                      ? $t('home.modules.status.active', 'Active')
                      : module.status === 'contact-sales'
                        ? $t('home.modules.status.proFeature', 'Pro Feature')
                        : $t('home.modules.status.comingSoon', 'Coming Soon')
                  "
                  size="xs"
                  rounded
                />
              </div>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
              {{ module.description }}
            </p>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="pb-6 flex items-center justify-between">
          <div class="flex space-x-2">
            <Button
              v-if="module.status === 'contact-sales'"
              variant="secondary"
              color="warning"
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
              @click="handleModuleAction(module)"
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
import Badge from '@/components/ui/Badge.vue'
import Tag from '@/components/ui/Tag.vue'
import Button from '@/components/ui/Button.vue'
import Card from '../ui/Card.vue'

interface Module {
  name: string
  description: string
  category: string
  icon: string
  unlocked: boolean
  soon: boolean
  status: 'contact-sales' | 'coming-soon' | 'available' | 'external'
  favorite: boolean
}

const router = useRouter()
const { t } = useI18n()

const modules = computed<Module[]>(() => [
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
  },
  {
    name: t('home.modules.discover.name', 'Discover'),
    description: t('home.modules.discover.description', 'Share strategic insights'),
    category: t('home.modules.discover.category', 'Search Data'),
    icon: 'fa-solid fa-rss',
    unlocked: true,
    soon: false,
    status: 'external',
    favorite: false,
  },
])

const handleModuleAction = (module: Module) => {
  if (module.name === 'Screen' && module.unlocked) {
    router.push('/folders')
  }
}

const handleContactSales = (module: Module) => {
  // Implement contact sales functionality
  console.log('Contact sales for:', module.name)
}

const handleShare = (module: Module) => {
  // Implement share functionality
  console.log('Share module:', module.name)
}

const handleFavorite = (module: Module) => {
  module.favorite = !module.favorite
}
</script>
