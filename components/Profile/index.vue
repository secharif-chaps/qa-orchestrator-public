<template>
  <Card class="h-full flex flex-col justify-center">
    <div class="flex justify-between items-center">
      <div class="flex space-x-6 items-center">
        <div
          class="bg-emerald-500 w-24 h-24 rounded-full min-w-24 flex items-center justify-center"
        >
          <i class="fa fa-building text-5xl text-emerald-300"></i>
        </div>
        <div class="space-y-2">
          <p class="font-bold">
            {{ company?.profile?.name || companyName }}
          </p>

          <!-- Catchphrase - individual property loading -->
          <p
            v-if="hasPropertyBeenUpdated('profile.catchphrase')"
            class="max-w-lg text-secondary font-bold"
          >
            {{ company?.profile?.catchphrase }}
          </p>
          <p
            v-else
            class="text-secondary italic"
          >
            Loading catchphrase...
          </p>

          <!-- Social Media - individual property loading -->
          <div
            v-if="hasPropertyBeenUpdated('social_media')"
            class="space-x-2 text-primary"
          >
            <NuxtLink
              target="_blank"
              v-for="(url, media) in company?.social_media"
              :to="url"
            >
              <i
                class="fa"
                :class="getIcon(media)"
              >
              </i>
            </NuxtLink>
          </div>
          <div
            v-else
            class="text-secondary italic"
          >
            Loading social media...
          </div>
        </div>
      </div>
      <div class="lg:flex gap-2 hidden">
        <div
          class="bg-slate-100 w-24 h-24 rounded-lg flex items-center justify-center"
        >
          <i class="fa fa-image text-5xl text-slate-200"></i>
        </div>
        <div
          class="bg-slate-100 w-24 h-24 rounded-lg flex items-center justify-center"
        >
          <i class="fa fa-image text-5xl text-slate-200"></i>
        </div>
        <div
          class="bg-slate-100 w-24 h-24 rounded-lg flex items-center justify-center"
        >
          <i class="fa fa-image text-5xl text-slate-200"></i>
        </div>
      </div>
    </div>
  </Card>
</template>

<script setup lang="ts">
const { company, companyName, hasPropertyBeenUpdated } = useCompanyData()

const getIcon = (media: string) => {
  switch (media) {
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
