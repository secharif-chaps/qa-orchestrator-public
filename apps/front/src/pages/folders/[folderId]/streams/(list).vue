<template>
  <div class="flex flex-col gap-4">
    <!-- Filter pills row -->
    <div class="flex items-center justify-between">
      <p class="text-secondary text-sm">
        {{ t('stream.list.count', { count: streamsData?.meta?.total || 0 }) }}
      </p>

      <StreamFilterPills v-model="channelFilter" />
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <div class="border-primary h-10 w-10 animate-spin rounded-full border-b-2"></div>
    </div>

    <!-- Content -->
    <template v-else>
      <!-- Grid view -->
      <div
        v-if="viewMode === 'grid' && filteredStreams.length > 0"
        class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3"
      >
        <StreamCard
          v-for="stream in filteredStreams"
          :key="stream.id"
          :stream="stream"
          :can-write="canWriteStreams"
          @pause="handlePause"
          @activate="handleActivate"
          @delete="handleDelete"
        />
      </div>

      <!-- Table view -->
      <StreamTable
        v-else-if="viewMode === 'table' && filteredStreams.length > 0"
        :streams="filteredStreams"
        :can-write="canWriteStreams"
        @pause="handlePause"
        @activate="handleActivate"
        @delete="handleDelete"
      />

      <!-- Empty state -->
      <Alert
        v-if="filteredStreams.length === 0 && searchTerm"
        variant="info"
        icon="fa-search"
        :title="t('stream.list.noResults')"
      />

      <Alert
        v-else-if="filteredStreams.length === 0 && !searchTerm"
        variant="info"
        icon="fa-paper-plane"
        class="py-6"
        :title="t('stream.list.empty.title')"
        :description="t('stream.list.empty.description')"
      >
        <template #actions>
          <Button
            v-if="canWriteStreams"
            variant="primary"
            icon="fa-plus"
            :label="t('stream.list.empty.cta')"
            @click="router.push(`/folders/${route.params.folderId}/streams/create`)"
          />
        </template>
      </Alert>
    </template>

    <!-- Delete confirmation modal -->
    <StreamDeleteModal
      v-model="showDeleteModal"
      :stream-name="streamToDelete?.name ?? ''"
      :is-deleting="isDeletingStream"
      @confirm="handleConfirmDelete"
    />
  </div>
</template>

<script setup lang="ts">
import StreamCard from '@/components/streams/StreamCard.vue'
import StreamDeleteModal from '@/components/streams/StreamDeleteModal.vue'
import StreamFilterPills from '@/components/streams/StreamFilterPills.vue'
import StreamTable from '@/components/streams/StreamTable.vue'
import { useStreamPermissions } from '@/composables/useStreamPermissions'
import { useDeleteStream, useUpdateStreamStatus } from '@/mutations/streams'
import { streamsByFolderQuery } from '@/queries/streams'
import type { Folder } from '@/types/folder'
import type { StreamRead } from '@/types/stream'
import { Alert, Button } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

// Props received from the layout parent
interface Props {
  folder: Folder
  searchTerm: string
  viewMode: 'table' | 'grid'
}

const props = defineProps<Props>()

const route = useRoute('/folders/[folderId]/streams')
const router = useRouter()
const { t } = useI18n()
const { canWriteStreams } = useStreamPermissions()

const channelFilter = ref('all')
const showDeleteModal = ref(false)
const streamToDelete = ref<StreamRead | null>(null)

const page = ref(1)
const perPage = ref(50)

const { data: streamsData, isLoading } = useQuery(() =>
  streamsByFolderQuery({
    folderId: route.params.folderId as string,
    page: page.value,
    perPage: perPage.value,
  }),
)

const { deleteStream, isLoading: isDeletingStream } = useDeleteStream()
const { updateStreamStatus } = useUpdateStreamStatus()

// Filter streams by channel type and search term
const filteredStreams = computed(() => {
  if (!streamsData.value?.data) return []
  let streams = streamsData.value.data

  // Filter by channel type
  if (channelFilter.value !== 'all') {
    streams = streams.filter((s) => s.channel_type === channelFilter.value)
  }

  // Filter by search term
  if (props.searchTerm.trim()) {
    const query = props.searchTerm.toLowerCase()
    streams = streams.filter((s) => s.name.toLowerCase().includes(query))
  }

  return streams
})

const handlePause = async (stream: StreamRead) => {
  await updateStreamStatus({ streamId: String(stream.id), data: { status: 'paused' } })
}

const handleActivate = async (stream: StreamRead) => {
  await updateStreamStatus({ streamId: String(stream.id), data: { status: 'active' } })
}

const handleDelete = (stream: StreamRead) => {
  streamToDelete.value = stream
  showDeleteModal.value = true
}

const handleConfirmDelete = async () => {
  if (!streamToDelete.value) return
  await deleteStream({
    streamId: String(streamToDelete.value.id),
    streamName: streamToDelete.value.name,
  })
  showDeleteModal.value = false
  streamToDelete.value = null
}
</script>
