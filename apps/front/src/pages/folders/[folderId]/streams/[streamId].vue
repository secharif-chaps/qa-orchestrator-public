<template>
  <div>
    <div class="flex flex-col gap-6">
      <!-- Loading -->
      <div v-if="isLoading" class="flex justify-center py-12">
        <div class="border-primary h-10 w-10 animate-spin rounded-full border-b-2"></div>
      </div>

      <!-- Error -->
      <Alert
        v-else-if="status === 'error'"
        variant="danger"
        :title="t('common.errors.unexpected')"
        :description="t('stream.toast.updateError')"
        icon="fa-exclamation-triangle"
      />

      <!-- Content -->
      <template v-else-if="stream">
        <!-- Page header -->
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <Button
              variant="tertiary"
              icon="fa-arrow-left"
              icon-only
              size="sm"
              @click="router.back()"
            />
            <h1 class="text-2xl font-bold">{{ stream.name }}</h1>
          </div>

          <div class="flex items-center gap-2">
            <!-- Status actions -->
            <Button
              v-if="stream.status === 'active'"
              variant="secondary"
              color="warning"
              icon="fa-pause"
              :label="t('stream.actions.pause')"
              @click="handleStatusChange('paused')"
            />
            <Button
              v-if="stream.status === 'paused' || stream.status === 'draft'"
              variant="secondary"
              icon="fa-play"
              :label="t('stream.actions.activate')"
              @click="handleStatusChange('active')"
            />
            <Button
              v-if="stream.status !== 'archived'"
              variant="secondary"
              icon="fa-archive"
              :label="t('stream.actions.archive')"
              @click="handleStatusChange('archived')"
            />
            <Button
              v-if="stream.status === 'active' && stream.mode === 'recurrence'"
              variant="primary"
              icon="fa-paper-plane"
              :label="t('stream.actions.dispatch')"
              :loading="isDispatching"
              @click="handleDispatch"
            />
            <!-- Tab navigation -->
            <Tab :tabs="tabOptions" />
          </div>
        </div>

        <!-- Tab content -->
        <Card>
          <StreamForm
            v-if="activeTab === 'settings'"
            :initial-data="stream"
            :is-submitting="isUpdating"
            @submit="handleUpdate"
            @cancel="router.back()"
          />

          <div v-else-if="activeTab === 'history'">
            <DeliveryHistory :stream-id="String(stream.id)" />
          </div>
        </Card>
      </template>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - stream.write
</route>

<script setup lang="ts">
import DeliveryHistory from '@/components/streams/DeliveryHistory.vue'
import StreamForm from '@/components/streams/StreamForm.vue'
import Card from '@/components/ui/Card.vue'
import { useDispatchStream, useUpdateStream, useUpdateStreamStatus } from '@/mutations/streams'
import { streamByIdQuery } from '@/queries/streams'
import type { ChannelConfig, ChannelType, StreamMode, StreamStatus } from '@/types/stream'
import { Alert, Button, Tab } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute('/folders/[folderId]/streams/[streamId]')
const router = useRouter()
const { t } = useI18n()

const activeTab = ref<'settings' | 'history'>('settings')

const tabOptions = computed(() => [
  {
    id: 'settings',
    icon: 'fa-cog',
    title: t('stream.tabs.settings'),
    isActive: activeTab.value === 'settings',
    click: () => {
      activeTab.value = 'settings'
    },
  },
  {
    id: 'history',
    icon: 'fa-history',
    title: t('stream.tabs.history'),
    isActive: activeTab.value === 'history',
    click: () => {
      activeTab.value = 'history'
    },
  },
])

const {
  data: stream,
  status,
  isLoading,
} = useQuery(() => streamByIdQuery({ streamId: route.params.streamId as string }))

const { updateStream, isLoading: isUpdating } = useUpdateStream()
const { updateStreamStatus } = useUpdateStreamStatus()
const { dispatchStream, isLoading: isDispatching } = useDispatchStream()

const handleUpdate = async (data: {
  name: string
  description: string | null
  channel_type: ChannelType
  channel_config: ChannelConfig
  mode: StreamMode
  subscribed_events: string[]
}) => {
  const streamId = route.params.streamId as string
  await updateStream({
    streamId,
    data: {
      name: data.name,
      description: data.description,
      channel_config: data.channel_config,
      subscribed_events: data.subscribed_events,
    },
  })
}

const handleStatusChange = async (newStatus: StreamStatus) => {
  const streamId = route.params.streamId as string
  await updateStreamStatus({
    streamId,
    data: { status: newStatus },
  })
}

const handleDispatch = async () => {
  const streamId = route.params.streamId as string
  await dispatchStream({ streamId })
}
</script>
