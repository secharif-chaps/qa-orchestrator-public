<template>
  <div class="bg-bg1 rounded-lg p-4 h-full">
    <div class="flex justify-between items-center">
      <div class="flex space-x-6 items-center">
        <div class="bg-primary w-24 h-24 rounded-full min-w-24 flex items-center justify-center">
          <i class="fa fa-building text-5xl text-white"></i>
        </div>

        <div class="space-y-2">
          <p class="font-bold capitalize">
            {{ company?.name }}
          </p>

          <!-- Catchphrase - individual property loading -->
          <p
            class="max-w-lg text-secondary font-bold"
            :title="getSourcedSource(company?.profile?.catchphrase)"
          >
            {{ getSourcedValue(company?.profile?.catchphrase) }}
            <Source :sourced-value="company?.profile?.catchphrase" />
          </p>

          <!-- CEO - individual property loading -->
          <div class="flex items-center gap-2">
            <i class="fa fa-user-tie text-primary"></i>
            <span class="font-semibold">CEO:</span>
            <span class="text-secondary">
              {{ getSourcedValue(company?.profile?.ceo) || 'Not found ' }}
              <Source :sourced-value="company?.profile?.ceo" />
            </span>
          </div>

          <!-- HQ - individual property loading -->
          <div class="flex items-center gap-2">
            <i class="fa fa-map-marker text-primary"></i>
            <span class="font-semibold">HQ:</span>
            <span class="text-secondary">
              {{ getSourcedValue(company?.profile?.hq) || 'Not found' }}
              <Source :sourced-value="company?.profile?.hq" />
            </span>
          </div>

          <!-- Social Media - individual property loading -->
          <div class="space-x-2 text-primary">
            <div class="space-x-2 text-primary">
              <a
                target="_blank"
                v-for="platform in company?.digital?.socialMedia"
                :key="platform.name"
                :href="getSourcedValue(platform.url) as string"
                :title="getSourcedSource(platform.url)"
              >
                <i class="fa" :class="getIcon(platform.name)"></i>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { useRoute } from 'vue-router'
import { computed } from 'vue'
import { getSourcedSource, getSourcedValue } from '@/components/helpers/sourcedValues'
import Source from '../Source.vue'

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

const { data: company } = useQuery(companyByIdQuery, () => ({
  id: companyId.value,
}))

const getIcon = (media: string) => {
  switch (media.toLowerCase()) {
    case 'facebook':
      return `fa-facebook`
    case 'twitter':
      return `fa-twitter`
    case 'instagram':
      return `fa-instagram`
    case 'linkedin':
      return `fa-linkedin`
    case 'youtube':
      return `fa-youtube`
    case 'tiktok':
      return `fab fa-tiktok`
    case 'pinterest':
      return `fa-pinterest`
    case 'snapchat':
      return `fa-snapchat`
    case 'telegram':
      return `fa-telegram`
    case 'whatsapp':
      return `fa-whatsapp`
    case 'discord':
      return `fa-discord`
    case 'reddit':
      return `fa-reddit`
    case 'twitch':
      return `fa-twitch`
    case 'github':
      return `fa-github`
    case 'gitlab':
      return `fa-gitlab`
    case 'bitbucket':
      return `fa-bitbucket`
    default:
      return `fa-globe`
  }
}
</script>
