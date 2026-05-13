<template>
  <Modal
    :display-modal="true"
    :title="
      currentOrganization
        ? $t('admin.users.modal.changeOrganization')
        : $t('admin.users.modal.assignOrganization')
    "
    icon="fa-building"
    size="lg"
    color=""
    @close="emit('cancel')"
  >
    <!-- Loading State -->
    <div v-if="isLoadingOrg" class="py-12 text-center">
      <div class="border-primary mx-auto mb-4 h-8 w-8 animate-spin rounded-full border-b-2"></div>
      <p class="text-neutral-black-font text-sm">
        {{ $t('admin.users.modal.loading') }}
      </p>
    </div>

    <!-- Error State -->
    <Alert
      v-else-if="orgError"
      variant="danger"
      icon="fa-exclamation-circle"
      :title="$t('admin.users.modal.error.title')"
      :description="String(orgError)"
    />

    <!-- Content (only shown when loaded) -->
    <div v-else class="flex flex-col gap-6">
      <!-- User Info -->
      <div class="bg-primary-lightest border-primary-lighter-stroke rounded-sm border p-4">
        <div class="flex items-center gap-3">
          <div class="bg-primary/10 flex h-10 w-10 items-center justify-center rounded-full">
            <i class="fa fa-user text-neutral-black-font"></i>
          </div>
          <div>
            <div class="text-base font-medium">{{ username }}</div>
          </div>
        </div>

        <!-- Current Organization -->
        <div v-if="currentOrganization" class="border-primary-lighter-stroke mt-3 border-t pt-3">
          <div class="text-neutral-black-font mb-1 text-xs">
            {{ $t('admin.userOrganization.currentOrganization') }}
          </div>
          <div class="flex items-center gap-2">
            <span
              class="bg-primary-light text-primary-light-content border-primary-lighter-stroke rounded border px-2 py-1 text-sm"
            >
              {{ currentOrganization.name }}
            </span>
          </div>
        </div>
        <div v-else class="border-primary-lighter-stroke mt-3 border-t pt-3">
          <div class="text-neutral-black-font text-xs italic">
            {{ $t('admin.userOrganization.noOrganization') }}
          </div>
        </div>
      </div>

      <!-- Organization Selection -->
      <div class="flex flex-col gap-3">
        <h4 class="text-neutral-black-font text-sm font-medium">
          {{ $t('admin.users.modal.selectOrganization') }}
        </h4>

        <div class="flex max-h-96 flex-col gap-2 overflow-y-auto">
          <button
            v-for="organization in organizations"
            :key="organization.id"
            class="w-full rounded-sm border p-3 text-left transition-colors"
            :class="{
              'border-primary bg-primary/5': selectedOrganizationId === organization.id,
              'border-primary-lighter-stroke hover:bg-primary-lightest':
                selectedOrganizationId !== organization.id,
              'opacity-50': organization.id === currentOrganization?.id,
            }"
            :disabled="organization.id === currentOrganization?.id"
            @click="selectedOrganizationId = organization.id"
          >
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <div class="flex items-center gap-2">
                  <i
                    class="fa fa-building text-sm"
                    :class="{
                      'text-primary': selectedOrganizationId === organization.id,
                      'text-neutral-black-font': selectedOrganizationId !== organization.id,
                    }"
                  ></i>
                  <span class="font-medium">{{ organization.name }}</span>
                  <span
                    v-if="organization.id === currentOrganization?.id"
                    class="text-neutral-black-font text-xs"
                  >
                    {{ $t('admin.userOrganization.current') }}
                  </span>
                </div>
                <div v-if="organization.description" class="text-neutral-black-font mt-1 text-sm">
                  {{ organization.description }}
                </div>
              </div>
              <div v-if="selectedOrganizationId === organization.id">
                <i class="fa fa-check-circle text-primary"></i>
              </div>
            </div>
          </button>

          <!-- Empty state -->
          <div v-if="organizations.length === 0" class="py-8 text-center">
            <i class="fa fa-building text-neutral-black-font/50 mb-2 text-4xl"></i>
            <p class="text-neutral-black-font text-sm">
              {{ $t('admin.userOrganization.noOrganizationsAvailable') }}
            </p>
          </div>
        </div>
      </div>

      <!-- Info Alert -->
      <Alert
        v-if="currentOrganization && selectedOrganizationId !== currentOrganization.id"
        variant="warning"
        :title="$t('admin.users.modal.warning.title')"
        :description="$t('admin.users.modal.warning.message')"
        icon="fa-info-circle"
      />
    </div>

    <template #footer>
      <Button
        variant="primary"
        icon="fa-check"
        :label="
          isAssigning
            ? $t('admin.users.modal.assigning')
            : currentOrganization
              ? $t('admin.users.modal.changeOrganization')
              : $t('admin.users.modal.assignOrganization')
        "
        :loading="isAssigning"
        :disabled="
          isAssigning ||
          selectedOrganizationId === null ||
          selectedOrganizationId === currentOrganization?.id
        "
        @click="handleConfirm"
      />
      <Button
        variant="tertiary"
        :label="$t('common.cancel')"
        :disabled="isAssigning"
        @click="emit('cancel')"
      />
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { userOrganizationQuery } from '@/queries/admin-users'
import type { OrganizationAdminResponse } from '@/types/organization'
import { Alert, Button, Modal } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { computed, ref, watch } from 'vue'

interface Props {
  userId: string
  username: string
  organizations: OrganizationAdminResponse[]
  isAssigning?: boolean
}

const props = defineProps<Props>()

const emit = defineEmits<{
  confirm: [organizationId: string]
  cancel: []
}>()

// Fetch user organization on-demand
const {
  data: orgData,
  isLoading: isLoadingOrg,
  error: orgError,
} = useQuery(() => userOrganizationQuery({ userId: props.userId }))

// Current organization from fetched data
const currentOrganization = computed(() => orgData.value?.organization ?? null)

// Selected organization ID - initialize when data loads
const selectedOrganizationId = ref<string | null>(null)

// Initialize selected organization when data loads
watch(
  orgData,
  (data) => {
    if (data?.organization) {
      selectedOrganizationId.value = data.organization.id
    }
  },
  { immediate: true },
)

const handleConfirm = () => {
  if (selectedOrganizationId.value !== null) {
    emit('confirm', selectedOrganizationId.value)
  }
}
</script>
