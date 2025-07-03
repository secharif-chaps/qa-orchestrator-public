<template>
  <div class=" py-8">
    <div class="max-w-7xl mx-auto">
      <!-- Welcome Header -->
      <div class="mb-8">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-3xl font-bold text-gray-900">
              Welcome back, {{ userDisplayName }}! 👋
            </h1>
            <p class="text-gray-600 mt-2">{{ greetingMessage }}</p>
          </div>
          <div class="text-right text-sm text-gray-500">
            <p>{{ currentDate }}</p>
            <p>{{ currentTime }}</p>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="mb-8">
        <DashboardQuickActions />
      </div>

      <!-- Statistics Overview -->
      <div class="mb-8">
        <DashboardStatisticsOverview 
          :stats="companiesStats" 
          :loading="pendingCompanies" 
        />
      </div>

      <!-- Recent Companies Section -->
      <div class="bg-white rounded-lg shadow-md mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-900">Recent Companies</h2>
            <NuxtLink 
              to="/companies"
              class="text-indigo-600 hover:text-indigo-500 text-sm font-medium flex items-center"
            >
              View all
              <i class="fas fa-arrow-right ml-1"></i>
            </NuxtLink>
          </div>
        </div>
        
        <!-- Loading State -->
        <div v-if="pendingCompanies" class="px-6 py-8">
          <div class="flex justify-center">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
          </div>
        </div>

        <!-- Error State -->
        <div v-else-if="companiesError" class="px-6 py-8">
          <div class="text-center">
            <i class="fas fa-exclamation-triangle text-red-400 text-2xl mb-2"></i>
            <p class="text-gray-500">Unable to load recent companies</p>
            <button @click="refreshCompanies" 
                    class="mt-2 text-indigo-600 hover:text-indigo-500 text-sm font-medium">
              Try again
            </button>
          </div>
        </div>

        <!-- Companies Grid -->
        <div v-else-if="recentCompanies.length > 0" class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div v-for="company in recentCompanies" 
                 :key="company.id"
                 class="border border-gray-200 rounded-lg p-4 hover:border-indigo-300 hover:shadow-md transition-all cursor-pointer group"
                 @click="navigateTo(`/companies/${company.id}`)">
              <div class="flex items-start justify-between mb-2">
                <h3 class="font-medium text-gray-900 truncate group-hover:text-indigo-600 transition-colors">
                  {{ company.name }}
                </h3>
                <span class="text-xs text-gray-500 ml-2">
                  {{ formatRelativeTime(company.created_at) }}
                </span>
              </div>
              <p class="text-sm text-gray-500 truncate mb-2">{{ company.website }}</p>
              <div class="flex items-center justify-between">
                <div class="flex items-center text-xs text-gray-400">
                  <i class="fas fa-tasks mr-1"></i>
                  {{ company.tasks?.length || 0 }} tasks
                </div>
                <div class="flex items-center">
                  <span class="w-2 h-2 rounded-full mr-1"
                        :class="getCompanyStatusColor(company)"></span>
                  <span class="text-xs text-gray-400">{{ getCompanyStatus(company) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="px-6 py-8 text-center">
          <i class="fas fa-building text-gray-300 text-3xl mb-4"></i>
          <h3 class="text-lg font-medium text-gray-900 mb-2">No companies yet</h3>
          <p class="text-gray-500 mb-4">Start by adding your first company to the database</p>
          <button @click="navigateTo('/search')"
                  class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
            <i class="fas fa-plus mr-2"></i>
            Add Company
          </button>
        </div>
      </div>

      <!-- Quick Tips -->
      <div class="bg-gradient-to-r from-primary to-purple-600 rounded-lg shadow-md p-6 text-white">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <i class="fas fa-lightbulb text-2xl text-indigo-100"></i>
          </div>
          <div class="ml-4">
            <h3 class="text-lg font-medium">Pro Tip</h3>
            <p class="text-indigo-100 mt-1">
              Use the search feature to quickly find and analyze companies. 
              You can also view detailed profiles and track company activities through the task system.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// Only access auth on client side
const { user } = process.client ? useAuth() : { user: ref(null) }
const companyRepository = useCompanyRepository()

// Reactive data
const recentCompanies = ref([])
const pendingCompanies = ref(true)
const companiesError = ref(null)
const currentTime = ref('')
const currentDate = ref('')

// Computed properties
const userDisplayName = computed(() => {
  if (!user.value?.profile) return 'User'
  return user.value.profile.given_name || 
         user.value.profile.preferred_username || 
         user.value.profile.name || 
         'User'
})

const greetingMessage = computed(() => {
  const hour = new Date().getHours()
  if (hour < 12) return 'Good morning! Ready to explore companies today?'
  if (hour < 17) return 'Good afternoon! Let\'s discover some amazing companies.'
  return 'Good evening! Time to wrap up your company research.'
})

const companiesStats = computed(() => {
  if (!recentCompanies.value.length) return {}
  
  const total = recentCompanies.value.length
  const activeTasks = recentCompanies.value.reduce((acc, company) => {
    return acc + (company.tasks?.filter(task => task.status !== 'succeeded').length || 0)
  }, 0)
  
  const today = new Date()
  const oneDayAgo = new Date(today.getTime() - 24 * 60 * 60 * 1000)
  const recentUpdates = recentCompanies.value.filter(company => 
    new Date(company.updated_at) > oneDayAgo
  ).length
  
  return { total, activeTasks, recentUpdates }
})

// Methods
const loadRecentCompanies = async () => {
  try {
    pendingCompanies.value = true
    companiesError.value = null
    
    const companies = await companyRepository.getCompanies()
    
    // Sort by creation date and take the 4 most recent
    recentCompanies.value = companies
      .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
      .slice(0, 4)
      
  } catch (error) {
    console.error('Error loading companies:', error)
    companiesError.value = error.message || 'Failed to load companies'
  } finally {
    pendingCompanies.value = false
  }
}

const refreshCompanies = () => {
  loadRecentCompanies()
}

const formatRelativeTime = (dateString) => {
  const date = new Date(dateString)
  const now = new Date()
  const diffInSeconds = Math.floor((now - date) / 1000)
  
  if (diffInSeconds < 60) return 'Just now'
  if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`
  if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`
  return `${Math.floor(diffInSeconds / 86400)}d ago`
}

const getCompanyStatus = (company) => {
  if (!company.tasks || company.tasks.length === 0) return 'New'
  
  const hasRunningTasks = company.tasks.some(task => 
    task.status === 'running' || task.status === 'pending'
  )
  if (hasRunningTasks) return 'Processing'
  
  const hasFailedTasks = company.tasks.some(task => task.status === 'failed')
  if (hasFailedTasks) return 'Issues'
  
  return 'Complete'
}

const getCompanyStatusColor = (company) => {
  const status = getCompanyStatus(company)
  switch (status) {
    case 'New': return 'bg-gray-400'
    case 'Processing': return 'bg-yellow-400'
    case 'Issues': return 'bg-red-400'
    case 'Complete': return 'bg-green-400'
    default: return 'bg-gray-400'
  }
}

const updateTime = () => {
  const now = new Date()
  currentTime.value = now.toLocaleTimeString('en-US', { 
    hour: '2-digit', 
    minute: '2-digit',
    hour12: true
  })
  currentDate.value = now.toLocaleDateString('en-US', { 
    weekday: 'long',
    year: 'numeric', 
    month: 'long', 
    day: 'numeric'
  })
}

// Lifecycle
onMounted(() => {
  loadRecentCompanies()
  updateTime()
  
  // Update time every minute
  const timeInterval = setInterval(updateTime, 60000)
  
  onUnmounted(() => {
    clearInterval(timeInterval)
  })
})

// Page meta
definePageMeta({
  layout: 'default',
  title: 'Dashboard - Mint'
})
</script>