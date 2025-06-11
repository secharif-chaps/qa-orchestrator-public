export default {
  welcome: 'Bienvenue',
  settings: {
    title: 'Paramètres',
    language: {
      title: 'Paramètres de langue',
      description: 'Choisissez votre langue préférée pour l\'application'
    },
    theme: {
      title: 'Paramètres du thème',
      description: 'Personnalisez l\'apparence de votre application'
    },
    notifications: {
      title: 'Paramètres de notification',
      description: 'Gérez vos préférences de notification'
    }
  },
  sidebar: {
    home: 'Accueil',
    search: 'Recherche',
    cards: 'Cartes',
    settings: 'Paramètres',
    help: 'Aide'
  },
  help: {
    title: 'Aide',
    description: 'Besoin d\'assistance ? Trouvez des réponses aux questions courantes et apprenez à utiliser l\'application.'
  },
  cards: {
    title: 'Cartes',
    noResults: 'Aucune entreprise trouvée',
    table: {
      name: 'Nom',
      creator: 'Créateur',
      lastModification: 'Dernière modification',
      actions: 'actions'
    },
    actions: {
      view: 'Voir',
      delete: 'Supprimer'
    }
  },
  search: {
    title: 'Nouvelle entreprise',
    companyIdentity: 'Identité de l\'entreprise',
    advancedSearch: 'Recherche avancée',
    fields: {
      companyName: {
        label: 'Nom de l\'entreprise',
        placeholder: 'Sephora',
        error: 'Le nom de l\'entreprise doit contenir au moins 2 caractères'
      },
      website: {
        label: 'Site web',
        placeholder: 'https://www.sephora.fr',
        error: 'Veuillez entrer une URL valide (ex: https://www.example.com)'
      }
    },
    mandatoryFields: 'champs obligatoires pour démarrer la recherche',
    actions: {
      deleteData: 'Supprimer les données',
      launchSearch: 'Lancer la recherche'
    }
  },
  login: {
    title: 'Connexion',
    email: {
      label: 'Adresse mail',
      placeholder: 'exemple@gmail.com'
    },
    password: {
      label: 'Mot de passe',
      placeholder: 'Entrer un mot de passe...',
      forgot: 'Mot de passe oublié ?'
    },
    submit: 'Connexion',
    errors: {
      invalidCredentials: 'Email ou mot de passe invalide',
      connectionError: 'Une erreur est survenue lors de la connexion'
    }
  },
  dashboard: {
    title: 'Tableau de bord',
    actions: {
      search: 'Rechercher',
      cards: 'Cartes'
    }
  },
  common: {
    comingSoon: 'Bientôt disponible',
    notFound: 'Non trouvé',
    loading: 'Chargement...',
    noData: 'Aucune donnée disponible'
  },
  appbar: {
    search: 'Rechercher...',
    theme: {
      pink: 'Thème rose',
      indigo: 'Thème indigo',
      emerald: 'Thème émeraude',
      dark: 'Thème sombre'
    }
  },
  components: {
    card: {
      defaultComingSoon: 'Bientôt disponible'
    }
  },
  timeline: {
    title: 'Chronologie & Jalons Clés',
    loading: {
      title: 'Chargement des données de chronologie...',
      description: 'Récupération des données d\'événements marquants depuis l\'agent IA...'
    },
    noData: {
      title: 'Aucune Donnée de Chronologie Disponible',
      description: 'Récupérez les événements marquants de cette entreprise pour voir son historique'
    },
    search: {
      placeholder: 'Rechercher...',
      noResults: 'Aucun événement trouvé correspondant à "{query}"'
    }
  },
  communications: {
    title: 'Communications d\'Entreprise',
    loading: {
      title: 'Non implémenté',
      description: 'Cette fonctionnalité n\'est pas encore implémentée.'
    },
    comingSoon: 'Bientôt disponible'
  },
  financials: {
    title: 'Finances',
    loading: {
      title: 'Non implémenté',
      description: 'Cette fonctionnalité n\'est pas encore implémentée.'
    },
    comingSoon: 'Bientôt disponible'
  },
  jobs: {
    title: 'Offres d\'Emploi',
    loading: {
      title: 'Chargement des offres d\'emploi...',
      description: 'Récupération des opportunités actuelles...'
    },
    noData: {
      title: 'Aucune Offre d\'Emploi Disponible',
      description: 'Récupérez les offres d\'emploi de cette entreprise pour voir les opportunités actuelles'
    },
    insights: {
      title: 'Aperçu des Recrutements',
      totalOpenings: 'Total des Postes',
      topDepartments: 'Départements Principaux',
      hiringFocus: 'Focus de Recrutement',
      growthIndicators: 'Indicateurs de Croissance'
    },
    listings: {
      title: 'Postes Actuels',
      search: {
        placeholder: 'Rechercher des emplois...'
      },
      noResults: 'Aucune offre d\'emploi trouvée correspondant à "{query}"'
    }
  },
  mentions: {
    title: 'Mentions',
    loading: {
      title: 'Non implémenté',
      description: 'Cette fonctionnalité n\'est pas encore implémentée.'
    },
    comingSoon: 'Bientôt disponible'
  },
  products: {
    title: 'Produits & Services',
    loading: {
      title: 'Chargement des produits...',
      description: 'Récupération des données des produits et services...'
    },
    noData: {
      title: 'Aucun Produit Disponible',
      description: 'Les informations sur les produits seront affichées ici une fois disponibles.'
    }
  },
  profile: {
    title: 'Profil de l\'Entreprise',
    loading: {
      title: 'Chargement du profil de l\'entreprise...',
      description: 'Récupération des informations complètes de l\'entreprise...'
    },
    sections: {
      products: {
        title: 'Produits et services',
        range: 'Gamme de produits',
        partnerBrands: 'Marques partenaires',
        privateLabels: 'Marques propres {company}'
      },
      target: {
        title: 'Public cible',
        customerBase: 'Base clientèle',
        positioning: 'Positionnement marketing'
      },
      csr: {
        title: 'Responsabilité Sociale d\'Entreprise',
        responsibility: 'Initiatives de responsabilité',
        charity: 'Actions caritatives'
      },
      digital: {
        title: 'Stratégie digitale',
        strategy: 'Stratégie digitale',
        loyaltyProgram: 'Programme de fidélité',
        onlineServices: 'Services en ligne'
      },
      news: 'Actualités récentes',
      metrics: {
        establishment: 'Année de création',
        employees: 'Nombre d\'employés',
        revenue: 'Revenus'
      }
    }
  },
  team: {
    title: 'Équipe & Management',
    loading: {
      title: 'Chargement des données d\'équipe...',
      description: 'Récupération de la hiérarchie et de la structure de management...'
    },
    noData: {
      title: 'Aucune Donnée d\'Équipe Disponible',
      description: 'Récupérez la hiérarchie de l\'équipe pour cette entreprise pour voir la structure de management'
    },
    hierarchy: {
      title: 'Hiérarchie de Management',
      viewLinkedIn: 'Voir le Profil LinkedIn'
    }
  },
  company: {
    dashboard: {
      title: 'Tableau de Bord de l\'Entreprise',
      description: 'Tableau de Bord des Informations de l\'Entreprise',
      generalInfo: {
        website: 'Site Web',
        headquarters: 'Siège Social',
        ceo: 'PDG',
        revenue: 'Revenus'
      },
      infoCards: {
        profile: {
          title: 'Profil de l\'Entreprise',
          description: 'Consultez les informations détaillées de l\'entreprise, les lignes de produits et les indicateurs clés.'
        },
        activities: {
          title: 'Activités & Événements',
          description: 'Explorez les événements de l\'entreprise, les salons et les activités clés.'
        },
        products: {
          title: 'Produits',
          description: 'Parcourez les produits, services et offres de l\'entreprise.'
        },
        team: {
          title: 'Équipe & Management',
          description: 'Équipe dirigeante, structure organisationnelle et personnel clé.'
        },
        jobs: {
          title: 'Offres d\'Emploi',
          description: 'Postes vacants actuels, opportunités de carrière et informations sur le recrutement.'
        },
        communications: {
          title: 'Communications d\'Entreprise',
          description: 'Communiqués de presse, déclarations publiques et communications officielles.'
        },
        financials: {
          title: 'Finances',
          description: 'Données financières, informations sur les revenus et performance du marché.'
        },
        mentions: {
          title: 'Mentions',
          description: 'Articles de presse, couverture médiatique et mentions tierces.'
        }
      }
    },
    list: {
      title: 'Entreprises',
      create: {
        title: 'Créer une Nouvelle Entreprise',
        name: {
          label: 'Nom de l\'Entreprise',
          placeholder: 'Entrez le nom de l\'entreprise'
        },
        website: {
          label: 'Site Web',
          placeholder: 'Entrez l\'URL du site web'
        },
        actions: {
          cancel: 'Annuler',
          create: 'Créer'
        }
      },
      delete: {
        title: 'Supprimer l\'Entreprise',
        confirm: 'Êtes-vous sûr de vouloir supprimer',
        warning: 'Cette action ne peut pas être annulée.',
        actions: {
          cancel: 'Annuler',
          delete: 'Supprimer'
        }
      },
      table: {
        website: 'Site Web',
        loading: 'Chargement...'
      }
    }
  }
} 