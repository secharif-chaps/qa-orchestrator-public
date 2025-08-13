export default {
  welcome: 'Bienvenue',
  settings: {
    language: {
      title: 'Paramètres de langue',
      description: "Choisissez votre langue préférée pour l'application",
    },
    theme: {
      title: 'Paramètres du thème',
      description: "Personnalisez l'apparence de votre application",
    },
    notifications: {
      title: 'Paramètres de notification',
      description: 'Gérez vos préférences de notification',
    },
    title: 'Paramètres du compte',
    description: 'Gérez les informations de votre compte et vos préférences',
    tabs: {
      profile: 'Profil',
      appearance: 'Apparence',
      preferences: 'Préférences',
      security: 'Sécurité',
    },
    profile: {
      error: {
        title: 'Erreur lors du chargement du profil',
      },
      basic: {
        title: 'Informations de base',
        description: 'Détails personnels de votre compte',
      },
      auth: {
        title: "Détails d'authentification",
        description: "Informations de session et d'authentification",
      },
      roles: {
        title: 'Rôles et permissions',
        description: "Vos rôles assignés et niveaux d'accès",
        none: 'Aucun rôle assigné',
      },
      debug: {
        title: 'Informations de débogage',
        description: 'Détails techniques pour le débogage',
      },
      fields: {
        username: "Nom d'utilisateur",
        email: 'E-mail',
        firstName: 'Prénom',
        lastName: 'Nom de famille',
        userId: 'ID utilisateur',
        expiresAt: 'Le token expire le',
        issuedAt: 'Émis le',
        sessionState: 'État de la session',
      },
      status: {
        active: 'Actif',
        expired: 'Expiré',
      },
      actions: {
        refresh: 'Actualiser le profil',
        signOut: 'Se déconnecter',
      },
    },
    appearance: {
      theme: {
        title: 'Thème',
        description: 'Choisissez votre thème préféré',
        options: {
          light: {
            title: 'Clair',
            description: 'Interface propre et lumineuse',
          },
          dark: {
            title: 'Sombre',
            description: 'Repose les yeux en faible luminosité',
          },
          system: {
            title: 'Système',
            description: 'Correspond à la préférence de votre appareil',
          },
        },
      },
      accent: {
        title: "Couleur d'accent",
        description: "Choisissez votre couleur d'accent préférée pour l'interface",
      },
      language: {
        title: 'Langue',
        description: 'Sélectionnez votre langue préférée',
      },
      layout: {
        title: 'Préférences de mise en page',
        description: "Personnalisez la mise en page de l'interface",
        compact: {
          title: 'Mode compact',
          description: "Réduire l'espacement pour plus de contenu",
        },
        reducedMotion: {
          title: 'Mouvements réduits',
          description: 'Minimiser les animations et transitions',
        },
      },
      preview: {
        title: 'Aperçu du thème',
        description: 'Voyez à quoi ressemble votre thème',
        sample: 'Ceci est un échantillon de votre thème actuel',
        tag1: 'Exemple',
        tag2: 'Aperçu',
      },
    },
    preferences: {
      notifications: {
        title: 'Notifications',
        description: 'Contrôlez quand et comment vous recevez les notifications',
        email: {
          title: 'Notifications par e-mail',
          options: {
            welcome: {
              title: 'E-mails de bienvenue',
              description: 'Recevoir des messages de bienvenue et guides de démarrage',
            },
            updates: {
              title: 'Mises à jour produit',
              description: 'Actualités sur les nouvelles fonctionnalités et améliorations',
            },
            security: {
              title: 'Alertes de sécurité',
              description: 'Notifications importantes de sécurité et de compte',
            },
            marketing: {
              title: 'E-mails marketing',
              description: 'Contenu promotionnel et offres spéciales',
            },
          },
        },
        push: {
          title: 'Notifications push',
          options: {
            mentions: {
              title: 'Mentions',
              description: "Quand quelqu'un vous mentionne",
            },
            messages: {
              title: 'Messages directs',
              description: 'Nouveaux messages directs et réponses',
            },
            updates: {
              title: 'Mises à jour système',
              description: 'Notifications système importantes',
            },
          },
        },
      },
      privacy: {
        title: 'Données et confidentialité',
        description: 'Gérez vos paramètres de données et de confidentialité',
        analytics: {
          title: 'Analytiques',
          description: "Aidez à améliorer le service en partageant les données d'utilisation",
        },
        export: {
          title: 'Exporter les données',
          description: 'Télécharger une copie de vos données',
        },
        delete: {
          title: 'Supprimer le compte',
          description: 'Supprimer définitivement votre compte et toutes les données',
          dialog: {
            title: 'Supprimer le compte',
            description:
              'Cette action ne peut pas être annulée. Toutes vos données seront définitivement supprimées.',
          },
        },
      },
      actions: {
        cancel: 'Annuler',
        save: 'Enregistrer les modifications',
        export: 'Exporter les données',
        deleteAccount: 'Supprimer le compte',
      },
      behavior: {
        title: 'Comportement',
        description: "Personnalisez le comportement de l'application",
        autoSave: {
          title: 'Sauvegarde automatique',
          description: 'Sauvegarder automatiquement les modifications pendant le travail',
        },
        confirmations: {
          title: 'Afficher les confirmations',
          description: 'Demander confirmation avant les actions importantes',
        },
      },
    },
    security: {
      sessions: {
        title: 'Gestion des sessions',
        description: 'Gérez vos sessions actives et appareils',
        current: {
          title: 'Session actuelle',
          badge: 'Actuelle',
          lastActive: 'Dernière activité',
        },
        lastActive: 'Dernière activité',
        signOutAll: {
          title: 'Déconnecter tous les appareils',
          description: 'Se déconnecter de toutes les autres sessions et appareils',
        },
      },
      twoFactor: {
        title: 'Authentification à deux facteurs',
        description: 'Ajoutez une couche de sécurité supplémentaire à votre compte',
        authenticator: {
          title: "Application d'authentification",
          description: "Utilisez une application d'authentification pour une connexion sécurisée",
        },
        securityKeys: {
          title: 'Clés de sécurité',
          description: 'Utilisez des clés de sécurité matérielles pour la connexion',
          count: 'clés',
        },
      },
      activity: {
        title: "Journal d'activité",
        description: 'Activité récente de sécurité et de compte',
      },
      recovery: {
        title: 'Récupération de compte',
        description: 'Configurez les options de récupération pour votre compte',
        backupCodes: {
          title: 'Codes de sauvegarde',
          description: 'Générer des codes de sauvegarde pour la récupération de compte',
        },
        email: {
          title: 'E-mail de récupération',
          notSet: 'Aucun e-mail de récupération défini',
        },
      },
      status: {
        enabled: 'Activé',
        disabled: 'Désactivé',
        generated: 'Généré',
        notGenerated: 'Non généré',
      },
      actions: {
        setup: 'Configurer',
        disable: 'Désactiver',
        manage: 'Gérer',
        generate: 'Générer',
        regenerate: 'Régénérer',
        add: 'Ajouter',
        update: 'Mettre à jour',
        revoke: 'Révoquer',
      },
    },
  },
  sidebar: {
    home: 'Accueil',
    search: 'Recherche',
    cards: 'Cartes',
    workspaces: 'Espaces de travail',
    settings: 'Paramètres',
    help: 'Aide',
    workspace: 'Équipe',
    team: 'Équipe',
    admin: 'Admin',
  },
  workspaces: {
    title: 'Gestion des espaces de travail',
    description: 'Gérez tous les espaces de travail de votre organisation',
    fields: {
      name: 'Nom',
      slug: 'Identifiant',
      description: 'Description',
      created: 'Créé le',
      members: 'membres',
    },
    placeholders: {
      name: "Nom de l'espace de travail",
      slug: 'identifiant-unique',
      description: "Description de l'espace de travail",
    },
    actions: {
      create: 'Nouvel espace',
    },
    modal: {
      create: 'Créer un espace de travail',
      edit: "Modifier l'espace de travail",
    },
    error: {
      loading: 'Erreur lors du chargement des espaces de travail',
      save: 'Erreur lors de la sauvegarde',
      delete: 'Erreur lors de la suppression',
    },
    confirm: {
      delete: 'Êtes-vous sûr de vouloir supprimer l\'espace "{name}" ?',
    },
  },
  help: {
    title: 'Aide',
    description:
      "Besoin d'assistance ? Trouvez des réponses aux questions courantes et apprenez à utiliser l'application.",
  },
  cards: {
    title: 'Cartes',
    noResults: 'Aucune entreprise trouvée',
    table: {
      name: 'Nom',
      creator: 'Créateur',
      lastModification: 'Dernière modification',
      actions: 'actions',
      created: 'Créé le',
      owner: 'Créé par',
    },
    actions: {
      view: 'Voir',
      delete: 'Supprimer',
    },
  },
  search: {
    title: 'Nouvelle entreprise',
    companyIdentity: "Identité de l'entreprise",
    advancedSearch: 'Recherche avancée',
    fields: {
      companyName: {
        label: "Nom de l'entreprise",
        placeholder: 'Sephora',
        error: "Le nom de l'entreprise doit contenir au moins 2 caractères",
      },
      website: {
        label: 'Site web',
        placeholder: 'https://www.sephora.fr',
        error: 'Veuillez entrer une URL valide (ex: https://www.example.com)',
      },
    },
    mandatoryFields: 'champs obligatoires pour démarrer la recherche',
    actions: {
      deleteData: 'Supprimer les données',
      launchSearch: 'Lancer la recherche',
    },
  },
  login: {
    title: 'Connexion',
    email: {
      label: 'Adresse mail',
      placeholder: 'exemple@gmail.com',
    },
    password: {
      label: 'Mot de passe',
      placeholder: 'Entrer un mot de passe...',
      forgot: 'Mot de passe oublié ?',
    },
    submit: 'Connexion',
    errors: {
      invalidCredentials: 'Email ou mot de passe invalide',
      connectionError: 'Une erreur est survenue lors de la connexion',
    },
  },
  dashboard: {
    title: 'Tableau de bord',
    actions: {
      search: 'Rechercher',
      cards: 'Cartes',
    },
  },
  common: {
    comingSoon: 'Bientôt disponible',
    notFound: 'Non trouvé',
    loading: 'Chargement...',
    noData: 'Aucune donnée disponible',
    cancel: 'Annuler',
    save: 'Enregistrer',
  },
  appbar: {
    search: 'Rechercher...',
    theme: {
      pink: 'Thème rose',
      indigo: 'Thème indigo',
      emerald: 'Thème émeraude',
      dark: 'Thème sombre',
    },
  },
  components: {
    card: {
      defaultComingSoon: 'Bientôt disponible',
    },
  },
  timeline: {
    title: 'Chronologie & Jalons Clés',
    loading: {
      title: 'Chargement des données de chronologie...',
      description: "Récupération des données d'événements marquants depuis l'agent IA...",
    },
    noData: {
      title: 'Aucune Donnée de Chronologie Disponible',
      description:
        'Récupérez les événements marquants de cette entreprise pour voir son historique',
    },
    search: {
      placeholder: 'Rechercher...',
      noResults: 'Aucun événement trouvé correspondant à "{query}"',
    },
  },
  communications: {
    title: "Communications d'Entreprise",
    loading: {
      title: 'Non implémenté',
      description: "Cette fonctionnalité n'est pas encore implémentée.",
    },
    comingSoon: 'Bientôt disponible',
  },
  financials: {
    title: 'Finances',
    loading: {
      title: 'Non implémenté',
      description: "Cette fonctionnalité n'est pas encore implémentée.",
    },
    comingSoon: 'Bientôt disponible',
  },
  jobs: {
    title: "Offres d'Emploi",
    loading: {
      title: "Chargement des offres d'emploi...",
      description: 'Récupération des opportunités actuelles...',
    },
    noData: {
      title: "Aucune Offre d'Emploi Disponible",
      description:
        "Récupérez les offres d'emploi de cette entreprise pour voir les opportunités actuelles",
    },
    insights: {
      title: 'Aperçu des Recrutements',
      totalOpenings: 'Total des Postes',
      topDepartments: 'Départements Principaux',
      hiringFocus: 'Focus de Recrutement',
      growthIndicators: 'Indicateurs de Croissance',
    },
    listings: {
      title: 'Postes Actuels',
      search: {
        placeholder: 'Rechercher des emplois...',
      },
      noResults: 'Aucune offre d\'emploi trouvée correspondant à "{query}"',
    },
  },
  mentions: {
    title: 'Mentions',
    loading: {
      title: 'Non implémenté',
      description: "Cette fonctionnalité n'est pas encore implémentée.",
    },
    comingSoon: 'Bientôt disponible',
  },
  products: {
    title: 'Produits & Services',
    loading: {
      title: 'Chargement des produits...',
      description: 'Récupération des données des produits et services...',
    },
    noData: {
      title: 'Aucun Produit Disponible',
      description: 'Les informations sur les produits seront affichées ici une fois disponibles.',
    },
  },
  profile: {
    title: "Profil de l'Entreprise",
    loading: {
      title: "Chargement du profil de l'entreprise...",
      description: "Récupération des informations complètes de l'entreprise...",
    },
    sections: {
      products: {
        title: 'Produits et services',
        range: 'Gamme de produits',
        partnerBrands: 'Marques partenaires',
        privateLabels: 'Marques propres {company}',
      },
      target: {
        title: 'Public cible',
        customerBase: 'Base clientèle',
        positioning: 'Positionnement marketing',
      },
      csr: {
        title: "Responsabilité Sociale d'Entreprise",
        responsibility: 'Initiatives de responsabilité',
        charity: 'Actions caritatives',
      },
      digital: {
        title: 'Stratégie digitale',
        strategy: 'Stratégie digitale',
        loyaltyProgram: 'Programme de fidélité',
        onlineServices: 'Services en ligne',
      },
      news: 'Actualités récentes',
      metrics: {
        establishment: 'Année de création',
        employees: "Nombre d'employés",
        revenue: 'Revenus',
      },
    },
  },
  team: {
    title: 'Équipe & Management',
    loading: {
      title: "Chargement des données d'équipe...",
      description: 'Récupération de la hiérarchie et de la structure de management...',
    },
    noData: {
      title: "Aucune Donnée d'Équipe Disponible",
      description:
        "Récupérez la hiérarchie de l'équipe pour cette entreprise pour voir la structure de management",
    },
    hierarchy: {
      title: 'Hiérarchie de Management',
      viewLinkedIn: 'Voir le Profil LinkedIn',
    },
    email: 'E-mail',
    emailPlaceholder: "Saisir l'adresse e-mail",
    emailCannotChange: "L'e-mail ne peut pas être modifié après la création",
    usernamePlaceholder: "Saisir le nom d'utilisateur (optionnel)",
    usernameCannotChange: "Le nom d'utilisateur ne peut pas être modifié après la création",
    permissions: 'Permissions',
    'permissions.description': "Sélectionnez les permissions pour ce membre d'équipe",
    edit: 'Modifier',
    disable: 'Désactiver',
    loading: "Chargement des membres de l'équipe...",
    description: "Gérez les membres de l'équipe et leurs permissions",
    table: {
      user: 'Utilisateur',
      permissions: 'Permissions',
      created: 'Créé',
      status: 'Statut',
      actions: 'Actions',
    },
    create: {
      button: 'Ajouter un membre',
    },
    edit: {
      title: "Modifier le membre de l'équipe",
      description: 'Mettre à jour les informations et permissions du membre',
    },
    firstName: 'Prénom',
    firstNamePlaceholder: 'Saisir le prénom',
    lastName: 'Nom',
    lastNamePlaceholder: 'Saisir le nom',
    username: "Nom d'utilisateur",
    search: {
      placeholder: 'Rechercher des membres...',
    },
    pageSize: {
      label: 'Éléments par page',
    },
    sort: {
      label: 'Trier par',
      created: 'Date de création',
      name: 'Nom',
      email: 'E-mail',
      username: "Nom d'utilisateur",
      asc: 'Croissant',
      desc: 'Décroissant',
    },
    status: {
      active: 'Actif',
      label: 'Statut',
      all: 'Tous',
      disabled: 'Désactivé',
    },
  },
  admin: {
    dashboard: {
      title: 'Tableau de Bord Admin',
      description: 'Gérez les fonctionnalités système et les paramètres',
      back: 'Retour à Admin',
      quickStats: {
        title: 'Aperçu Système',
      },
      stats: {
        workspaces: 'Total Espaces de travail',
        users: 'Utilisateurs Actifs',
        companies: 'Entreprises',
        health: 'Santé Système',
      },
    },
    features: {
      workspaces: {
        title: 'Gestion des Espaces de travail',
        description: 'Gérez tous les espaces de travail, utilisateurs et paramètres',
      },
      uiDemo: {
        title: 'Démo Composants UI',
        description: 'Prévisualisez et testez tous les composants UI et le système de design',
      },
      workflows: {
        title: 'Gestion des Workflows',
        description: 'Configurez et gérez les workflows automatisés et processus',
      },
      manage: 'Gérer',
      explore: 'Explorer',
      configure: 'Configurer',
    },
    workflows: {
      title: 'Gestion des Workflows',
      description: 'Configurez et gérez les workflows automatisés et processus',
      backToDashboard: 'Retour au Tableau de Bord',
      requestFeature: 'Demander une Fonctionnalité',
      comingSoon: {
        title: 'Fonctionnalités Workflow Bientôt Disponibles',
        description: 'Nous travaillons sur des fonctionnalités puissantes de gestion des workflows qui vous permettront d\'automatiser les processus et rationaliser les opérations.',
        features: {
          title: 'Fonctionnalités Prévues',
          automation: {
            title: 'Automatisation des Processus',
            description: 'Créez des workflows automatisés pour les tâches routinières',
          },
          triggers: {
            title: 'Déclencheurs Personnalisés',
            description: 'Configurez des déclencheurs basés sur des événements et conditions',
          },
          monitoring: {
            title: 'Surveillance des Workflows',
            description: 'Surveillez l\'exécution et les performances des workflows',
          },
          templates: {
            title: 'Modèles de Workflow',
            description: 'Modèles pré-construits pour les patterns de workflow courants',
          },
        },
      },
    },
  },
  company: {
    dashboard: {
      title: "Tableau de Bord de l'Entreprise",
      description: "Tableau de Bord des Informations de l'Entreprise",
      generalInfo: {
        website: 'Site Web',
        headquarters: 'Siège Social',
        ceo: 'PDG',
        revenue: 'Revenus',
      },
      infoCards: {
        profile: {
          title: "Profil de l'Entreprise",
          description:
            "Consultez les informations détaillées de l'entreprise, les lignes de produits et les indicateurs clés.",
        },
        activities: {
          title: 'Activités & Événements',
          description: "Explorez les événements de l'entreprise, les salons et les activités clés.",
        },
        products: {
          title: 'Produits',
          description: "Parcourez les produits, services et offres de l'entreprise.",
        },
        team: {
          title: 'Équipe & Management',
          description: 'Équipe dirigeante, structure organisationnelle et personnel clé.',
        },
        jobs: {
          title: "Offres d'Emploi",
          description:
            'Postes vacants actuels, opportunités de carrière et informations sur le recrutement.',
        },
        communications: {
          title: "Communications d'Entreprise",
          description:
            'Communiqués de presse, déclarations publiques et communications officielles.',
        },
        financials: {
          title: 'Finances',
          description:
            'Données financières, informations sur les revenus et performance du marché.',
        },
        mentions: {
          title: 'Mentions',
          description: 'Articles de presse, couverture médiatique et mentions tierces.',
        },
      },
    },
    list: {
      title: 'Entreprises',
      description: "Gérez votre base de données d'entreprises",
      create: {
        title: 'Nouveau Screen',
        name: {
          label: "Nom de l'Entreprise",
          placeholder: "Entrez le nom de l'entreprise",
        },
        website: {
          label: 'Site Web',
          placeholder: "Entrez l'URL du site web",
        },
        actions: {
          cancel: 'Annuler',
          create: 'Créer',
        },
      },
      delete: {
        title: "Supprimer l'Entreprise",
        confirm: 'Êtes-vous sûr de vouloir supprimer',
        warning: 'Cette action ne peut pas être annulée.',
        actions: {
          cancel: 'Annuler',
          delete: 'Supprimer',
        },
      },
      table: {
        website: 'Site Web',
        loading: 'Chargement...',
      },
    },
  },
  workspace: {
    title: "Gestion de l'équipe",
    description: 'Gérez les membres et les paramètres de votre équipe',
    error: {
      loading: "Erreur lors du chargement des données de l'équipe",
    },
    info: {
      title: "Informations de l'équipe",
      name: 'Nom',
      description: 'Description',
      slug: 'Identifiant',
    },
    members: {
      title: "Membres de l'équipe",
      add: 'Ajouter un membre',
      empty: 'Aucun membre dans cette équipe',
      revoke: 'Révoquer',
      activate: 'Activer',
      status: {
        active: 'Actif',
        revoked: 'Révoqué',
      },
      addDialog: {
        title: 'Ajouter un nouveau membre',
        email: 'Adresse e-mail',
        emailPlaceholder: 'email@exemple.com',
        username: "Nom d'utilisateur",
        usernamePlaceholder: 'Optionnel - sera généré automatiquement',
        submit: 'Ajouter le membre',
      },
    },
  },
}
