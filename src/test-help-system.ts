// Test file to verify permission-based help system
// This file can be used to manually test the help system with different permissions

import { usePermissionBasedHelp } from '@/composables/usePermissionBasedHelp'
import { useAuthStore } from '@/stores/auth'

// Mock user with different permissions for testing
export const testHelpSystem = () => {
  const { 
    availableHelpSections, 
    hasAnyHelpAccess, 
    helpCategories,
    helpSectionsByCategory,
    loadHelpContent 
  } = usePermissionBasedHelp()
  
  console.log('=== Permission-Based Help System Test ===')
  console.log('Available help sections:', availableHelpSections.value)
  console.log('Has any help access:', hasAnyHelpAccess.value)
  console.log('Help categories:', helpCategories.value)
  console.log('Help sections by category:', helpSectionsByCategory.value)
  
  // Test loading content for each available section
  availableHelpSections.value.forEach(async (section) => {
    console.log(`\n--- Testing ${section.permission} ---`)
    try {
      const content = await loadHelpContent(section.permission)
      console.log(`Content loaded successfully (${content.length} characters)`)
    } catch (error) {
      console.error(`Failed to load content for ${section.permission}:`, error)
    }
  })
}

// Test scenarios with different permission sets
export const testPermissionScenarios = () => {
  const authStore = useAuthStore()
  
  console.log('=== Testing Different Permission Scenarios ===')
  
  // Scenario 1: Admin user with all permissions
  console.log('\n1. Testing admin user scenario:')
  const adminPermissions = [
    'admin.costs', 'admin.workspaces', 'admin.workflows', 
    'company.create', 'company.view', 'company.delete',
    'workspace.read', 'workspace.write'
  ]
  
  // Scenario 2: Company analyst with limited permissions  
  console.log('\n2. Testing company analyst scenario:')
  const analystPermissions = [
    'company.create', 'company.view', 'workspace.read'
  ]
  
  // Scenario 3: Workspace manager
  console.log('\n3. Testing workspace manager scenario:')
  const managerPermissions = [
    'company.view', 'workspace.read', 'workspace.write'
  ]
  
  // Scenario 4: Read-only user
  console.log('\n4. Testing read-only user scenario:')
  const readOnlyPermissions = [
    'company.view', 'workspace.read'
  ]
  
  console.log('Admin permissions available help sections:', adminPermissions.length)
  console.log('Analyst permissions available help sections:', analystPermissions.length)
  console.log('Manager permissions available help sections:', managerPermissions.length)  
  console.log('Read-only permissions available help sections:', readOnlyPermissions.length)
}

// Export for browser console testing
if (typeof window !== 'undefined') {
  (window as any).testHelpSystem = testHelpSystem
  (window as any).testPermissionScenarios = testPermissionScenarios
}