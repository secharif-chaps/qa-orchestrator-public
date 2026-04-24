<template>
  <Table :items="streams" :fields="columns" @row-clicked="navigateToEdit">
    <template #cell(name)="{ item }">
      <td class="px-4 py-3">
        <div class="flex items-center gap-3">
          <Badge size="sm" variant="secondary" :icon="getIconClass(item.channel_type)" />
          <span class="font-medium">{{ item.name }}</span>
        </div>
      </td>
    </template>

    <template #cell(updated_at)="{ value }">
      <td class="px-4 py-3">
        <span class="text-secondary text-sm">{{ getRelativeTime(value) }}</span>
      </td>
    </template>

    <template #cell(channel_type)="{ value }">
      <td class="px-4 py-3">
        <Tag :label="t(`stream.channel.${value}`)" size="sm" />
      </td>
    </template>

    <template #cell(status)="{ value }">
      <td class="px-4 py-3">
        <Tag
          :icon="value === 'active' ? 'fa-play' : 'fa-times'"
          :label="t(`stream.status.${value}`)"
          size="sm"
          color="sage"
        />
      </td>
    </template>

    <template #cell(actions)="{ item }">
      <td class="px-4 py-3 text-right" @click.stop>
        <Dropdown align="right">
          <template #trigger>
            <button
              class="text-secondary hover:bg-base-200 hover:text-primary flex h-8 w-8 items-center justify-center rounded-full transition-colors"
            >
              <Icon icon="fa-ellipsis-v" class="text-sm" />
            </button>
          </template>
          <template #content>
            <DropdownItem @click="navigateToEdit(item)">
              <Icon icon="fa-edit" class="fa-fw mr-2" />{{ t('stream.actions.edit') }}
            </DropdownItem>
            <template v-if="canWrite">
              <DropdownItem v-if="item.status === 'active'" @click="emit('pause', item)">
                <Icon icon="fa-pause" class="fa-fw mr-2" />{{ t('stream.actions.pause') }}
              </DropdownItem>
              <DropdownItem
                v-if="item.status === 'paused' || item.status === 'draft'"
                @click="emit('activate', item)"
              >
                <Icon icon="fa-play" class="fa-fw mr-2" />{{ t('stream.actions.activate') }}
              </DropdownItem>
              <DropdownItem variant="danger" @click="emit('delete', item)">
                <Icon icon="fa-trash" class="fa-fw mr-2" />{{ t('stream.actions.delete') }}
              </DropdownItem>
            </template>
          </template>
        </Dropdown>
      </td>
    </template>
  </Table>
</template>

<script setup lang="ts">
import Dropdown from '@/components/ui/Dropdown.vue'
import DropdownItem from '@/components/ui/DropdownItem.vue'
import type { ChannelType, StreamRead } from '@/types/stream'
import { useDateTime } from '@/composables/useDateTime'
import { Badge, Icon, Table, Tag } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

interface Props {
  streams: StreamRead[]
  canWrite?: boolean
}

const { canWrite = false } = defineProps<Props>()

const emit = defineEmits<{
  pause: [stream: StreamRead]
  activate: [stream: StreamRead]
  delete: [stream: StreamRead]
}>()

const { t } = useI18n()
const router = useRouter()
const { formatRelativeTime } = useDateTime()
const route = useRoute()

const columns = computed(() => [
  { key: 'name', label: t('stream.list.columns.name') },
  { key: 'updated_at', label: t('stream.list.columns.lastModified') },
  { key: 'channel_type', label: t('stream.list.columns.type') },
  { key: 'status', label: t('stream.list.columns.status') },
  { key: 'actions', label: t('stream.list.columns.actions') },
])

const navigateToEdit = (stream: StreamRead) => {
  const folderId = (route.params as Record<string, string>).folderId
  router.push(`/folders/${folderId}/streams/${stream.id}`)
}

const getRelativeTime = (timestamp: string) => {
  return formatRelativeTime(timestamp)
}

const getIconClass = (channelType: ChannelType) => {
  const map: Record<ChannelType, string> = {
    teams: 'fab fa-microsoft',
    slack_webhook: 'fab fa-slack',
    webhook: 'fas fa-plug',
  }
  return map[channelType]
}
</script>
