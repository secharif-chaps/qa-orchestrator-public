<template>
  <form class="flex flex-col gap-6" @submit.prevent="handleSubmit">
    <!-- Name -->
    <Input
      id="stream-name"
      v-model="formData.name"
      :label="t('stream.form.name')"
      :placeholder="t('stream.form.namePlaceholder')"
      :error="errors.name"
      required
    />

    <!-- Description -->
    <Input
      id="stream-description"
      v-model="formData.description"
      :label="t('stream.form.description')"
      :placeholder="t('stream.form.descriptionPlaceholder')"
    />

    <!-- Channel Type -->
    <div class="flex flex-col gap-2">
      <Label id="channel-type" size="sm">{{ t('stream.form.channelType') }}</Label>
      <div class="flex gap-4">
        <Radio
          v-for="channel in channelOptions"
          :id="`channel-type-${channel.value}`"
          :key="channel.value"
          v-model="formData.channel_type"
          :value="channel.value"
          :label="channel.label"
          name="channel-type"
        />
      </div>
    </div>

    <!-- Channel Config -->
    <div class="flex flex-col gap-2">
      <Label id="channel-config" size="sm">{{ t('stream.form.channelConfig') }}</Label>
      <ChannelConfigForm v-model="formData.channel_config" :channel-type="formData.channel_type" />
    </div>

    <!-- Mode -->
    <div class="flex flex-col gap-2">
      <Label id="stream-mode" size="sm">{{ t('stream.form.mode') }}</Label>
      <div class="flex items-center gap-4">
        <Radio
          id="stream-mode-live"
          v-model="formData.mode"
          value="live"
          :label="t('stream.mode.live')"
          name="stream-mode"
        />
        <div class="flex items-center gap-2 opacity-50">
          <Radio
            id="stream-mode-recurrence"
            v-model="formData.mode"
            value="recurrence"
            disabled
            :label="t('stream.mode.recurrence')"
            name="stream-mode"
          />
          <Tag intent="accent" size="xs" :label="t('stream.form.modeComingSoon')" />
        </div>
      </div>
    </div>

    <!-- Event Types -->
    <div class="flex flex-col gap-2">
      <Label id="stream-events" size="sm">{{ t('stream.form.events') }}</Label>
      <EventTypeSelector v-model="formData.subscribed_events" :error="errors.events" />
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-end gap-3">
      <Button variant="secondary" :label="t('stream.form.cancel')" @click="emit('cancel')" />
      <Button
        variant="primary"
        type="submit"
        :label="isEditMode ? t('stream.form.submitEdit') : t('stream.form.submit')"
        :loading="isSubmitting"
      />
    </div>
  </form>
</template>

<script setup lang="ts">
import type { ChannelConfig, ChannelType, StreamMode, StreamRead } from '@/types/stream'
import { Button, Input, Label, Radio, Tag } from '@owlint/feathers-vue'
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ChannelConfigForm from './ChannelConfigForm.vue'
import EventTypeSelector from './EventTypeSelector.vue'

interface Props {
  initialData?: StreamRead
  isSubmitting?: boolean
}

interface StreamFormData {
  name: string
  description: string | null
  channel_type: ChannelType
  channel_config: ChannelConfig
  mode: StreamMode
  subscribed_events: string[]
}

const props = defineProps<Props>()
const emit = defineEmits<{
  submit: [data: StreamFormData]
  cancel: []
}>()

const { t } = useI18n()

const isEditMode = computed(() => !!props.initialData)

const formData = reactive({
  name: props.initialData?.name ?? '',
  description: props.initialData?.description ?? '',
  channel_type: (props.initialData?.channel_type ?? 'teams') as ChannelType,
  channel_config: (props.initialData?.channel_config ?? {}) as ChannelConfig,
  mode: (props.initialData?.mode ?? 'live') as StreamMode,
  subscribed_events: [...(props.initialData?.subscribed_events ?? [])] as string[],
})

const errors = ref<{ name?: string; events?: string }>({})

const channelOptions = computed(() => [
  { value: 'teams' as ChannelType, label: t('stream.channel.teams') },
  { value: 'slack_webhook' as ChannelType, label: t('stream.channel.slack_webhook') },
  { value: 'webhook' as ChannelType, label: t('stream.channel.webhook') },
])

const handleSubmit = () => {
  errors.value = {}

  if (!formData.name.trim()) {
    errors.value.name = t('stream.form.nameRequired')
    return
  }

  if (formData.subscribed_events.length === 0) {
    errors.value.events = t('stream.form.eventsRequired')
    return
  }

  emit('submit', {
    name: formData.name.trim(),
    description: formData.description?.trim() || null,
    channel_type: formData.channel_type,
    channel_config: formData.channel_config,
    mode: formData.mode,
    subscribed_events: formData.subscribed_events,
  })
}
</script>
