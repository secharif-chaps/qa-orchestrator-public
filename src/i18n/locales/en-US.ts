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
      'ai-preferences': 'AI Assistant',
      preferences: 'Preferences',
      security: 'Security',
      team: 'Team Management',
      credits: 'Credits',
    },
    credits: {
      cardDescription: 'View credit usage and statistics',
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
        currentColor: 'Current Accent Color',
        active: 'Active',
        personalizeTitle: 'Personalize Your Experience',
        personalizeDescription:
          'Your accent color affects buttons, links, highlights, and interactive elements throughout the application.',
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
        interfaceDescription:
          'Experience how your interface looks with the current theme settings.',
        card: {
          title: 'Sample Card Title',
          description: 'This card demonstrates the current theme styling',
        },
        input: {
          label: 'Sample Input Field',
          placeholder: 'Type something here...',
        },
        toggle: {
          label: 'Sample Toggle',
          description: 'This toggle demonstrates switch styling',
        },
        buttons: {
          primary: 'Primary Button',
          secondary: 'Secondary Button',
          danger: 'Danger Button',
        },
        status: {
          active: 'Active',
          pending: 'Pending',
          error: 'Error',
        },
        list: {
          title: 'Sample List Items',
        },
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
        error: 'Error loading sessions',
        errorDescription: 'Unable to load your active sessions. Please try again.',
        noOtherSessions: 'No other active sessions',
        webSession: 'Web Session',
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
        error: 'Error loading activity',
        errorDescription: 'Unable to load your activity log. Please try again.',
        noEvents: 'No activity events found',
        loadMore: 'Load more',
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
    organizations: 'organizations',
    admin: 'Admin',
    footer: {
      profile: 'Profile',
      settings: 'Settings',
      accessibility: 'Accessibility',
    },
    foldersSidebar: {
      title: 'Folders',
      viewAll: 'View all folders →',
      search: 'Search a folder...',
      noFolders: 'No folders',
      noFoldersFound: 'No folders found',
      favorites: 'Favorites',
      allFolders: 'Folders',
    },
    notifications: {
      title: 'Notifications',
      markAllRead: 'Mark all as read',
      noNotifications: 'No notifications',
      upToDate: 'You are up to date! All notifications will appear here.',
      // Temporarily disabled: Re-enable once /notifications page is implemented
      // viewAll: 'View all notifications',
      companyCreated: 'New company added',
      folderCreated: 'New folder created',
      activityMessage: 'created',
      categoryCompany: 'Company',
      categoryFolder: 'Folder',
      categories: {
        team: 'Team',
        share: 'Share',
        system: 'System',
        monitor: 'Monitoring',
        export: 'Export',
      },
    },
    tokens: {
      title: 'Credits',
      credits: '{count} credits',
      yesterday: 'Yesterday',
      viewHistory: 'View all history',
      needMore: 'Need more Credits?',
      advisor: 'Your ChapsVision advisor',
      contact: 'Contact',
      noHistory: 'No usage history',
      noHistoryDesc: 'Token usage will appear here when you create company cards.',
      createdBy: 'Card created by',
    },
    chapse: {
      title: 'Chaps-e',
      context: 'Context:',
      activeContext: 'Active context:',
      thinking: 'Thinking...',
      placeholder: 'Write a message...',
    },
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
  auth: {
    callback: {
      processing: 'Processing login...',
      loginFailed: 'Login failed',
      tryAgain: 'Try again',
    },
  },
  dashboard: {
    title: 'Dashboard',
    actions: {
      search: 'Search',
      cards: 'Cards',
    },
    stats: {
      totalCompanies: 'Total Companies',
      activeTasks: 'Active Tasks',
      recentUpdates: 'Recent Updates',
      last24Hours: 'Last 24 hours',
    },
    quickActions: {
      searchCompanies: {
        title: 'Search Companies',
        description: 'Find and explore companies',
      },
      allCompanies: {
        title: 'All Companies',
        description: 'View companies database',
      },
      settings: {
        title: 'Settings',
        description: 'Configure preferences',
      },
    },
  },
  common: {
    time: {
      day: 'day',
      days: 'days',
      hours: '{count}h',
      fewMinutes: 'a few minutes',
      justNow: 'just now',
      minutesAgo: '{count} min ago',
      hoursAgo: '{count}h ago',
      daysAgo: '{count}d ago',
    },
    comingSoon: 'Coming soon',
    soon: 'Soon',
    moduleUnavailable: 'Module unavailable',
    modules: {
      screen: 'Company Card',
      target: 'Watchfile',
      explore: 'Knowledge Graph',
      stream: 'Stream',
    },
    notFound: 'Not found',
    loading: 'Loading...',
    na: 'N/A',
    noData: 'No data available',
    save: 'Save',
    error: 'Error',
    cancel: 'Cancel',
    close: 'Close',
    back: 'Back',
    next: 'Next',
    done: 'Done',
    dismiss: 'Dismiss',
    breadcrumb: 'Breadcrumb',
    preview: {
      items: {
        newMessage: 'New Message Received',
        newMessageDesc: 'You have a new message from John Doe',
        systemUpdate: 'System Update',
        systemUpdateDesc: 'Application updated to version 2.1.0',
        profileComplete: 'Profile Completed',
        profileCompleteDesc: 'Your profile setup is now complete',
      },
    },
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
    sort: {
      oldestFirst: 'Oldest first',
      newestFirst: 'Newest first',
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
    noResults: 'No products found matching your search',
  },
  profile: {
    title: 'Company Profile',
    loading: {
      title: 'Loading company profile...',
      description: 'Fetching comprehensive company information...',
    },
    sections: {
      insights: {
        title: 'Insights',
      },
      products: {
        title: 'Products and services',
        insights: {
          title: 'Product Insights',
        },
        range: 'Product Range',
        partnerBrands: 'Partner Brands',
        privateLabels: '{company} Private Labels',
        noData: 'No product data available',
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
        noData: 'No CSR data available',
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
        noData: 'No press coverage data available',
      },
      jobs: {
        noData: 'No job offers data available',
      },
      team: {
        noData: 'No team data available',
      },
      timeline: {
        noData: 'No timeline data available',
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
    tabs: {
      users: 'Team Users',
      settings: 'Settings',
      apis: 'External APIs',
    },
    users: {
      loading: 'Loading users...',
      error: {
        title: 'Error loading users',
        description: 'Failed to load team members',
      },
      pagination: {
        itemName: 'users',
      },
    },
    settings: {
      title: 'Team Settings',
      comingSoon: 'Team settings will be available soon',
    },
    apis: {
      title: 'External APIs',
      description:
        'Connect external APIs to use as data sources in your workflows. Add your API credentials to integrate third-party services and expand your automation capabilities.',
      added: 'Added',
      active: 'Active',
      inactive: 'Inactive',
      requests: 'requests',
      add_new: 'Add External API',
      add_description: 'Connect a new data source',
      new_api: 'New External API',
      name: 'API Name',
      namePlaceholder: 'e.g., Weather API',
      url: 'API URL',
      urlLabel: 'URL:',
      urlPlaceholder: 'https://api.example.com/v1',
      api_key: 'API Key',
      apiKeyLabel: 'API Key:',
      apiKeyPlaceholder: 'Your API key',
      save: 'Save API',
      no_apis: 'No External APIs',
      no_apis_description: 'Add external APIs to connect new data sources',
      add_first: 'Add Your First API',
    },
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
    empty: {
      noUsers: {
        title: 'No users in organization',
        description: 'Add your first team member to get started with collaboration',
      },
      noResults: {
        title: 'No users found',
        description: 'Try a different search term or filter',
        descriptionNoFilter: 'No users match the current filters',
      },
      loading: {
        title: 'Loading users...',
        description: 'Please wait while we load your team members',
      },
      default: 'No users',
    },
    validation: {
      firstName: {
        required: 'First name is required',
      },
      lastName: {
        required: 'Last name is required',
      },
      username: {
        required: 'Username is required',
        minLength: 'Username must be at least 3 characters',
      },
      email: {
        required: 'Email is required',
        invalid: 'Please enter a valid email address',
      },
      password: {
        required: 'Password is required',
        minLength: 'Password must be at least 8 characters',
      },
    },
    permissionsList: {
      organizationRead: {
        name: 'Basic Access',
        description: 'View organization content and companies',
      },
      companyView: {
        name: 'View Companies',
        description: 'Access detailed company information',
      },
      companyCreate: {
        name: 'Create Companies',
        description: 'Add new companies to the organization',
      },
      companyDelete: {
        name: 'Delete Companies',
        description: 'Remove companies from the organization',
      },
      organizationWrite: {
        name: 'Team Management',
        description: 'Manage organization users and settings',
      },
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
        organizations: 'Total organizations',
        users: 'Active Users',
        companies: 'Companies',
        health: 'System Health',
      },
    },
    features: {
      organizations: {
        title: 'organization Management',
        description: 'Manage all organizations, users, and organization settings',
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
      status: {
        active: 'Active',
        partial: 'Partial',
        notConfigured: 'Not Configured',
      },
      error: {
        title: 'Failed to Load Workflows',
      },
    },
    organizations:{
      showing: 'Showing {from} to {to} of {total} organizations',
      members: 'Members'
    },
    import: {
      title: 'Import Users',
      description: 'Import users from CSV or Excel file',
      needHelpTitle: 'Need a template?',
      needHelpMessage: 'Download our sample CSV file to see the expected format with example data.',
      downloadSample: 'Download sample CSV',
      dropZoneTitle: 'Drag and drop your file here',
      dropZoneSubtitle: 'Supports CSV and Excel files (max 100 users)',
      browseFiles: 'Browse files',
      rows: 'rows',
      parseError: 'Error parsing file',
      csvColumn: 'CSV Column',
      mapsTo: 'Maps to',
      ignore: 'Ignore column',
      fields: {
        username: 'Username',
        email: 'Email',
        firstname: 'First Name',
        lastname: 'Last Name',
        password: 'Password',
      },
      ignoredColumnsWarning: 'Some columns will be ignored',
      ignoredColumnsMessage: 'The following columns are not mapped: {columns}',
      requiredFieldsWarning: 'Required fields not mapped',
      requiredFieldsMessage: 'Username and Email columns must be mapped to proceed.',
      // Password handling - no password column
      noPasswordColumnTitle: 'No password column detected',
      noPasswordColumnMessage:
        'Random passwords will be generated for all users. You will be able to download them after the import is complete.',
      // Password handling - with password column
      passwordHandling: 'Password handling',
      usePasswordsFromFile: 'Use passwords from file',
      usePasswordsFromFileHint:
        'Passwords from the CSV will be used. Random passwords will be generated for users without one.',
      generateAllPasswords: 'Generate all passwords',
      generateAllPasswordsHint:
        'Random passwords will be generated for all users, ignoring passwords in the file.',
      // Legacy keys (kept for compatibility)
      generatePasswords: 'Generate random passwords',
      generatePasswordsHint: 'Passwords will be generated for all users. They must change it on first login.',
      generatePasswordsHintWithColumn: 'Passwords will be generated only for users without a password in the file.',
      previewSummary: '{count} users ready to import',
      previewSkipped: '{count} users will be skipped due to errors',
      duplicatesFound: 'Duplicate emails found',
      duplicatesMessage: 'The following users have duplicate email addresses and will be skipped:',
      duplicate: 'Duplicate',
      validationErrors: 'Validation errors found',
      willGenerate: 'Will generate',
      status: 'Status',
      ready: 'Ready',
      showingPreview: 'Showing {shown} of {total} users',
      importButton: 'Import Users',
      successCount: '{count} users imported successfully',
      successMessage: 'All users were imported and can now log in with their temporary passwords.',
      errorCount: '{count} users failed to import',
      errorMessage: 'Some users could not be imported. See details below.',
      showDetails: 'Show error details',
      hideDetails: 'Hide details',
      passwordsGenerated: 'Temporary passwords generated',
      passwordsMessage: 'Download the password file to share with users. This is the only time you can download this file.',
      downloadPasswords: 'Download Passwords CSV',
      autoDownloaded: 'Password file automatically downloaded',
      cancelTitle: 'Cancel Import',
      cancelMessage: 'Are you sure you want to cancel? All progress will be lost.',
      confirmCancel: 'Yes, cancel',
      selectOrganization: 'Target Organization',
      selectOrganizationPlaceholder: 'Select an organization...',
      steps: {
        upload: 'Upload File',
        map: 'Map Columns',
        review: 'Review',
        results: 'Results',
      },
      errors: {
        usernameRequired: 'Username is required',
        emailRequired: 'Email is required',
        emailInvalid: 'Invalid email format',
      },
    },
  },
  tasks: {
    events: {
      ready: 'Your Screen about <span class="font-bold">{companyName}</span> is ready!',
      readyWithErrors:
        'Your Screen about <span class="font-bold">{companyName}</span> completed with some errors.',
      view: 'View',
      taskSucceeded: 'Task "{taskType}" completed successfully',
      taskFailed: 'Task "{taskType}" failed: {error}',
      taskStatus: 'Task "{taskType}" status: {status}',
    },
  },
  company: {
    loading: 'Loading companies...',
    name: 'Company',
    created: 'Created',
    createdAt: 'Created on',
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
      inFolder: 'Create a new company screen in folder',
      selectFolder: 'Select Folder',
      chooseFolderPlaceholder: 'Choose a folder...',
      noFolders: {
        title: 'No folders available',
        message: 'You need to create a folder before creating a company screen',
        action: 'Create Folder',
      },
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
      analyses: 'Analysis',
      notAvailable: 'Section not available',
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
    debug: {
      workflowTitle: 'Debug: Search Workflow',
    },
    validation: {
      loadingorganization: 'Loading organization...',
      loadingTokens: 'Loading tokens...',
      moduleDisabled: 'The Stream module is disabled',
      insufficientTokens: 'Insufficient tokens. You need at least 35 tokens to create a company.',
      invalidNameFormat: 'Company name must contain at least 2 alphabetic characters',
      invalidWebsiteFormat: 'Please enter a valid website URL',
      nameRequired: 'Company name is required',
      websiteRequired: 'Website URL is required',
      createError: 'An error occurred while creating the company',
      networkError: 'Network error - please try again',
      folderRequired: 'Please select a folder',
    },
    empty: {
      noResults: {
        title: 'No companies found',
        description: 'No companies match "{query}". Try adjusting your search terms.',
        descriptionNoQuery: 'Try adjusting your search terms',
      },
      noCompanies: {
        title: 'No companies yet',
        description:
          'Get started by creating your first company to track and manage your business relationships.',
      },
    },
    archive: {
      title: 'Archive Company',
      subtitle: 'This will move the company to the archive.',
      details: 'Company Details',
      warning: {
        message:
          'Archiving a company will hide it from the main list. You can restore it later from the archived view.',
      },
      confirm: {
        button: 'Archive Company',
      },
      success: 'Company "{name}" has been archived successfully',
      error: 'Failed to archive company "{name}". Please try again.',
    },
    delete: {
      title: 'Delete Company',
      button: 'Delete',
      success: 'Company "{name}" has been deleted successfully',
      error: 'Failed to delete company "{name}". Please try again.',
    },
    restore: {
      success: 'Company "{name}" has been restored successfully',
      error: 'Failed to restore company "{name}". Please try again.',
    },
    item: {
      tasks: {
        count: '{count} tasks',
        status: {
          new: 'New',
          processing: 'Processing',
          issues: 'Issues',
          complete: 'Complete',
          partial: 'Partial',
        },
      },
      time: {
        justNow: 'just now',
        minutesAgo: '{minutes}m ago',
        hoursAgo: '{hours}h ago',
        daysAgo: '{days}d ago',
      },
    },
    export: {
      button: 'Export',
    },
    onlinePresence: {
      title: 'Online Presence',
      website: 'Website',
      socialMedia: 'Social Media Presence',
    },
    tasks: {
      completed: '{count} completed ({percentage}%)',
      running: '{count} running ({percentage}%)',
      error: '{count} errors ({percentage}%)',
      blocked: '{count} blocked ({percentage}%)',
      pending: '{count} pending ({percentage}%)',
      analysisInProgress: 'Analysis in progress...',
      completedShort: '{count} completed',
      runningShort: '{count} running',
      errorShort: '{count} errors',
      blockedShort: '{count} blocked',
      pendingShort: '{count} pending',
      canBeRestarted: 'Tasks can be restarted or have not been started yet',
      startAll: 'Start all tasks',
      completedCount: '{completed}/{total} tasks completed',
      dataCollection: 'Data Collection',
      dataCollectionDescription: 'Structured data collection',
    },
    analysisCard: {
      viewMore: 'View more',
      loading: 'Analysis in progress...',
      noData: 'No data available for this section',
      comingSoon: 'Coming soon',
      error: {
        title: 'Error',
        message: 'An error occurred during analysis',
      },
      status: {
        succeeded: 'Completed',
        error: 'Error',
        running: 'In progress',
        pending: 'Pending',
        blocked: 'Pending (blocked)',
        notStarted: 'Not started',
      },
    },
    footer: {
      createdBy: 'Created by {username} on {date}',
    },
    translation: {
      button: 'Translate',
      original: 'Original',
      clickToTranslate: 'Click to translate',
      currentlyViewing: 'Currently viewing',
      clickToView: 'Click to view in this language',
      inProgress: 'Translation in progress...',
      progressDetail: '{translated}/{total} fields ({percent}%)',
      viewingDefault: 'Viewing in original language',
      viewingIn: 'Now viewing in {language}',
      alreadyInProgress: 'Translation already in progress',
      started: 'Translation started for {count} fields to {language}',
      failed: 'Translation request failed. Please try again.',
      languages: {
        fr: 'French',
        es: 'Spanish',
        de: 'German',
        pt: 'Portuguese',
      },
    }
  },
  tokens: {
    module: '{module} Module',
    token: 'token',
    tokens: 'tokens',
    credits: 'credits',
    companies: 'companies',
    loading: 'Loading token data...',
    refresh: 'Refresh token count',
    companyEquivalence: {
      none: 'Not enough for 1 company creation',
      singular: '≈ 1 company creation',
      plural: '≈ {count} company creations',
    },
    status: {
      disabled: 'Disabled',
      noTokens: 'No tokens',
      low: 'Low',
      active: 'Active',
    },
    modules: {
      screen: {
        name: 'Screen',
        description: 'Company search and screening',
      },
      target: {
        name: 'Target',
        description: 'Advanced targeting features',
      },
      explore: {
        name: 'Explore',
        description: 'Market exploration tools',
      },
      stream: {
        name: 'Stream',
        description: 'Data streaming capabilities',
      },
    },
  },
  folder: {
    title: 'Folders',
    description: 'Organize your companies into folders',
    search: {
      placeholder: 'Search items...',
    },
    view: {
      grid: 'Grid View',
      table: 'Table View',
    },
    actions: {
      delete: 'Delete',
      edit: 'Edit',
      favorite: 'Favorite',
      unfavorite: 'Unfavorite',
      view: 'View',
      share: 'Share',
      addToFavorites: 'Add to favorites',
      removeFromFavorites: 'Remove from favorites',
    },
    itemCount: '{count} item | {count} items',
    itemsChip: '{count} item | {count} items',
    items: {
      add: 'Add Items',
      empty: 'No items in this folder',
    },
    addItems: {
      company: 'Add Company',
      companyScreen: 'Company Screen',
      companyDescription: 'Create a company card to monitor company information',
      watchfile: 'Watchfile',
      watchfileDescription: 'Set up monitoring for specific topics',
      graphrag: 'Knowledge Graph',
      graphragDescription: 'Explore connections and relationships',
    },
    header: {
      itemsCount: '{count} item | {count} items',
      createdOn: 'created on {date}',
      by: 'by',
    },
    grid: {
      created: 'Created',
      by: 'by',
      owner: 'Owner:',
    },
    shared: {
      badge: 'Shared',
    },
    share: {
      title: 'Share Folder',
      description: 'Share this folder with other users in your organization',
      searchLabel: 'Add people',
      searchPlaceholder: 'Search by username or email...',
      searching: 'Searching...',
      readOnly: 'Read only',
      alreadyShared: 'Already shared',
      noResults: 'No users found',
      searchError: 'Failed to search users. You may not have permission to share folders.',
      add: 'Add',
      writerDisabledNote: 'Writer role is disabled because this user only has read permissions in the organization.',
      currentShares: 'People with access',
      loadingShares: 'Loading...',
      addedOn: 'Added',
      remove: 'Remove access',
      noShares: 'This folder is not shared with anyone yet',
      reader: 'Reader',
      writer: 'Writer',
    },
    moveCompany: {
      button: 'Move to Folder',
      title: 'Move Company to Folder',
      selectFolder: 'Select a destination folder',
      searchPlaceholder: 'Search folders...',
      noFolders: 'No writable folders available',
      currentFolder: 'Current folder (cannot select)',
      loadError: 'Failed to load folders',
      success: 'Company moved to {folderName}',
      error: 'Failed to move company',
      move: 'Move',
    },
    permissions: {
      owner: 'Owner',
      writer: 'Writer',
      reader: 'Reader',
    },
    itemTypes: {
      company: 'Company Card',
    },
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
      all: 'All folders',
      allLabel: 'All',
      favorites: 'Favorite folders',
      favoritesLabel: 'Favorites',
      archived: 'Archived folders',
      archivedLabel: 'Archived',
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
      owner: 'Owner',
      created: 'Created',
      actions: 'Actions',
    },
    empty: {
      noResults: 'No results found',
      title: 'This folder is empty',
      tryDifferentSearch: 'Try a different search term',
      description: 'Start by creating your first company in this folder',
      readOnly: 'No items in this folder',
    },
    emptyList: {
      title: 'No folders yet',
      description: 'Create your first folder to organize your companies',
      descriptionReadOnly: 'No folders have been shared with you yet',
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
      by: 'by {username}',
      actions: {
        createdCompany: 'created a new Company Card about',
        createdFolder: 'created the Folder',
      },
    },
    modules: {
      status: {
        active: 'Active',
        proFeature: 'Pro Feature',
        comingSoon: 'Coming Soon',
      },
      actions: {
        contactSales: 'Contact Sales',
        open: 'Open',
        companyScreen: 'Create a Screen',
      },
      screen: {
        name: 'Screen',
        description:
          'Deep company intelligence and comprehensive business screening with advanced analytics',
        category: 'Business Intelligence',
      },
      target: {
        name: 'Target',
        description: 'AI-powered market watch with smart alerts and comprehensive monitoring tools',
        category: 'Market Analysis',
      },
      explore: {
        name: 'Explore',
        description: 'Interactive knowledge graph for advanced data visualization and discovery',
        category: 'Cartography',
      },
      discover: {
        name: 'Discover',
        description: 'Share strategic insights',
        category: 'Search Data',
      },
    },
  },
  organization: {
    admin: {
      title: 'organization Management',
      description: 'Manage all organizations in the system',
    },
    create: {
      title: 'Create organization',
      description: 'Create a new organization for your organization',
      button: 'Create organization',
      submit: 'Create organization',
      preview: 'Preview',
    },
    form: {
      name: {
        label: 'organization Name',
        placeholder: 'Enter organization name...',
        help: 'This will be the display name for your organization',
      },
      slug: {
        label: 'organization Slug',
        help: 'URL-friendly identifier (lowercase, no spaces)',
      },
      description: {
        label: 'Description',
        placeholder: 'Describe the purpose of this organization...',
        help: 'Brief description to help users understand this organization',
      },
    },
    detail: {
      title: 'organization Details',
      description: 'organization information and settings',
      basicInfo: 'Basic Information',
      members: 'Members',
      settings: 'Settings',
      settingsPlaceholder: 'organization settings will be implemented here',
    },
    search: {
      placeholder: 'Search organizations...',
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
      noResults: 'No organizations found',
      title: 'No organizations yet',
      tryDifferentSearch: 'Try a different search term',
      description: 'Create your first organization to get started',
    },
    justCreated: 'Just created',
    loading: 'Loading organizations...',
    name: 'organization Name',
    slug: 'organization Slug',
    description: 'Description',
    created: 'Created',
    updated: 'Updated',
    members: 'Members',
    actions: 'Actions',
    current: 'Current',
    noDescription: 'No description',
    alreadyCurrent: 'Already in this organization',
    pick: 'Switch to this organization',
    view: 'View Details',
    delete: 'Delete organization',
    cannotDeleteDefault: 'Cannot delete the default organization',
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
      description: 'Are you sure you want to remove this user from the organization?',
      button: 'Remove User',
    },
    validation: {
      username: {
        required: 'Username is required',
        minLength: 'Username must be at least 3 characters',
      },
      email: {
        required: 'Email is required',
        invalid: 'Please enter a valid email address',
      },
      temporaryPassword: {
        required: 'Temporary password is required',
        minLength: 'Password must be at least 8 characters',
      },
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
        errorsFound: 'Validation Errors Found',
        errorsFoundMessage:
          'You can either fix the errors in your CSV file and re-upload, or proceed with import which will skip invalid rows.',
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
        importCompanies: 'Import {count} Companies',
        fixAndReupload: 'Fix CSV and Re-upload',
        goToFolder: 'Go to Folder',
        uploadAnother: 'Upload Another CSV',
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
  help: {
    noContent: {
      title: 'No Help Content Available',
      message: "You don't have access to any help sections based on your current permissions.",
    },
    selectTopic: {
      placeholder: 'Select a help topic',
      title: 'Select a Help Topic',
      message: 'Choose a topic from the sidebar to view detailed documentation.',
    },
    loading: {
      content: 'Loading help content...',
    },
    categories: {
      admin: 'Administration',
      company: 'Company Screening',
      organization: 'organization Management',
    },
  },
  login: {
    heading: 'Sign in to your account',
    signingIn: 'Signing in...',
    signInButton: 'Sign in with Keycloak',
    errors: {
      genericError: 'An error occurred during login',
    },
  },
  errors: {
    notFound: {
      title: 'Page Not Found',
      message: "The page you're looking for doesn't exist or has been moved.",
      goHome: 'Go to Home',
      goBack: 'Go Back',
      help: 'Need Help?',
    },
    forbidden: {
      title: 'Access Denied',
      message: "You don't have permission to access this resource.",
      goHome: 'Go to Home',
      goBack: 'Go Back',
      contactAdmin: 'Contact Administrator',
      token: {
        moduleDisabled: '{module} Disabled',
        insufficientTokens: 'Insufficient Tokens',
        accessRestricted: 'Access Restricted',
        moduleDisabledMessage:
          'The {module} module has been disabled for your organization. Contact your administrator to enable this feature.',
        insufficientTokensMessage:
          "You don't have enough tokens to access the {module} module. Contact your administrator to add more tokens.",
        unavailable: 'This feature is currently unavailable.',
        status: {
          disabled: 'Disabled',
          noTokens: 'No Tokens',
        },
      },
    },
  },
  chapseAssist: {
    alert: {
      title: 'Get Personalized AI Recommendations',
      actionLabel: 'Set Up Now',
      dismissLabel: 'Maybe Later',
    },
    quickActions: {
      title: 'Chaps-e Smart Assist',
      refresh: 'Refresh',
      checkingPreferences: 'Checking AI preferences...',
      loading: 'Generating personalized actions...',
      tryAgain: 'Try Again',
      configure: 'Configure AI Preferences',
      error: {
        title: 'Failed to Load Quick Actions',
        message: 'An error occurred while generating actions. Please try again.',
        preferencesCheck: 'Failed to Check Preferences',
      },
      empty: {
        title: 'No Quick Actions Available',
        message: 'Configure your AI preferences to see personalized recommendations.',
        loadedMessage: 'Unable to generate quick actions for this company. Try refreshing or check back later.',
      },
    },
  },
  aiPreferences: {
    setup: {
      title: 'Set Up Your AI Assistant',
      description:
        'Tell us about your role and goals so we can provide personalized quick actions and recommendations tailored to your needs.',
      optional: 'Optional',
      fields: {
        role: {
          label: 'Your Role',
          placeholder: 'e.g., Sales Representative, Marketing Manager, CEO',
          helper: 'What is your professional role?',
          required: "Role is required",
          tooLong: "Role must be less than 255 characters",
        },
        goals: {
          label: 'Your Goals',
          placeholder:
            'e.g., I want to identify which companies would benefit from our product and understand their pain points',
          helper: 'What are you trying to achieve when researching companies?',
          required: "Goals are required",
          tooLong: "Goals must be less than 2000 characters",
        },
        desiredOutput: {
          label: 'Desired Output Format',
          placeholder:
            'e.g., Generate personalized outreach emails highlighting pain points with specific company references',
          helper: 'How would you like the AI to format its recommendations?',
          required: "Desired output is required",
          tooLong: "Desired output must be less than 2000 characters",
        },
        documentation: {
          label: 'Product/Service Documentation',
          placeholder:
            'e.g., Our product is a B2B SaaS platform that helps companies streamline workflow automation',
          helper: 'Describe your product or service to help personalize recommendations (optional)',
        },
      },
      actions: {
        save: 'Save & Continue',
        cancel: 'Cancel',
      },
      success: {
        title: 'Success!',
        message:
          'Your AI preferences have been saved. Quick actions will now be personalized based on your profile.',
      },
      error: {
        title: 'Error',
        message: 'Failed to save your preferences. Please try again.',
      },
      help: {
        title: 'Tips for Better Results',
        tip1: 'Be specific about your role and goals for more relevant recommendations',
        tip2: 'Describe your desired output format clearly to get better-formatted results',
        tip3: 'Include product details to receive more personalized and contextual suggestions',
      },
      validation: {
        formInvalid: "Please fix the errors in the form",
      },
    },
    settings: {
      title: 'AI Assistant Preferences',
      description: 'Update your AI preferences to refine personalized recommendations',
      lastUpdated: 'Last updated: {date}',
      notConfigured: 'Not configured',
      setUpDescription: 'Set up your AI preferences to enable personalized quick actions and recommendations.',
      setUpButton: 'Set Up AI Preferences',
      actions: {
        edit: 'Edit Preferences',
        save: 'Save Changes',
        cancel: 'Cancel',
      },
      success: {
        title: 'Updated Successfully',
        message: 'Your AI preferences have been updated successfully.',
      },
      error: {
        title: 'Update Failed',
        message: 'Failed to update your preferences. Please try again.',
      },
      messages: {
        loadError: "Failed to load your preferences. Please try again.",
        authError: "You must be logged in to update AI preferences",
      },
    },
  },
  credits: {
    unit: 'credits',
    usedCredits: 'credits used',
    balance: {
      label: 'Available balance',
      current: 'Current balance',
    },
    usage: {
      title: 'Credit distribution',
      total: 'Total consumed',
      noData: 'No consumption for this period',
    },
    forecast: {
      title: 'Remaining capacity',
    },
    module: {
      disabled: 'Module disabled',
      costPerItem: '1 {item} = {cost} credits',
      remaining: '{item} remaining',
      canCreate: 'You can still create',
      getQuote: 'Get a quote',
      screen: {
        label: 'Company card',
        item: 'company card',
        itemPlural: 'Company cards',
      },
      target: {
        label: 'Watchfile',
        item: 'watchfile',
        itemPlural: 'Watchfiles',
      },
      explore: {
        label: 'Graph',
        item: 'graph',
        itemPlural: 'Graphs',
      },
    },
    modules: {
      all: 'All',
      screen: 'Screen',
      target: 'Target',
      explore: 'Explore',
    },
    period: {
      select: 'Period',
      '7d': 'Last 7 days',
      '30d': 'Last 30 days',
      '90d': 'Last 90 days',
      custom: 'Custom',
      startDate: 'Start',
      endDate: 'End',
    },
    topUsers: {
      title: 'User ranking',
      rank: 'Rank',
      user: 'User',
      credits: 'Credits',
      search: 'Search...',
      noData: 'No users found',
      itemName: 'users',
    },
    dailyUsage: {
      title: 'Daily consumption',
      noData: 'No consumption for this period',
    },
  },
  breadcrumb: {
    companies: 'Companies',
    folders: 'Folders',
    team: 'Team',
    admin: 'Admin',
    settings: 'Settings',
    search: 'Search',
    profile: 'Profile',
    jobs: 'Jobs',
    timeline: 'Timeline',
    products: 'Products',
    press: 'Press',
    edit: 'Edit',
    create: 'Create',
    company: 'Company',
    organizations: 'Organizations',
    costs: 'Costs',
    appearance: 'Appearance',
    security: 'Security',
    credits: 'Credits',
  },
  logout: {
    title: 'Confirm Logout',
    subtitle: 'This action will end your session',
    message: 'Are you sure you want to log out of your account?',
    cancel: 'Cancel',
    confirm: 'Logout',
    tooltip: 'Logout',
  },
}
