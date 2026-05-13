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
            class="bg-primary-light flex h-8 w-8 items-center justify-center rounded-full"
          >
            <Badge
              :icon="modalState.titleIcon"
              variant="secondary"
              size="sm"
              :aria-label="modalState.titleIcon"
            />
          </div>
          <span class="text-base text-lg">{{ modalState.title }}</span>
        </div>
        <Button
          variant="tertiary"
          size="sm"
          icon="fa-xmark"
          :disabled="loadingValue"
          @click="handleCancel"
        />
      </div>
    </template>

    <template #description>
      <div class="flex flex-col gap-3 text-sm">
        <!-- Main message -->
        <p v-sanitize-html="modalState.message" class="text-neutral-black-font" />

        <!-- Info Section -->
        <div v-if="modalState.infoSection" class="bg-primary-light rounded-lg p-3">
          <div class="text-primary-light-content mb-2 flex items-center gap-2">
            <Icon v-if="modalState.infoSection.icon" :icon="modalState.infoSection.icon" />
            <Icon v-else icon="fa-check" />
            <h4 v-if="modalState.infoSection.title" class="font-semibold">
              {{ modalState.infoSection.title }}
            </h4>
            <h4 v-else class="font-semibold">
              {{ t('common.dialog.info_section.title') }}
            </h4>
          </div>
          <ul class="flex flex-col gap-1 p-1">
            <li
              v-for="item in modalState.infoSection.items"
              :key="item"
              class="text-primary-light-content flex items-start gap-2"
            >
              <span class="bg-primary mt-2 h-1.5 w-1.5 shrink-0 rounded-full"></span>
              <span>{{ item }}</span>
            </li>
          </ul>
        </div>

        <!-- Warning Section -->
        <div
          v-if="modalState.warningSection"
          class="bg-warning-light text-warning-light-content rounded-lg p-3"
        >
          <div class="mb-2 flex items-center gap-2">
            <Icon v-if="modalState.warningSection.icon" :icon="modalState.warningSection.icon" />
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
        <p v-if="modalState.additionalText" class="text-neutral-black-font text-sm">
          {{ modalState.additionalText }}
        </p>
      </div>
    </template>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          variant="tertiary"
          :disabled="loadingValue"
          :label="modalState.cancelLabel"
          @click.stop="handleCancel"
        />
        <Button
          :variant="modalState.confirmVariant"
          :icon="modalState.confirmIcon ?? modalState.titleIcon"
          :loading="loadingValue"
          :disabled="loadingValue"
          :label="modalState.confirmLabel"
          @click.stop="handleConfirm"
        />
      </div>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { Badge, Button, Icon, Modal } from '@owlint/feathers-vue'
import { useConfirmModal } from '@/composables/useConfirmModal'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { modalState, loadingGetter, handleConfirm, handleCancel } = useConfirmModal()
const { t } = useI18n()

const loadingValue = computed(() => loadingGetter.value())
</script>
