<template>
  <div
    class="fixed inset-0 bg-base-100/20 backdrop-blur-sm flex items-center justify-center z-50"
    @click.self="$emit('cancel')"
  >
    <div
      class="bg-base-100 rounded-xl shadow-2xl border border-primary-stroke p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto"
    >
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-semibold text-base">
          {{
            currentOrganization
              ? $t('admin.users.modal.changeOrganization', 'Change User Organization')
              : $t('admin.users.modal.assignOrganization', 'Assign User to Organization')
          }}
        </h3>
        <Button variant="tertiary" icon="fa fa-times" @click="$emit('cancel')" />
      </div>

      <!-- Loading State -->
      <div v-if="isLoadingOrg" class="py-12 text-center">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-4"></div>
        <p class="text-secondary text-sm">{{ $t('admin.users.modal.loading', 'Loading organization...') }}</p>
      </div>

      <!-- Error State -->
      <Alert
        v-else-if="orgError"
        variant="danger"
        class="mb-6"
        icon="fa-exclamation-circle"
        :title="$t('admin.users.modal.error.title', 'Error loading organization')"
        :description="String(orgError)"
      />

      <!-- Content (only shown when loaded) -->
      <template v-else>
        <!-- User Info -->
        <div class="mb-6 bg-base-200 p-4 rounded-lg border border-primary-stroke">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center">
              <i class="fa fa-user text-secondary"></i>
            </div>
            <div>
              <div class="font-medium text-base">{{ username }}</div>
            </div>
          </div>

          <!-- Current Organization -->
          <div v-if="currentOrganization" class="mt-3 pt-3 border-t border-primary-stroke">
            <div class="text-xs text-secondary mb-1">Current organization:</div>
            <div class="flex items-center gap-2">
              <span
                class="text-sm bg-primary-light text-primary-light-content border border-primary-stroke px-2 py-1 rounded"
              >
                {{ currentOrganization.name }}
              </span>
            </div>
          </div>
          <div v-else class="mt-3 pt-3 border-t border-primary-stroke">
            <div class="text-xs text-secondary italic">No organization assigned</div>
          </div>
        </div>

        <!-- Organization Selection -->
        <div class="mb-6">
          <h4 class="text-sm font-medium text-secondary mb-3">
            {{ $t('admin.users.modal.selectOrganization', 'Select organization:') }}
          </h4>

          <div class="space-y-2 max-h-96 overflow-y-auto">
            <button
              v-for="organization in organizations"
              :key="organization.id"
              @click="selectedOrganizationId = organization.id"
              class="w-full text-left p-3 rounded-lg border transition-colors"
              :class="{
                'border-primary bg-primary/5': selectedOrganizationId === organization.id,
                'border-primary-stroke hover:bg-base-200': selectedOrganizationId !== organization.id,
                'opacity-50': organization.id === currentOrganization?.id,
              }"
              :disabled="organization.id === currentOrganization?.id"
            >
              <div class="flex items-center justify-between">
                <div class="flex-1">
                  <div class="flex items-center gap-2">
                    <i
                      class="fa fa-building text-sm"
                      :class="{
                        'text-primary': selectedOrganizationId === organization.id,
                        'text-secondary': selectedOrganizationId !== organization.id,
                      }"
                    ></i>
                    <span class="font-medium">{{ organization.name }}</span>
                    <span
                      v-if="organization.id === currentOrganization?.id"
                      class="text-xs text-secondary"
                    >
                      (current)
                    </span>
                  </div>
                  <div v-if="organization.description" class="text-sm text-secondary mt-1">
                    {{ organization.description }}
                  </div>
                </div>
                <div v-if="selectedOrganizationId === organization.id">
                  <i class="fa fa-check-circle text-primary"></i>
                </div>
              </div>
            </button>

            <!-- Empty state -->
            <div v-if="organizations.length === 0" class="text-center py-8">
              <i class="fa fa-building text-4xl text-secondary/50 mb-2"></i>
              <p class="text-sm text-secondary">No organizations available</p>
            </div>
          </div>
        </div>

        <!-- Info Alert -->
        <Alert
          v-if="currentOrganization && selectedOrganizationId !== currentOrganization.id"
          variant="warning"
          :title="$t('admin.users.modal.warning.title', 'Organization Change')"
          :description="$t('admin.users.modal.warning.message', `Changing this user's organization will move them to the new organization. Their data will remain in the original organization.`)"
          icon="fa-info-circle"
          class="mb-4"
        />

        <!-- Actions -->
        <div class="flex items-center gap-3 justify-end">
          <Button
            variant="tertiary"
            :label="$t('common.cancel', 'Cancel')"
            @click="$emit('cancel')"
            :disabled="isAssigning"
          />

          <Button
            variant="primary"
            icon="fa fa-check"
            :label="
              isAssigning
                ? $t('admin.users.modal.assigning', 'Assigning...')
                : currentOrganization
                  ? $t('admin.users.modal.changeOrganization', 'Change Organization')
                  : $t('admin.users.modal.assignOrganization', 'Assign Organization')
            "
            :loading="isAssigning"
            :disabled="isAssigning || selectedOrganizationId === null || selectedOrganizationId === currentOrganization?.id"
            @click="handleConfirm"
          />
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useQuery } from '@pinia/colada'
import { Alert, Button } from '@owlint/feathers-vue'
import { userOrganizationQuery } from '@/queries/admin-users'
import type { OrganizationAdminResponse } from '@/types/organization'

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
} = useQuery(userOrganizationQuery, () => ({ userId: props.userId }))

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

// Handle confirm
const handleConfirm = () => {
  if (selectedOrganizationId.value !== null) {
    emit('confirm', selectedOrganizationId.value)
  }
}
</script>
