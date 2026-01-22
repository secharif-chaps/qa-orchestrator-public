<template>
  <div v-if="company" class="h-full">
    <!-- Header with background pattern -->
    <Card class="h-full p-6 flex flex-col gap-4">

        <div class="flex items-start gap-6">
          <!-- Logo Section -->
            <div
              class="relative size-14 rounded-xl overflow-hidden bg-white ring-2 ring-primary-stroke"
            >
              <img
                v-if="getCompanyDomain(company?.website)"
                :src="getLogoUrl(company?.website)"
                :alt="`${company?.name} logo`"
                class="w-full h-full object-contain p-2"
                @error="showFallbackIcon = true"
                v-show="!showFallbackIcon"
              />
              <div
                v-show="showFallbackIcon || !getCompanyDomain(company?.website)"
                class="w-full h-full flex items-center justify-center bg-gradient-to-br from-primary/10 to-primary/20"
              >
                <i class="fa fa-building text-3xl text-secondary"></i>
              </div>
            </div>


          <!-- Company Info Section -->
          <div class="flex-1">
            <!-- Company Name & Catchphrase -->
            <div class="mb-4">
              <h1 class="text-2xl font-bold text-secondary mb-1">
                {{ company.name }}
              </h1>
              <p v-if="company.profile?.catchphrase" class="text-secondary italic text-sm">
                "{{ getSourcedValue(company.profile?.catchphrase) }}"
                <Source :sourced-value="company?.profile?.catchphrase" />
              </p>
            </div>

            <!-- Social Media Links -->
            <div
              class="flex items-center gap-2"
            >
            <a v-if="company.website" :href="company.website" target="_blank">
                <Button variant="tertiary"
                  icon="fa fa-globe"
                >
                </Button>
              </a>

                <a
                  v-for="account in company.digital?.socialMediaAccounts || []"
                  :key="account.platform"
                  :href="account.url"
                  target="_blank"
                  :title="account.platform"
                >
                  <Button variant="tertiary"
                    :icon="getIcon(account.platform)"
                    lib="fab"
                  >
                  </Button>
                </a>

            </div>
          </div>
        </div>

      <!-- Quick Info Grid -->
      <div class="grid grid-cols-2 gap-4 h-full">
        <ProfileInfoItem
          v-for="item in infoItems"
          :key="item.label"
          :icon="item.icon"
          :label="item.label"
          :value="item.value"
        />
      </div>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { useRoute } from 'vue-router'
import { computed, ref } from 'vue'
import { getSourcedValue } from '@/components/helpers/sourcedValues'
import Source from '../Source.vue'
import Card from '@/components/ui/Card.vue'
import ProfileInfoItem from './ProfileInfoItem.vue'
import { Button } from '@owlint/feathers-vue'

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

const { data: company } = useQuery(companyByIdQuery, () => ({
  id: companyId.value,
}))

const showFallbackIcon = ref(false)

// Quick info items for the grid
const infoItems = computed(() => [
  {
    icon: 'fa fa-user-tie',
    label: 'CEO',
    value: getSourcedValue(company.value?.profile?.ceo) as string | undefined,
  },
  {
    icon: 'fa fa-map-marker',
    label: 'Headquarters',
    value: getSourcedValue(company.value?.profile?.hq) as string | undefined,
  },
  {
    icon: 'fa fa-calendar',
    label: 'Founded',
    value: getSourcedValue(company.value?.profile?.establishmentYear) as string | undefined,
  },
  {
    icon: 'fa fa-industry',
    label: 'Industry',
    value: getSourcedValue(company.value?.profile?.businessLine) as string | undefined,
  },
])

// Format website URL
const formatWebsiteUrl = (website?: string) => {
  if (!website) return '#'
  return website.startsWith('http') ? website : `https://${website}`
}

// Helper function to extract domain from website URL
const getCompanyDomain = (website?: string) => {
  if (!website) return null
  try {
    // Remove protocol and www
    let domain = website.replace(/^https?:\/\//, '').replace(/^www\./, '')
    // Remove trailing slash and any path
    domain = domain.split('/')[0]
    return domain
  } catch {
    return null
  }
}

// Helper function to get logo URL from logo.dev
const getLogoUrl = (website?: string) => {
  const domain = getCompanyDomain(website)
  if (!domain) return ''
  return `https://img.logo.dev/${domain}?token=pk_Buf4yyXmRC2HMagyfO0jrg&retina=true`
}

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
