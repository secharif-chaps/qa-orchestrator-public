<template>
  <div>
    <div
      v-if="watchFileUsers.length > 0 || isLoading"
      class="text-neutral-black-font-font mb-1 pt-4 text-sm"
    >
      {{ $t('target.watchFiles.shareDialog.accessListTitle') }}
    </div>
    <div v-if="isLoading" class="scrollable max-h-60 divide-y divide-gray-200 rounded-md">
      <div
        v-for="i in skeletonCount || 3"
        :key="`skeleton-${i}`"
        class="flex min-h-[55px] animate-pulse items-center bg-gray-50 p-2"
      >
        <div class="mr-3 h-8 w-8 rounded-full bg-gray-200"></div>
        <div class="flex-1">
          <div class="mb-1 h-4 w-3/4 rounded bg-gray-200"></div>
          <div class="h-3 w-1/2 rounded bg-gray-200"></div>
        </div>
        <div class="ml-2 h-8 w-20 rounded bg-gray-200"></div>
        <div class="ml-2 h-8 w-8 rounded bg-gray-200"></div>
      </div>
    </div>
    <div
      v-else-if="watchFileUsers.length > 0"
      class="scrollable max-h-60 divide-y divide-gray-200 rounded-md"
    >
      <TransitionGroup name="user-list" tag="div" :css="allowAnimations" appear>
        <div
          v-for="watchFileUser in watchFileUsers"
          :key="watchFileUser.id"
          class="user-list-item flex min-h-[55px] items-center bg-gray-50 p-2"
        >
          <Badge
            :number="watchFileUser.user.defaultThumbnail"
            size="sm"
            variant="secondary"
            class="mr-3"
          />
          <span class="flex-1">{{ watchFileUser.user.displayName }}</span>
          <span
            v-if="watchFileUser.role === 'owner'"
            class="text-neutral-black-font-font ml-2 text-sm"
            >{{ $t('target.watchFiles.shareDialog.owner') }}</span
          >
          <div v-else class="flex items-center gap-2">
            <Select
              :id="'user-role-' + watchFileUser.id"
              v-model="watchFileUser.role"
              :display-value="roleLabel"
              :options="[WATCH_FILE_USER_ROLE.VIEWER, WATCH_FILE_USER_ROLE.EDITOR]"
              to="#modal"
            >
              <template #items="{ options }">
                <SelectItem
                  v-for="option in options"
                  :key="option"
                  :value="option"
                  @select="onChangeRole(watchFileUser, option)"
                >
                  <ORadio :id="option" v-model="watchFileUser.role" :value="option" />

                  {{ roleLabel(option) }}
                </SelectItem>
              </template>
            </Select>
            <Button
              variant="tertiary"
              size="sm"
              icon="fa-trash"
              @click="onRemoveUser(watchFileUser)"
            />
          </div>
        </div>
      </TransitionGroup>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Badge, Button, ORadio, Select, SelectItem } from '@owlint/feathers-vue'
import {
  useRemoveWatchFileUser,
  useUpdateWatchFileUserRole,
} from '@target/api/mutations/watchFileUser'
import { useConfirmModal } from '@target/composables/useConfirmModal'
import { useMotionPreference } from '@target/composables/useMotionPreference'
import { useRole } from '@target/composables/useRole'
import type { WatchFileUser, WatchFileUserRole } from '@target/types/watchFileUser'
import { WATCH_FILE_USER_ROLE } from '@target/types/watchFileUser'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const { roleLabel } = useRole()
const { showConfirmModal } = useConfirmModal()

const { removeUser } = useRemoveWatchFileUser()
const { updateRole } = useUpdateWatchFileUserRole()

const props = defineProps<{
  watchFileId: string
  watchFileUsers: WatchFileUser[]
  isLoading: boolean
  skeletonCount?: number
}>()

const emit = defineEmits<{
  (e: 'user-removed'): void
  (e: 'role-changed', watchFileUser: WatchFileUser, newRole: WatchFileUserRole): void
}>()

const originalRoles = ref<Record<string, WatchFileUserRole>>({})
const { allowAnimations } = useMotionPreference()

// Watch for changes in watchFileUsers to update originalRoles
watch(
  () => props.watchFileUsers,
  (newUsers) => {
    originalRoles.value = {}
    newUsers.forEach((user) => {
      originalRoles.value[user.id] = user.role
    })
  },
  { immediate: true, deep: true },
)

async function onRemoveUser(watchFileUser: WatchFileUser) {
  showConfirmModal({
    title: t('target.watchFiles.shareDialog.removeUserTitle'),
    message: t('target.watchFiles.shareDialog.removeUserMessage', {
      user: watchFileUser.user.displayName,
    }),
    confirmLabel: t('common.button.confirm'),
    cancelLabel: t('common.button.cancel'),
    onConfirm: async () => {
      try {
        await removeUser({
          watchFileId: props.watchFileId,
          watchFileUserId: watchFileUser.id,
          displayName: watchFileUser.user.displayName,
        })

        emit('user-removed')
      } catch (error) {
        console.error('Failed to remove user:', error)
      }
    },
  })
}

function onChangeRole(watchFileUser: WatchFileUser, role: WatchFileUserRole) {
  // Get the original role from our stored values
  const originalRole = originalRoles.value[watchFileUser.id]
  if (originalRole === role) return
  showConfirmModal({
    title: t('target.watchFiles.shareDialog.changeRoleTitle'),
    message: t('target.watchFiles.shareDialog.changeRoleMessage', {
      user: watchFileUser.user.displayName,
      role: roleLabel(role),
    }),
    onConfirm: async () => {
      try {
        await updateRole({
          watchFileId: props.watchFileId,
          user: watchFileUser.user,
          role,
        })
        // Update the stored original role to the new confirmed role
        originalRoles.value[watchFileUser.id] = role

        emit('role-changed', watchFileUser, role)
      } catch (error) {
        console.error('Failed to update role:', error)

        if (originalRole) {
          watchFileUser.role = originalRole
        }
      }
    },
    onCancel: () => {
      // Reset the role to original value in case of cancellation
      if (originalRole) {
        watchFileUser.role = originalRole
      }
    },
  })
}
</script>

<style scoped>
/* Transition animations - only applied when motion is not reduced */
.user-list-enter-active,
.user-list-leave-active {
  transition: all 0.3s ease;
  transform-origin: center;
}

.user-list-enter-from {
  opacity: 0;
  transform: translateY(-10px) scale(0.95);
}

.user-list-leave-to {
  opacity: 0;
  transform: translateX(20px) scale(0.95);
}

.user-list-move {
  transition: transform 0.3s ease;
}

/* Ensure smooth layout shifts */
.user-list-item {
  transition: all 0.2s ease;
}

/* Loading skeleton animation */
@keyframes pulse {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

.animate-pulse {
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Respect reduced motion preference */
@media (prefers-reduced-motion: reduce) {
  .user-list-enter-active,
  .user-list-leave-active,
  .user-list-move,
  .user-list-item {
    transition: none !important;
    animation: none !important;
  }

  .animate-pulse {
    animation: none !important;
    opacity: 0.7;
  }
}
</style>
