<template>
  <div class="bg-base-100 border border-primary-stroke rounded-lg">
    <div class="px-6 py-6">
      <div class="flex flex-wrap gap-4">
        <Button
          :label="$t('settings.profile.actions.refresh')"
          icon="fa fa-refresh"
          variant="primary"
          :loading="refreshing"
          @click="handleRefreshUser"
        />
        <Button
          :label="$t('settings.profile.actions.signOut')"
          icon="fa fa-sign-out-alt"
          variant="secondary"
          @click="handleSignOut"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Button } from '@owlint/feathers-vue'

interface Props {
  refreshing: boolean
}

defineProps<Props>()

const emit = defineEmits<{
  refreshUser: []
  signOut: []
}>()

const handleRefreshUser = () => {
  emit('refreshUser')
}

const handleSignOut = () => {
  emit('signOut')
}
</script>
