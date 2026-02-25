<template>
  <div class="bg-base-100 border-primary-stroke rounded-lg border">
    <div class="border-primary-stroke border-b px-6 py-4">
      <h2 class="text-lg font-semibold">{{ $t('settings.appearance.preview.title') }}</h2>
      <p class="text-secondary mt-1 text-sm">
        {{ $t('settings.appearance.preview.description') }}
      </p>
    </div>
    <div class="px-6 py-6">
      <!-- Preview Container -->
      <div class="border-primary-stroke bg-base-100 dark:bg-base-300 rounded-lg border p-6">
        <!-- Preview Header -->
        <div class="mb-6">
          <h3 class="mb-2 text-lg font-semibold">
            {{ $t('settings.appearance.preview.sample') }}
          </h3>
          <p class="text-secondary text-sm">
            {{
              $t(
                'settings.appearance.preview.interfaceDescription',
                'Experience how your interface looks with the current theme settings.',
              )
            }}
          </p>
        </div>

        <!-- Sample Card -->
        <div class="bg-base-100 border-primary-stroke mb-6 rounded-lg border p-4">
          <div class="mb-4 flex items-start justify-between">
            <div>
              <h4 class="font-medium">
                {{ $t('settings.appearance.preview.card.title', 'Sample Card Title') }}
              </h4>
              <p class="text-secondary mt-1 text-sm">
                {{
                  $t(
                    'settings.appearance.preview.card.description',
                    'This card demonstrates the current theme styling',
                  )
                }}
              </p>
            </div>
            <div class="flex gap-2">
              <Tag variant="success" :label="$t('settings.appearance.preview.tag1')" />
              <Tag variant="info" :label="$t('settings.appearance.preview.tag2')" />
            </div>
          </div>

          <!-- Sample Form Elements -->
          <div class="space-y-4">
            <!-- Input Field -->
            <div>
              <label class="mb-2 block text-sm font-medium">{{
                $t('settings.appearance.preview.input.label', 'Sample Input Field')
              }}</label>
              <input
                v-model="previewInputValue"
                type="text"
                :placeholder="
                  $t('settings.appearance.preview.input.placeholder', 'Type something here...')
                "
                class="bg-base-100 text-secondary focus:ring-primary w-full rounded-md border border-slate-300 px-3 py-2 placeholder-slate-400 focus:border-transparent focus:ring-2 focus:outline-none dark:border-slate-600 dark:placeholder-slate-500"
              />
            </div>

            <!-- Toggle Switch -->
            <div class="flex items-center justify-between">
              <div>
                <label class="text-sm font-medium">{{
                  $t('settings.appearance.preview.toggle.label', 'Sample Toggle')
                }}</label>
                <p class="text-secondary text-sm">
                  {{
                    $t(
                      'settings.appearance.preview.toggle.description',
                      'This toggle demonstrates switch styling',
                    )
                  }}
                </p>
              </div>
              <Switch.Root
                v-model:checked="previewToggleValue"
                class="focus:ring-primary relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:ring-2 focus:ring-offset-2 focus:outline-none"
                :class="previewToggleValue ? 'bg-primary' : 'bg-slate-200 dark:bg-slate-600'"
              >
                <Switch.Thumb
                  class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                  :class="previewToggleValue ? 'translate-x-6' : 'translate-x-1'"
                />
              </Switch.Root>
            </div>

            <!-- Buttons -->
            <div class="flex flex-wrap gap-3">
              <Button
                :label="$t('settings.appearance.preview.buttons.primary', 'Primary Button')"
                variant="primary"
              />
              <Button
                :label="$t('settings.appearance.preview.buttons.secondary', 'Secondary Button')"
                variant="secondary"
              />
              <Button
                :label="$t('settings.appearance.preview.buttons.danger', 'Danger Button')"
                variant="tertiary"
              />
            </div>

            <!-- Status Indicators -->
            <div class="flex flex-wrap gap-2">
              <div class="flex items-center gap-2">
                <div class="h-2 w-2 rounded-full bg-green-500"></div>
                <span class="text-secondary text-sm">{{
                  $t('settings.appearance.preview.status.active', 'Active')
                }}</span>
              </div>
              <div class="flex items-center gap-2">
                <div class="h-2 w-2 rounded-full bg-yellow-500"></div>
                <span class="text-secondary text-sm">{{
                  $t('settings.appearance.preview.status.pending', 'Pending')
                }}</span>
              </div>
              <div class="flex items-center gap-2">
                <div class="h-2 w-2 rounded-full bg-red-500"></div>
                <span class="text-secondary text-sm">{{
                  $t('settings.appearance.preview.status.error', 'Error')
                }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Sample List -->
        <div class="bg-base-100 border-primary-stroke overflow-hidden rounded-lg border">
          <div class="border-primary-stroke border-b px-4 py-3">
            <h4 class="text-sm font-medium">
              {{ $t('settings.appearance.preview.list.title', 'Sample List Items') }}
            </h4>
          </div>
          <div class="divide-y divide-slate-200 dark:divide-slate-700">
            <div
              v-for="(item, index) in previewItems"
              :key="index"
              class="px-4 py-3 transition-colors hover:bg-slate-50 dark:hover:bg-slate-700"
            >
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div class="bg-primary/10 flex h-8 w-8 items-center justify-center rounded-full">
                    <i :class="item.icon" class="text-secondary text-sm"></i>
                  </div>
                  <div>
                    <p class="text-sm font-medium">{{ item.title }}</p>
                    <p class="text-secondary text-xs">{{ item.description }}</p>
                  </div>
                </div>
                <div class="text-secondary text-xs">{{ item.time }}</div>
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
import Tag from '@/components/ui/Tag.vue'
import { Button } from '@owlint/feathers-vue'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

// Preview component data
const previewInputValue = ref('Sample text input')
const previewToggleValue = ref(true)
const previewItems = ref([
  {
    title: t('common.preview.items.newMessage', 'New Message Received'),
    description: t('common.preview.items.newMessageDesc', 'You have a new message from John Doe'),
    icon: 'fas fa-envelope',
    time: t('common.time.minutesAgo', '{count} min ago', { count: 2 }),
  },
  {
    title: t('common.preview.items.systemUpdate', 'System Update'),
    description: t('common.preview.items.systemUpdateDesc', 'Application updated to version 2.1.0'),
    icon: 'fas fa-download',
    time: t('common.time.hoursAgo', '{count} hour ago', { count: 1 }),
  },
  {
    title: t('common.preview.items.profileComplete', 'Profile Completed'),
    description: t(
      'common.preview.items.profileCompleteDesc',
      'Your profile setup is now complete',
    ),
    icon: 'fas fa-check-circle',
    time: t('common.time.hoursAgo', '{count} hours ago', { count: 3 }),
  },
])
</script>
