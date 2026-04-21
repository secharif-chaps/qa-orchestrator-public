<template>
  <div>
    <div class="flex flex-col gap-6">
      <!-- Page header -->
      <div class="flex flex-col gap-2">
        <div class="flex items-center gap-3">
          <Button
            variant="tertiary"
            icon="fa-arrow-left"
            icon-only
            size="sm"
            @click="router.back()"
          />
          <h1 class="text-2xl font-bold">{{ t('stream.form.createTitle') }}</h1>
        </div>
        <p class="text-secondary pl-11">{{ t('stream.form.createDescription') }}</p>
      </div>

      <!-- Form -->
      <Card>
        <StreamForm :is-submitting="isCreating" @submit="handleCreate" @cancel="router.back()" />
      </Card>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - stream.write
</route>

<script setup lang="ts">
import StreamForm from '@/components/streams/StreamForm.vue'
import Card from '@/components/ui/Card.vue'
import { Button } from '@owlint/feathers-vue'
import { useCreateStream } from '@/mutations/streams'
import { buildStreamCreate } from '@/types/stream'
import type { ChannelConfig, ChannelType, StreamMode } from '@/types/stream'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute('/folders/[folderId]/streams/create')
const router = useRouter()
const { t } = useI18n()

const { createStream, isLoading: isCreating } = useCreateStream()

const handleCreate = async (data: {
  name: string
  description: string | null
  channel_type: ChannelType
  channel_config: ChannelConfig
  mode: StreamMode
  subscribed_events: string[]
}) => {
  const folderId = route.params.folderId as string
  try {
    await createStream({
      folderId,
      data: buildStreamCreate(data),
    })
    router.push(`/folders/${folderId}/streams`)
  } catch {
    // Error toast already shown by mutation's onError handler
  }
}
</script>
