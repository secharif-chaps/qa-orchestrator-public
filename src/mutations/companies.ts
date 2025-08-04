import { ref } from 'vue'
import { defineMutation, useMutation } from '@pinia/colada'
import { createCompany } from '@/api/companies'

export const useCreateCompany = defineMutation(() => {
  const name = ref('')
  const website = ref('')

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: (company: { name: string; website: string }) => createCompany(company),
  })

  return {
    ...mutation,
    createCompany: () => {
      if (!name.value || !website.value) {
        throw new Error('Company name and website are required')
      }
      return mutateAsync({
        name: name.value,
        website: website.value,
      })
    },
    name,
    website,
    mutate,
    mutateAsync,
  }
})