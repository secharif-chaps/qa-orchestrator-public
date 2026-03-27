<template>
  <div class="bg-accent-light rounded-full px-8 py-4">
    <p class="text-accent-light-content text-center font-medium">
      {{ $t('settings.credits.balance.current', 'Solde actuel') }} : {{ formattedBalance }}
      {{ $t('settings.credits.unit', 'crédits') }}
    </p>
  </div>
</template>

<script setup lang="ts">
/**
 * Balance banner showing current credit balance.
 * Displays in a success-themed card with prominent balance display.
 */
import { computed } from 'vue'

interface Props {
  balance: number
}

const props = defineProps<Props>()

const formattedBalance = computed(() => {
  return props.balance.toLocaleString()
})
</script>
