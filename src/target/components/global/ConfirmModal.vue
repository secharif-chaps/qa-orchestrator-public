<template>
  <Modal
    v-if="modalState.isOpen"
    v-model:display-modal="modalState.isOpen"
    size="xl"
    @close="handleCancel"
  >
    <!-- Custom title slot to handle icon -->
    <template #title>
      <div class="flex w-full items-center justify-between">
        <div class="flex items-center gap-3">
          <div
            v-if="modalState.titleIcon"
            class="bg-sage-100 flex h-8 w-8 items-center justify-center rounded-full"
          >
            <Badge
              :icon="modalState.titleIcon"
              variant="secondary"
              size="sm"
              :aria-label="modalState.titleIcon"
            />
          </div>
          <span class="text-lg text-gray-900">{{ modalState.title }}</span>
        </div>
        <Button
          variant="tertiary"
          size="sm"
          icon="fa-xmark"
          @click="handleCancel"
        />
      </div>
    </template>

    <template #description>
      <div class="space-y-3 text-sm">
        <!-- Main message -->
        <p v-sanitize-html="modalState.message" class="text-gray-700" />

        <!-- Info Section (light blue container) -->
        <div v-if="modalState.infoSection" class="bg-sage-50 rounded-lg p-3">
          <div class="text-sage-600 mb-2 flex items-center gap-2">
            <Icon
              v-if="modalState.infoSection.icon"
              :icon="modalState.infoSection.icon"
            />
            <Icon v-else icon="fa-check" />
            <h4 v-if="modalState.infoSection.title" class="font-semibold">
              {{ modalState.infoSection.title }}
            </h4>
            <h4 v-else class="font-semibold">
              {{ t('common.dialog.info_section.title') }}
            </h4>
          </div>
          <ul class="p-1">
            <li
              v-for="item in modalState.infoSection.items"
              :key="item"
              class="text-sage-600 flex items-start gap-2"
            >
              <span
                class="bg-sage-600 mt-2 h-1.5 w-1.5 shrink-0 rounded-full"
              ></span>
              <span>{{ item }}</span>
            </li>
          </ul>
        </div>

        <!-- Warning Section (Orange container) -->
        <div
          v-if="modalState.warningSection"
          class="rounded-lg bg-orange-50 p-3 text-orange-900"
        >
          <div class="mb-2 flex items-center gap-2">
            <Icon
              v-if="modalState.warningSection.icon"
              :icon="modalState.warningSection.icon"
            />
            <Icon v-else icon="fa-triangle-exclamation" />
            <h4 v-if="modalState.warningSection.title" class="font-semibold">
              {{ modalState.warningSection.title }}
            </h4>
            <h4 v-else class="font-semibold">
              {{ t('common.dialog.warning_section.title') }}
            </h4>
          </div>
          <p>{{ modalState.warningSection.message }}</p>
        </div>

        <!-- Additional text -->
        <p v-if="modalState.additionalText" class="text-sm text-gray-600">
          {{ modalState.additionalText }}
        </p>
      </div>
    </template>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button variant="tertiary" @click.stop="handleCancel">
          {{ modalState.cancelLabel }}
        </Button>
        <Button :icon="modalState.titleIcon" @click.stop="handleConfirm">
          {{ modalState.confirmLabel }}
        </Button>
      </div>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { Badge, Button, Icon, Modal } from '@owlint/feathers-vue';
import { useI18n } from 'vue-i18n';
import { useConfirmModal } from '~/composables/useConfirmModal';

const { modalState, handleConfirm, handleCancel } = useConfirmModal();
const { t } = useI18n();
</script>
