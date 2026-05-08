<template>
  <Dropdown align="right" width="sm" :close-on-select="false">
    <template #trigger="{ isOpen }">
      <button
        class="flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1 transition-colors"
        :class="
          isOpen ? 'bg-sage-100 dark:bg-sage-800' : 'hover:bg-sage-100 dark:hover:bg-sage-900'
        "
      >
        <Avatar :label="authStore.username" color="almond" size="sm" />
        <span class="text-sage-800 dark:text-sage-200 text-sm">{{ authStore.username }}</span>
        <Icon
          icon="fa-chevron-down"
          class="text-neutral-black-font text-xs transition-transform"
          :class="{ 'rotate-180': isOpen }"
        />
      </button>
    </template>

    <template #content="{ close }">
      <div class="flex flex-col gap-0.5">
        <!-- Debug: Theme toggle -->
        <button
          v-if="isDebugUser"
          class="text-sage-800 dark:text-sage-200 hover:bg-sage-100 dark:hover:bg-sage-700 flex w-full items-center gap-3 rounded-sm px-3 py-2 text-left text-sm transition-colors"
          @click="toggleTheme"
        >
          <Icon :icon="isDark ? 'fa-sun' : 'fa-moon'" class="w-4 text-center" />
          <span>{{ isDark ? t('common.userMenu.lightMode') : t('common.userMenu.darkMode') }}</span>
        </button>

        <!-- Debug: Language toggle -->
        <button
          v-if="isDebugUser"
          class="text-sage-800 dark:text-sage-200 hover:bg-sage-100 dark:hover:bg-sage-700 flex w-full items-center gap-3 rounded-sm px-3 py-2 text-left text-sm transition-colors"
          @click="toggleLocale"
        >
          <Icon icon="fa-language" class="w-4 text-center" />
          <span>{{
            locale === LOCALES.EN
              ? t('common.userMenu.switchToFrench')
              : t('common.userMenu.switchToEnglish')
          }}</span>
        </button>

        <!-- Separator (only if debug items shown) -->
        <div v-if="isDebugUser" class="bg-sage-200 dark:bg-sage-700 my-1 h-px" />

        <!-- Admin -->
        <button
          v-if="hasAdminPermission"
          class="text-sage-800 dark:text-sage-200 hover:bg-sage-100 dark:hover:bg-sage-700 flex w-full items-center gap-3 rounded-sm px-3 py-2 text-left text-sm transition-colors"
          @click="goToAdmin(close)"
        >
          <Icon icon="fa-shield" class="w-4 text-center" />
          <span>{{ t('common.userMenu.administration') }}</span>
        </button>

        <!-- Separator (only if admin shown) -->
        <div v-if="hasAdminPermission" class="bg-sage-200 dark:bg-sage-700 my-1 h-px" />

        <!-- Logout -->
        <button
          class="text-error hover:bg-error-light flex w-full items-center gap-3 rounded-sm px-3 py-2 text-left text-sm transition-colors"
          @click="handleLogout(close)"
        >
          <Icon icon="fa-arrow-right-from-bracket" class="w-4 text-center" />
          <span>{{ t('common.logout.confirm') }}</span>
        </button>
      </div>
    </template>
  </Dropdown>

  <!-- Logout Confirmation Modal -->
  <LogoutConfirmationModal
    v-model="showLogoutModal"
    :is-loading="isLoggingOut"
    @confirm="confirmLogout"
  />
</template>

<script lang="ts" setup>
import LogoutConfirmationModal from '@/components/global/LogoutConfirmationModal.vue'
import Dropdown from '@/components/ui/Dropdown.vue'
import { useTheme } from '@/composables/useTheme'
import { LOCALES } from '@/i18n'
import { useAuthStore } from '@/stores/auth'
import { Avatar, Icon } from '@owlint/feathers-vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const { t, locale } = useI18n()
const router = useRouter()
const authStore = useAuthStore()
const { signOut } = authStore
const { isDark, setTheme } = useTheme()

// Permission checks
const hasAdminPermission = computed(() => authStore.hasPermission('admin.organizations'))

const isDebugUser = computed(() => {
  const username = authStore.user?.profile?.preferred_username?.toLowerCase()
  return username === 'nmr' || username === 'suh' || username === 'nmr-cv'
})

// Theme toggle
const toggleTheme = () => {
  setTheme(isDark.value ? 'light' : 'dark')
}

// Language toggle
const toggleLocale = () => {
  locale.value = locale.value === LOCALES.EN ? LOCALES.FR : LOCALES.EN
}

// Admin navigation
const goToAdmin = (close: () => void) => {
  close()
  router.push('/admin')
}

// Logout
const showLogoutModal = ref(false)
const isLoggingOut = ref(false)

const handleLogout = (close: () => void) => {
  close()
  showLogoutModal.value = true
}

const confirmLogout = async () => {
  isLoggingOut.value = true
  try {
    await signOut()
  } catch (error) {
    console.error('Logout error:', error)
  } finally {
    isLoggingOut.value = false
    showLogoutModal.value = false
  }
}
</script>
