import { useUpdateWatchFile } from '@target/api/mutations/watchFile'
import type { WatchFile } from '@target/types/watchFile'
import type { MaybeRef } from 'vue'
import { computed, nextTick, readonly, ref, unref } from 'vue'
import { useI18n } from 'vue-i18n'

interface UseWatchFileTitleOptions {
  watchFile?: MaybeRef<WatchFile | null>
  onUpdate?: (updatedWatchFile: WatchFile) => void
  canEdit?: MaybeRef<boolean>
}

export const useWatchFileTitle = (options: UseWatchFileTitleOptions = {}) => {
  const { t } = useI18n()

  const { updateTask, isLoading: isSaving } = useUpdateWatchFile()

  const isEditing = ref(false)
  const editValue = ref('')
  const originalValue = ref('')
  const currentTitle = computed(() => {
    return unref(options.watchFile)?.name || ''
  })

  const maxTitleDisplayLength = 70
  const currentTitleTruncated = computed(() => {
    const title = currentTitle.value
    return title.length > maxTitleDisplayLength
      ? `${title.slice(0, maxTitleDisplayLength)}...`
      : title
  })

  const displayTitle = computed(() => {
    return currentTitle.value
  })

  const isPlaceholder = computed(() => {
    return !currentTitle.value
  })

  const hasChanges = computed(() => {
    return editValue.value !== originalValue.value
  })

  const isValidTitle = computed<boolean>(() => {
    const trimmed = editValue.value.trim()
    return trimmed.length >= 3 && trimmed.length <= 255
  })

  const error = computed<string | null>(() => {
    if (!isEditing.value) {
      return null
    }

    if (editValue.value.trim() === '') {
      return t('watch_files.title.error.empty')
    }

    if (!isValidTitle.value) {
      return t('watch_files.title.error.invalid_length')
    }

    return null
  })

  const enterEditMode = () => {
    if (!unref(options.canEdit)) {
      return false
    }

    const watchFile = unref(options.watchFile)
    if (!watchFile) return false

    originalValue.value = currentTitle.value
    editValue.value = currentTitle.value
    isEditing.value = true

    nextTick(() => {
      const input = document.querySelector('[data-watch-file-title-input]') as HTMLInputElement
      input?.focus()
      input?.select()
    })
  }

  const exitEditMode = () => {
    isEditing.value = false
    editValue.value = ''
    originalValue.value = ''
  }

  const cancelEdit = () => {
    editValue.value = originalValue.value
    exitEditMode()
  }

  const saveTitle = async () => {
    const watchFile = unref(options.watchFile)
    if (!watchFile) return

    if (!isValidTitle.value) return

    if (!hasChanges.value) {
      exitEditMode()
      return
    }

    updateTask({ id: watchFile.id, data: { name: editValue.value.trim() } })
    exitEditMode()
  }

  const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Enter') {
      event.preventDefault()
      saveTitle()
    } else if (event.key === 'Escape') {
      event.preventDefault()
      cancelEdit()
    }
  }

  const handleClickOutside = (event: Event) => {
    if (!isEditing.value) return

    if (isSaving.value) {
      event.stopPropagation()
      return
    }

    const titleContainer = document.querySelector('[data-watch-file-title-container]')

    if (titleContainer && titleContainer.contains(event.target as HTMLElement)) {
      return
    }

    if (hasChanges.value) {
      saveTitle()
    } else {
      cancelEdit()
    }
  }

  return {
    isEditing: readonly(isEditing),
    isSaving: readonly(isSaving),
    error,
    editValue,
    currentTitle,
    currentTitleTruncated,
    maxTitleDisplayLength,
    displayTitle,
    isPlaceholder,
    hasChanges,
    isValidTitle,
    enterEditMode,
    exitEditMode,
    cancelEdit,
    saveTitle,
    handleKeydown,
    handleClickOutside,
  }
}
