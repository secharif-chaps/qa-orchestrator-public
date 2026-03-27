<template>
  <ItemCard
    :variant="variant"
    :domain="source.primaryDomain"
    :name="source.name"
    :description="source.description?.[shortLocale] || source.description?.en"
    :date="source.createdAt"
  >
    <template v-if="variant !== 'list' && variant !== 'minimal'" #status>
      <span class="text-sm">
        {{ statusText }}
      </span>
      <Bullet :intent="sourceColor" />
    </template>

    <template v-if="variant !== 'minimal'" #footer>
      <UrlDomain
        :show-logo="false"
        size="sm"
        :domain="source.primaryDomain"
        :url="source.url"
        :alt="source.name"
        :label="t('target.watchFiles.sources.link_label')"
        class="text-base-alt"
      />
      <div v-if="source.actor" class="text-base-alt text-sm">
        {{ t('target.watchFiles.sources.actor', { label: source.actor.label }) }}
      </div>
    </template>

    <template v-if="variant !== 'minimal'" #action>
      <Button
        variant="tertiary"
        size="sm"
        icon="fa-magnifying-glass-waveform"
        @click="onMonitoringClick"
      >
        {{ t('target.watchFiles.activity.sources.action.monitoring') }}
      </Button>
      <SourceCardDrawer v-model="isDrawerOpen" :source="source" />
    </template>
  </ItemCard>
</template>

<script setup lang="ts">
import { Bullet, Button } from '@owlint/feathers-vue'
import ItemCard from '@target/components/global/ItemCard.vue'
import UrlDomain from '@target/components/global/UrlDomain.vue'
import SourceCardDrawer from '@target/components/sources/SourceCardDrawer.vue'
import { useLocalized } from '@target/composables/useLocalized'
import type { Source } from '@target/types/source'
import { CollectorStatus } from '@target/types/source'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const { shortLocale } = useLocalized()

interface Props {
  source: Source
  variant?: 'compact' | 'detail' | 'list' | 'minimal'
}

const { source, variant = undefined } = defineProps<Props>()

const statusText = computed(() => {
  const statusMap: Record<string, string> = {
    running: t('target.watchFiles.activity.sources.status.running'),
    error: t('target.watchFiles.activity.sources.status.error'),
    stopped: t('target.watchFiles.activity.sources.status.stopped'),
  }
  return statusMap[source.collectStatus] || t('target.watchFiles.activity.sources.status.stopped')
})

const sourceColor = computed(() => {
  switch (source.collectStatus) {
    case CollectorStatus.ERROR:
      return 'danger'
    case CollectorStatus.RUNNING:
      return 'success'
    case CollectorStatus.STOPPED:
      return 'warning'
    default:
      return 'danger'
  }
})

const isDrawerOpen = ref(false)

const onMonitoringClick = () => {
  isDrawerOpen.value = true
}
</script>
