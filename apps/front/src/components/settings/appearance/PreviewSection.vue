<template>
  <div class="border-primary-lighter-stroke rounded-sm border bg-white">
    <div class="border-primary-lighter-stroke border-b px-6 py-4">
      <h2 class="text-lg font-semibold">{{ $t('settings.appearance.preview.title') }}</h2>
      <p class="text-neutral-black-font mt-1 text-sm">
        {{ $t('settings.appearance.preview.description') }}
      </p>
    </div>
    <div class="px-6 py-6">
      <!-- Preview Container -->
      <div
        class="border-primary-lighter-stroke dark:bg-primary-lighter rounded-sm border bg-white p-6"
      >
        <!-- Preview Header -->
        <div class="mb-6">
          <h3 class="mb-2 text-lg font-semibold">
            {{ $t('settings.appearance.preview.sample') }}
          </h3>
          <p class="text-neutral-black-font text-sm">
            {{ $t('settings.appearance.preview.interfaceDescription') }}
          </p>
        </div>

        <!-- Sample Card -->
        <div class="border-primary-lighter-stroke mb-6 rounded-sm border bg-white p-4">
          <div class="mb-4 flex items-start justify-between">
            <div>
              <h4 class="font-medium">
                {{ $t('settings.appearance.preview.card.title') }}
              </h4>
              <p class="text-neutral-black-font mt-1 text-sm">
                {{ $t('settings.appearance.preview.card.description') }}
              </p>
            </div>
            <div class="flex gap-2">
              <Tag intent="success" :label="$t('settings.appearance.preview.tag1')" />
              <Tag intent="info" :label="$t('settings.appearance.preview.tag2')" />
            </div>
          </div>

          <!-- Sample Form Elements -->
          <div class="space-y-4">
            <!-- Input Field -->
            <div>
              <label class="mb-2 block text-sm font-medium">{{
                $t('settings.appearance.preview.input.label')
              }}</label>
              <input
                v-model="previewInputValue"
                type="text"
                :placeholder="$t('settings.appearance.preview.input.placeholder')"
                class="text-neutral-black-font focus:ring-primary w-full rounded-sm border border-slate-300 bg-white px-3 py-2 placeholder-slate-400 focus:border-transparent focus:ring-2 focus:outline-none dark:border-slate-600 dark:placeholder-slate-500"
              />
            </div>

            <!-- Toggle Switch -->
            <div class="flex items-center justify-between">
              <div>
                <label class="text-sm font-medium">{{
                  $t('settings.appearance.preview.toggle.label')
                }}</label>
                <p class="text-neutral-black-font text-sm">
                  {{ $t('settings.appearance.preview.toggle.description') }}
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
                :label="$t('settings.appearance.preview.buttons.primary')"
                variant="primary"
              />
              <Button
                :label="$t('settings.appearance.preview.buttons.secondary')"
                variant="secondary"
              />
              <Button
                :label="$t('settings.appearance.preview.buttons.danger')"
                variant="tertiary"
              />
            </div>

            <!-- Status Indicators -->
            <div class="flex flex-wrap gap-2">
              <div class="flex items-center gap-2">
                <div class="h-2 w-2 rounded-full bg-green-500"></div>
                <span class="text-neutral-black-font text-sm">{{
                  $t('settings.appearance.preview.status.active')
                }}</span>
              </div>
              <div class="flex items-center gap-2">
                <div class="h-2 w-2 rounded-full bg-yellow-500"></div>
                <span class="text-neutral-black-font text-sm">{{
                  $t('settings.appearance.preview.status.pending')
                }}</span>
              </div>
              <div class="flex items-center gap-2">
                <div class="h-2 w-2 rounded-full bg-red-500"></div>
                <span class="text-neutral-black-font text-sm">{{
                  $t('settings.appearance.preview.status.error')
                }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Sample List -->
        <div class="border-primary-lighter-stroke overflow-hidden rounded-sm border bg-white">
          <div class="border-primary-lighter-stroke border-b px-4 py-3">
            <h4 class="text-sm font-medium">
              {{ $t('settings.appearance.preview.list.title') }}
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
                    <i :class="item.icon" class="text-neutral-black-font text-sm"></i>
                  </div>
                  <div>
                    <p class="text-sm font-medium">{{ item.title }}</p>
                    <p class="text-neutral-black-font text-xs">{{ item.description }}</p>
                  </div>
                </div>
                <div class="text-neutral-black-font text-xs">{{ item.time }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Button, Tag } from '@owlint/feathers-vue'
import { Switch } from 'reka-ui/namespaced'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

// Preview component data
const previewInputValue = ref('Sample text input')
const previewToggleValue = ref(true)
const previewItems = ref([
  {
    title: t('common.preview.items.newMessage'),
    description: t('common.preview.items.newMessageDesc'),
    icon: 'fas fa-envelope',
    time: t('common.time.minutesAgo', { count: 2 }),
  },
  {
    title: t('common.preview.items.systemUpdate'),
    description: t('common.preview.items.systemUpdateDesc'),
    icon: 'fas fa-download',
    time: t('common.time.hoursAgo', { count: 1 }),
  },
  {
    title: t('common.preview.items.profileComplete'),
    description: t('common.preview.items.profileCompleteDesc'),
    icon: 'fas fa-check-circle',
    time: t('common.time.hoursAgo', { count: 3 }),
  },
])
</script>
