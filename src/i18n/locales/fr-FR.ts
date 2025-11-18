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
        currentColor: "Couleur d'Accent Actuelle",
        active: 'Actif',
        personalizeTitle: 'Personnalisez Votre Expérience',
        personalizeDescription:
          "Votre couleur d'accent affecte les boutons, liens, surlignages et éléments interactifs dans toute l'application.",
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
        interfaceDescription:
          "Expérimentez l'apparence de votre interface avec les paramètres de thème actuels.",
        card: {
          title: 'Titre de la Carte Exemple',
          description: 'Cette carte démontre le style du thème actuel',
        },
        input: {
          label: 'Champ de Saisie Exemple',
          placeholder: 'Tapez quelque chose ici...',
        },
        toggle: {
          label: 'Commutateur Exemple',
          description: 'Ce commutateur démontre le style de basculement',
        },
        buttons: {
          primary: 'Bouton Principal',
          secondary: 'Bouton Secondaire',
          danger: 'Bouton Danger',
        },
        status: {
          active: 'Actif',
          pending: 'En Attente',
          error: 'Erreur',
        },
        list: {
          title: 'Éléments de Liste Exemple',
        },
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
    folders: 'Dossiers',
    home: 'Accueil',
    search: 'Recherche',
    cards: 'Cartes',
    organizations: 'Espaces de travail',
    settings: 'Paramètres',
    help: 'Aide',
    organization: 'Équipe',
    team: 'Équipe',
    admin: 'Admin',
    footer: {
      profile: 'Profil',
      settings: 'Paramètres',
      accessibility: 'Accessibilité',
    },
    foldersSidebar: {
      title: 'Dossiers',
      viewAll: 'Voir tous les dossiers →',
      search: 'Rechercher un dossier...',
      noFolders: 'Aucun dossier',
      noFoldersFound: 'Aucun dossier trouvé',
      favorites: 'Favoris',
      allFolders: 'Dossiers',
    },
    notifications: {
      title: 'Notifications',
      markAllRead: 'Tout marquer comme lu',
      noNotifications: 'Aucune notification',
      upToDate: 'Vous êtes à jour ! Toutes vos notifications apparaîtront ici.',
      viewAll: 'Voir toutes les notifications',
      categories: {
        team: 'Équipe',
        share: 'Partage',
        system: 'Système',
        monitor: 'Surveillance',
        export: 'Export',
      },
    },
    tokens: {
      title: 'Crédits',
      credits: '{count} crédits',
      yesterday: 'Hier',
      viewHistory: "Voir tout l'historique",
      needMore: 'Besoin de plus de Crédits ?',
      advisor: 'Votre conseiller ChapsVision',
      contact: 'Contacter',
    },
    chapse: {
      title: 'Chaps-e',
      context: 'Contexte :',
      activeContext: 'Contexte actif :',
      thinking: 'Réflexion en cours...',
      placeholder: 'Écrivez un message...',
    },
  },
  organizations: {
    title: 'Gestion des espaces de travail',
    description: 'Gérez tous les espaces de travail de votre organization',
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
  auth: {
    callback: {
      processing: 'Traitement de la connexion...',
      loginFailed: 'Échec de la connexion',
      tryAgain: 'Réessayer',
    },
  },
  dashboard: {
    title: 'Tableau de bord',
    actions: {
      search: 'Rechercher',
      cards: 'Cartes',
    },
    stats: {
      totalCompanies: 'Total des Entreprises',
      activeTasks: 'Tâches Actives',
      recentUpdates: 'Mises à jour Récentes',
      last24Hours: 'Dernières 24 heures',
    },
    quickActions: {
      searchCompanies: {
        title: 'Rechercher des Entreprises',
        description: 'Trouver et explorer des entreprises',
      },
      allCompanies: {
        title: 'Toutes les Entreprises',
        description: 'Voir la base de données des entreprises',
      },
      settings: {
        title: 'Paramètres',
        description: 'Configurer les préférences',
      },
    },
  },
  common: {
    time: {
      day: 'jour',
      days: 'jours',
      hours: '{count}h',
      fewMinutes: 'quelques minutes',
      minutesAgo: 'il y a {count} min',
      hoursAgo: 'il y a {count} heures',
    },
    comingSoon: 'Bientôt disponible',
    moduleUnavailable: 'Module non disponible',
    notFound: 'Non trouvé',
    loading: 'Chargement...',
    na: 'N/D',
    noData: 'Aucune donnée disponible',
    cancel: 'Annuler',
    save: 'Enregistrer',
    error: 'Erreur',
    close: 'Fermer',
    dismiss: 'Ignorer',
    breadcrumb: "Fil d'Ariane",
    preview: {
      items: {
        newMessage: 'Nouveau Message Reçu',
        newMessageDesc: 'Vous avez un nouveau message de John Doe',
        systemUpdate: 'Mise à Jour Système',
        systemUpdateDesc: 'Application mise à jour vers la version 2.1.0',
        profileComplete: 'Profil Complété',
        profileCompleteDesc: 'La configuration de votre profil est terminée',
      },
    },
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
    noResults: 'Aucun produit trouvé correspondant à votre recherche',
  },
  profile: {
    title: "Profil de l'Entreprise",
    loading: {
      title: "Chargement du profil de l'entreprise...",
      description: "Récupération des informations complètes de l'entreprise...",
    },
    sections: {
      insights: {
        title: 'Aperçu',
      },
      products: {
        title: 'Produits et services',
        insights: {
          title: 'Aperçu des Produits',
        },
        range: 'Gamme de produits',
        partnerBrands: 'Marques partenaires',
        privateLabels: 'Marques propres {company}',
        noData: 'Aucune donnée produit disponible',
      },
      target: {
        title: 'Public cible',
        customerBase: 'Base clientèle',
        positioning: 'Positionnement marketing',
      },
      csr: {
        title: "Responsabilité Sociale d'Entreprise",
        insights: {
          title: 'Aperçu RSE',
        },
        responsibility: 'Déclaration de responsabilité',
        responsibility_initiatives: 'Initiatives de responsabilité',
        charity: 'Actions caritatives',
        sustainability: 'Programmes de développement durable',
        community: 'Implication communautaire',
        diversity: 'Diversité et inclusion',
        ethics: 'Pratiques éthiques',
        awards: 'Prix et certifications',
        noData: 'Aucune donnée RSE disponible',
      },
      digital: {
        title: 'Stratégie digitale',
        insights: {
          title: 'Aperçu de la Stratégie Digitale',
        },
        strategy: 'Stratégie digitale',
        loyaltyProgram: 'Programme de fidélité',
        onlineServices: 'Services en ligne',
      },
      news: {
        title: 'Actualités récentes',
      },
      press: {
        insights: {
          title: 'Aperçu de la Couverture Presse',
        },
        noData: 'Aucune donnée de couverture presse disponible',
      },
      jobs: {
        noData: "Aucune donnée d'offres d'emploi disponible",
      },
      team: {
        noData: "Aucune donnée d'équipe disponible",
      },
      timeline: {
        noData: 'Aucune donnée de chronologie disponible',
      },
      metrics: {
        establishment: 'Année de création',
        employees: "Nombre d'employés",
        revenue: 'Revenus',
      },
    },
  },
  team: {
    title: 'Équipe & Management',
    tabs: {
      users: "Membres de l'Équipe",
      settings: 'Paramètres',
      apis: 'APIs Externes',
    },
    users: {
      loading: 'Chargement des utilisateurs...',
      error: {
        title: 'Erreur lors du chargement des utilisateurs',
        description: "Échec du chargement des membres de l'équipe",
      },
      pagination: {
        itemName: 'utilisateurs',
      },
    },
    settings: {
      title: "Paramètres de l'Équipe",
      comingSoon: "Les paramètres d'équipe seront bientôt disponibles",
    },
    apis: {
      title: 'APIs Externes',
      description:
        "Connectez des APIs externes pour les utiliser comme sources de données dans vos workflows. Ajoutez vos identifiants API pour intégrer des services tiers et étendre vos capacités d'automatisation.",
      added: 'Ajouté',
      active: 'Actif',
      inactive: 'Inactif',
      requests: 'requêtes',
      add_new: 'Ajouter une API Externe',
      add_description: 'Connecter une nouvelle source de données',
      new_api: 'Nouvelle API Externe',
      name: "Nom de l'API",
      namePlaceholder: 'ex., API Météo',
      url: "URL de l'API",
      urlLabel: 'URL :',
      urlPlaceholder: 'https://api.exemple.com/v1',
      api_key: 'Clé API',
      apiKeyLabel: 'Clé API :',
      apiKeyPlaceholder: 'Votre clé API',
      save: "Enregistrer l'API",
      no_apis: 'Aucune API Externe',
      no_apis_description:
        'Ajoutez des APIs externes pour connecter de nouvelles sources de données',
      add_first: 'Ajouter Votre Première API',
    },
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
    disable: 'Désactiver',
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
    empty: {
      noUsers: {
        title: "Aucun utilisateur dans l'espace de travail",
        description: "Ajoutez votre premier membre d'équipe pour commencer la collaboration",
      },
      noResults: {
        title: 'Aucun utilisateur trouvé',
        description: 'Essayez un autre terme de recherche ou filtre',
        descriptionNoFilter: 'Aucun utilisateur ne correspond aux filtres actuels',
      },
      loading: {
        title: 'Chargement des utilisateurs...',
        description: "Veuillez patienter pendant que nous chargeons vos membres d'équipe",
      },
      default: 'Aucun utilisateur',
    },
    validation: {
      firstName: {
        required: 'Le prénom est requis',
      },
      lastName: {
        required: 'Le nom est requis',
      },
      username: {
        required: "Le nom d'utilisateur est requis",
        minLength: "Le nom d'utilisateur doit contenir au moins 3 caractères",
      },
      email: {
        required: "L'email est requis",
        invalid: 'Veuillez entrer une adresse email valide',
      },
      password: {
        required: 'Le mot de passe est requis',
        minLength: 'Le mot de passe doit contenir au moins 8 caractères',
      },
    },
    permissionsList: {
      organizationRead: {
        name: 'Accès de Base',
        description: "Voir le contenu de l'espace de travail et les entreprises",
      },
      companyView: {
        name: 'Voir les Entreprises',
        description: 'Accéder aux informations détaillées des entreprises',
      },
      companyCreate: {
        name: 'Créer des Entreprises',
        description: "Ajouter de nouvelles entreprises à l'espace de travail",
      },
      companyDelete: {
        name: 'Supprimer des Entreprises',
        description: "Retirer des entreprises de l'espace de travail",
      },
      organizationWrite: {
        name: "Gestion d'Équipe",
        description: "Gérer les utilisateurs et paramètres de l'espace de travail",
      },
    },
  },
  admin: {
    dashboard: {
      title: 'Tableau de Bord Admin',
      description: 'Gérez les fonctionnalités système et les paramètres',
      back: 'Retour à Admin',
      limitedAccess: {
        title: 'Accès Limité',
        message:
          'Vous avez accès aux fonctionnalités admin de base. Contactez votre administrateur pour des permissions supplémentaires.',
      },
      quickStats: {
        title: 'Aperçu Système',
      },
      stats: {
        organizations: 'Total Espaces de travail',
        users: 'Utilisateurs Actifs',
        companies: 'Entreprises',
        health: 'Santé Système',
      },
    },
    features: {
      organizations: {
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
      costs: {
        title: 'Analyse des Coûts',
        description: "Surveillez l'utilisation des tokens et les coûts des workflows MINT",
      },
      manage: 'Gérer',
      explore: 'Explorer',
      configure: 'Configurer',
      analyze: 'Analyser',
    },
    workflows: {
      title: 'Gestion des Workflows',
      description: "Configurez les intégrations Dify pour les tâches d'analyse automatisées",
      loading: 'Chargement des workflows...',
      updateSuccess: 'Workflow mis à jour avec succès !',
      workflowId: 'ID du Workflow',
      workflowIdPlaceholder: "Saisissez l'ID du workflow Dify",
      apiKey: 'Clé API',
      apiKeyPlaceholder: 'Saisissez la clé API Dify',
      notConfigured: 'Non configuré',
      status: {
        active: 'Actif',
        partial: 'Partiel',
        notConfigured: 'Non Configuré',
      },
      error: {
        title: 'Échec du chargement des workflows',
      },
    },
  },
  company: {
    loading: 'Chargement des entreprises...',
    name: 'Entreprise',
    created: 'Créé',
    owner: 'Propriétaire',
    status: 'Statut',
    actions: 'Actions',
    clearSearch: 'Effacer la recherche',
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
          description: 'Équipe dirigeante, structure organizationnelle et personnel clé.',
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
        press: {
          title: 'Presse et Couverture Médiatique',
          description:
            'Communiqués de presse, articles de presse, interviews et mentions médiatiques.',
        },
      },
    },
    list: {
      title: 'Entreprises',
      description: "Gérez votre base de données d'entreprises",
      error: {
        description: 'Échec du chargement des entreprises',
      },
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
    create: {
      button: 'Faire une nouvelle recherche',
    },
    fields: {
      employeeCount: "Nombre d'employés",
      headquarters: 'Siège Social',
      ceo: 'PDG',
      revenue: 'Revenus',
      website: 'Site Web',
      notSpecified: 'Non spécifié',
    },
    sections: {
      onlinePresence: 'Présence en Ligne',
      socialMedia: 'Réseaux Sociaux',
      analysis: 'Analyse',
    },
    analysisCards: {
      profile: {
        title: "Profil de l'Entreprise",
        description: 'Consultez les informations détaillées, les activités et les indicateurs clés',
        insights: 'Informations du Profil',
      },
      timeline: {
        title: 'Chronologie & Historique',
        description: "Historique de l'entreprise, jalons et événements clés",
        insights: 'Informations Historiques',
      },
      products: {
        title: 'Produits & Services',
        description: 'Parcourez les produits, services et offres',
        insights: 'Informations Produits',
      },
      team: {
        title: 'Équipe & Management',
        description: 'Équipe dirigeante, structure organizationnelle et personnel clé',
        insights: 'Informations Équipe',
      },
      jobs: {
        title: "Offres d'Emploi",
        description: 'Postes vacants actuels et opportunités de carrière',
        insights: 'Informations Recrutement',
      },
      press: {
        title: 'Presse & Médias',
        description: 'Communiqués de presse, articles et couverture médiatique',
        insights: 'Informations Médias',
      },
      csr: {
        title: "Responsabilité Sociale d'Entreprise",
        description: 'Initiatives RSE, programmes de durabilité et impact social',
        insights: 'Informations RSE',
      },
      communications: {
        title: "Communications d'Entreprise",
        description: 'Communiqués de presse, déclarations publiques et communications officielles',
        insights: 'Informations Communication',
      },
    },
    debug: {
      workflowTitle: 'Debug : Workflow de recherche',
    },
    validation: {
      loadingorganization: "Chargement de l'espace de travail...",
      loadingTokens: 'Chargement des jetons...',
      moduleDisabled: 'Le module Stream est désactivé',
      insufficientTokens:
        "Jetons insuffisants. Vous avez besoin d'au moins 1 jeton pour créer une entreprise.",
      invalidNameFormat: "Le nom de l'entreprise doit contenir au moins 2 caractères alphabétiques",
      invalidWebsiteFormat: 'Veuillez entrer une URL de site web valide',
      nameRequired: "Le nom de l'entreprise est requis",
      websiteRequired: "L'URL du site web est requise",
      createError: "Une erreur s'est produite lors de la création de l'entreprise",
      networkError: 'Erreur réseau - veuillez réessayer',
    },
    empty: {
      noResults: {
        title: 'Aucune entreprise trouvée',
        description:
          'Aucune entreprise ne correspond à "{query}". Essayez d\'ajuster vos termes de recherche.',
        descriptionNoQuery: "Essayez d'ajuster vos termes de recherche",
      },
      noCompanies: {
        title: 'Aucune entreprise pour le moment',
        description:
          "Commencez par créer votre première entreprise pour suivre et gérer vos relations d'affaires.",
      },
    },
    archive: {
      success: 'L\'entreprise "{name}" a été archivée avec succès',
      error: 'Échec de l\'archivage de l\'entreprise "{name}". Veuillez réessayer.',
    },
    delete: {
      success: 'L\'entreprise "{name}" a été supprimée avec succès',
      error: 'Échec de la suppression de l\'entreprise "{name}". Veuillez réessayer.',
    },
    restore: {
      success: 'L\'entreprise "{name}" a été restaurée avec succès',
      error: 'Échec de la restauration de l\'entreprise "{name}". Veuillez réessayer.',
    },
    item: {
      tasks: {
        count: '{count} tâches',
        status: {
          new: 'Nouveau',
          processing: 'En cours',
          issues: 'Problèmes',
          complete: 'Terminé',
          partial: 'Partiel',
        },
      },
      time: {
        justNow: "à l'instant",
        minutesAgo: 'il y a {minutes}m',
        hoursAgo: 'il y a {hours}h',
        daysAgo: 'il y a {days}j',
      },
    },
  },
  tokens: {
    module: 'Module {module}',
    token: 'crédit',
    tokens: 'crédits',
    credits: 'crédits',
    companies: 'entreprises',
    loading: 'Chargement des données de crédits...',
    companyEquivalence: {
      none: 'Pas assez pour 1 création d\'entreprise',
      singular: '≈ 1 création d\'entreprise',
      plural: '≈ {count} créations d\'entreprise',
    },
    status: {
      disabled: 'Désactivé',
      noTokens: 'Aucun crédit',
      low: 'Faible',
      active: 'Actif',
    },
    modules: {
      screen: {
        name: 'Screen',
        description: "Recherche et screening d'entreprises",
      },
      target: {
        name: 'Target',
        description: 'Fonctionnalités de ciblage avancées',
      },
      explore: {
        name: 'Explore',
        description: "Outils d'exploration de marché",
      },
      stream: {
        name: 'Stream',
        description: 'Capacités de streaming de données',
      },
    },
  },
  organization: {
    admin: {
      title: 'Gestion des Espaces de Travail',
      description: 'Gérez tous les espaces de travail du système',
    },
    create: {
      title: 'Créer un Espace de Travail',
      description: 'Créez un nouvel espace de travail pour votre organization',
      button: 'Créer un Espace de Travail',
      submit: 'Créer un Espace de Travail',
      preview: 'Aperçu',
    },
    form: {
      name: {
        label: "Nom de l'Espace de Travail",
        placeholder: "Saisir le nom de l'espace de travail...",
        help: "Ce sera le nom d'affichage pour votre espace de travail",
      },
      slug: {
        label: "Identifiant de l'Espace de Travail",
        help: 'Identifiant compatible URL (minuscules, sans espaces)',
      },
      description: {
        label: 'Description',
        placeholder: 'Décrivez le but de cet espace de travail...',
        help: 'Brève description pour aider les utilisateurs à comprendre cet espace de travail',
      },
    },
    detail: {
      title: "Détails de l'Espace de Travail",
      description: "Informations et paramètres de l'espace de travail",
      basicInfo: 'Informations de Base',
      members: 'Membres',
      settings: 'Paramètres',
      settingsPlaceholder: "Les paramètres de l'espace de travail seront implémentés ici",
    },
    search: {
      placeholder: 'Rechercher des espaces de travail...',
    },
    sort: {
      label: 'Trier par',
      created: 'Date de Création',
      name: 'Nom',
      members: 'Membres',
      order: 'Ordre',
      ascending: 'Croissant',
      descending: 'Décroissant',
    },
    table: {
      description: 'Description',
    },
    empty: {
      noResults: 'Aucun espace de travail trouvé',
      title: 'Aucun espace de travail pour le moment',
      tryDifferentSearch: 'Essayez un autre terme de recherche',
      description: 'Créez votre premier espace de travail pour commencer',
    },
    justCreated: "Vient d'être créé",
    loading: 'Chargement des espaces de travail...',
    name: 'Nom',
    slug: 'Identifiant',
    description: 'Description',
    created: 'Créé',
    updated: 'Mis à jour',
    members: 'Membres',
    actions: 'Actions',
    current: 'Actuel',
    noDescription: 'Aucune description',
    alreadyCurrent: 'Déjà dans cet espace de travail',
    pick: 'Basculer vers cet espace de travail',
    view: 'Voir les Détails',
    delete: "Supprimer l'Espace de Travail",
    cannotDeleteDefault: "Impossible de supprimer l'espace de travail par défaut",
    clearSearch: 'Effacer la Recherche',
  },
  user: {
    create: {
      button: 'Ajouter un Utilisateur',
    },
    loading: 'Chargement des utilisateurs...',
    status: {
      active: 'Actif',
      pending: 'En attente',
      disabled: 'Désactivé',
    },
    actions: {
      resetPassword: 'Réinitialiser le Mot de Passe',
      disable: "Désactiver l'Utilisateur",
      enable: "Activer l'Utilisateur",
      delete: "Retirer l'Utilisateur",
    },
    empty: {
      title: 'Aucun utilisateur trouvé',
      description: 'Créez votre premier utilisateur pour commencer',
    },
    delete: {
      title: "Retirer l'Utilisateur",
      description: "Êtes-vous sûr de vouloir retirer cet utilisateur de l'espace de travail ?",
      button: "Retirer l'Utilisateur",
    },
    validation: {
      username: {
        required: "Le nom d'utilisateur est requis",
        minLength: "Le nom d'utilisateur doit contenir au moins 3 caractères",
      },
      email: {
        required: "L'email est requis",
        invalid: 'Veuillez entrer une adresse email valide',
      },
      temporaryPassword: {
        required: 'Le mot de passe temporaire est requis',
        minLength: 'Le mot de passe doit contenir au moins 8 caractères',
      },
    },
  },
  folder: {
    title: 'Dossiers',
    description: 'Organisez vos entreprises en dossiers',
    search: 'Rechercher des dossiers...',
    list: {
      error: {
        title: 'Erreur',
        description: 'Échec du chargement des dossiers',
      },
    },
    loading: 'Chargement des dossiers...',
    create: {
      title: 'Créer un nouveau dossier',
      subtitle: 'Organisez vos entreprises avec un dossier personnalisé',
      description: 'Organisez vos entreprises en dossiers',
      button: 'Créer un dossier',
    },
    edit: {
      title: 'Modifier le dossier',
      subtitle: "Mettez à jour les paramètres et l'apparence de votre dossier",
      error: {
        title: 'Erreur',
        description: 'Échec du chargement du dossier',
      },
    },
    detail: {
      error: {
        title: 'Erreur',
        description: 'Échec du chargement du dossier',
      },
    },
    item: {
      name: 'Élément',
      type: 'Type',
      created: 'Créé',
      owner: 'Propriétaire',
      actions: 'Actions',
      view: 'Voir',
      deleted: 'Supprimé',
    },
    form: {
      name: 'Nom du dossier',
      namePlaceholder: 'Saisir le nom du dossier...',
      tags: 'Étiquettes',
      tagsOptional: 'optionnel',
      tagsPlaceholder: 'Saisir les étiquettes séparées par des virgules...',
      favorite: 'Marquer comme favori',
      cancel: 'Annuler',
      create: 'Créer le dossier',
      save: 'Enregistrer les modifications',
    },
    filter: {
      all: 'Tous les dossiers',
      allLabel: 'Tous',
      favorites: 'Dossiers favoris',
      favoritesLabel: 'Favoris',
      archived: 'Dossiers archivés',
      archivedLabel: 'Archivés',
      allFolders: 'Tous les dossiers',
      favoriteFolders: 'Dossiers favoris',
      archivedFolders: 'Dossiers archivés',
    },
    viewMode: {
      table: 'Tableau',
      grid: 'Grille',
      tableView: 'Vue tableau',
      gridView: 'Vue grille',
    },
    validation: {
      nameRequired: 'Le nom du dossier est requis',
      nameMinLength: 'Le nom du dossier doit contenir au moins 3 caractères',
      nameMaxLength: 'Le nom du dossier doit contenir moins de 50 caractères',
    },
    table: {
      name: 'Nom',
      items: 'Éléments',
      created: 'Créé le',
      actions: 'Actions',
    },
    empty: {
      noResults: 'Aucun résultat trouvé',
      title: 'Ce dossier est vide',
      tryDifferentSearch: 'Essayez avec un autre terme de recherche',
      description: 'Commencez par créer votre première entreprise dans ce dossier',
    },
    emptyList: {
      title: 'Aucun dossier pour le moment',
      description: 'Créez votre premier dossier pour organiser vos entreprises',
    },
    clearSearch: 'Effacer la recherche',
  },
  home: {
    welcome: {
      title: 'Bienvenue, {name} !',
    },
    assistant: {
      greeting: 'Est-ce que je peux vous aider ?',
      actions: {
        generatePdf: 'Génère moi un PDF',
        newSearch: 'Je souhaite faire une nouvelle recherche',
      },
    },
    recentProjects: {
      title: 'Projets récents',
      viewAll: 'Voir tout',
      timeAgo: 'il y a {time}',
      noFolder: 'Sans dossier',
      badge: {
        collaborative: 'Collaboratif',
      },
    },
    recentActivities: {
      title: 'Activités récentes',
      by: 'par {username}',
      actions: {
        createdCompany: 'a créé une nouvelle Carte Entreprise pour',
        createdFolder: 'a créé le Dossier',
      },
    },
    modules: {
      status: {
        active: 'Actif',
        proFeature: 'Fonctionnalité Pro',
        comingSoon: 'Bientôt Disponible',
      },
      actions: {
        contactSales: 'Contacter les Ventes',
        open: 'Ouvrir',
      },
      screen: {
        name: 'Screen',
        description:
          'Intelligence approfondie des entreprises et screening complet avec analyses avancées',
        category: 'Business Intelligence',
      },
      target: {
        name: 'Target',
        description:
          'Veille de marché alimentée par IA avec alertes intelligentes et outils de surveillance complets',
        category: 'Analyse de Marché',
      },
      explore: {
        name: 'Explore',
        description:
          'Graphe de connaissances interactif pour visualisation et découverte avancées de données',
        category: 'Cartographie',
      },
      discover: {
        name: 'Discover',
        description: 'Partager des insights stratégiques',
        category: 'Recherche de Données',
      },
    },
  },
  csv: {
    upload: {
      button: 'Import CSV',
      title: 'Importer des Entreprises depuis un CSV',
      description: 'Téléchargez un fichier CSV pour importer plusieurs entreprises à la fois',
      step1: 'Étape 1 : Choisir le Fichier',
      step2: 'Étape 2 : Valider les Données',
      step3: 'Étape 3 : Importer',
      dragDrop: 'Glissez et déposez votre fichier CSV ici',
      or: 'ou',
      chooseFile: 'Choisir un Fichier',
      selectedFile: 'Fichier sélectionné',
      remove: 'Supprimer',
      formatTitle: 'Format CSV Attendu',
      format1: 'La première ligne doit contenir les en-têtes de colonnes',
      format2: 'Colonnes requises : company_name, website',
      format3: 'Colonnes optionnelles : description, tags',
      format4: 'Exemple : "Acme Inc.,https://acme.com,Une super entreprise,tech;saas"',
      processing: 'Traitement du fichier CSV...',
      preview: 'Aperçu des Données',
      companiesFound: 'entreprises trouvées',
      companiesFoundCount: '{count} entreprises trouvées dans le CSV',
      showingFirst: 'Affichage des',
      rows: 'premières lignes',
      moreRows: '{count} lignes supplémentaires non affichées',
      validateData: 'Valider les Données',
      validating: 'Validation des entreprises...',
      validation: {
        title: 'Résultats de la Validation',
        valid: 'Entreprises valides',
        invalid: 'Entreprises invalides',
        validCompanies: '{count} entreprises valides',
        invalidCompanies: '{count} entreprises invalides',
        viewDetails: 'Voir les Détails',
        hideDetails: 'Masquer les Détails',
        row: 'Ligne',
        company: 'Entreprise',
        error: 'Erreur',
        errorsFound: 'Erreurs de Validation Trouvées',
        errorsFoundMessage:
          "Vous pouvez soit corriger les erreurs dans votre fichier CSV et le télécharger à nouveau, soit continuer l'importation qui ignorera les lignes invalides.",
      },
      tokens: {
        title: 'Utilisation des Jetons',
        required: 'jetons requis',
        tokensRequired: '{count} jetons requis',
        available: 'jetons disponibles',
        tokensAvailable: 'Vous avez {count} jetons disponibles',
        insufficient: 'Jetons insuffisants',
        insufficientMessage: "Vous avez besoin de {required} jetons mais n'en avez que {available}",
        moduleDisabled: "L'import CSV nécessite l'activation du module Stream",
      },
      actions: {
        cancel: 'Annuler',
        import: 'Importer les Entreprises',
        importing: 'Importation...',
        close: 'Fermer',
        importCompanies: 'Importer {count} Entreprises',
        fixAndReupload: 'Corriger le CSV et Télécharger à Nouveau',
        goToFolder: 'Aller au Dossier',
        uploadAnother: 'Télécharger un Autre CSV',
      },
      results: {
        title: "Résultats de l'Import",
        success: 'Importées avec succès',
        failed: "Échec de l'import",
        successCount: '{count} entreprises importées avec succès',
        failedCount: "{count} entreprises n'ont pas pu être importées",
        viewCompanies: 'Voir les Entreprises',
      },
      errors: {
        invalidFile: 'Format de fichier invalide. Veuillez télécharger un fichier CSV.',
        emptyFile: 'Le fichier CSV est vide',
        missingColumns: 'Colonnes requises manquantes : {columns}',
        parseError: "Erreur lors de l'analyse du fichier CSV",
        uploadError: 'Erreur lors du téléchargement du fichier',
        validationError: 'Erreur lors de la validation des entreprises',
        importError: "Erreur lors de l'importation des entreprises",
      },
    },
  },
  help: {
    noContent: {
      title: "Aucun Contenu d'Aide Disponible",
      message:
        "Vous n'avez accès à aucune section d'aide en fonction de vos permissions actuelles.",
    },
    selectTopic: {
      placeholder: "Sélectionner un sujet d'aide",
      title: "Sélectionner un Sujet d'Aide",
      message: 'Choisissez un sujet dans la barre latérale pour voir la documentation détaillée.',
    },
    loading: {
      content: "Chargement du contenu d'aide...",
    },
    categories: {
      admin: 'Administration',
      company: "Analyse d'Entreprises",
      organization: "Gestion de l'Espace de Travail",
    },
  },
  login: {
    heading: 'Connectez-vous à votre compte',
    signingIn: 'Connexion en cours...',
    signInButton: 'Se connecter avec Keycloak',
    errors: {
      genericError: "Une erreur s'est produite lors de la connexion",
    },
  },
  errors: {
    notFound: {
      title: 'Page Non Trouvée',
      message: "La page que vous recherchez n'existe pas ou a été déplacée.",
      goHome: "Aller à l'Accueil",
      goBack: 'Retour',
      help: "Besoin d'Aide ?",
    },
    forbidden: {
      title: 'Accès Refusé',
      message: "Vous n'avez pas la permission d'accéder à cette ressource.",
      goHome: "Aller à l'Accueil",
      goBack: 'Retour',
      contactAdmin: "Contacter l'Administrateur",
      token: {
        moduleDisabled: '{module} Désactivé',
        insufficientTokens: 'Jetons Insuffisants',
        accessRestricted: 'Accès Restreint',
        moduleDisabledMessage:
          'Le module {module} a été désactivé pour votre espace de travail. Contactez votre administrateur pour activer cette fonctionnalité.',
        insufficientTokensMessage:
          "Vous n'avez pas assez de jetons pour accéder au module {module}. Contactez votre administrateur pour ajouter plus de jetons.",
        unavailable: 'Cette fonctionnalité est actuellement indisponible.',
        status: {
          disabled: 'Désactivé',
          noTokens: 'Aucun Jeton',
        },
      },
    },
  },
}
