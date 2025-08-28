import { defineMutation, useMutation } from '@pinia/colada'
import { addItemToFolder, removeItemFromFolder, updateFolder } from '@/api/folders'
import type { FolderItemAdd, FolderUpdate } from '@/types/folder'

export const useAddItemToFolder = defineMutation(() => {
  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: ({ folderId, item }: { folderId: string; item: FolderItemAdd }) =>
      addItemToFolder(folderId, item),
  })

  return {
    ...mutation,
    mutate,
    mutateAsync,
  }
})

export const useRemoveItemFromFolder = defineMutation(() => {
  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: ({ folderId, itemId, itemType }: { folderId: string; itemId: string; itemType: 'company' }) =>
      removeItemFromFolder(folderId, itemId, itemType),
  })

  return {
    ...mutation,
    mutate,
    mutateAsync,
  }
})

export const useUpdateFolder = defineMutation(() => {
  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: ({ folderId, folder }: { folderId: string; folder: FolderUpdate }) =>
      updateFolder(folderId, folder),
  })

  return {
    ...mutation,
    mutate,
    mutateAsync,
  }
})