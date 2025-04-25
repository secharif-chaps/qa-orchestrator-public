// stores/company.ts
import { defineStore } from 'pinia';
import type { Company, SourcedValue } from '~/types.global';
import { ref, computed } from 'vue';

interface CompanyState {
  companies: Record<string, Partial<Company>>;
  currentCompany: string | null;
}

// Définir l'interface du store
export interface CompanyStore {
  // state
  companies: Record<string, Partial<Company>>;
  currentCompany: string | null;
  
  // getters
  getCurrentCompany: () => Partial<Company> | null;
  getCompanyByName: (name: string) => Partial<Company> | null;
  getCompanyList: () => Partial<Company>[];
  
  // actions
  initCompany: (name: string) => void;
  updateCompanyProperty: (companyName: string, propertyPath: string, value: any) => void;
  updateCompanyProperties: (companyName: string, properties: Partial<Company>) => void;
  setCompanyData: (name: string, data: Partial<Company>) => void;
  setCurrentCompany: (name: string) => void;
  deleteCompany: (name: string) => void;
}

export const useCompanyStore = defineStore('company', () => {
  // State
  const companies = ref<Record<string, Partial<Company>>>({});
  const currentCompany = ref<string | null>(null);

  const router = useRouter()

  // Getters
  const getCurrentCompany = computed(() => {
    if (!currentCompany.value) return null;
    return companies.value[currentCompany.value] || null;
  });

  const getCompanyByName = (name: string) => {
    return companies.value[name] || null;
  };

  const getCompanyList = computed(() => {
    return Object.values(companies.value);
  });

  // Actions
  const initCompany = (name: string, website: string) => {
    if (!companies.value[name]) {
      companies.value[name] = {
        name,
        website,
      };
    } else {
      console.log('Company already exists');
    }
  };

  const startSearch = async (company: string, website: string) => {
    try {
      if (!companies.value[company]) {
        initCompany(company, website);
      }
      companies.value[company].pending = true;
      router.push(`/cards/${company}`)

      const response = await fetch('https://nico-mrc.app.n8n.cloud/webhook-test/57be7c18-e8b2-47aa-b9aa-f7c2696f4523', {
        method: 'POST',
        body: JSON.stringify({
          company: company,
          website: website
        }),
        headers: {
          'Content-Type': 'application/json',
        }
      });

      let data = await response.json();
      data = data[0].data[0].output;
      
      companies.value[company]  = {...companies.value[company], ...data};
      
    } catch (error) {
      console.error('Error fetching data:', error);
    } finally {
      companies.value[company].pending = false;
    }
  };

  const setCurrentCompany = (name: string) => {
    name = name.toLocaleLowerCase();
    if (companies.value[name]) {
      currentCompany.value = name;
    } else {
      initCompany(name, '');
    }
  };

  const deleteCompany = (name: string) => {
    if (companies.value[name]) {
      delete companies.value[name];
      delete propertyUpdates.value[name];
      
      if (currentCompany.value === name) {
        currentCompany.value = null;
      }
    }

    const lowerCaseName = name.toLocaleLowerCase();

    if (companies.value[lowerCaseName]) {
      delete companies.value[lowerCaseName];
      delete propertyUpdates.value[lowerCaseName];
      
      if (currentCompany.value === lowerCaseName) {
        currentCompany.value = null;
      }
    }
  };

  return {
    // State
    companies,
    currentCompany,

    // Getters
    getCurrentCompany,
    getCompanyByName,
    getCompanyList,

    // Actions
    initCompany,
    startSearch,
    setCurrentCompany,
    deleteCompany
  };
}) as unknown as () => CompanyStore;