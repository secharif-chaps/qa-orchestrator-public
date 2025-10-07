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
          <div class="flex  flex-col gap-4">
          <div class="flex items-start space-x-3">
            <!-- Avatar -->
            <div
              :class="[
                'w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0',
                module.unlocked
                  ? 'bg-sage-600 text-white'
                  : 'bg-gray-200 dark:bg-gray-700 text-gray-400',
              ]"
            >
              <i :class="[module.icon, 'text-sm']"></i>
            </div>

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
            <Badge
              :variant="
                module.unlocked
                  ? 'success'
                  : module.status === 'contact-sales'
                    ? 'warning'
                    : 'slate'
              "
              :label="
                module.unlocked
                  ? 'Active'
                  : module.status === 'contact-sales'
                    ? 'Pro Feature'
                    : 'Coming Soon'
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
              label="Contact Sales"
              icon="fa-solid fa-envelope"
              @click="handleContactSales(module)"
            />
            <Button
              v-else-if="module.status === 'coming-soon'"
              variant="secondary"
              size="sm"
              label="Coming Soon"
              icon="fa-solid fa-clock"
              disabled
            />
            <Button
              v-else-if="module.status === 'external'"
              variant="secondary"
              size="sm"
              label="Open"
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
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import Badge from '@/components/ui/Badge.vue'
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

const modules = ref<Module[]>([
  {
    name: 'Screen',
    description:
      'Deep company intelligence and comprehensive business screening with advanced analytics',
    category: 'Business Intelligence',
    icon: 'fa-solid fa-magnifying-glass',
    unlocked: true,
    soon: false,
    status: 'available',
    favorite: false,
  },
  {
    name: 'Target',
    description: 'AI-powered market watch with smart alerts and comprehensive monitoring tools',
    category: 'Market Analysis',
    icon: 'fa-solid fa-bullseye',
    unlocked: false,
    soon: false,
    status: 'contact-sales',
    favorite: true,
  },
  {
    name: 'Explore',
    description: 'Interactive knowledge graph for advanced data visualization and discovery',
    category: 'Cartography',
    icon: 'fa-solid fa-project-diagram',
    unlocked: false,
    soon: true,
    status: 'coming-soon',
    favorite: false,
  },
  {
    name: 'Discover',
    description: 'Share strategic insights',
    category: 'Search Data',
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
