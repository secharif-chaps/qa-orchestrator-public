<template>
  <Toggle v-model="model" size="sm" type="multiple" :options="filterOptions" variant="pill" />
</template>

<script setup lang="ts">
import { Toggle } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const model = defineModel<string>({ required: true })
const { t } = useI18n()

const filterOptions = computed(() => [
  { value: 'all', label: t('stream.filter.all'), icon: 'fas fa-paper-plane' },
  { value: 'slack_webhook', label: t('stream.filter.slack'), icon: 'fab fa-slack' },
  { value: 'teams', label: t('stream.filter.teams'), icon: 'fab fa-microsoft' },
  { value: 'webhook', label: t('stream.filter.webhook'), icon: 'fas fa-plug' },
])
</script>
