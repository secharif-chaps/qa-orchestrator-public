<template>
  <div class="flex items-center gap-2" @click.stop>
    <Logo
      v-if="showLogo && domain"
      :domain
      :alt
      :width="18"
      :height="18"
      class="size-4 rounded-full"
    />
    <OPopper placement="top" class="tooltip-wrapper">
      <template #tooltip>
        {{ $t('common.openInNewTab', { url: href }) }}
      </template>
      <Link
        v-if="label || domain"
        :href="href"
        target="_blank"
        icon-right="fa-up-right-from-square"
        :size
      >
        {{ label ?? domain }}
      </Link>
      <Link v-else :href="href" target="_blank">
        <Icon icon="fa-up-right-from-square" />
      </Link>
    </OPopper>
  </div>
</template>

<script setup lang="ts">
import { Link, OPopper, Icon } from '@owlint/feathers-vue'
import { computed } from 'vue'
import Logo from '~/components/global/Logo.vue'

interface Props {
  domain?: string
  alt?: string
  url?: string
  label?: string
  showLogo?: boolean
  size?: 'sm' | 'lg'
}

const {
  domain = undefined,
  alt = '',
  showLogo = true,
  size = 'lg',
  url = undefined,
  label = undefined,
} = defineProps<Props>()

const href = computed(() => {
  return url ?? 'https://' + domain
})
</script>
