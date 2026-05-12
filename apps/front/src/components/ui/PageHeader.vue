<template>
  <div
    class="bg-neutral-white gap-3xs py-xs sticky top-0 z-10 -mx-4 flex flex-col px-4 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8"
  >
    <!-- Row 1: Back button + Buttons (hidden when neither is present) -->
    <template v-if="showBack || hasRightContent">
      <div class="flex items-center">
        <Button
          v-if="showBack"
          size="sm"
          icon="fa-chevron-left"
          variant="neutral"
          :as="backTo ? RouterLink : undefined"
          :to="backTo"
          @click="handleBack"
        >
          {{ effectiveBackLabel }}
        </Button>

        <div v-if="hasRightContent" class="ml-auto flex shrink-0 items-center gap-2">
          <slot name="buttons" />
          <Tag
            v-if="privacy === 'private'"
            intent="neutral"
            icon="fa-lock"
            :label="t('common.privacy.private')"
            size="xs"
            class="rounded-full"
          />
          <Tag
            v-else-if="privacy === 'shared'"
            intent="info"
            icon="fa-share-nodes"
            :label="t('common.privacy.shared')"
            size="xs"
            class="rounded-full"
          />
        </div>
      </div>

      <!-- Separator line -->
      <div class="border-primary-lighter-stroke border-b" />
    </template>

    <!-- Row 2: Logo + Title + Edit + Info + Actions -->
    <div class="gap-xl pt-3xs flex items-center">
      <div class="flex min-w-0 shrink flex-col">
        <div class="gap-2xs flex items-center">
          <SquareProgressRing
            v-if="hasLogo"
            :size="36"
            :segments="progressSegments ?? []"
            :show-progress="showProgress ?? false"
          >
            <div class="relative size-9 shrink-0 overflow-hidden rounded-full bg-white">
              <div :class="{ 'opacity-30': showProgress }">
                <Logo
                  :website="logoWebsite"
                  :name="logoName"
                  :alt="logoName"
                  :width="36"
                  :height="36"
                />
              </div>
            </div>
          </SquareProgressRing>

          <h1 v-if="!isEditing" class="truncate text-2xl font-bold">{{ title }}</h1>
          <input
            v-else
            ref="editInput"
            v-model="editValue"
            type="text"
            class="border-primary-base min-w-0 flex-1 truncate border-b bg-transparent text-2xl font-bold outline-none"
            @keydown.enter="confirmEdit"
            @keydown.escape="cancelEdit"
            @blur="cancelEdit"
          />

          <Button
            v-if="editable && !isEditing && title"
            variant="tertiary"
            size="xs"
            icon-only
            icon="fa-pen"
            class="mx-xs"
            @click="startEdit"
          />

          <Transition
            mode="out-in"
            enter-active-class="transition-all duration-300 ease-out"
            leave-active-class="transition-all duration-300 ease-out"
            enter-from-class="opacity-0 translate-y-1.5"
            leave-to-class="opacity-0 -translate-y-1.5"
          >
            <slot name="info" />
          </Transition>
        </div>

        <p v-if="description" class="text-neutral-black-font">
          {{ description }}
        </p>
      </div>

      <div v-if="$slots.actions" class="flex min-w-0 flex-1 justify-end">
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import Logo from '@/components/ui/Logo.vue'
import SquareProgressRing from '@/components/ui/SquareProgressRing.vue'
import type { ProgressSegment } from '@/composables/useTaskProgress'
import { Button, Tag } from '@owlint/feathers-vue'
import { computed, nextTick, ref, useSlots, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter, type RouteLocationRaw } from 'vue-router'

interface Props {
  title: string
  description?: string
  showBack?: boolean
  backTo?: RouteLocationRaw
  backLabel?: string
  logoName?: string
  logoWebsite?: string
  progressSegments?: ProgressSegment[]
  showProgress?: boolean
  privacy?: 'private' | 'shared' | null
  editable?: boolean
}

const {
  logoName,
  logoWebsite,
  privacy,
  backTo,
  backLabel,
  title,
  editable,
  showBack = true,
} = defineProps<Props>()

interface Emits {
  titleUpdate: [value: string]
}

const emit = defineEmits<Emits>()

const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const slots = useSlots()

// `history.state.back` is set by Vue Router when navigating within the SPA.
// It's null when the user landed directly on this page (external link, refresh, etc.)
// — in that case `router.back()` would do nothing, so we fall back to home.
const hasHistory = computed(() => {
  void route.fullPath
  return window.history.state?.back != null
})

const effectiveBackLabel = computed(() => {
  if (backLabel) return backLabel
  if (!backTo && !hasHistory.value) return t('common.action.backToHome')
  return t('common.action.back')
})

const handleBack = () => {
  if (backTo) return // RouterLink handles navigation
  if (hasHistory.value) {
    router.back()
  } else {
    router.push({ name: '/(home)' })
  }
}

const hasLogo = computed(() => !!(logoName || logoWebsite))

const hasRightContent = computed(() => !!slots.buttons || privacy != null)

const isEditing = ref(false)
const editValue = ref('')
const editInput = useTemplateRef<HTMLInputElement>('editInput')

const startEdit = async () => {
  editValue.value = title
  isEditing.value = true
  await nextTick()
  editInput.value?.focus()
  editInput.value?.select()
}

const confirmEdit = () => {
  const trimmed = editValue.value.trim()
  if (trimmed && trimmed !== title) {
    emit('titleUpdate', trimmed)
  }
  isEditing.value = false
}

const cancelEdit = () => {
  isEditing.value = false
}
</script>
