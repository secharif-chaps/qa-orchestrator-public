import type { Company } from '~/types.global'
import type { CompanyResponse } from '~/types/company'

/**
 * Adapter to convert between the API company model and the app company model
 */
export const useCompanyAdapter = () => {
  /**
   * Convert an API company response to the app company model
   */
  const apiToAppModel = (apiCompany: CompanyResponse): Partial<Company> => {
    const appCompany: Partial<Company> = {
      id: apiCompany.id,
      name: apiCompany.name,
      website: apiCompany.website,
      tasks: apiCompany.tasks
    }

    // Copy over profile data
    if (apiCompany.profile) {
      appCompany.profile = {
        ...(apiCompany.profile as any)
      }
    }

    // Copy over digital data
    if (apiCompany.digital) {
      appCompany.digital = {
        ...(apiCompany.digital as any)
      }
    }

    // Copy over timeline data
    if (apiCompany.timeline) {
      appCompany.timeline = {
        ...(apiCompany.timeline as any)
      }
    }

    // Copy over products data
    if (apiCompany.products) {
      appCompany.products = {
        ...(apiCompany.products as any)
      }
    }

    // Copy over jobs data
    if (apiCompany.jobs) {
      appCompany.jobs = {
        ...(apiCompany.jobs as any)
      }
    }

    // Copy over CSR data
    if (apiCompany.csr) {
      appCompany.csr = {
        ...(apiCompany.csr as any)
      }
    }

    // Copy over press data
    if (apiCompany.press) {
      appCompany.press = {
        ...(apiCompany.press as any)
      }
    }

    // Copy over team data
    if (apiCompany.team) {
      appCompany.team = [...apiCompany.team] as any
    }

    appCompany.created_at = apiCompany.created_at
    appCompany.updated_at = apiCompany.updated_at
    appCompany.owner_username = apiCompany.owner_username

    return appCompany
  }

  /**
   * Convert an app company model to the API company create/update model
   */
  const appToApiModel = (appCompany: Partial<Company>) => {
    return {
      name: appCompany.name,
      website: appCompany.website,
      tasks: appCompany.tasks,
      profile: appCompany.profile,
      digital: appCompany.digital,
      timeline: appCompany.timeline,
      products: appCompany.products,
      jobs: appCompany.jobs,
      csr: appCompany.csr,
      press: appCompany.press,
      team: appCompany.team
    }
  }

  return {
    apiToAppModel,
    appToApiModel
  }
}
