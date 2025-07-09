<template>
  <div class="bg-bg1 border border-border-2 rounded-lg">
    <div class="px-6 py-4 border-b border-border-2">
      <h2 class="text-lg font-semibold">{{ $t('account.appearance.preview.title') }}</h2>
      <p class="text-sm text-secondary mt-1">{{ $t('account.appearance.preview.description') }}</p>
    </div>
    <div class="px-6 py-6">
      <!-- Preview Container -->
      <div class="border border-border-2 rounded-lg p-6 bg-bg1 dark:bg-bg3">
        <!-- Preview Header -->
        <div class="mb-6">
          <h3 class="text-lg font-semibold mb-2">
            {{ $t('account.appearance.preview.sample') }}
          </h3>
          <p class="text-sm text-secondary">
            Experience how your interface looks with the current theme settings.
          </p>
        </div>

        <!-- Sample Card -->
        <div class="bg-bg1 border border-border-2 rounded-lg p-4 mb-6">
          <div class="flex items-start justify-between mb-4">
            <div>
              <h4 class="font-medium">Sample Card Title</h4>
              <p class="text-sm text-secondary mt-1">This card demonstrates the current theme styling</p>
            </div>
            <div class="flex gap-2">
              <OBadge color="green" :text="$t('account.appearance.preview.tag1')" />
              <OBadge color="blue" :text="$t('account.appearance.preview.tag2')" />
            </div>
          </div>
          
          <!-- Sample Form Elements -->
          <div class="space-y-4">
            <!-- Input Field -->
            <div>
              <label class="block text-sm font-medium mb-2">
                Sample Input Field
              </label>
              <input 
                v-model="previewInputValue"
                type="text" 
                placeholder="Type something here..."
                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-bg1 text-secondary placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
              />
            </div>

            <!-- Toggle Switch -->
            <div class="flex items-center justify-between">
              <div>
                <label class="text-sm font-medium">
                  Sample Toggle
                </label>
                <p class="text-sm text-secondary">
                  This toggle demonstrates switch styling
                </p>
              </div>
              <Switch.Root 
                v-model:checked="previewToggleValue"
                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                :class="previewToggleValue ? 'bg-primary' : 'bg-slate-200 dark:bg-slate-600'"
              >
                <Switch.Thumb class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                  :class="previewToggleValue ? 'translate-x-6' : 'translate-x-1'" />
              </Switch.Root>
            </div>

            <!-- Buttons -->
            <div class="flex flex-wrap gap-3">
              <OButton 
                label="Primary Button"
                type="primary"
                color="primary"
                icon="fas fa-star"
              />
              <OButton 
                label="Secondary Button"
                type="secondary"
                color="primary"
                icon="fas fa-cog"
              />
              <OButton 
                label="Danger Button"
                type="tertiary"
                color="primary"
                icon="fas fa-trash"
              />
            </div>

            <!-- Status Indicators -->
            <div class="flex flex-wrap gap-2">
              <div class="flex items-center gap-2">
                <div class="h-2 w-2 bg-green-500 rounded-full"></div>
                <span class="text-sm text-secondary">Active</span>
              </div>
              <div class="flex items-center gap-2">
                <div class="h-2 w-2 bg-yellow-500 rounded-full"></div>
                <span class="text-sm text-secondary">Pending</span>
              </div>
              <div class="flex items-center gap-2">
                <div class="h-2 w-2 bg-red-500 rounded-full"></div>
                <span class="text-sm text-secondary">Error</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Sample List -->
        <div class="bg-bg1 border border-border-2 rounded-lg overflow-hidden">
          <div class="px-4 py-3 border-b border-border-2">
            <h4 class="text-sm font-medium">Sample List Items</h4>
          </div>
          <div class="divide-y divide-slate-200 dark:divide-slate-700">
            <div v-for="(item, index) in previewItems" :key="index" 
                 class="px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div class="h-8 w-8 bg-primary/10 rounded-full flex items-center justify-center">
                    <i :class="item.icon" class="text-primary text-sm"></i>
                  </div>
                  <div>
                    <p class="text-sm font-medium">{{ item.title }}</p>
                    <p class="text-xs text-secondary">{{ item.description }}</p>
                  </div>
                </div>
                <div class="text-xs text-secondary">{{ item.time }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Switch } from 'reka-ui/namespaced'
import { OBadge, OButton } from '@owlint/feathers-vue'

// Preview component data
const previewInputValue = ref('Sample text input')
const previewToggleValue = ref(true)
const previewItems = ref([
  {
    title: 'New Message Received',
    description: 'You have a new message from John Doe',
    icon: 'fas fa-envelope',
    time: '2 min ago'
  },
  {
    title: 'System Update',
    description: 'Application updated to version 2.1.0',
    icon: 'fas fa-download',
    time: '1 hour ago'
  },
  {
    title: 'Profile Completed',
    description: 'Your profile setup is now complete',
    icon: 'fas fa-check-circle',
    time: '3 hours ago'
  }
])
</script>