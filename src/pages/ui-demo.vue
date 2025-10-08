<template>
  <div class="min-h-screen">
    <div class="">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold mb-2">UI Components Showcase</h1>
        <p class="text-primary-light-content">Custom theme-aware components for the application</p>
      </div>

      <!-- Tab Navigation -->
      <div class="bg-base-100 border border-primary-stroke rounded-lg overflow-hidden">
        <div class="border-b border-primary-stroke">
          <div class="flex">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              :class="[
                'px-6 py-3 text-sm font-medium transition-all relative border-b-2',
                activeTab === tab.id
                  ? 'text-primary-light-content bg-primary/5  border-primary'
                  : 'text-primary-light-content  hover:bg-base-200/50 border-transparent',
              ]"
            >
              <i :class="tab.icon" class="mr-2"></i>
              {{ tab.label }}
            </button>
          </div>
        </div>

        <!-- Tab Content -->
        <div class="p-8">
          <BadgeDemo v-if="activeTab === 'badge-circle'" />
          <TagDemo v-if="activeTab === 'badge'" />
          <IndicatorDemo v-if="activeTab === 'indicator'" />
          <AlertDemo v-if="activeTab === 'alert'" />
          <ButtonDemo v-if="activeTab === 'button'" />
          <InputDemo v-if="activeTab === 'input'" />
          <ExamplesDemo v-if="activeTab === 'examples'" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import BadgeDemo from '@/components/features/ui-demo/BadgeDemo.vue'
import TagDemo from '@/components/features/ui-demo/TagDemo.vue'
import IndicatorDemo from '@/components/features/ui-demo/IndicatorDemo.vue'
import AlertDemo from '@/components/features/ui-demo/AlertDemo.vue'
import ButtonDemo from '@/components/features/ui-demo/ButtonDemo.vue'
import InputDemo from '@/components/features/ui-demo/InputDemo.vue'
import ExamplesDemo from '@/components/features/ui-demo/ExamplesDemo.vue'

// Tab management
const activeTab = ref('badge-circle')
const tabs = [
  { id: 'badge-circle', label: 'Badge', icon: 'fa fa-circle' },
  { id: 'badge', label: 'Tag', icon: 'fa fa-tag' },
  { id: 'indicator', label: 'Indicator', icon: 'fa fa-circle-dot' },
  { id: 'alert', label: 'Alert', icon: 'fa fa-exclamation-circle' },
  { id: 'input', label: 'Input', icon: 'fa fa-keyboard' },
  { id: 'button', label: 'Button', icon: 'fa fa-hand-pointer' },
  { id: 'examples', label: 'Examples', icon: 'fa fa-flask' },
]
</script>
