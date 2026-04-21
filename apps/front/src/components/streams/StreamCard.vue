<template>
  <div
    class="shadow-shadow-2 bg-base-100 border-base-300 cursor-pointer overflow-hidden rounded-2xl border p-5 transition-all duration-300"
    @click="navigateToEdit"
  >
    <div class="flex flex-col gap-3">
      <!-- Header: icon + name + kebab -->
      <div class="flex items-start justify-between">
        <div class="flex items-center gap-3">
          <Badge size="sm" variant="secondary" :icon="iconClass"> </Badge>
          <h3 class="text-base font-medium">{{ stream.name }}</h3>
        </div>

        <!-- Kebab menu -->
        <div @click.stop>
          <Dropdown align="right">
            <template #trigger>
              <button
                class="text-secondary hover:bg-base-200 hover:text-primary flex h-8 w-8 items-center justify-center rounded-full transition-colors"
              >
                <Icon icon="fa-ellipsis-v" class="text-sm" />
              </button>
            </template>
            <template #content>
              <DropdownItem @click="navigateToEdit">
                <Icon icon="fa-edit" class="fa-fw mr-2" />{{ t('stream.actions.edit') }}
              </DropdownItem>
              <template v-if="canWrite">
                <DropdownItem v-if="stream.status === 'active'" @click="emit('pause', stream)">
                  <Icon icon="fa-pause" class="fa-fw mr-2" />{{ t('stream.actions.pause') }}
                </DropdownItem>
                <DropdownItem
                  v-if="stream.status === 'paused' || stream.status === 'draft'"
                  @click="emit('activate', stream)"
                >
                  <Icon icon="fa-play" class="fa-fw mr-2" />{{ t('stream.actions.activate') }}
                </DropdownItem>
                <DropdownItem variant="danger" @click="emit('delete', stream)">
                  <Icon icon="fa-trash" class="fa-fw mr-2" />{{ t('stream.actions.delete') }}
                </DropdownItem>
              </template>
            </template>
          </Dropdown>
        </div>
      </div>

      <!-- Last modified -->
      <p class="text-secondary text-sm">
        {{ t('stream.card.lastModified', { date: relativeTime }) }}
      </p>

      <!-- Status + channel type tags -->
      <div class="flex items-center gap-2">
        <Tag
          :icon="stream.status === 'active' ? 'fa-play' : 'fa-times'"
          :label="t(`stream.status.${stream.status}`)"
          size="sm"
          color="sage"
        />
        <Tag :label="t(`stream.channel.${stream.channel_type}`)" size="sm" color="sage" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import Dropdown from '@/components/ui/Dropdown.vue'
import DropdownItem from '@/components/ui/DropdownItem.vue'
import type { ChannelType, StreamRead } from '@/types/stream'
import { formatRelativeTime } from '@/utils/time'
import { Badge, Icon, Tag } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

interface Props {
  stream: StreamRead
  canWrite?: boolean
}

const { stream, canWrite = false } = defineProps<Props>()

const emit = defineEmits<{
  pause: [stream: StreamRead]
  activate: [stream: StreamRead]
  delete: [stream: StreamRead]
}>()

const { t } = useI18n()
const router = useRouter()
const route = useRoute()

const navigateToEdit = () => {
  const folderId = (route.params as Record<string, string>).folderId
  router.push(`/folders/${folderId}/streams/${stream.id}`)
}

const relativeTime = computed(() => {
  return formatRelativeTime(stream.updated_at)
})

const iconClass = computed(() => {
  const map: Record<ChannelType, string> = {
    teams: 'fab fa-microsoft',
    slack_webhook: 'fab fa-slack',
    webhook: 'fas fa-plug',
  }
  return map[stream.channel_type]
})
</script>
