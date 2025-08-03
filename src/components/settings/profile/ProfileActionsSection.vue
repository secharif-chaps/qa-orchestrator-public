<template>
  <div class="space-y-6">
    <!-- Debug Information (Collapsible) -->
    <Collapsible.Root v-model:open="showDebugInfo">
      <div class="bg-bg1 border border-border-2 rounded-lg overflow-hidden">
        <Collapsible.Trigger
          class="w-full px-6 py-4 border-border-2 flex items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
          :class="{
            'border-b': showDebugInfo,
          }"
        >
          <div>
            <h2 class="text-lg font-semibold">{{ $t('settings.profile.debug.title') }}</h2>
            <p class="text-sm text-secondary mt-1">
              {{ $t('settings.profile.debug.description') }}
            </p>
          </div>
          <i
            class="fas fa-chevron-down transition-transform"
            :class="showDebugInfo ? 'rotate-180' : ''"
          ></i>
        </Collapsible.Trigger>
        <Collapsible.Content class="px-6 py-6">
          <pre class="text-xs bg-slate-50 dark:bg-slate-900 p-4 rounded overflow-auto">{{
            JSON.stringify(user, null, 2)
          }}</pre>
        </Collapsible.Content>
      </div>
    </Collapsible.Root>

    <!-- Actions -->
    <div class="bg-bg1 border border-border-2 rounded-lg">
      <div class="px-6 py-6">
        <div class="flex flex-wrap gap-4">
          <OButton
            :label="$t('settings.profile.actions.refresh')"
            icon="fas fa-refresh"
            type="primary"
            color="primary"
            :loading="refreshing"
            @click="handleRefreshUser"
          />
          <OButton
            :label="$t('settings.profile.actions.signOut')"
            icon="fas fa-sign-out-alt"
            type="secondary"
            color="red"
            @click="handleSignOut"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { OButton } from '@owlint/feathers-vue'
import { Collapsible } from 'reka-ui/namespaced'
import { ref } from 'vue'

interface User {
  [key: string]: unknown
}

interface Props {
  user: User | null
  refreshing: boolean
}

defineProps<Props>()

const emit = defineEmits<{
  refreshUser: []
  signOut: []
}>()

const showDebugInfo = ref(false)

const handleRefreshUser = () => {
  emit('refreshUser')
}

const handleSignOut = () => {
  emit('signOut')
}
</script>
