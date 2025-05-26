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
    comingSoon: 'Coming soon'
  },
  appbar: {
    search: 'Search...',
    theme: {
      pink: 'Pink theme',
      indigo: 'Indigo theme',
      emerald: 'Emerald theme',
      dark: 'Dark theme'
    }
  }
}
