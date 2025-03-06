// stores/company.ts
import { defineStore } from 'pinia';
import type { Company } from '~/types.global';

interface CompanyState {
  companies: Record<string, Partial<Company>>;
  currentCompany: string | null;
  propertyUpdates: Record<string, Record<string, boolean>>;
}

export const useCompanyStore = defineStore('company', {
  state: (): CompanyState => ({
    companies: {},
    currentCompany: null,
    propertyUpdates: {}
  }),

  persist: {
    // You can specify which paths to persist
    paths: ['companies'],
    // Custom storage key (optional)
    key: 'companies',
  },

  getters: {
    getCurrentCompany: (state) => {
      if (!state.currentCompany) return null
      return state.companies[state.currentCompany] || null
    },
    
    getCompanyByName: (state) => (name: string) => {
      return state.companies[name] || null
    },
    
    getCompanyList: (state) => {
      return Object.values(state.companies)
    },
    
    // Check if a specific property has been updated
    hasPropertyBeenUpdated: (state) => (companyName: string, propertyPath: string) => {
      const companyUpdates = state.propertyUpdates[companyName]
      if (!companyUpdates) return false
      return companyUpdates[propertyPath] || false
    }
  },

  actions: {
    // Initialize a company with empty data
    initCompany(name: string) {
      if (!this.companies[name]) {
        this.companies[name] = {
          profile: { name }
        }
      }
      
      // Initialize property updates tracking
      if (!this.propertyUpdates[name]) {
        this.propertyUpdates[name] = {}
      }
      
      this.currentCompany = name
    },
    
    // Update a specific property path in the company object
    updateCompanyProperty(companyName: string, propertyPath: string, value: any) {
      if (!this.companies[companyName]) {
        this.initCompany(companyName)
      }
      
      // Set value by path
      const parts = propertyPath.split('.')
      let current = this.companies[companyName]
      
      for (let i = 0; i < parts.length - 1; i++) {
        const part = parts[i]
        if (!current[part]) {
          current[part] = {}
        }
        current = current[part]
      }
      
      const lastPart = parts[parts.length - 1]
      current[lastPart] = value
      
      // Mark this property as updated
      if (!this.propertyUpdates[companyName]) {
        this.propertyUpdates[companyName] = {}
      }
      this.propertyUpdates[companyName][propertyPath] = true
    },
    
    // Update multiple properties at once
    updateCompanyProperties(companyName: string, properties: Partial<Company>) {
      if (!this.companies[companyName]) {
        this.initCompany(companyName)
      }
      
      // Function to recursively merge objects
      const deepMerge = (target, source) => {
        const output = { ...target }
        
        if (isObject(target) && isObject(source)) {
          Object.keys(source).forEach(key => {
            if (isObject(source[key])) {
              if (!(key in target)) {
                Object.assign(output, { [key]: source[key] })
              } else {
                output[key] = deepMerge(target[key], source[key])
              }
            } else if (Array.isArray(source[key])) {
              // For arrays, we replace rather than merge
              output[key] = [...source[key]]
            } else {
              Object.assign(output, { [key]: source[key] })
            }
          })
        }
        
        return output
      }
      
      // Helper to check if value is an object
      const isObject = (item) => {
        return (item && typeof item === 'object' && !Array.isArray(item))
      }
      
      // Merge the properties with existing company data
      this.companies[companyName] = deepMerge(this.companies[companyName], properties)
      
      // Mark all the top-level properties as updated
      if (!this.propertyUpdates[companyName]) {
        this.propertyUpdates[companyName] = {}
      }
      
      // Recursively mark all property paths as updated
      const markUpdated = (obj, prefix = '') => {
        for (const key in obj) {
          const path = prefix ? `${prefix}.${key}` : key
          this.propertyUpdates[companyName][path] = true
          
          if (isObject(obj[key])) {
            markUpdated(obj[key], path)
          }
        }
      }
      
      markUpdated(properties)
    },
    
    // Set complete company data
    setCompanyData(name: string, data: Partial<Company>) {
      if (!this.companies[name]) {
        this.initCompany(name)
      }
      
      // Preserve the graph if it exists
      const graph = this.companies[name].partners_and_competitors_graph
      
      // Replace all data
      this.companies[name] = {
        ...data,
        partners_and_competitors_graph: graph || data.partners_and_competitors_graph
      }
      
      // Mark all properties as updated
      if (!this.propertyUpdates[name]) {
        this.propertyUpdates[name] = {}
      }
      
      // Recursively mark all property paths as updated
      const markUpdated = (obj, prefix = '') => {
        for (const key in obj) {
          const path = prefix ? `${prefix}.${key}` : key
          this.propertyUpdates[name][path] = true
          
          if (obj[key] && typeof obj[key] === 'object' && !Array.isArray(obj[key])) {
            markUpdated(obj[key], path)
          }
        }
      }
      
      markUpdated(data)
    },
    
    // Update a specific property in the graph
    updateGraphProperty(companyName: string, propertyName: 'nodes' | 'edges', value: any) {
      if (!this.companies[companyName]) {
        this.initCompany(companyName)
      }
      
      // Ensure the graph object exists
      if (!this.companies[companyName].partners_and_competitors_graph) {
        this.companies[companyName].partners_and_competitors_graph = {
          nodes: [],
          edges: []
        }
      }
      
      // Update the specific property
      this.companies[companyName].partners_and_competitors_graph[propertyName] = value
      
      // Mark this property as updated
      if (!this.propertyUpdates[companyName]) {
        this.propertyUpdates[companyName] = {}
      }
      this.propertyUpdates[companyName][`partners_and_competitors_graph.${propertyName}`] = true
    },
    
    // Set graph data
    setGraphData(name: string, graphData: any) {
      if (!this.companies[name]) {
        this.initCompany(name)
      }
      
      // Update only the graph part
      this.companies[name].partners_and_competitors_graph = graphData
      
      // Mark graph properties as updated
      if (!this.propertyUpdates[name]) {
        this.propertyUpdates[name] = {}
      }
      this.propertyUpdates[name]['partners_and_competitors_graph'] = true
      this.propertyUpdates[name]['partners_and_competitors_graph.nodes'] = true
      this.propertyUpdates[name]['partners_and_competitors_graph.edges'] = true
    },
    
    setCurrentCompany(name: string) {
      if (this.companies[name]) {
        this.currentCompany = name
      } else {
        this.initCompany(name)
      }
    },
    
    // Clean up method
    deleteCompany(name: string) {
      if (this.companies[name]) {
        delete this.companies[name]
        delete this.propertyUpdates[name]
        
        if (this.currentCompany === name) {
          this.currentCompany = null
        }
      }
    }
  }
})