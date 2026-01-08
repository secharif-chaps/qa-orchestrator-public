<template>
  <div>
    <!-- Header with background pattern -->
    <Card class="relative">
      <!-- Background pattern overlay using CSS gradient instead of SVG -->
      <div class="absolute inset-0 opacity-10">
        <div
          class="absolute inset-0 bg-gradient-to-br from-primary/5 via-transparent to-primary/5"
        ></div>
      </div>

      <div class="relative p-6">
        <div class="flex items-start gap-6">
          <!-- Logo Section -->
          <div class="relative group">
            <div
              class="absolute -inset-1 bg-gradient-to-r from-primary/20 to-primary/10 rounded-2xl blur opacity-25 group-hover:opacity-75 transition duration-300"
            ></div>
            <div
              class="relative w-20 h-20 rounded-xl overflow-hidden bg-white ring-2 ring-primary-stroke"
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
          </div>

          <!-- Company Info Section -->
          <div class="flex-1">
            <!-- Company Name & Catchphrase -->
            <div class="mb-4">
              <h1 class="text-2xl font-bold text-secondary mb-1">
                {{ company?.name }}
              </h1>
              <p v-if="company?.profile?.catchphrase" class="text-secondary italic text-sm">
                "{{ getSourcedValue(company?.profile?.catchphrase) }}"
                <Source :sourced-value="company?.profile?.catchphrase" />
              </p>
            </div>

            <!-- Quick Info Grid -->
            <div class="grid grid-cols-2 gap-4 mb-4">
              <!-- CEO -->
              <div class="flex items-start gap-2">
                <div
                  class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center flex-shrink-0"
                >
                  <i class="fa fa-user-tie text-secondary text-xs"></i>
                </div>
                <div class="min-w-0">
                  <p class="text-xs text-secondary/70">CEO</p>
                  <p class="text-sm font-medium text-secondary truncate">
                    {{ getSourcedValue(company?.profile?.ceo) || 'Unknown' }}
                  </p>
                </div>
              </div>

              <!-- Headquarters -->
              <div class="flex items-start gap-2">
                <div
                  class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center flex-shrink-0"
                >
                  <i class="fa fa-map-marker text-secondary text-xs"></i>
                </div>
                <div class="min-w-0">
                  <p class="text-xs text-secondary/70">Headquarters</p>
                  <p class="text-sm font-medium text-secondary truncate">
                    {{ getSourcedValue(company?.profile?.hq) || 'Unknown' }}
                  </p>
                </div>
              </div>

              <!-- Establishment Year -->
              <div v-if="company?.profile?.establishmentYear" class="flex items-start gap-2">
                <div
                  class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center flex-shrink-0"
                >
                  <i class="fa fa-calendar text-secondary text-xs"></i>
                </div>
                <div class="min-w-0">
                  <p class="text-xs text-secondary/70">Founded</p>
                  <p class="text-sm font-medium text-secondary">
                    {{ getSourcedValue(company?.profile?.establishmentYear) }}
                  </p>
                </div>
              </div>

              <!-- Business Line -->
              <div v-if="company?.profile?.businessLine" class="flex items-start gap-2">
                <div
                  class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center flex-shrink-0"
                >
                  <i class="fa fa-industry text-secondary text-xs"></i>
                </div>
                <div class="min-w-0">
                  <p class="text-xs text-secondary/70">Industry</p>
                  <p class="text-sm font-medium text-secondary truncate">
                    {{ getSourcedValue(company?.profile?.businessLine) }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Social Media Links -->
            <div
              v-if="company?.digital?.socialMediaAccounts?.length"
              class="flex items-center gap-3"
            >
              <div class="flex gap-2">
                <a
                  v-for="account in company?.digital?.socialMediaAccounts"
                  :key="account.platform"
                  :href="account.url"
                  target="_blank"
                  class="w-8 h-8 rounded-lg bg-base-100 hover:bg-primary hover:text-white text-secondary flex items-center justify-center transition-all duration-200 border border-primary-stroke hover:shadow-md hover:scale-110"
                  :title="account.platform"
                >
                  <i class="text-sm fab" :class="getIcon(account.platform)"></i>
                </a>
              </div>
            </div>
          </div>

          <!-- Stats Section (Right side) -->
          <div class="hidden lg:flex flex-col gap-3">
            <!-- Website -->
            <div v-if="company?.website" class="text-right">
              <p class="text-xs text-secondary/70 mb-1">Website</p>
              <a
                :href="formatWebsiteUrl(company?.website)"
                target="_blank"
                class="text-sm font-medium text-secondary hover:text-sage-content/80 inline-flex items-center gap-1"
              >
                <span>Visit</span>
                <i class="fa fa-external-link text-xs"></i>
              </a>
            </div>
          </div>
        </div>
      </div>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { useRoute } from 'vue-router'
import { computed, ref } from 'vue'
import { getSourcedSource, getSourcedValue } from '@/components/helpers/sourcedValues'
import Source from '../Source.vue'
import Card from '@/components/ui/Card.vue'

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

const { data: company } = useQuery(companyByIdQuery, () => ({
  id: companyId.value,
}))

const showFallbackIcon = ref(false)

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
