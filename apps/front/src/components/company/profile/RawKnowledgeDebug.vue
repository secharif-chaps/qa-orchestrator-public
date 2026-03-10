<template>
  <div v-if="hasAnyRawData" class="col-span-12 space-y-4">
    <h2 class="text-secondary flex items-center gap-2 text-xl font-semibold">
      <i class="fa fa-code text-lg"></i>
      {{ t('profile.sections.debug.title') }}
    </h2>

    <!-- Mistral Knowledge -->
    <Card v-if="company?.raw_mistral_knowledge" class="bg-base-200">
      <template #header>
        <div class="flex items-center gap-2">
          <i class="fa fa-brain text-accent"></i>
          <h3 class="font-semibold">{{ t('profile.sections.debug.mistral') }}</h3>
        </div>
      </template>
      <div class="bg-base-100 text-secondary overflow-x-auto rounded-lg p-4 font-mono text-sm">
        <pre class="break-words whitespace-pre-wrap">{{ company.raw_mistral_knowledge }}</pre>
      </div>
    </Card>

    <!-- GPT Knowledge -->
    <Card v-if="company?.raw_gpt_knowledge" class="bg-base-200">
      <template #header>
        <div class="flex items-center gap-2">
          <i class="fa fa-robot text-info"></i>
          <h3 class="font-semibold">{{ t('profile.sections.debug.gpt') }}</h3>
        </div>
      </template>
      <div class="bg-base-100 text-secondary overflow-x-auto rounded-lg p-4 font-mono text-sm">
        <pre class="break-words whitespace-pre-wrap">{{ company.raw_gpt_knowledge }}</pre>
      </div>
    </Card>

    <!-- Wikipedia Knowledge -->
    <Card v-if="company?.raw_wikipedia_knowledge" class="bg-base-200">
      <template #header>
        <div class="flex items-center gap-2">
          <i class="fa fa-book text-warning"></i>
          <h3 class="font-semibold">{{ t('profile.sections.debug.wikipedia') }}</h3>
        </div>
      </template>
      <div class="bg-base-100 text-secondary overflow-x-auto rounded-lg p-4 font-mono text-sm">
        <pre class="break-words whitespace-pre-wrap">{{ company.raw_wikipedia_knowledge }}</pre>
      </div>
    </Card>

    <!-- Scraped Website Knowledge -->
    <Card v-if="company?.raw_scraped_website_knowledge" class="bg-base-200">
      <template #header>
        <div class="flex items-center gap-2">
          <i class="fa fa-globe text-success"></i>
          <h3 class="font-semibold">{{ t('profile.sections.debug.website') }}</h3>
        </div>
      </template>
      <div class="bg-base-100 text-secondary overflow-x-auto rounded-lg p-4 font-mono text-sm">
        <pre class="break-words whitespace-pre-wrap">{{
          company.raw_scraped_website_knowledge
        }}</pre>
      </div>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useQuery } from '@pinia/colada'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { companyByIdQuery } from '@/queries/companies'
import Card from '@/components/ui/Card.vue'

const { t } = useI18n()
const route = useRoute()

const companyId = computed(() => String((route.params as Record<string, string>).companyId || ''))

const { data: company } = useQuery(companyByIdQuery, () => ({
  id: companyId.value,
}))

const hasAnyRawData = computed(() => {
  return (
    company.value?.raw_mistral_knowledge ||
    company.value?.raw_gpt_knowledge ||
    company.value?.raw_wikipedia_knowledge ||
    company.value?.raw_scraped_website_knowledge
  )
})
</script>
