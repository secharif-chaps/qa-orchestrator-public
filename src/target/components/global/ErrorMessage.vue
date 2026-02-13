<template>
  <InformationMessage
    :title="title || $t('common.error.title')"
    :description="description || $t('common.error.description')"
    color="error"
    icon="fa-circle-exclamation"
    :width="width"
    :vertical-align="verticalAlign"
    :fill="fill"
    :transparent="transparent"
    :is-chat-message="isChatMessage"
  />
  <Button v-if="retryButton" @click="$emit('retry')">
    {{ $t('common.button.retry') }}
  </Button>
</template>

<script setup lang="ts">
import InformationMessage from './InformationMessage.vue';
import { Button } from '@owlint/feathers-vue';

interface Props {
  title?: string;
  description?: string;
  width?: 'md' | 'lg' | 'full';
  verticalAlign?: 'top' | 'center';
  fill?: boolean;
  transparent?: boolean;
  isChatMessage?: boolean;
  retryButton?: boolean;
}

const {
  title = undefined,
  description = undefined,
  width = 'md',
  verticalAlign = 'top',
  fill = false,
  transparent = false,
  isChatMessage = false,
  retryButton = false,
} = defineProps<Props>();

defineEmits<{
  retry: [];
}>();
</script>
