// stores/company.ts
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import type { Company } from '~/types.global'

// Définir l'interface du store
export interface CompanyStore {
  // state
  companies: Record<string, Partial<Company>>

  // getters
  getCompanyByName: (name: string) => Partial<Company> | null
  getCompanyList: () => Partial<Company>[]

  // actions
  initCompany: (name: string) => void
  deleteCompany: (name: string) => void
  startQuery: (company: string, website: string, query: string) => Promise<void>
}

export const useCompanyStore = defineStore(
  'company',
  () => {
    // State
    const companies = ref<Record<string, Partial<Company>>>({})
    const currentCompany = ref<string | null>(null)

    const router = useRouter()

    // Getters
    const getCompanyByName = (name: string) => {
      return companies.value[name] || null
    }

    const getCompanyList = computed(() => {
      return Object.values(companies.value)
    })

    // Actions
    const initCompany = (name: string, website: string) => {
      if (!companies.value[name]) {
        companies.value[name] = {
          pendingStates: {},
          name,
          website
        }
      } else {
        console.log('Company already exists')
      }
    }

    const startQuery = async (company: string, website: string, query: string) => {
      if (!companies.value[company].pendingStates) {
        companies.value[company].pendingStates = {}
      }

      companies.value[company].pendingStates[query] = {
        pending: true,
        error: undefined
      }

      const response = await fetch(
        // 'http://ec2-34-244-245-92.eu-west-1.compute.amazonaws.com:5678/webhook-test/57be7c18-e8b2-47aa-b9aa-f7c2696f4523',
        'http://ec2-34-244-245-92.eu-west-1.compute.amazonaws.com:5678/webhook/57be7c18-e8b2-47aa-b9aa-f7c2696f4523',
        {
          method: 'POST',
          body: JSON.stringify({
            company: company,
            website: website,
            query: query
          }),
          headers: {
            'Content-Type': 'application/json'
          }
        }
      )
        .then(res => res.json())
        .then(data => {
          console.log(data[0].output)
          companies.value[company] = {
            ...companies.value[company],
            ...data[0].output
          }
        })
        .catch(err => {
          companies.value[company].pendingStates![query].error = err
        })
        .finally(() => {
          companies.value[company].pendingStates![query].pending = false
        })

      return response
    }

    const startSearch = async (company: string, website: string) => {
      try {
        if (!companies.value[company]) {
          initCompany(company, website)
        }
        router.push(`/cards/${company}`)

        const queries = [
          'team',
          'products',
          'profile',
          'digital',
          'timeline',
          'press',
          'csr',
          'jobs'
        ]

        for (const query of queries) {
          const response = startQuery(company, website, query)

          companies.value[company] = {
            ...companies.value[company],
            ...response
          }
        }
      } catch (error) {
        console.error('Error fetching data:', error)
        companies.value[company] = {
          ...companies.value[company],
          error: error instanceof Error ? error.message : 'An unknown error occurred',
          pending: false
        }
      }
    }

    const deleteCompany = (name: string) => {
      if (companies.value[name]) {
        delete companies.value[name]

        if (currentCompany.value === name) {
          currentCompany.value = null
        }
      }

      const lowerCaseName = name.toLocaleLowerCase()

      if (companies.value[lowerCaseName]) {
        delete companies.value[lowerCaseName]

        if (currentCompany.value === lowerCaseName) {
          currentCompany.value = null
        }
      }
    }

    return {
      // State
      companies,

      // Getters
      getCompanyByName,
      getCompanyList,

      // Actions
      initCompany,
      startSearch,
      deleteCompany,
      startQuery
    }
  },
  {
    persist: {
      storage: localStorage
    }
  }
) as unknown as () => CompanyStore
