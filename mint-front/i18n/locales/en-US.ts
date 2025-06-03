export default {
  welcome: 'Welcome',
  settings: {
    title: 'Settings',
    language: {
      title: 'Language Settings',
      description: 'Choose your preferred language for the application'
    },
    theme: {
      title: 'Theme Settings',
      description: 'Customize the appearance of your application'
    },
    notifications: {
      title: 'Notification Settings',
      description: 'Manage your notification preferences'
    }
  },
  sidebar: {
    search: 'Search',
    cards: 'Cards',
    settings: 'Settings',
    help: 'Help'
  },
  help: {
    title: 'Help',
    description: 'Need assistance? Find answers to common questions and learn how to use the application.'
  },
  cards: {
    title: 'Cards',
    noResults: 'No company found',
    table: {
      name: 'Name',
      creator: 'Creator',
      lastModification: 'Last modification',
      actions: 'actions'
    },
    actions: {
      view: 'View',
      delete: 'Delete'
    }
  },
  search: {
    title: 'New company',
    companyIdentity: 'Company identity',
    advancedSearch: 'Advanced search',
    fields: {
      companyName: {
        label: 'Company name',
        placeholder: 'Sephora',
        error: 'Company name must be at least 2 characters long'
      },
      website: {
        label: 'Website',
        placeholder: 'https://www.sephora.fr',
        error: 'Please enter a valid URL (e.g., https://www.example.com)'
      }
    },
    mandatoryFields: 'mandatory fields to start search',
    actions: {
      deleteData: 'Delete Data',
      launchSearch: 'Launch Search'
    }
  },
  login: {
    title: 'Login',
    email: {
      label: 'Email address',
      placeholder: 'example@gmail.com'
    },
    password: {
      label: 'Password',
      placeholder: 'Enter a password...',
      forgot: 'Forgot password?'
    },
    submit: 'Login',
    errors: {
      invalidCredentials: 'Invalid email or password',
      connectionError: 'An error occurred during login'
    }
  },
  dashboard: {
    title: 'Dashboard',
    actions: {
      search: 'Search',
      cards: 'Cards'
    }
  },
  common: {
    comingSoon: 'Coming soon',
    notFound: 'Not found',
    loading: 'Loading...',
    noData: 'No data available'
  },
  appbar: {
    search: 'Search...',
    theme: {
      pink: 'Pink theme',
      indigo: 'Indigo theme',
      emerald: 'Emerald theme',
      dark: 'Dark theme'
    }
  },
  timeline: {
    title: 'Timeline & Key Milestones',
    loading: {
      title: 'Loading company timeline data...',
      description: 'Fetching milestone events data from AI agent...'
    },
    noData: {
      title: 'No Timeline Data Available',
      description: 'Fetch milestone events for this company to see its history'
    },
    search: {
      placeholder: 'Search...',
      noResults: 'No events found matching "{query}"'
    }
  },
  communications: {
    title: 'Corporate Communications',
    loading: {
      title: 'Not implemented yet',
      description: 'This feature is not implemented yet.'
    },
    comingSoon: 'Coming soon'
  },
  financials: {
    title: 'Financials',
    loading: {
      title: 'Not implemented yet',
      description: 'This feature is not implemented yet.'
    },
    comingSoon: 'Coming soon'
  },
  jobs: {
    title: 'Job Offers',
    loading: {
      title: 'Loading job offers...',
      description: 'Fetching current job opportunities...'
    },
    noData: {
      title: 'No Job Offers Available',
      description: 'Fetch job offers for this company to see current opportunities'
    },
    insights: {
      title: 'Hiring Insights',
      totalOpenings: 'Total Openings',
      topDepartments: 'Top Departments',
      hiringFocus: 'Hiring Focus',
      growthIndicators: 'Growth Indicators'
    },
    listings: {
      title: 'Current Openings',
      search: {
        placeholder: 'Search jobs...'
      },
      noResults: 'No job offers found matching "{query}"'
    }
  },
  mentions: {
    title: 'Mentions',
    loading: {
      title: 'Not implemented yet',
      description: 'This feature is not implemented yet.'
    },
    comingSoon: 'Coming soon'
  },
  products: {
    title: 'Products & Services',
    loading: {
      title: 'Loading products...',
      description: 'Fetching products and services data...'
    },
    noData: {
      title: 'No Products Available',
      description: 'Products information will be displayed here once available.'
    }
  },
  profile: {
    title: 'Company Profile',
    loading: {
      title: 'Loading company profile...',
      description: 'Fetching comprehensive company information...'
    },
    sections: {
      products: {
        title: 'Products and services',
        range: 'Product Range',
        partnerBrands: 'Partner Brands',
        privateLabels: '{company} Private Labels'
      },
      target: {
        title: 'Target audience',
        customerBase: 'Customer Base',
        positioning: 'Marketing Positioning'
      },
      csr: {
        title: 'Corporate Social Responsibility',
        responsibility: 'Responsibility Initiatives',
        charity: 'Charity Actions'
      },
      digital: {
        title: 'Digital strategy',
        strategy: 'Digital Strategy',
        loyaltyProgram: 'Loyalty Program',
        onlineServices: 'Online Services'
      },
      news: 'Recent news',
      metrics: {
        establishment: 'Year of establishment',
        employees: 'Number of employees',
        revenue: 'Revenue'
      }
    }
  },
  team: {
    title: 'Team & Management',
    loading: {
      title: 'Loading team data...',
      description: 'Fetching team hierarchy and management structure...'
    },
    noData: {
      title: 'No Team Data Available',
      description: 'Fetch team hierarchy for this company to see management structure'
    },
    hierarchy: {
      title: 'Management Hierarchy',
      viewLinkedIn: 'View LinkedIn Profile'
    }
  },
  company: {
    dashboard: {
      title: 'Company Dashboard',
      description: 'Company Information Dashboard',
      generalInfo: {
        website: 'Website',
        headquarters: 'Headquarters',
        ceo: 'CEO',
        revenue: 'Revenue'
      },
      infoCards: {
        profile: {
          title: 'Company Profile',
          description: 'View detailed company information, business lines, and key metrics.'
        },
        activities: {
          title: 'Activities & Events',
          description: 'Explore company events, trade shows, and key activities.'
        },
        products: {
          title: 'Products',
          description: 'Browse the company\'s products, services, and offerings.'
        },
        team: {
          title: 'Team & Management',
          description: 'Leadership team, organizational structure, and key personnel.'
        },
        jobs: {
          title: 'Job Offers',
          description: 'Current job openings, career opportunities, and hiring information.'
        },
        communications: {
          title: 'Corporate Communications',
          description: 'Press releases, public statements, and official communications.'
        },
        financials: {
          title: 'Financials',
          description: 'Financial data, revenue information, and market performance.'
        },
        mentions: {
          title: 'Mentions',
          description: 'News articles, media coverage, and third-party mentions.'
        }
      }
    },
    list: {
      title: 'Companies',
      create: {
        title: 'Create New Company',
        name: {
          label: 'Company Name',
          placeholder: 'Enter company name'
        },
        website: {
          label: 'Website',
          placeholder: 'Enter website URL'
        },
        actions: {
          cancel: 'Cancel',
          create: 'Create'
        }
      },
      delete: {
        title: 'Delete Company',
        confirm: 'Are you sure you want to delete',
        warning: 'This action cannot be undone.',
        actions: {
          cancel: 'Cancel',
          delete: 'Delete'
        }
      },
      table: {
        website: 'Website',
        loading: 'Loading...'
      }
    }
  }
}
