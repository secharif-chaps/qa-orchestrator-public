export default {
  welcome: 'Welcome',
  settings: {
    language: {
      title: 'Language Settings',
      description: 'Choose your preferred language for the application',
    },
    theme: {
      title: 'Theme Settings',
      description: 'Customize the appearance of your application',
    },
    notifications: {
      title: 'Notification Settings',
      description: 'Manage your notification preferences',
    },
    title: 'Account Settings',
    description: 'Manage your account information and preferences',
    tabs: {
      profile: 'Profile',
      appearance: 'Appearance',
      preferences: 'Preferences',
      security: 'Security',
    },
    profile: {
      error: {
        title: 'Error loading profile',
      },
      basic: {
        title: 'Basic Information',
        description: 'Your personal account details',
      },
      auth: {
        title: 'Authentication Details',
        description: 'Session and authentication information',
      },
      roles: {
        title: 'Roles & Permissions',
        description: 'Your assigned roles and access levels',
        none: 'No roles assigned',
      },
      debug: {
        title: 'Debug Information',
        description: 'Technical details for debugging purposes',
      },
      fields: {
        username: 'Username',
        email: 'Email',
        firstName: 'First Name',
        lastName: 'Last Name',
        userId: 'User ID',
        expiresAt: 'Token Expires At',
        issuedAt: 'Issued At',
        sessionState: 'Session State',
      },
      status: {
        active: 'Active',
        expired: 'Expired',
      },
      actions: {
        refresh: 'Refresh Profile',
        signOut: 'Sign Out',
      },
    },
    appearance: {
      theme: {
        title: 'Theme',
        description: 'Choose your preferred theme',
        options: {
          light: {
            title: 'Light',
            description: 'Clean and bright interface',
          },
          dark: {
            title: 'Dark',
            description: 'Easy on the eyes in low light',
          },
          system: {
            title: 'System',
            description: 'Matches your device preference',
          },
        },
      },
      accent: {
        title: 'Accent Color',
        description: 'Choose your preferred accent color for the interface',
      },
      language: {
        title: 'Language',
        description: 'Select your preferred language',
      },
      layout: {
        title: 'Layout Preferences',
        description: 'Customize the interface layout',
        compact: {
          title: 'Compact Mode',
          description: 'Reduce spacing for more content',
        },
        reducedMotion: {
          title: 'Reduced Motion',
          description: 'Minimize animations and transitions',
        },
      },
      preview: {
        title: 'Theme Preview',
        description: 'See how your theme looks',
        sample: 'This is a sample of your current theme',
        tag1: 'Sample',
        tag2: 'Preview',
      },
    },
    preferences: {
      notifications: {
        title: 'Notifications',
        description: 'Control when and how you receive notifications',
        email: {
          title: 'Email Notifications',
          options: {
            welcome: {
              title: 'Welcome emails',
              description: 'Receive welcome messages and getting started guides',
            },
            updates: {
              title: 'Product updates',
              description: 'News about new features and improvements',
            },
            security: {
              title: 'Security alerts',
              description: 'Important security and account notifications',
            },
            marketing: {
              title: 'Marketing emails',
              description: 'Promotional content and special offers',
            },
          },
        },
        push: {
          title: 'Push Notifications',
          options: {
            mentions: {
              title: 'Mentions',
              description: 'When someone mentions you',
            },
            messages: {
              title: 'Direct messages',
              description: 'New direct messages and replies',
            },
            updates: {
              title: 'System updates',
              description: 'Important system notifications',
            },
          },
        },
      },
      privacy: {
        title: 'Data & Privacy',
        description: 'Manage your data and privacy settings',
        analytics: {
          title: 'Analytics',
          description: 'Help improve the service by sharing usage data',
        },
        export: {
          title: 'Export Data',
          description: 'Download a copy of your data',
        },
        delete: {
          title: 'Delete Account',
          description: 'Permanently delete your account and all data',
          dialog: {
            title: 'Delete Account',
            description: 'This action cannot be undone. All your data will be permanently deleted.',
          },
        },
      },
      actions: {
        cancel: 'Cancel',
        save: 'Save Changes',
        export: 'Export Data',
        deleteAccount: 'Delete Account',
      },
      behavior: {
        title: 'Behavior',
        description: 'Customize how the application behaves',
        autoSave: {
          title: 'Auto-save',
          description: 'Automatically save changes as you work',
        },
        confirmations: {
          title: 'Show confirmations',
          description: 'Ask for confirmation before important actions',
        },
      },
    },
    security: {
      sessions: {
        title: 'Session Management',
        description: 'Manage your active sessions and devices',
        current: {
          title: 'Current Session',
          badge: 'Current',
          lastActive: 'Last active',
        },
        lastActive: 'Last active',
        signOutAll: {
          title: 'Sign out all devices',
          description: 'Sign out of all other sessions and devices',
        },
      },
      twoFactor: {
        title: 'Two-Factor Authentication',
        description: 'Add an extra layer of security to your account',
        authenticator: {
          title: 'Authenticator App',
          description: 'Use an authenticator app for secure login',
        },
        securityKeys: {
          title: 'Security Keys',
          description: 'Use hardware security keys for login',
          count: 'keys',
        },
      },
      activity: {
        title: 'Activity Log',
        description: 'Recent security and account activity',
      },
      recovery: {
        title: 'Account Recovery',
        description: 'Set up recovery options for your account',
        backupCodes: {
          title: 'Backup Codes',
          description: 'Generate backup codes for account recovery',
        },
        email: {
          title: 'Recovery Email',
          notSet: 'No recovery email set',
        },
      },
      status: {
        enabled: 'Enabled',
        disabled: 'Disabled',
        generated: 'Generated',
        notGenerated: 'Not Generated',
      },
      actions: {
        setup: 'Setup',
        disable: 'Disable',
        manage: 'Manage',
        generate: 'Generate',
        regenerate: 'Regenerate',
        add: 'Add',
        update: 'Update',
        revoke: 'Revoke',
      },
    },
  },
  sidebar: {
    folders: 'Folders',
    home: 'Home',
    search: 'Search',
    cards: 'Cards',
    settings: 'Settings',
    help: 'Help',
    team: 'Team',
    workspaces: 'Workspaces',
    admin: 'Admin',
  },
  help: {
    title: 'Help',
    description:
      'Need assistance? Find answers to common questions and learn how to use the application.',
  },
  cards: {
    title: 'Cards',
    noResults: 'No company found',
    table: {
      name: 'Name',
      creator: 'Creator',
      lastModification: 'Last modification',
      actions: 'actions',
    },
    actions: {
      view: 'View',
      delete: 'Delete',
    },
  },
  search: {
    title: 'New company',
    companyIdentity: 'Company identity',
    advancedSearch: 'Advanced search',
    fields: {
      companyName: {
        label: 'Company name',
        placeholder: 'Sephora',
        error: 'Company name must be at least 2 characters long',
      },
      website: {
        label: 'Website',
        placeholder: 'https://www.sephora.fr',
        error: 'Please enter a valid URL (e.g., https://www.example.com)',
      },
    },
    mandatoryFields: 'mandatory fields to start search',
    actions: {
      deleteData: 'Delete Data',
      launchSearch: 'Launch Search',
    },
  },
  login: {
    title: 'Login',
    email: {
      label: 'Email address',
      placeholder: 'example@gmail.com',
    },
    password: {
      label: 'Password',
      placeholder: 'Enter a password...',
      forgot: 'Forgot password?',
    },
    submit: 'Login',
    errors: {
      invalidCredentials: 'Invalid email or password',
      connectionError: 'An error occurred during login',
    },
  },
  auth: {
    callback: {
      processing: 'Processing login...',
      loginFailed: 'Login failed',
      tryAgain: 'Try again',
    },
  },
  errors: {
    notFound: {
      title: 'Page Not Found',
      message: "The page you're looking for doesn't exist or has been moved.",
      goHome: 'Go to Home',
      goBack: 'Go Back',
      help: 'If you believe this page should exist, please contact support.',
    },
    forbidden: {
      title: 'Access Forbidden',
      message: "You don't have permission to access this page.",
      goHome: 'Go to Home',
      goBack: 'Go Back',
      contact: 'If you believe this is an error, please contact your administrator.',
    },
  },
  dashboard: {
    title: 'Dashboard',
    actions: {
      search: 'Search',
      cards: 'Cards',
    },
  },
  common: {
    time: {
      day: 'day',
      days: 'days',
      hours: '{count}h',
      fewMinutes: 'a few minutes',
    },
    comingSoon: 'Coming soon',
    notFound: 'Not found',
    loading: 'Loading...',
    noData: 'No data available',
    save: 'Save',
    error: 'Error',
  },
  appbar: {
    search: 'Search...',
    theme: {
      pink: 'Pink theme',
      indigo: 'Indigo theme',
      emerald: 'Emerald theme',
      dark: 'Dark theme',
    },
  },
  timeline: {
    title: 'Timeline & Key Milestones',
    loading: {
      title: 'Loading company timeline data...',
      description: 'Fetching milestone events data from AI agent...',
    },
    noData: {
      title: 'No Timeline Data Available',
      description: 'Fetch milestone events for this company to see its history',
    },
    search: {
      placeholder: 'Search...',
      noResults: 'No events found matching "{query}"',
    },
  },
  communications: {
    title: 'Corporate Communications',
    loading: {
      title: 'Not implemented yet',
      description: 'This feature is not implemented yet.',
    },
    comingSoon: 'Coming soon',
  },
  financials: {
    title: 'Financials',
    loading: {
      title: 'Not implemented yet',
      description: 'This feature is not implemented yet.',
    },
    comingSoon: 'Coming soon',
  },
  jobs: {
    title: 'Job Offers',
    loading: {
      title: 'Loading job offers...',
      description: 'Fetching current job opportunities...',
    },
    noData: {
      title: 'No Job Offers Available',
      description: 'Fetch job offers for this company to see current opportunities',
    },
    insights: {
      title: 'Hiring Insights',
      totalOpenings: 'Total Openings',
      topDepartments: 'Top Departments',
      hiringFocus: 'Hiring Focus',
      growthIndicators: 'Growth Indicators',
    },
    listings: {
      title: 'Current Openings',
      search: {
        placeholder: 'Search jobs...',
      },
      noResults: 'No job offers found matching "{query}"',
    },
  },
  mentions: {
    title: 'Mentions',
    loading: {
      title: 'Not implemented yet',
      description: 'This feature is not implemented yet.',
    },
    comingSoon: 'Coming soon',
  },
  products: {
    title: 'Products & Services',
    loading: {
      title: 'Loading products...',
      description: 'Fetching products and services data...',
    },
    noData: {
      title: 'No Products Available',
      description: 'Products information will be displayed here once available.',
    },
  },
  profile: {
    title: 'Company Profile',
    loading: {
      title: 'Loading company profile...',
      description: 'Fetching comprehensive company information...',
    },
    sections: {
      products: {
        title: 'Products and services',
        insights: {
          title: 'Product Insights',
        },
        range: 'Product Range',
        partnerBrands: 'Partner Brands',
        privateLabels: '{company} Private Labels',
      },
      target: {
        title: 'Target audience',
        customerBase: 'Customer Base',
        positioning: 'Marketing Positioning',
      },
      csr: {
        title: 'Corporate Social Responsibility',
        insights: {
          title: 'CSR Insights',
        },
        responsibility: 'Responsibility Statement',
        responsibility_initiatives: 'Responsibility Initiatives',
        charity: 'Charity Actions',
        sustainability: 'Sustainability Programs',
        community: 'Community Involvement',
        diversity: 'Diversity & Inclusion',
        ethics: 'Ethical Practices',
        awards: 'Awards & Certifications',
      },
      digital: {
        title: 'Digital strategy',
        insights: {
          title: 'Digital Strategy Insights',
        },
        strategy: 'Digital Strategy',
        loyaltyProgram: 'Loyalty Program',
        onlineServices: 'Online Services',
      },
      news: {
        title: 'Recent news',
      },
      press: {
        insights: {
          title: 'Press Coverage Insights',
        },
      },
      metrics: {
        establishment: 'Year of establishment',
        employees: 'Number of employees',
        revenue: 'Revenue',
      },
    },
  },
  team: {
    title: 'Team & Management',
    loading: {
      title: 'Loading team data...',
      description: 'Fetching team hierarchy and management structure...',
    },
    noData: {
      title: 'No Team Data Available',
      description: 'Fetch team hierarchy for this company to see management structure',
    },
    hierarchy: {
      title: 'Management Hierarchy',
      viewLinkedIn: 'View LinkedIn Profile',
    },
    email: 'Email',
    emailPlaceholder: 'Enter email address',
    emailCannotChange: 'Email cannot be changed after creation',
    usernamePlaceholder: 'Enter username (optional)',
    usernameCannotChange: 'Username cannot be changed after creation',
    permissions: 'Permissions',
    'permissions.description': 'Select the permissions for this team member',
    disable: 'Disable',
    description: 'Manage team members and their permissions',
    table: {
      user: 'User',
      permissions: 'Permissions',
      created: 'Created',
      status: 'Status',
      actions: 'Actions',
    },
    create: {
      button: 'Add Member',
    },
    edit: {
      title: 'Edit Team Member',
      description: 'Update team member information and permissions',
    },
    firstName: 'First Name',
    firstNamePlaceholder: 'Enter first name',
    lastName: 'Last Name',
    lastNamePlaceholder: 'Enter last name',
    username: 'Username',
    search: {
      placeholder: 'Search team members...',
    },
    pageSize: {
      label: 'Items per page',
    },
    sort: {
      label: 'Sort by',
      created: 'Created Date',
      name: 'Name',
      email: 'Email',
      username: 'Username',
      asc: 'Ascending',
      desc: 'Descending',
    },
    status: {
      active: 'Active',
      label: 'Status',
      all: 'All',
      disabled: 'Disabled',
    },
  },
  admin: {
    dashboard: {
      title: 'Admin Dashboard',
      description: 'Manage system features and settings',
      back: 'Back to Admin',
      limitedAccess: {
        title: 'Limited Access',
        message:
          'You have access to basic admin features. Contact your administrator for additional permissions.',
      },
      quickStats: {
        title: 'System Overview',
      },
      stats: {
        workspaces: 'Total Workspaces',
        users: 'Active Users',
        companies: 'Companies',
        health: 'System Health',
      },
    },
    features: {
      workspaces: {
        title: 'Workspace Management',
        description: 'Manage all workspaces, users, and workspace settings',
      },
      uiDemo: {
        title: 'UI Components Demo',
        description: 'Preview and test all UI components and design system',
      },
      workflows: {
        title: 'Workflow Management',
        description: 'Configure and manage automated workflows and processes',
      },
      costs: {
        title: 'Cost Analysis',
        description: 'Monitor token usage and costs for MINT screening workflows',
      },
      manage: 'Manage',
      explore: 'Explore',
      configure: 'Configure',
      analyze: 'Analyze',
    },
    workflows: {
      title: 'Workflow Management',
      description: 'Configure Dify workflow integrations for automated analysis tasks',
      loading: 'Loading workflows...',
      updateSuccess: 'Workflow updated successfully!',
      workflowId: 'Workflow ID',
      workflowIdPlaceholder: 'Enter Dify workflow ID',
      apiKey: 'API Key',
      apiKeyPlaceholder: 'Enter Dify API key',
      notConfigured: 'Not configured',
      error: {
        title: 'Failed to Load Workflows',
      },
    },
  },
  company: {
    loading: 'Loading companies...',
    name: 'Company',
    created: 'Created',
    owner: 'Owner',
    status: 'Status',
    actions: 'Actions',
    clearSearch: 'Clear Search',
    dashboard: {
      title: 'Company Dashboard',
      description: 'Company Information Dashboard',
      generalInfo: {
        website: 'Website',
        headquarters: 'Headquarters',
        ceo: 'CEO',
        revenue: 'Revenue',
      },
      infoCards: {
        profile: {
          title: 'Company Profile',
          description: 'View detailed company information, business lines, and key metrics.',
        },
        activities: {
          title: 'Activities & Events',
          description: 'Explore company events, trade shows, and key activities.',
        },
        products: {
          title: 'Products',
          description: "Browse the company's products, services, and offerings.",
        },
        team: {
          title: 'Team & Management',
          description: 'Leadership team, organizational structure, and key personnel.',
        },
        jobs: {
          title: 'Job Offers',
          description: 'Current job openings, career opportunities, and hiring information.',
        },
        communications: {
          title: 'Corporate Communications',
          description: 'Press releases, public statements, and official communications.',
        },
        financials: {
          title: 'Financials',
          description: 'Financial data, revenue information, and market performance.',
        },
        mentions: {
          title: 'Mentions',
          description: 'News articles, media coverage, and third-party mentions.',
        },
        press: {
          title: 'Press & Media Coverage',
          description: 'Press releases, news articles, interviews, and media mentions.',
        },
      },
    },
    list: {
      title: 'Companies',
      description: 'Manage your company database',
      error: {
        description: 'Failed to load companies',
      },
      create: {
        title: 'Create New Company',
        name: {
          label: 'Company Name',
          placeholder: 'Enter company name',
        },
        website: {
          label: 'Website',
          placeholder: 'Enter website URL',
        },
        actions: {
          cancel: 'Cancel',
          create: 'Create',
        },
      },
      delete: {
        title: 'Delete Company',
        confirm: 'Are you sure you want to delete',
        warning: 'This action cannot be undone.',
        actions: {
          cancel: 'Cancel',
          delete: 'Delete',
        },
      },
      table: {
        website: 'Website',
        loading: 'Loading...',
      },
    },
    create: {
      button: 'Make a new search',
    },
    empty: {
      noResults: 'No companies found',
      title: 'No companies yet',
      tryDifferentSearch: 'Try a different search term',
      description: 'Start by adding your first company',
    },
    fields: {
      employeeCount: 'Employee Count',
      headquarters: 'Headquarters',
      ceo: 'CEO',
      revenue: 'Revenue',
      website: 'Website',
      notSpecified: 'Not specified',
    },
    sections: {
      onlinePresence: 'Online Presence',
      socialMedia: 'Social Media',
      analysis: 'Analysis',
    },
    analysisCards: {
      profile: {
        title: 'Company Profile',
        description: 'View detailed company information, business lines, and key metrics',
        insights: 'Profile Insights',
      },
      timeline: {
        title: 'Timeline & History',
        description: 'Company history, milestones, and key events over time',
        insights: 'Historical Insights',
      },
      products: {
        title: 'Products & Services',
        description: 'Browse products, services, and offerings',
        insights: 'Product Insights',
      },
      team: {
        title: 'Team & Management',
        description: 'Leadership team, organizational structure, and key personnel',
        insights: 'Team Insights',
      },
      jobs: {
        title: 'Job Offers',
        description: 'Current job openings and career opportunities',
        insights: 'Hiring Insights',
      },
      press: {
        title: 'Press & Media',
        description: 'Press releases, news articles, and media coverage',
        insights: 'Media Insights',
      },
      csr: {
        title: 'Corporate Social Responsibility',
        description: 'CSR initiatives, sustainability programs, and social impact',
        insights: 'CSR Insights',
      },
      communications: {
        title: 'Corporate Communications',
        description: 'Press releases, public statements, and official communications',
        insights: 'Communication Insights',
      },
    },
    restore: {
      title: 'Restore Company',
    },
    validation: {
      loadingWorkspace: 'Loading workspace...',
      loadingTokens: 'Loading tokens...',
      moduleDisabled: 'The Stream module is disabled',
      insufficientTokens: 'Insufficient tokens. You need at least 1 token to create a company.',
      invalidNameFormat: 'Company name must contain at least 2 alphabetic characters',
      invalidWebsiteFormat: 'Please enter a valid website URL',
      nameRequired: 'Company name is required',
      websiteRequired: 'Website URL is required',
    },
  },
  folder: {
    title: 'Folders',
    description: 'Organize your companies into folders',
    search: 'Search folders...',
    list: {
      error: {
        title: 'Error',
        description: 'Failed to load folders',
      },
    },
    loading: 'Loading folders...',
    create: {
      title: 'Create New Folder',
      subtitle: 'Organize your companies with a custom folder',
      description: 'Organize your companies into folders',
      button: 'Create Folder',
    },
    edit: {
      title: 'Edit Folder',
      subtitle: 'Update your folder settings and appearance',
      error: {
        title: 'Error',
        description: 'Failed to load folder',
      },
    },
    detail: {
      error: {
        title: 'Error',
        description: 'Failed to load folder',
      },
    },
    item: {
      name: 'Item',
      type: 'Type',
      created: 'Created',
      owner: 'Owner',
      actions: 'Actions',
      view: 'View',
      deleted: 'Deleted',
    },
    form: {
      name: 'Folder Name',
      namePlaceholder: 'Enter folder name...',
      tags: 'Tags',
      tagsOptional: 'optional',
      tagsPlaceholder: 'Enter tags separated by commas...',
      favorite: 'Mark as favorite',
      cancel: 'Cancel',
      create: 'Create Folder',
      save: 'Save Changes',
    },
    filter: {
      all: 'All',
      favorites: 'Favorites',
      archived: 'Archived',
      allFolders: 'All folders',
      favoriteFolders: 'Favorite folders',
      archivedFolders: 'Archived folders',
    },
    viewMode: {
      table: 'Table',
      grid: 'Grid',
      tableView: 'Table View',
      gridView: 'Grid View',
    },
    validation: {
      nameRequired: 'Folder name is required',
      nameMinLength: 'Folder name must be at least 3 characters',
      nameMaxLength: 'Folder name must be less than 50 characters',
    },
    table: {
      name: 'Name',
      items: 'Items',
      created: 'Created',
      actions: 'Actions',
    },
    empty: {
      noResults: 'No folders found',
      title: 'No folders yet',
      tryDifferentSearch: 'Try a different search term',
      description: 'Create your first folder to organize your companies',
    },
    clearSearch: 'Clear Search',
  },
  home: {
    welcome: {
      title: 'Welcome back, {name}!',
    },
    assistant: {
      greeting: 'Can I help you?',
      actions: {
        generatePdf: 'Generate a PDF for me',
        newSearch: 'I want to make a new search',
      },
    },
    recentProjects: {
      title: 'Recent Projects',
      viewAll: 'View all',
      timeAgo: '{time} ago',
      noFolder: 'No folder',
      badge: {
        collaborative: 'Collaborative',
      },
    },
    recentActivities: {
      title: 'Recent Activities',
      by: 'by @{username}',
      actions: {
        createdCompany: 'created a new Company Card about',
        createdFolder: 'created the Folder',
      },
    },
  },
  workspace: {
    admin: {
      title: 'Workspace Management',
      description: 'Manage all workspaces in the system',
    },
    create: {
      title: 'Create Workspace',
      description: 'Create a new workspace for your organization',
      button: 'Create Workspace',
      submit: 'Create Workspace',
      preview: 'Preview',
    },
    form: {
      name: {
        label: 'Workspace Name',
        placeholder: 'Enter workspace name...',
        help: 'This will be the display name for your workspace',
      },
      slug: {
        label: 'Workspace Slug',
        help: 'URL-friendly identifier (lowercase, no spaces)',
      },
      description: {
        label: 'Description',
        placeholder: 'Describe the purpose of this workspace...',
        help: 'Brief description to help users understand this workspace',
      },
    },
    detail: {
      title: 'Workspace Details',
      description: 'Workspace information and settings',
      basicInfo: 'Basic Information',
      members: 'Members',
      settings: 'Settings',
      settingsPlaceholder: 'Workspace settings will be implemented here',
    },
    search: {
      placeholder: 'Search workspaces...',
    },
    sort: {
      label: 'Sort by',
      created: 'Created Date',
      name: 'Name',
      members: 'Members',
      order: 'Order',
      ascending: 'Ascending',
      descending: 'Descending',
    },
    table: {
      description: 'Description',
    },
    empty: {
      noResults: 'No workspaces found',
      title: 'No workspaces yet',
      tryDifferentSearch: 'Try a different search term',
      description: 'Create your first workspace to get started',
    },
    justCreated: 'Just created',
    loading: 'Loading workspaces...',
    name: 'Workspace Name',
    slug: 'Workspace Slug',
    description: 'Description',
    created: 'Created',
    updated: 'Updated',
    members: 'Members',
    actions: 'Actions',
    current: 'Current',
    noDescription: 'No description',
    alreadyCurrent: 'Already in this workspace',
    pick: 'Switch to this workspace',
    view: 'View Details',
    delete: 'Delete Workspace',
    cannotDeleteDefault: 'Cannot delete the default workspace',
    clearSearch: 'Clear Search',
  },
  user: {
    create: {
      button: 'Add User',
    },
    loading: 'Loading users...',
    status: {
      active: 'Active',
      pending: 'Pending',
      disabled: 'Disabled',
    },
    actions: {
      resetPassword: 'Reset Password',
      disable: 'Disable User',
      enable: 'Enable User',
      delete: 'Remove User',
    },
    empty: {
      title: 'No users found',
      description: 'Create your first user to get started',
    },
    delete: {
      title: 'Remove User',
      description: 'Are you sure you want to remove this user from the workspace?',
      button: 'Remove User',
    },
  },
  csv: {
    upload: {
      button: 'CSV Upload',
      title: 'Import Companies from CSV',
      description: 'Upload a CSV file to import multiple companies at once',
      step1: 'Step 1: Choose File',
      step2: 'Step 2: Validate Data',
      step3: 'Step 3: Import',
      dragDrop: 'Drag and drop your CSV file here',
      or: 'or',
      chooseFile: 'Choose File',
      selectedFile: 'Selected file',
      remove: 'Remove',
      formatTitle: 'Expected CSV Format',
      format1: 'First row must contain column headers',
      format2: 'Required columns: company_name, website',
      format3: 'Optional columns: description, tags',
      format4: 'Example: "Acme Inc.,https://acme.com,A great company,tech;saas"',
      processing: 'Processing CSV file...',
      preview: 'Data Preview',
      companiesFound: 'companies found',
      companiesFoundCount: '{count} companies found in CSV',
      showingFirst: 'Showing first',
      rows: 'rows',
      moreRows: '{count} more rows not shown',
      validateData: 'Validate Data',
      validating: 'Validating companies...',
      validation: {
        title: 'Validation Results',
        valid: 'Valid companies',
        invalid: 'Invalid companies',
        validCompanies: '{count} valid companies',
        invalidCompanies: '{count} invalid companies',
        viewDetails: 'View Details',
        hideDetails: 'Hide Details',
        row: 'Row',
        company: 'Company',
        error: 'Error',
      },
      tokens: {
        title: 'Token Usage',
        required: 'tokens required',
        tokensRequired: '{count} tokens required',
        available: 'tokens available',
        tokensAvailable: 'You have {count} tokens available',
        insufficient: 'Insufficient tokens',
        insufficientMessage: 'You need {required} tokens but only have {available} available',
        moduleDisabled: 'CSV import requires the Stream module to be enabled',
      },
      actions: {
        cancel: 'Cancel',
        import: 'Import Companies',
        importing: 'Importing...',
        close: 'Close',
      },
      results: {
        title: 'Import Results',
        success: 'Successfully imported',
        failed: 'Failed to import',
        successCount: '{count} companies imported successfully',
        failedCount: '{count} companies failed to import',
        viewCompanies: 'View Companies',
      },
      errors: {
        invalidFile: 'Invalid file format. Please upload a CSV file.',
        emptyFile: 'The CSV file is empty',
        missingColumns: 'Missing required columns: {columns}',
        parseError: 'Error parsing CSV file',
        uploadError: 'Error uploading file',
        validationError: 'Error validating companies',
        importError: 'Error importing companies',
      },
    },
  },
}
