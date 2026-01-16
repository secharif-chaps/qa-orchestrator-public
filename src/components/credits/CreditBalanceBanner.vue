<template>
  <div class="bg-accent-light rounded-full py-4 px-8">
    <p class="text-center text-accent-light-content font-medium">
      {{ $t('credits.balance.current', 'Solde actuel') }} : {{ formattedBalance }} {{ $t('credits.unit', 'crédits') }}
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
