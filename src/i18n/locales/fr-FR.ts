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
      team: 'Équipe',
      credits: 'Crédits',
      'ai-preferences': 'Préférences IA',
    },
    credits: {
      cardDescription: 'Consultez les statistiques de consommation de crédits',
    },
    team: {
      title: 'Vos membres',
      resetPassword: 'Réinitialiser le mot de passe',
      you: 'Vous',
      table: {
        member: 'Membre',
        email: 'Email',
        permissions: 'Permissions',
        actions: 'Actions',
      },
      empty: {
        title: 'Aucun membre trouvé',
        description: 'Aucun membre dans votre organisation',
        searchDescription: 'Essayez un autre terme de recherche',
      },
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
        error: 'Erreur de chargement des sessions',
        errorDescription: 'Impossible de charger vos sessions actives. Veuillez réessayer.',
        noOtherSessions: 'Aucune autre session active',
        webSession: 'Session Web',
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
        error: "Erreur de chargement de l'activité",
        errorDescription: "Impossible de charger votre journal d'activité. Veuillez réessayer.",
        noEvents: "Aucun événement d'activité trouvé",
        loadMore: 'Charger plus',
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
      // Temporarily disabled: Re-enable once /notifications page is implemented
      // viewAll: 'Voir toutes les notifications',
      companyCreated: 'Nouvelle entreprise ajoutée',
      folderCreated: 'Nouveau dossier créé',
      activityMessage: 'a créé',
      categoryCompany: 'Entreprise',
      categoryFolder: 'Dossier',
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
      noHistory: "Aucun historique d'utilisation",
      noHistoryDesc: "L'utilisation des crédits apparaîtra ici lorsque vous créerez des fiches entreprises.",
      createdBy: 'Fiche créée par',
    },
    chapse: {
      title: 'Chaps-e',
      welcomeMessage: 'Bonjour ! Je suis Chaps-e, votre assistant IA. Comment puis-je vous aider aujourd\'hui ?',
      addCompany: 'Ajouter une entreprise',
      addThisCompany: 'Ajouter',
      toContext: 'au contexte',
      context: 'Contexte :',
      activeContext: 'Contexte actif :',
      thinking: 'Réflexion en cours...',
      placeholder: 'Écrivez un message...',
      confirmDeleteConversation: 'Êtes-vous sûr de vouloir supprimer cette conversation ?',
      confirmClearMessages: 'Êtes-vous sûr de vouloir effacer tous les messages ?',
      searchCompanies: 'Rechercher des entreprises...',
      typeToSearch: 'Tapez pour rechercher des entreprises',
      toSend: 'Envoyer',
      clearHistory: 'Effacer l\'historique',
      newConversation: 'Nouvelle conversation',
      enterFullscreen: 'Plein écran',
      exitFullscreen: 'Quitter le plein écran',
      loadMore: 'Charger plus',
      dateGroups: {
        today: 'Aujourd\'hui',
        yesterday: 'Hier',
        lastWeek: '7 derniers jours',
        lastMonth: '30 derniers jours',
        older: 'Plus ancien',
      },
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
        placeholder: 'ChapsVision',
        error: "Le nom de l'entreprise doit contenir au moins 2 caractères",
      },
      website: {
        label: 'Site web',
        placeholder: 'https://www.chapsvision.com',
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
    loader: {
      title: 'Authentification...',
      message: 'Veuillez patienter pendant que nous vérifions votre session',
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
      justNow: "à l'instant",
      minutesAgo: 'il y a {count} min',
      hoursAgo: 'il y a {count}h',
      daysAgo: 'il y a {count}j',
    },
    comingSoon: 'Bientôt disponible',
    soon: 'Bientôt',
    moduleUnavailable: 'Module non disponible',
    modules: {
      screen: 'Fiche entreprise',
      target: 'Veille',
      explore: 'Cartographie',
      stream: 'Stream',
    },
    notFound: 'Non trouvé',
    loading: 'Chargement...',
    na: 'N/D',
    noData: 'Aucune donnée disponible',
    cancel: 'Annuler',
    save: 'Enregistrer',
    confirm: 'Confirmer',
    error: 'Erreur',
    genericError: 'Une erreur s\'est produite',
    close: 'Fermer',
    back: 'Retour',
    next: 'Suivant',
    done: 'Terminé',
    dismiss: 'Ignorer',
    breadcrumb: "Fil d'Ariane",
    keyboard: {
      ctrl: 'Ctrl',
      enter: 'Entrée',
    },
    validation: {
      password: {
        required: 'Le mot de passe est requis',
        minLength: 'Le mot de passe doit contenir au moins 8 caractères',
        uppercase: 'Le mot de passe doit contenir au moins une lettre majuscule',
        lowercase: 'Le mot de passe doit contenir au moins une lettre minuscule',
        number: 'Le mot de passe doit contenir au moins un chiffre',
        specialChar: 'Le mot de passe doit contenir au moins un caractère spécial',
      },
    },
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
    sort: {
      oldestFirst: 'Plus ancien',
      newestFirst: 'Plus récent',
    },
    event: {
      impactAnalysis: "Analyse d'impact",
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
    card: {
      postedDate: 'Publié le :',
      description: 'Description',
      requirements: 'Exigences',
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
    count: '{count} produits',
    countInCategory: '{count} produits dans cette catégorie',
    badges: {
      new: 'Nouveau',
    },
    showLess: 'Voir moins',
    showMore: 'Voir {count} de plus',
    header: {
      title: 'Portefeuille Produits',
      summary: '{total} produits dans {categories} catégories',
    },
    viewMode: {
      list: 'Vue liste',
      grid: 'Vue grille',
    },
    search: {
      placeholder: 'Rechercher des produits...',
    },
    categories: {
      all: 'Toutes les catégories',
    },
  },
  profile: {
    title: "Profil de l'Entreprise",
    loading: {
      title: "Chargement du profil de l'entreprise...",
      description: "Récupération des informations complètes de l'entreprise...",
    },
    tabs: {
      productsOverview: 'Aperçu des produits',
      partnersLabels: 'Partenaires & Marques',
      digitalStrategy: 'Stratégie digitale',
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
        viewProducts: 'Voir les produits',
        customerType: 'Type de clientèle',
        marketingPositioning: 'Positionnement marketing',
        partnersAndLabels: 'Partenaires & Marques',
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
        overallStrategy: 'Stratégie globale',
        digitalTransformation: 'Transformation numérique',
        eCommerceCapabilities: 'Capacités e-commerce',
        mobileStrategy: 'Stratégie mobile',
        digitalMarketingApproach: 'Approche marketing digital',
        loyaltyProgram: 'Programme de fidélité',
        onlineServices: 'Services en ligne',
      },
      news: {
        title: 'Actualités récentes',
        viewAll: 'Voir tout',
        stats: {
          totalPressItems: 'Total des articles de presse',
          financialNews: 'Actualités financières',
          mediaMentions: 'Mentions médiatiques',
          productLaunches: 'Lancements de produits',
        },
        latestUpdates: 'Dernières mises à jour',
      },
      press: {
        insights: {
          title: 'Aperçu de la Couverture Presse',
        },
        noData: 'Aucune donnée de couverture presse disponible',
        categories: {
          financialNews: 'Actualités financières',
          productLaunches: 'Lancements de produits',
          executiveInterviews: 'Interviews de dirigeants',
          mediaMentions: 'Mentions médiatiques',
          pressReleases: 'Communiqués de presse',
          articles: 'Articles',
          partnershipAnnouncements: 'Annonces de partenariats',
          awardsRecognition: 'Prix et reconnaissances',
        },
        stats: {
          title: 'Statistiques de couverture presse',
        },
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
      businessLine: {
        title: "Secteur d'activité",
        notFound: 'Non trouvé',
      },
      group: {
        title: 'Groupe',
        notFound: 'Non trouvé',
      },
      debug: {
        title: 'Données brutes de connaissance (Débogage)',
        mistral: 'Connaissance brute Mistral',
        gpt: 'Connaissance brute GPT',
        wikipedia: 'Connaissance brute Wikipedia',
        website: 'Contenu brut du site web',
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
      interactive: 'Interactif',
      screenshot: 'Capture d\'écran',
    },
    levels: {
      all: 'Tous les niveaux',
      ceo: 'PDG',
      executives: 'Cadres dirigeants',
      managers: 'Managers',
      teamMembers: 'Membres de l\'équipe',
    },
    views: {
      grid: 'Vue grille',
      list: 'Vue liste',
    },
    noResults: 'Aucun membre de l\'équipe ne correspond à vos critères',
    searchPlaceholder: 'Rechercher par nom ou poste...',
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
      users: {
        title: 'Gestion des Utilisateurs',
        description: 'Gérer les assignations d\'organisation des utilisateurs et les accès'
      },
      usage:{
        title: 'Tableau de Bord d\'Utilisation',
        description: 'Voir les métriques d\'utilisation de l\'application pour toutes les organisations'
      },
      uiDemo: {
        title: 'Démo Composants UI',
        description: 'Prévisualisez et testez tous les composants UI et le système de design',
      },
      workflows: {
        title: 'Gestion des Workflows',
        description: 'Configurez et gérez les workflows automatisés et processus',
      },
      tasks: {
        title: 'Suivi des Tâches',
        description: 'Surveiller les tâches en cours dans toutes les organisations et redémarrer les processus bloqués',
      },
      costs: {
        title: 'Analyse des Coûts',
        description: "Surveillez l'utilisation des tokens et les coûts des workflows MINT",
      },
      manage: 'Gérer',
      view: 'Voir',
      explore: 'Explorer',
      configure: 'Configurer',
      analyze: 'Analyser',
      monitor: 'Surveiller',
    },
    disableUser: {
      title: 'Désactiver l\'Utilisateur',
      confirmText: 'Êtes-vous sûr de vouloir désactiver',
      actionTitle: 'Cette action va :',
      actionDescription: '• Empêcher l\'utilisateur de se connecter • Révoquer toutes les sessions actives • Conserver toutes les données utilisateur. Vous pourrez réactiver l\'utilisateur ultérieurement si nécessaire.',
      cancel: 'Annuler',
      confirm: 'Désactiver l\'Utilisateur',
    },
    userActions: {
      changeOrganization: 'Changer d\'Organisation',
      managePermissions: 'Gérer les Permissions',
      enableUser: 'Activer l\'Utilisateur',
      disableUser: 'Désactiver l\'Utilisateur',
      resetPassword: 'Réinitialiser le Mot de Passe',
    },
    userOrganization: {
      title: 'Changer l\'Organisation de l\'Utilisateur',
      currentOrganization: 'Organisation actuelle :',
      noOrganization: 'Aucune organisation assignée',
      current: '(actuelle)',
      noOrganizationsAvailable: 'Aucune organisation disponible',
      selectOrganization: 'Sélectionner une organisation',
      cancel: 'Annuler',
      save: 'Enregistrer',
    },
    permissions: {
      title: 'Gérer les Permissions',
      description: 'Configurer les permissions pour {username}',
      loading: 'Chargement des permissions...',
      save: 'Enregistrer les Permissions',
      alwaysOn: 'Toujours actif',
      error: {
        title: 'Erreur lors du chargement des permissions',
      },
      legacyWarning: {
        title: 'Permissions héritées détectées',
        description: 'Cet utilisateur a des permissions de l\'ancien modèle. Elles seront automatiquement converties au nouveau modèle lors de l\'enregistrement.',
      },
      customWarning: {
        title: 'Permissions personnalisées détectées',
        description: 'Cet utilisateur a des permissions personnalisées qui ne correspondent à aucun rôle prédéfini. Sélectionner un rôle remplacera ses permissions actuelles.',
      },
      tabs: {
        roles: 'Rôles rapides',
        custom: 'Permissions personnalisées',
      },
      sections: {
        base: 'Accès de base',
        modules: 'Permissions des modules',
        modulesDescription: 'Ces permissions permettent de créer des types de contenu spécifiques. Nécessite l\'accès en écriture.',
        admin: 'Permissions administrateur',
        adminDescription: 'L\'accès administrateur donne un contrôle total. À utiliser avec précaution.',
      },
      organizationRead: {
        label: 'Accès en lecture',
        description: 'Voir les dossiers et entreprises de l\'organisation',
      },
      organizationWrite: {
        label: 'Accès en écriture',
        description: 'Créer et gérer les dossiers, gérer le contenu possédé',
      },
      organizationManage: {
        label: 'Gestion d\'équipe',
        description: 'Gérer les membres de l\'équipe et leurs permissions',
      },
      companyCreate: {
        label: 'Ajouter des éléments',
        description: 'Ajouter des fiches entreprises et autres éléments aux dossiers',
      },
      targetCreate: {
        label: 'Créer des cibles',
        description: 'Créer de nouveaux watchfiles (module Target)',
      },
      requiresWriteAccess: 'Nécessite l\'accès en écriture activé',
      adminOrganizations: {
        label: 'Admin organisation',
        description: 'Accès administrateur complet à toutes les organisations',
      },
      summary: {
        title: 'Résumé des permissions',
        noPermissions: 'Aucune permission sélectionnée',
      },
      roles: {
        reader: {
          name: 'Lecteur',
          description: 'Accès en lecture seule aux dossiers et entreprises',
        },
        writer: {
          name: 'Rédacteur',
          description: 'Créer et gérer les dossiers et entreprises',
        },
        manager: {
          name: 'Manager',
          description: 'Permissions de rédacteur plus gestion d\'équipe',
        },
        admin: {
          name: 'Administrateur',
          description: 'Accès administrateur complet',
        },
      },
    },
    taskTypes: {
      title: 'Efficacité des coûts par type de tâche',
      loading: 'Chargement des données de types de tâches...',
      error: 'Échec du chargement des données de types de tâches',
      noData: 'Aucune donnée de type de tâche disponible',
      columns: {
        taskType: 'Type de tâche',
        totalCost: 'Coût total',
        taskCount: 'Nombre de tâches',
        avgCostPerTask: 'Coût moy./Tâche',
        avgInputTokens: 'Tokens entrée moy.',
        avgOutputTokens: 'Tokens sortie moy.',
      },
      percentOfTotal: '% du total',
      percentOfAllTasks: '% de toutes les tâches',
      summary: {
        mostExpensive: 'Plus coûteux',
        mostFrequent: 'Plus fréquent',
        totalTaskTypes: 'Total types de tâches',
      },
      efficiency: {
        veryEfficient: 'Très efficace',
        average: 'Moyen',
        expensive: 'Coûteux',
      },
    },
    organizationBreakdown: {
      title: 'Répartition des coûts par organisation',
      loading: 'Chargement des données d\'organisation...',
      error: 'Échec du chargement des données d\'organisation',
      noData: 'Aucune donnée d\'organisation disponible',
      columns: {
        organization: 'Organisation',
        totalCost: 'Coût total',
        tasks: 'Tâches',
        companies: 'Entreprises',
        avgCostPerTask: 'Coût moy./Tâche',
        avgCostPerCompany: 'Coût moy./Entreprise',
      },
      id: 'ID :',
      percentOfTotal: '{percent}% du total',
      tokens: '{count} tokens',
      noTokens: 'Aucun token',
    },
    costChart: {
      title: 'Répartition des coûts par espace de travail',
      loading: 'Chargement des données du graphique...',
      error: 'Échec du chargement des données du graphique',
      noData: 'Aucune donnée d\'espace de travail disponible',
      tooltip: {
        cost: 'Coût',
        percentage: 'Pourcentage',
        tasks: 'Tâches',
        companies: 'Entreprises',
      },
    },
    usage: {
      title: "Tableau de bord d'utilisation",
      description: "Voir les métriques d'utilisation de l'application pour toutes les organisations",
      timeRange: {
        last7Days: '7 derniers jours',
        last30Days: '30 derniers jours',
        last90Days: '90 derniers jours',
        allTime: 'Tout le temps',
      },
      error: {
        title: 'Erreur de chargement des données',
        retry: 'Réessayer',
        message: "Échec du chargement des statistiques d'utilisation",
      },
      stackedChart: {
        title: 'Entreprises par Organisation',
        loading: 'Chargement des données du graphique...',
        noData: "Aucune donnée d'organisation pour la période sélectionnée",
        companies: 'entreprises',
      },
      lineChart: {
        title: 'Entreprises au Fil du Temps',
        loading: 'Chargement des données du graphique...',
        noData: "Aucune donnée d'entreprise pour la période sélectionnée",
        label: 'Entreprises Créées',
        tooltip: '{count} entreprise créée | {count} entreprises créées',
      },
      kpi: {
        companiesCreated: 'Entreprises Créées',
        taskSuccessRate: 'Taux de Réussite des Tâches',
        activeUsers: 'Utilisateurs Actifs',
        azureCost: 'Coût Azure',
        phase2Feature: 'Fonctionnalité Phase 2',
      },
      table: {
        title: 'Répartition par Organisation',
        organization: 'Organisation',
        companiesCreated: 'Entreprises Créées',
        percentOfTotal: '% du Total',
        noData: "Aucune donnée d'organisation pour la période sélectionnée",
      },
    },
    workflowCard: {
      save: 'Enregistrer',
      cancel: 'Annuler',
      editing: 'Modification...',
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
    organizations: {
      title: 'Gestion des Organisations',
      description: 'Gérer toutes les organisations dans Keycloak',
      search: 'Rechercher des organisations...',
      showing: 'Affichage de {from} à {to} sur {total} organisations',
      members: 'Membres',
      empty: 'Aucune organisation trouvée',
      error: {
        title: 'Erreur de Chargement des Organisations',
      },
    },
    tasks: {
      title: 'Suivi des Tâches',
      description: 'Surveiller et gérer les tâches de toutes les organisations',
      refresh: 'Actualiser',
      autoRefresh: {
        on: 'Auto-refresh ON',
        off: 'Auto-refresh OFF',
      },
      lastUpdated: {
        seconds: 'Mis à jour il y a {seconds}s',
        minutes: 'Mis à jour il y a {minutes}m',
      },
      loading: 'Chargement des tâches...',
      error: {
        title: 'Erreur de Chargement',
        description: 'Impossible de charger les tâches',
        retry: 'Réessayer',
      },
      stats: {
        totalTasks: 'Total Tâches',
        running: 'En cours',
        pending: 'En attente',
        blocked: 'Bloquées',
        failed: 'Échouées',
        successRate: 'Taux de Succès',
      },
      stuckAlert: {
        title: '{count} Tâche Bloquée Détectée | {count} Tâches Bloquées Détectées',
        description: 'Des tâches en cours depuis trop longtemps nécessitent votre attention. Vérifiez et redémarrez si nécessaire.',
        selectAll: 'Sélectionner Toutes les Bloquées',
      },
      filters: {
        allStatuses: 'Tous les Statuts',
        allTypes: 'Tous les Types',
        allOrganizations: 'Toutes les Organisations',
        clearFilters: 'Effacer les Filtres',
        internal: 'Interne',
      },
      status: {
        running: 'En cours',
        pending: 'En attente',
        blocked: 'Bloquée',
        succeeded: 'Réussie',
        error: 'Erreur',
      },
      taskTypes: {
        profile: 'Profil',
        digital: 'Digital',
        timeline: 'Chronologie',
        products: 'Produits',
        jobs: 'Emplois',
        csr: 'RSE',
        press: 'Presse',
        team: 'Équipe',
        dataCollection: 'Collecte de Données',
      },
      table: {
        id: 'ID',
        company: 'Entreprise',
        organization: 'Organisation',
        type: 'Type',
        status: 'Statut',
        elapsed: 'Temps Écoulé',
        actions: 'Actions',
        noTasks: 'Aucune tâche trouvée correspondant à vos filtres',
        restartTask: 'Redémarrer la tâche',
        viewError: 'Voir l\'erreur',
      },
      selection: {
        selected: '{count} sélectionnée | {count} sélectionnées',
        restart: 'Redémarrer {count} Tâche | Redémarrer {count} Tâches',
        clearSelection: 'Effacer la Sélection',
      },
      pagination: {
        showing: 'Affichage de {from} à {to} sur {total} tâches',
        page: 'Page {page} sur {pages}',
      },
      modal: {
        title: 'Confirmer le Redémarrage Groupé',
        description: 'Vous êtes sur le point de redémarrer {count} tâche. Cette action va : | Vous êtes sur le point de redémarrer {count} tâches. Cette action va :',
        actions: {
          cancel: 'Annuler les tâches en cours',
          queue: 'Les remettre en file d\'attente pour exécution immédiate',
          reset: 'Réinitialiser leur statut à "en attente"',
        },
        selectedTasks: 'Tâches sélectionnées :',
        stuckWarning: '{count} de ces tâches sont en cours depuis plus de 3 minutes et peuvent être bloquées.',
        result: {
          initiated: 'Redémarrage Initié',
          noTasks: 'Aucune Tâche Redémarrée',
          restarted: '{count} tâche redémarrée avec succès | {count} tâches redémarrées avec succès',
          skipped: '{count} tâche ignorée | {count} tâches ignorées',
          skippedReasons: 'Raisons des exclusions :',
          taskReason: 'Tâche #{id} : {reason}',
        },
        buttons: {
          cancel: 'Annuler',
          restarting: 'Redémarrage...',
          restart: 'Redémarrer {count} Tâche | Redémarrer {count} Tâches',
        },
      },
    },
    adminRequired: 'Administrateur Requis',
    import: {
      title: 'Importer des utilisateurs',
      description: 'Importer des utilisateurs depuis un fichier CSV ou Excel',
      needHelpTitle: 'Besoin d\'un modèle ?',
      needHelpMessage: 'Téléchargez notre fichier CSV exemple pour voir le format attendu avec des données d\'exemple.',
      downloadSample: 'Télécharger un exemple CSV',
      dropZoneTitle: 'Glissez-déposez votre fichier ici',
      dropZoneSubtitle: 'Supporte les fichiers CSV et Excel (max 100 utilisateurs)',
      browseFiles: 'Parcourir les fichiers',
      rows: 'lignes',
      parseError: 'Erreur lors de l\'analyse du fichier',
      csvColumn: 'Colonne CSV',
      mapsTo: 'Correspond à',
      ignore: 'Ignorer la colonne',
      fields: {
        username: 'Nom d\'utilisateur',
        email: 'Email',
        firstname: 'Prénom',
        lastname: 'Nom',
        password: 'Mot de passe',
      },
      ignoredColumnsWarning: 'Certaines colonnes seront ignorées',
      ignoredColumnsMessage: 'Les colonnes suivantes ne sont pas mappées : {columns}',
      requiredFieldsWarning: 'Champs requis non mappés',
      requiredFieldsMessage: 'Les colonnes Nom d\'utilisateur et Email doivent être mappées pour continuer.',
      requiredFieldTooltip: 'Champ obligatoire',
      // Gestion des mots de passe - sans colonne mot de passe
      noPasswordColumnTitle: 'Aucune colonne mot de passe détectée',
      noPasswordColumnMessage:
        'Des mots de passe aléatoires seront générés pour tous les utilisateurs. Vous pourrez les télécharger une fois l\'import terminé.',
      // Gestion des mots de passe - avec colonne mot de passe
      passwordHandling: 'Gestion des mots de passe',
      usePasswordsFromFile: 'Utiliser les mots de passe du fichier',
      usePasswordsFromFileHint:
        'Les mots de passe du CSV seront utilisés. Des mots de passe aléatoires seront générés pour les utilisateurs sans mot de passe.',
      generateAllPasswords: 'Générer tous les mots de passe',
      generateAllPasswordsHint:
        'Des mots de passe aléatoires seront générés pour tous les utilisateurs, ignorant les mots de passe du fichier.',
      // Clés historiques (gardées pour compatibilité)
      generatePasswords: 'Générer des mots de passe aléatoires',
      generatePasswordsHint: 'Des mots de passe seront générés pour tous les utilisateurs. Ils devront les changer à la première connexion.',
      generatePasswordsHintWithColumn: 'Des mots de passe seront générés uniquement pour les utilisateurs sans mot de passe dans le fichier.',
      previewSummary: '{count} utilisateurs prêts à importer',
      previewSkipped: '{count} utilisateurs seront ignorés en raison d\'erreurs',
      duplicatesFound: 'Emails en double trouvés',
      duplicatesMessage: 'Les utilisateurs suivants ont des adresses email en double et seront ignorés :',
      duplicate: 'Doublon',
      validationErrors: 'Erreurs de validation trouvées',
      willGenerate: 'Sera généré',
      status: 'Statut',
      ready: 'Prêt',
      showingPreview: 'Affichage de {shown} sur {total} utilisateurs',
      importButton: 'Importer les utilisateurs',
      successCount: '{count} utilisateurs importés avec succès',
      successMessage: 'Tous les utilisateurs ont été importés et peuvent maintenant se connecter avec leurs mots de passe temporaires.',
      errorCount: '{count} utilisateurs n\'ont pas pu être importés',
      errorMessage: 'Certains utilisateurs n\'ont pas pu être importés. Voir les détails ci-dessous.',
      showDetails: 'Afficher les détails des erreurs',
      hideDetails: 'Masquer les détails',
      passwordsGenerated: 'Mots de passe temporaires générés',
      passwordsMessage: 'Téléchargez le fichier des mots de passe pour le partager avec les utilisateurs. C\'est la seule fois où vous pouvez télécharger ce fichier.',
      downloadPasswords: 'Télécharger les mots de passe CSV',
      autoDownloaded: 'Fichier de mots de passe téléchargé automatiquement',
      cancelTitle: 'Annuler l\'importation',
      cancelMessage: 'Êtes-vous sûr de vouloir annuler ? Toute progression sera perdue.',
      confirmCancel: 'Oui, annuler',
      selectOrganization: 'Organisation cible',
      selectOrganizationPlaceholder: 'Sélectionnez une organisation...',
      steps: {
        upload: 'Télécharger le fichier',
        map: 'Mapper les colonnes',
        review: 'Vérifier',
        results: 'Résultats',
      },
      errors: {
        usernameRequired: 'Le nom d\'utilisateur est requis',
        emailRequired: 'L\'email est requis',
        emailInvalid: 'Format d\'email invalide',
      },
    },
    users: {
      search: {
        placeholder: 'Rechercher par nom d\'utilisateur, nom, email ou organisation...',
      },
      title: 'Gestion des Utilisateurs',
      description: 'Gérer les assignations d\'organisation des utilisateurs',
      loading: 'Chargement des utilisateurs...',
      modal: {
        changeOrganization: 'Changer l\'Organisation de l\'Utilisateur',
        assignOrganization: 'Assigner un Utilisateur à une Organisation',
        loading: 'Chargement de l\'organisation...',
        selectOrganization: 'Sélectionner une organisation :',
        assigning: 'Assignation en cours...',
        error: {
          title: 'Erreur de chargement de l\'organisation',
        },
        warning: {
          title: 'Changement d\'Organisation',
          message: 'Changer l\'organisation de cet utilisateur le transférera vers la nouvelle organisation. Ses données resteront dans l\'organisation d\'origine.',
        },
      },
      table: {
        name: "Nom",
        username: "Nom d'utilisateur",
        email: "E-mail",
        actions: "Actions",
        status: "Statut"
      },
      status: {
        active: "Actif"
      },
      actions: {
        changeOrganization: "Changer d'organisation",
        managePermissions: "Gérer les permissions",
        enableUser: "Activer l'utilisateur",
        disableUser: "Désactiver l'utilisateur",
        resetPassword: "Réinitialiser le mot de passe"
      },
      assignOrganization: {
        success: 'Organisation assignée avec succès !',
        error: 'Échec de l\'assignation de l\'organisation',
      },
      updatePermissions: {
        success: 'Permissions mises à jour avec succès !',
        error: 'Échec de la mise à jour des permissions',
      },
      resetPassword: {
        success: 'Mot de passe réinitialisé avec succès !',
        error: 'Échec de la réinitialisation du mot de passe',
      },
      disable: {
        success: 'Utilisateur désactivé avec succès !',
        error: 'Échec de la désactivation de l\'utilisateur',
      },
      enable: {
        success: 'Utilisateur activé avec succès !',
        error: 'Échec de l\'activation de l\'utilisateur',
      },
    },
  },
  tasks: {
    events: {
      ready: 'Votre Screen sur <span class="font-bold">{companyName}</span> est prêt !',
      readyWithErrors:
        'Votre Screen sur <span class="font-bold">{companyName}</span> est terminé avec quelques erreurs.',
      view: 'Voir',
      taskSucceeded: 'Tâche "{taskType}" terminée avec succès',
      taskFailed: 'Tâche "{taskType}" échouée : {error}',
      taskStatus: 'Tâche "{taskType}" statut : {status}',
    },
  },
  company: {
    loading: 'Chargement des entreprises...',
    name: 'Entreprise',
    created: 'Créé',
    createdAt: 'Créé le',
    owner: 'Propriétaire',
    status: 'Statut',
    actions: 'Actions',
    clearSearch: 'Effacer la recherche',
    chat: {
      askOurAi: 'Demandez à notre IA',
      thinking: 'Réflexion en cours...',
      placeholder: 'Écrivez un message...',
      welcomeMessage: 'Bonjour ! Je suis Basil, votre assistant. Je peux vous aider avec des questions sur cette entreprise. Que souhaitez-vous savoir ?',
      noResponse: "J'ai reçu votre message mais je n'ai pas pu générer de réponse.",
      errorMessage: "Désolé, j'ai rencontré une erreur lors du traitement de votre demande. Veuillez réessayer.",
      assistant: {
        name: 'Basil Assistant IA',
        shortName: 'Basil IA',
        online: 'En ligne et prêt à aider',
      },
    },
    taskError: {
      title: 'Erreur',
      description: 'Une erreur est survenue lors du chargement de la page',
      restartTask: 'Relancer la tâche',
    },
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
      inFolder: 'Créer une nouvelle fiche entreprise dans le dossier',
      selectFolder: 'Sélectionner un dossier',
      chooseFolderPlaceholder: 'Choisissez un dossier...',
      noFolders: {
        title: 'Aucun dossier disponible',
        message: 'Vous devez créer un dossier avant de créer une fiche entreprise',
        action: 'Créer un dossier',
      },
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
      analyses: 'Analyses',
      notAvailable: 'Section non disponible',
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
      folderRequired: 'Veuillez sélectionner un dossier',
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
      title: "Archiver l'entreprise",
      subtitle: "L'entreprise sera déplacée vers les archives.",
      details: "Détails de l'entreprise",
      warning: {
        message:
          "L'archivage d'une entreprise la masquera de la liste principale. Vous pourrez la restaurer ultérieurement depuis la vue des archives.",
      },
      confirm: {
        button: "Archiver l'entreprise",
      },
      success: 'L\'entreprise "{name}" a été archivée avec succès',
      error: 'Échec de l\'archivage de l\'entreprise "{name}". Veuillez réessayer.',
    },
    delete: {
      title: "Supprimer l'entreprise",
      button: 'Supprimer',
      success: 'L\'entreprise "{name}" a été supprimée avec succès',
      error: 'Échec de la suppression de l\'entreprise "{name}". Veuillez réessayer.',
    },
    restore: {
      success: 'L\'entreprise "{name}" a été restaurée avec succès',
      error: 'Échec de la restauration de l\'entreprise "{name}". Veuillez réessayer.',
    },
    refresh: {
      button: 'Actualiser',
      title: 'Actualiser les données de l\'entreprise',
      subtitle: 'Obtenir les dernières informations sur {name}',
      warning: {
        message: 'Les données actuelles seront remplacées lors de l\'arrivée des nouvelles données',
      },
      consumptionNotice: '35 jetons seront consommés',
      tokens: {
        title: 'Informations sur les jetons',
        current: 'Jetons actuels',
        cost: 'Coût d\'actualisation',
        remaining: 'Après actualisation',
      },
      details: 'Détails de l\'entreprise',
      confirm: {
        button: 'Actualiser les données',
      },
      success: 'L\'actualisation de l\'entreprise "{name}" a commencé',
      error: 'Échec de l\'actualisation de l\'entreprise "{name}"',
      tooltip: {
        insufficientTokens: 'Jetons insuffisants',
        waitForTasks: 'Attendre que toutes les tâches soient terminées',
        tasksRunning: 'Les tâches sont en cours d\'exécution',
      },
    },
    item: {
      created: 'Créé le',
      by: 'par',
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
    export: {
      button: 'Exporter',
      modal: {
        title: "Options d'export",
        description: 'Sélectionnez les sections à inclure dans votre export PowerPoint :',
        preferencesSaved: 'Préférences enregistrées',
        selectAll: 'Tout sélectionner',
        deselectAll: 'Tout désélectionner',
        cancel: 'Annuler',
        export: 'Exporter',
        options: {
          titleSlide: {
            label: 'Diapositive de titre',
            description: "Page de couverture avec le nom de l'entreprise et la date",
          },
          profile: {
            label: "Profil de l'entreprise",
            description: "Informations de base, secteurs d'activité et indicateurs clés",
          },
          productsServices: {
            label: 'Produits et services',
            description: 'Gamme de produits, marques partenaires et marques privées',
          },
          targetAudience: {
            label: 'Public cible et clientèle',
            description: 'Type de clients et positionnement marketing',
          },
          digitalStrategy: {
            label: 'Stratégie digitale et réseaux sociaux',
            description: 'Approche digitale, programmes de fidélité et services en ligne',
          },
          csr: {
            label: 'Responsabilité sociale des entreprises',
            description: 'Initiatives de responsabilité et actions caritatives',
          },
          news: {
            label: 'Presse et médias',
            description: 'Articles de presse et couverture médiatique',
          },
          timeline: {
            label: 'Chronologie',
            description: "Événements et jalons de l'histoire de l'entreprise",
          },
          team: {
            label: 'Équipe et direction',
            description: 'Équipe dirigeante et structure organisationnelle',
          },
          jobs: {
            label: "Offres d'emploi",
            description: "Postes ouverts et informations sur le recrutement",
          },
          press: {
            label: 'Couverture presse',
            description: 'Articles médiatiques et communiqués de presse',
          },
        },
      },
    },
    onlinePresence: {
      title: 'Présence en ligne',
      website: 'Site web',
      socialMedia: 'Présence sur les réseaux sociaux',
    },
    tasks: {
      completed: '{count} terminées ({percentage}%)',
      running: '{count} en cours ({percentage}%)',
      error: '{count} erreurs ({percentage}%)',
      blocked: '{count} bloquées ({percentage}%)',
      pending: '{count} en attente ({percentage}%)',
      analysisInProgress: 'Analyse en cours...',
      completedShort: '{count} terminées',
      runningShort: '{count} en cours',
      errorShort: '{count} en erreur',
      blockedShort: '{count} bloquées',
      pendingShort: '{count} en attente',
      canBeRestarted: 'Des tâches peuvent être redémarrées ou ne sont pas encore lancées',
      startAll: 'Démarrer toutes les tâches',
      completedCount: '{completed}/{total} tâches terminées',
      dataCollection: 'Collecte de données',
      dataCollectionDescription: 'Collecte de données structurées',
    },
    analysisCard: {
      viewMore: 'Voir plus',
      loading: 'Analyse en cours...',
      noData: 'Aucune donnée disponible pour cette section',
      comingSoon: 'Bientôt disponible',
      error: {
        title: 'Erreur',
        message: "Une erreur s'est produite lors de l'analyse",
      },
      status: {
        succeeded: 'Terminé',
        error: 'Erreur',
        running: 'En cours',
        pending: 'En attente',
        blocked: 'En attente (bloquée)',
        notStarted: 'Non démarré',
      },
    },
    footer: {
      createdBy: 'Créé par {username} le {date}',
    },
    translation: {
      button: 'Traduire',
      original: 'Original',
      clickToTranslate: 'Cliquer pour traduire',
      currentlyViewing: 'Actuellement affiché',
      clickToView: 'Cliquer pour afficher dans cette langue',
      inProgress: 'Traduction en cours...',
      progressDetail: '{translated}/{total} champs ({percent}%)',
      viewingDefault: 'Affichage dans la langue originale',
      viewingIn: 'Affichage en {language}',
      alreadyInProgress: 'Traduction déjà en cours',
      started: 'Traduction lancée pour {count} champs vers {language}',
      failed: 'La demande de traduction a échoué. Veuillez réessayer.',
      languages: {
        fr: 'Français',
        es: 'Espagnol',
        de: 'Allemand',
        pt: 'Portugais',
      },
    }
  },
  tokens: {
    module: 'Module {module}',
    token: 'crédit',
    tokens: 'crédits',
    credits: 'crédits',
    companies: 'entreprises',
    loading: 'Chargement des données de crédits...',
    errorTitle: 'Erreur',
    currentTokens: 'Crédits actuels :',
    refresh: 'Actualiser le nombre de crédits',
    moduleStatus: 'Statut des modules',
    management: 'Gestion des crédits',
    managementDescription: 'Gérer le solde de crédits de votre organisation',
    globalBalance: 'Solde de crédits de l\'organisation',
    companyEquivalent: 'Équivalent entreprises',
    company: 'entreprise',
    addTokens: 'Ajouter des crédits',
    quickAdd: 'Ajout rapide (par nombre d\'entreprises)',
    screensWithTokens: '{count} écrans ({tokens})',
    customAmount: 'Montant personnalisé',
    enterAmount: 'Saisir le montant de crédits...',
    add: 'Ajouter',
    addHelper: 'Saisissez le nombre de crédits à ajouter, ou utilisez les boutons d\'ajout rapide ci-dessus.',
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
    history: {
      errorTitle: 'Impossible de charger l\'historique des crédits',
    },
  },
  featureFlags: {
    globalFeatures: 'Fonctionnalités globales',
    description: 'Capacités supplémentaires qui améliorent les modules principaux. Ces fonctionnalités sont désactivées par défaut.',
  },
  dataSources: {
    title: 'Sources de données',
    description: 'Configurer les fournisseurs de données externes pour le screening',
    enabled: 'Activé',
    disabled: 'Désactivé',
    edit: 'Modifier',
    save: 'Enregistrer',
    cancel: 'Annuler',
    enabledAt: 'Activé le',
    lastUpdated: 'Dernière mise à jour',
    apiKey: {
      label: 'Clé API',
      placeholder: 'Entrez la clé API...',
      notConfigured: 'Non configurée',
    },
    pappers: {
      name: 'Pappers',
      description: 'Fournisseur de données d\'entreprises françaises (infos légales, financières, dirigeants)',
    },
  },
  organization: {
    admin: {
      title: 'Gestion des Espaces de Travail',
      description: 'Gérez tous les espaces de travail du système',
      confirmAdminRole: {
        title: "Attribuer le rôle Admin ?",
        warningTitle: "Rôle à privilèges élevés",
        warningDescription: "Ce rôle donne un accès administratif complet à l'organisation.",
        description: "Le rôle Admin inclut :",
        permissions: {
          adminOrganizations: "Accès administrateur global à toutes les espaces de travail",
        }
      }
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
      title: 'Détails de l\'Organisation',
      description: 'Gérer les paramètres, tokens et membres de l\'organisation',
      basicInfo: 'Informations de Base',
      members: 'Membres',
      settings: 'Paramètres',
      settingsPlaceholder: 'Les paramètres de l\'organisation seront implémentés ici',
    },
    membersDescription: 'Gérer les utilisateurs dans cette organisation',
    tabs: {
      profile: 'Profil',
      tokens: 'Jetons',
      members: 'Membres',
      sources: 'Sources',
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
    id: 'ID',
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
      title: 'Créer un Nouvel Utilisateur',
      description: 'Ajouter un nouvel utilisateur à l\'organisation',
    },
    loading: 'Chargement des utilisateurs...',
    resetPassword: {
      title: 'Réinitialiser le Mot de Passe',
      button: 'Réinitialiser le Mot de Passe',
      description: 'Réinitialiser le mot de passe de cet utilisateur',
      infoTitle: 'Réinitialisation du Mot de Passe',
      infoDescription: 'Un mot de passe temporaire sera généré. L\'utilisateur devra le changer lors de sa première connexion.',
      newPassword: 'Nouveau Mot de Passe Temporaire',
      placeholder: 'Saisir le nouveau mot de passe',
      success: 'Mot de Passe Réinitialisé avec Succès',
      successDescription: 'Le mot de passe a été réinitialisé. Partagez ce mot de passe temporaire avec l\'utilisateur.',
      temporaryPassword: 'Mot de Passe Temporaire',
    },
    generatePassword: 'Générer un mot de passe aléatoire',
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
    descriptionGlobal: 'Tous les dossiers de votre organisation',
    viewScope: {
      myFolders: 'Mes dossiers',
      allFolders: 'Tous les dossiers',
    },
    groups: {
      mine: 'Mes dossiers',
      shared: 'Partagés avec moi',
    },
    tooltip: {
      createdBy: 'Créé par {username} le {date} à {time}',
    },
    search: {
      placeholder: 'Rechercher des éléments...',
    },
    view: {
      grid: 'Vue grille',
      table: 'Vue tableau',
    },
    actions: {
      delete: 'Supprimer',
      edit: 'Modifier',
      favorite: 'Favori',
      unfavorite: 'Retirer des favoris',
      view: 'Voir',
      share: 'Partager',
      addToFavorites: 'Ajouter aux favoris',
      removeFromFavorites: 'Retirer des favoris',
    },
    itemCount: '{count} élément | {count} éléments',
    itemsChip: '{count} élément | {count} éléments',
    items: {
      add: 'Ajouter des éléments',
      empty: 'Aucun élément dans ce dossier',
    },
    addItems: {
      company: 'Ajouter une entreprise',
      companyScreen: 'Fiche entreprise',
      companyDescription: 'Créez une fiche entreprise pour suivre les informations',
      watchfile: 'Veille',
      watchfileDescription: 'Configurez une surveillance sur des sujets spécifiques',
      graphrag: 'Cartographie',
      graphragDescription: 'Explorez les connexions et relations',
    },
    header: {
      itemsCount: '{count} élément | {count} éléments',
      createdOn: 'créé le {date}',
      by: 'par',
    },
    grid: {
      created: 'Créé le',
      by: 'par',
      owner: 'Propriétaire :',
    },
    shared: {
      badge: 'Partagé',
    },
    share: {
      title: 'Partager le dossier',
      description: 'Partagez ce dossier avec d\'autres utilisateurs de votre organisation',
      searchLabel: 'Ajouter des personnes',
      searchPlaceholder: 'Rechercher par nom d\'utilisateur ou email...',
      searching: 'Recherche en cours...',
      readOnly: 'Lecture seule',
      alreadyShared: 'Déjà partagé',
      noResults: 'Aucun utilisateur trouvé',
      searchError: 'Échec de la recherche d\'utilisateurs. Vous n\'avez peut-être pas la permission de partager des dossiers.',
      add: 'Ajouter',
      writerDisabledNote: 'Le rôle Éditeur est désactivé car cet utilisateur n\'a que des droits de lecture dans l\'organisation.',
      currentShares: 'Personnes ayant accès',
      loadingShares: 'Chargement...',
      addedOn: 'Ajouté le',
      remove: 'Supprimer l\'accès',
      noShares: 'Ce dossier n\'est partagé avec personne pour le moment',
      reader: 'Lecteur',
      writer: 'Éditeur',
    },
    moveCompany: {
      button: 'Déplacer vers un dossier',
      title: 'Déplacer l\'entreprise vers un dossier',
      selectFolder: 'Sélectionnez un dossier de destination',
      searchPlaceholder: 'Rechercher des dossiers...',
      noFolders: 'Aucun dossier accessible en écriture',
      currentFolder: 'Dossier actuel (ne peut pas être sélectionné)',
      loadError: 'Échec du chargement des dossiers',
      success: 'Entreprise déplacée vers {folderName}',
      error: 'Échec du déplacement de l\'entreprise',
      move: 'Déplacer',
    },
    permissions: {
      owner: 'Propriétaire',
      writer: 'Éditeur',
      reader: 'Lecteur',
    },
    itemTypes: {
      company: 'Fiche entreprise',
    },
    privacy: {
      private: 'Privé',
      shared: 'Partagé',
    },
    owner: {
      you: 'Vous',
    },
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
      color: 'Couleur',
      colors: {
        red: 'Rouge',
        orange: 'Orange',
        amber: 'Ambre',
        yellow: 'Jaune',
        lime: 'Citron vert',
        green: 'Vert',
        emerald: 'Émeraude',
        teal: 'Sarcelle',
        cyan: 'Cyan',
        sky: 'Ciel',
        blue: 'Bleu',
        indigo: 'Indigo',
        violet: 'Violet',
        purple: 'Pourpre',
        fuchsia: 'Fuchsia',
        pink: 'Rose',
        rose: 'Rosé',
        gray: 'Gris',
      },
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
      owner: 'Propriétaire',
      created: 'Créé le',
      actions: 'Actions',
    },
    empty: {
      noResults: 'Aucun résultat trouvé',
      title: 'Ce dossier est vide',
      tryDifferentSearch: 'Essayez avec un autre terme de recherche',
      description: 'Commencez par créer votre première entreprise dans ce dossier',
      readOnly: 'Aucun élément dans ce dossier',
    },
    emptyList: {
      title: 'Aucun dossier pour le moment',
      description: 'Créez votre premier dossier pour organiser vos entreprises',
      descriptionReadOnly: 'Aucun dossier ne vous a encore été partagé',
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
      noRecentProjects: 'Aucun projet récent',
      error: {
        title: 'Impossible de charger les projets récents',
        description: 'Un problème est survenu lors du chargement de vos projets récents. Veuillez réessayer plus tard.',
      },
    },
    recentActivities: {
      title: 'Activités récentes',
      by: 'par {username}',
      actions: {
        createdCompany: 'a créé une nouvelle Carte Entreprise pour',
        createdFolder: 'a créé le Dossier',
      },
      noRecentActivities: 'Aucune activité récente',
      error: {
        title: 'Impossible de charger les activités récentes',
        description: 'Un problème est survenu lors du chargement des activités de l\'organisation. Veuillez réessayer plus tard.',
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
        companyScreen: 'Créer un Screen',
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
      table: {
        row: 'Ligne',
        companyName: "Nom de l'entreprise",
        website: 'Site web',
      },
      validation: {
        title: 'Résultats de la Validation',
        valid: 'Entreprises valides',
        invalid: 'Entreprises invalides',
        validCompanies: '{count} entreprises valides',
        invalidCompanies: '{count} entreprises invalides',
        validCompaniesLabel: 'Entreprises valides',
        invalidCompaniesLabel: 'Entreprises invalides',
        viewDetails: 'Voir les Détails',
        hideDetails: 'Masquer les Détails',
        row: 'Ligne',
        company: 'Entreprise',
        error: 'Erreur',
        errorsTitle: 'Erreurs de validation',
        errorsFound: 'Erreurs de Validation Trouvées',
        errorsFoundMessage:
          "Vous pouvez soit corriger les erreurs dans votre fichier CSV et le télécharger à nouveau, soit continuer l'importation qui ignorera les lignes invalides.",
      },
      tokens: {
        title: 'Utilisation des Jetons',
        required: 'jetons requis',
        tokensRequired: '{count} jetons requis',
        tokensRequiredLabel: 'Jetons requis',
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
        successful: 'Réussies',
        failedLabel: 'Échouées',
        totalProcessed: 'Total traité',
        failedImportsTitle: "Échecs d'importation",
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
    title: 'Aide',
    description: "Trouvez des réponses à vos questions et apprenez à utiliser l'application",
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
    error: {
      loadingContent: "Erreur lors du chargement du contenu d'aide.",
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
  chapseAssist: {
    alert: {
      title: 'Obtenez des recommandations IA personnalisées',
      actionLabel: 'Configurer maintenant',
      dismissLabel: 'Plus tard',
    },
    quickActions: {
      title: 'Chaps-e Smart Assist',
      refresh: 'Actualiser',
      checkingPreferences: 'Vérification des préférences IA...',
      loading: 'Génération des actions personnalisées...',
      tryAgain: 'Réessayer',
      configure: 'Configurer les préférences IA',
      error: {
        title: 'Échec du chargement des actions rapides',
        message:
          "Une erreur s'est produite lors de la génération des actions. Veuillez réessayer.",
        preferencesCheck: 'Échec de la vérification des préférences',
      },
      empty: {
        title: 'Aucune action rapide disponible',
        message: 'Configurez vos préférences IA pour voir des recommandations personnalisées.',
        loadedMessage: 'Impossible de générer des actions rapides pour cette entreprise. Essayez de rafraîchir ou revenez plus tard.',
      },
    },
  },
  aiPreferences: {
    setup: {
      title: 'Configurez votre assistant IA',
      description:
        'Parlez-nous de votre rôle et de vos objectifs pour que nous puissions vous fournir des actions rapides et des recommandations personnalisées adaptées à vos besoins.',
      optional: 'Optionnel',
      fields: {
        role: {
          label: 'Votre rôle',
          placeholder: 'ex. Commercial, Responsable Marketing, Directeur',
          helper: 'Quel est votre rôle professionnel ?',
          required: "Le rôle est requis",
          tooLong: "Le rôle doit contenir moins de 255 caractères",
        },
        goals: {
          label: 'Vos objectifs',
          placeholder:
            'ex. Je veux identifier les entreprises qui bénéficieraient de notre produit et comprendre leurs problématiques',
          helper: "Qu'essayez-vous d'accomplir lors de vos recherches d'entreprises ?",
          required: "Les objectifs sont requis",
          tooLong: "Les objectifs doivent contenir moins de 2000 caractères",
        },
        desiredOutput: {
          label: 'Format de sortie souhaité',
          placeholder:
            'ex. Générer des emails de prospection personnalisés mettant en avant les problématiques avec des références spécifiques à l\'entreprise',
          helper: "Comment souhaitez-vous que l'IA formate ses recommandations ?",
          required: "Le format de sortie souhaité est requis",
          tooLong: "Le format de sortie souhaité doit contenir moins de 2000 caractères",
        },
        documentation: {
          label: 'Documentation produit/service',
          placeholder:
            'ex. Notre produit est une plateforme SaaS B2B qui aide les entreprises à automatiser leurs workflows',
          helper:
            'Décrivez votre produit ou service pour aider à personnaliser les recommandations (optionnel)',
        },
      },
      actions: {
        save: 'Enregistrer et continuer',
        cancel: 'Annuler',
      },
      success: {
        title: 'Succès !',
        message:
          'Vos préférences IA ont été enregistrées. Les actions rapides seront désormais personnalisées selon votre profil.',
      },
      error: {
        title: 'Erreur',
        message: "Échec de l'enregistrement de vos préférences. Veuillez réessayer.",
      },
      help: {
        title: 'Conseils pour de meilleurs résultats',
        tip1: 'Soyez précis sur votre rôle et vos objectifs pour des recommandations plus pertinentes',
        tip2:
          'Décrivez clairement votre format de sortie souhaité pour obtenir des résultats mieux formatés',
        tip3:
          'Incluez les détails du produit pour recevoir des suggestions plus personnalisées et contextuelles',
      },
      validation: {
        formInvalid: "Veuillez corriger les erreurs dans le formulaire",
      },
    },
    settings: {
      title: 'Préférences de l\'assistant IA',
      description: 'Mettez à jour vos préférences IA pour affiner les recommandations personnalisées',
      lastUpdated: 'Dernière mise à jour : {date}',
      notConfigured: 'Non configuré',
      setUpDescription: "Configurez vos préférences d'IA pour activer les actions rapides personnalisées et les recommandations.",
      setUpButton: "Configurer les préférences d'IA",
      actions: {
        edit: 'Modifier les préférences',
        save: 'Enregistrer les modifications',
        cancel: 'Annuler',
      },
      success: {
        title: 'Mise à jour réussie',
        message: 'Vos préférences IA ont été mises à jour avec succès.',
      },
      error: {
        title: 'Échec de la mise à jour',
        message: "Échec de la mise à jour de vos préférences. Veuillez réessayer.",
      },
      messages: {
        loadError: "Échec du chargement de vos préférences. Veuillez réessayer.",
        authError: "Vous devez être connecté pour mettre à jour les préférences IA",
      },
    },
  },
  credits: {
    unit: 'crédits',
    usedCredits: 'crédits utilisés',
    balance: {
      label: 'Solde disponible',
      current: 'Solde actuel',
    },
    usage: {
      title: 'Répartition des crédits',
      total: 'Total consommé',
      noData: 'Aucune consommation pour cette période',
    },
    forecast: {
      title: 'Capacité restante',
    },
    module: {
      disabled: 'Module désactivé',
      costPerItem: '1 {item} = {cost} crédits',
      remaining: '{item} restantes',
      canCreate: 'Vous pouvez encore créer',
      getQuote: 'Obtenir un devis',
      screen: {
        label: 'Fiche entreprise',
        item: 'fiche entreprise',
        itemPlural: 'Fiches entreprises',
      },
      target: {
        label: 'Veille',
        item: 'veille',
        itemPlural: 'Veilles',
      },
      explore: {
        label: 'Cartographie',
        item: 'cartographie',
        itemPlural: 'Cartographies',
      },
    },
    modules: {
      all: 'Tous',
      screen: 'Screen',
      target: 'Target',
      explore: 'Explore',
    },
    period: {
      select: 'Période',
      '7d': '7 jours',
      '30d': '30 jours',
      '90d': '90 jours',
      all: 'Tout',
      custom: 'Personnalisé',
      startDate: 'Début',
      endDate: 'Fin',
    },
    topUsers: {
      title: 'Classement des utilisateurs',
      rank: 'Rang',
      user: 'Utilisateur',
      credits: 'Crédits',
      search: 'Rechercher...',
      noData: 'Aucun utilisateur trouvé',
      itemName: 'utilisateurs',
    },
    dailyUsage: {
      title: 'Consommation quotidienne',
      noData: 'Aucune consommation pour cette période',
    },
  },
  breadcrumb: {
    companies: 'Entreprises',
    folders: 'Dossiers',
    team: 'Équipe',
    admin: 'Admin',
    settings: 'Paramètres',
    search: 'Recherche',
    profile: 'Profil',
    jobs: 'Emplois',
    timeline: 'Historique',
    products: 'Produits',
    press: 'Presse',
    edit: 'Modifier',
    create: 'Créer',
    company: 'Entreprise',
    organizations: 'Organisations',
    costs: 'Coûts',
    appearance: 'Apparence',
    security: 'Sécurité',
    credits: 'Crédits',
  },
  logout: {
    title: 'Confirmer la déconnexion',
    subtitle: 'Cette action mettra fin à votre session',
    message: 'Êtes-vous sûr de vouloir vous déconnecter de votre compte ?',
    cancel: 'Annuler',
    confirm: 'Se déconnecter',
    tooltip: 'Se déconnecter',
  },
  pagination: {
    show: 'Afficher :',
    page: 'Page :',
    of: 'sur',
    displaying: 'Affichage de {start} à {end} sur {total} {itemName}',
  },
}
