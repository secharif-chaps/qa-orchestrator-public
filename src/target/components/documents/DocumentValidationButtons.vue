<template>
  <ToggleGroup
    v-model="selectedStatus"
    :class="{
      'document-validation-buttons bg-sage-900 gap-1 border-0': darkMode,
    }"
    :options="options"
    :disabled="isBatchProcessing"
  />
</template>

<script setup lang="ts">
import { ToggleGroup, type ToggleGroupOption } from '@owlint/feathers-vue';
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useDocumentValidation } from '~/api/mutations/document';
import type { Document, ManualValidationStatus } from '~/types/document';
import { DocumentValidationAction } from '~/types/document';

const route = useRoute();

interface Props {
  document: Document;
  isBatchProcessing?: boolean;
  iconOnly?: boolean;
  darkMode?: boolean; // Temporary prop to match colors not yet supported by the design system. It will be removed when the design system is updated.
}

const {
  document,
  isBatchProcessing = false,
  iconOnly = false,
  darkMode = false,
} = defineProps<Props>();
const watchFileId = computed(() => route.params.id as string);

const selectedStatus = ref<ManualValidationStatus>(document.manualStatus);

watch(
  () => document.manualStatus,
  (newStatus) => {
    selectedStatus.value = newStatus;
  },
);

const options = computed<ToggleGroupOption[]>(() => [
  {
    icon: 'fa-thumbs-up',
    label: $t('documents.validation.accept'),
    value: 'accept',
    kind: 'accepted',
    iconOnly,
  },
  {
    icon: 'fa-thumbs-down',
    label: $t('documents.validation.reject'),
    value: 'refuse',
    kind: 'refused',
    iconOnly,
  },
]);

const { toggleDocumentStatus } = useDocumentValidation();

watch(selectedStatus, (newStatus) => {
  if (newStatus === document.manualStatus) {
    return;
  }

  const action: DocumentValidationAction =
    newStatus === 'accept'
      ? DocumentValidationAction.ACCEPT
      : newStatus === 'refuse'
        ? DocumentValidationAction.REFUSE
        : DocumentValidationAction.UNCERTAIN;

  toggleDocumentStatus({
    watchFileId: watchFileId.value,
    documentId: document.id,
    action,
  });
});
</script>

<style scoped>
.document-validation-buttons :deep(button) {
  background-color: var(--color-sage-900);
  color: var(--color-white);
  border-radius: 20px;

  & i {
    --symbol-fill: 0 !important;
  }
}

/* Accepted button styles */
.document-validation-buttons :deep(button[data-kind='accepted']:hover) {
  background-color: var(--color-green-200);
  color: var(--color-green-900);
}

.document-validation-buttons :deep(button[data-kind='accepted']:active) {
  background-color: var(--color-green-100);
  color: var(--color-green-600);
}

.document-validation-buttons
  :deep(button[data-kind='accepted'][aria-pressed='true']) {
  background-color: var(--color-green-300);
  color: var(--color-black);
}

/* Refused button styles */
.document-validation-buttons :deep(button[data-kind='refused']:hover) {
  background-color: var(--color-red-200);
  color: var(--color-red-900);
}

.document-validation-buttons :deep(button[data-kind='refused']:active) {
  background-color: var(--color-red-100);
  color: var(--color-red-600);
}

.document-validation-buttons
  :deep(button[data-kind='refused'][aria-pressed='true']) {
  background-color: var(--color-red-300);
  color: var(--color-black);
}
</style>
