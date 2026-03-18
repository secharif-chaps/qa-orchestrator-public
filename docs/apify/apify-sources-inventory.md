# Inventaire des sources Apify pour ChapsMind Target

> Epic : [TAR-1109](https://chapsvisiondev.atlassian.net/browse/TAR-1109) — Apify Collecteur web avancé
> Dépend de : [TAR-1148](https://chapsvisiondev.atlassian.net/browse/TAR-1148) — Architecture multi-provider
> Date : 2026-03-06

## Sommaire

- [Contexte](#contexte)
- [Inventaire par domaine](#inventaire-par-domaine)
  - [Réseaux sociaux](#réseaux-sociaux)
  - [Presse et News](#presse--news)
  - [Web et Sites](#web--sites)
  - [E-réputation et Avis](#e-réputation--avis-nouveau-domaine)
  - [Tendances et Concurrentiel](#tendances--concurrentiel-nouveau-domaine)
  - [RH et Emploi](#rh--emploi)
  - [Brevets et Propriété intellectuelle](#brevets--propriété-intellectuelle)
  - [Juridique et Réglementaire](#juridique--réglementaire)
  - [Académique et Scientifique](#académique--scientifique)
  - [Vidéo et Média](#vidéo--média)
  - [Divers et Spécialisé](#divers--spécialisé)
- [Récapitulatif chiffré](#récapitulatif-chiffré)
- [Impact sur les stories existantes](#impact-sur-les-stories-existantes)

---

## Contexte

ChapsMind Target dispose aujourd'hui de 52 types de sources, toutes collectées via le provider Bakus.
L'intégration d'Apify comme second provider (TAR-1109) ouvre la possibilité de :

1. **Remplacer** des collecteurs Bakus défaillants ou instables (Nitter/Twitter, Reddit)
2. **Compléter** des sources existantes comme fallback (YouTube, Facebook, NewsAPI)
3. **Ajouter** des sources entièrement nouvelles (TikTok, e-réputation, tendances, juridique FR)

Ce document inventorie l'ensemble des sources à considérer, leur priorité, et l'actor Apify correspondant.

### Légende

| Priorité | Signification |
|----------|---------------|
| **P1** | Indispensable — source critique ou manque majeur dans l'offre |
| **P2** | Important — complète significativement la couverture de veille |
| **P3** | Optionnel — niche ou couvert correctement par Bakus |
| **Déconseillé** | Pas d'investissement — plateforme morte, marginale, ou ROI négatif |

---

## Inventaire par domaine

### Réseaux sociaux

| Prio | Source | Actor Apify | V9 (utilisations) | Type de veille | Intérêt |
|------|--------|-------------|---------------------|----------------|---------|
| **P1** | `twitter` | `apidojo/tweet-scraper` | Oui (9 305) | Social media | Nitter est instable/en fin de vie. Plus grosse source sociale, risque de panne critique. Apify comme relève indispensable |
| **P1** | `linkedin` | `curious_coder/linkedin-profile-scraper` + `bebity/linkedin-premium-actor` | Oui (337) | Social media / RH | Pas d'API officielle. Apify seul moyen scalable. Profils + pages entreprises |
| **P1** | `facebook` | `apify/facebook-posts-scraper` + `apify/facebook-pages-scraper` | Oui (643) | Social media | Complément à l'API Graph qui a des limites strictes. Posts + pages + groupes |
| **P1** | `tiktok` | `clockworks/tiktok-scraper` | **Non** | Social media | Réseau majeur (1.5Md users) totalement absent. Incontournable pour veille social media moderne |
| **P1** | `youtube` | `streamers/youtube-scraper` + `streamers/youtube-comments-scraper` | Oui (883) | Social media / Média | Backup quota API Google. L'actor commentaires ajoute l'analyse de sentiment impossible via API officielle |
| **P2** | `threads` | `apify/threads-scraper` | Oui (0) | Social media | 0 utilisation = collecteur absent ou cassé. Meta Threads en forte croissance, remplacement naturel de Twitter pour certains users |
| **P2** | `bluesky` | `apify/bluesky-scraper` | Oui (1) | Social media | En croissance rapide (25M+ users). 1 seule utilisation = collecteur embryonnaire. API ouverte aussi, Apify en backup |
| **P2** | `reddit` | `trudax/reddit-scraper` | Oui (31) | Social media / Forum | 31 utilisations anormalement bas. Reddit a restreint son API en 2023. Apify contourne cette limitation |
| **P2** | `telegram` | — | Oui (697) | Social media / Messagerie | Pas d'actor Apify pertinent. Telegram Bot API robuste, Bakus reste adapté. **Pas de migration** |
| **P2** | `facebook_ads` | `apify/facebook-ads-scraper` | **Non** | Social media / Concurrentiel | Accès Meta Ad Library = stratégie pub concurrents. Très différenciant, données inaccessibles autrement |
| **P3** | `mastodon` | — | Oui (16) | Social media | API ouverte et stable, Bakus suffit. Pas d'actor Apify nécessaire. **Pas de migration** |
| **P3** | `vkontact` | — | Oui (25) | Social media | Niche (marché russe). Pas d'actor Apify fiable. Bakus suffit. **Pas de migration** |
| **P3** | `truth_social` | — | Oui (7) | Social media | Niche politique US. Pas d'actor Apify. **Pas de migration** |
| Déconseillé | `gab` | — | Oui (9) | Social media | Plateforme marginale, en déclin. Pas d'actor. Pas d'investissement |
| Déconseillé | `parler` | — | Oui (0) | Social media | Plateforme quasi morte (fermée 2023, relancée en mode minimal). 0 utilisation. À supprimer du catalogue |
| Déconseillé | `weibo` | — | Oui (0) | Social media | Marché chinois, géo-bloqué, 0 utilisation. Complexité disproportionnée |

### Presse & News

| Prio | Source | Actor Apify | V9 (utilisations) | Type de veille | Intérêt |
|------|--------|-------------|---------------------|----------------|---------|
| **P1** | `google_news` | `lhotanova/google-news-scraper` | Oui (680) | Presse | Actor bien maintenu, complément/remplacement du collecteur actuel |
| **P1** | `newsapi` | `lhotanova/google-news-scraper` | Oui (1 313) | Presse | NewsAPI a des quotas stricts (free: 100 req/jour). Apify comme fallback quand quota atteint |
| **P2** | `bing_news` | `piotrv1001/bing-news-scraper` | **Non** | Presse | Couverture complémentaire à Google News. Sources différentes, biais différent |
| **P3** | `aylien` | — | Oui (5) | Presse | Service NLP/News payant. 5 utilisations. Pas d'actor. **Pas de migration** |
| **P3** | `belgapress` | — | Oui (6) | Presse | Agence presse belge. API propriétaire. **Pas de migration** |
| **P3** | `gopress` | — | Oui (14) | Presse | Presse belge/néerlandophone. API propriétaire. **Pas de migration** |
| **P3** | `factiva` | — | Oui (44) | Presse | Dow Jones/Reuters sous licence. **Pas de migration** |
| **P3** | `lexisnexis` | — | Oui (14) | Presse | Base juridique/presse sous licence. **Pas de migration** |

### Web & Sites

| Prio | Source | Actor Apify | V9 (utilisations) | Type de veille | Intérêt |
|------|--------|-------------|---------------------|----------------|---------|
| **P1** | `link / rss / site` | `apify/website-content-crawler` | Oui (73 455) | Web | Source n°1 de Target. L'actor gère le JS rendering (Cheerio/Playwright) que Bakus peut rater |
| **P1** | `website_changes` | `tri_angle/website-changes-detector` | **Non** | Web / Concurrentiel | **Très différenciant**. Détecte changements de prix, messaging, produits sur sites concurrents. Aucune source équivalente |
| **P2** | `rss_feeds` | `jupri/rss-xml-scraper` | (inclus dans link/rss) | Web | Parser RSS avancé en complément. Utile si parser Bakus échoue sur flux complexes |
| **P2** | `page` | `apify/website-content-crawler` | Oui (863) | Web | Même actor que link/rss. Page unique = crawl depth 0 |
| **P3** | `page_slicing` | — | Oui (15) | Web | Extraction de section de page. Trop spécifique pour Apify. **Pas de migration** |
| **P3** | `page_uniq` | — | Oui (4) | Web | Variante page. 4 utilisations. **Pas de migration** |
| **P3** | `json` | — | Oui (573) | Web / Technique | Ingestion JSON structuré. Pas un cas de scraping. **Pas de migration** |
| **P3** | `ftp` | — | Oui (1) | Technique | Protocole FTP. Pas un cas Apify. **Pas de migration** |
| Déconseillé | `google_images` | — | Oui (6) | Web | 6 utilisations. Scraping fragile et peu utile pour veille texte |
| Déconseillé | `serpAPI` | `apify/google-search-scraper` | Oui (1) | Web / SEO | 1 utilisation. SerpAPI est un service payant. Remplacé par `google_serp` si besoin |

### E-réputation & Avis (nouveau domaine)

| Prio | Source | Actor Apify | V9 (utilisations) | Type de veille | Intérêt |
|------|--------|-------------|---------------------|----------------|---------|
| **P1** | `trustpilot` | `automation-lab/trustpilot` | **Non** | E-réputation | **Pan entier absent** de Target. 300M+ avis, standard e-réputation B2C. ~$0.20/1K avis |
| **P1** | `google_reviews` | `compass/google-maps-reviews-scraper` | **Non** | E-réputation | Avis Google Maps/Business. Veille locale concurrents. Sentiment analysis intégré |
| **P2** | `glassdoor_reviews` | `memo23/apify-glassdoor-reviews-scraper` | **Non** | E-réputation / RH | Marque employeur concurrents. Salaires, satisfaction, turnover signals |
| **P2** | `amazon_reviews` | `junglee/amazon-reviews-scraper` | **Non** | E-réputation / Produit | Pertinent si clients font veille produit e-commerce |
| **P3** | `app_store_reviews` | `epctex/appstore-scraper` + `curious_coder/google-play-scraper` | **Non** | E-réputation / Produit | Avis apps mobiles. Niche mais pertinent pour éditeurs logiciels |

### Tendances & Concurrentiel (nouveau domaine)

| Prio | Source | Actor Apify | V9 (utilisations) | Type de veille | Intérêt |
|------|--------|-------------|---------------------|----------------|---------|
| **P1** | `google_trends` | `apify/google-trends-scraper` | **Non** | Tendances | Détection signaux faibles, comparaison intérêt marques, prédiction IA. Transversal |
| **P2** | `crunchbase` | `curious_coder/crunchbase-scraper` | **Non** | Concurrentiel / Finance | Levées de fonds, investisseurs, acquisitions, tech stack. 104 champs. Veille startup |
| **P2** | `google_serp` | `apify/google-search-scraper` | **Non** | Concurrentiel / SEO | Suivi positionnement SEO, ads, People Also Ask. Remplace `serpAPI` (1 utilisation) |
| **P3** | `yahoo_finance` | `harvest/yahoo-finance-scraper` | **Non** | Finance | Cours, résultats financiers, analystes. Niche veille financière |
| **P3** | `product_hunt` | `runtime/producthunt-scraper` | **Non** | Innovation | Lancements produits, tendances tech. Niche startup/innovation |
| **P3** | `hacker_news` | `epctex/hackernews-scraper` | **Non** | Tech / Innovation | Discussions tech, produits émergents. Niche communauté dev/startup |

### RH & Emploi

| Prio | Source | Actor Apify | V9 (utilisations) | Type de veille | Intérêt |
|------|--------|-------------|---------------------|----------------|---------|
| **P2** | `indeed` | `misceres/indeed-scraper` | Oui (14) | RH / Concurrentiel | 14 utilisations = collecteur limité. Recrutements concurrents = signaux stratégiques |
| **P2** | `linkedin_jobs` | `curious_coder/linkedin-jobs-scraper` | **Non** | RH / Concurrentiel | LinkedIn = plateforme recrutement n°1. Détecte orientations stratégiques concurrents |
| **P3** | `glassdoor_jobs` | `bebity/glassdoor-jobs-scraper` | **Non** | RH | Complément Indeed/LinkedIn avec infos salaires |

### Brevets & Propriété intellectuelle

| Prio | Source | Actor Apify | V9 (utilisations) | Type de veille | Intérêt |
|------|--------|-------------|---------------------|----------------|---------|
| **P2** | `google_patents` | `mick-johnson/google-patents-scraper` | **Non** | PI / Brevets | Agrège 100+ offices (USPTO, EPO, WIPO, CNIPA). Complément à `espacenet` (333) et `patentscope` (16) |
| **P2** | `inpi` | `lexis-solutions/data-inpi-fr-scraper` | **Non** | PI / Marques | Marques et designs français. Pas couvert par espacenet/patentscope |
| **P3** | `espacenet` | — | Oui (333) | PI / Brevets | Bakus ok. Google Patents couvre aussi l'EPO. **Pas de migration** |
| **P3** | `patentscope` | — | Oui (16) | PI / Brevets | WIPO direct. Bakus ok. **Pas de migration** |
| Déconseillé | `questel` | — | Oui (0) | PI / Brevets | 0 utilisation. Plateforme payante sous licence. Probablement inutilisé |

### Juridique & Réglementaire

| Prio | Source | Actor Apify | V9 (utilisations) | Type de veille | Intérêt |
|------|--------|-------------|---------------------|----------------|---------|
| **P2** | `boamp` | `lexis-solutions/boamp-fr-scraper` | **Non** | Marchés publics FR | Appels d'offres publics français. Essentiel pour veille B2G |
| **P2** | `bodacc` | `lexis-solutions/bodacc-fr-scraper` | **Non** | Juridique FR | Annonces légales : insolvabilités, créations, cessions. Veille risque fournisseurs/concurrents |
| **P2** | `legifrance` | `nlp_data_lni/legifrance-scraper` | **Non** | Réglementaire FR | Nouveaux textes de loi, décrets. Veille réglementaire sectorielle |
| **P2** | `ted_europa` | `stagsz/ted-tender-crawler` | Oui (97) | Marchés publics UE | Complément/remplacement du collecteur actuel. Actor avec scoring intelligent |
| **P3** | `eurlex` | — | Oui (13) | Réglementaire UE | Bakus ok. **Pas de migration** |
| **P3** | `journal_officiel` | — | Oui (15) | Réglementaire FR | Bakus ok via scraping site JORF. **Pas de migration** |
| **P3** | `assemblee_nationale` | — | Oui (17) | Politique FR | Bakus ok. Niche parlementaire. **Pas de migration** |
| **P3** | `senat` | — | Oui (7) | Politique FR | Bakus ok. Niche. **Pas de migration** |
| **P3** | `france_diplomatie` | — | Oui (13) | Géopolitique | Bakus ok. Site institutionnel simple. **Pas de migration** |

### Académique & Scientifique

| Prio | Source | Actor Apify | V9 (utilisations) | Type de veille | Intérêt |
|------|--------|-------------|---------------------|----------------|---------|
| **P2** | `google_scholar` | `marco.gullo/google-scholar-scraper` | **Non** | Scientifique | Complément à `scopus` (103) et `web_of_science` (9). Plus large, gratuit, citations |
| **P3** | `scopus` | — | Oui (103) | Scientifique | Elsevier sous licence. Bakus ok. **Pas de migration** |
| **P3** | `openaccess` | — | Oui (9) | Scientifique | Open access repos. Bakus ok. **Pas de migration** |
| **P3** | `ieeexplore` | — | Oui (16) | Scientifique / Tech | IEEE sous licence. Bakus ok. **Pas de migration** |
| **P3** | `wiley` | — | Oui (11) | Scientifique | Éditeur académique sous licence. Bakus ok. **Pas de migration** |
| **P3** | `web_of_science` | — | Oui (9) | Scientifique | Clarivate sous licence. Bakus ok. **Pas de migration** |
| Déconseillé | `cordis` | — | Oui (0) | Scientifique / EU | 0 utilisation. Projets R&D UE. Trop niche |

### Vidéo & Média

| Prio | Source | Actor Apify | V9 (utilisations) | Type de veille | Intérêt |
|------|--------|-------------|---------------------|----------------|---------|
| **P3** | `dailymotion` | — | Oui (9) | Média / Vidéo | 9 utilisations. Plateforme en déclin. Bakus ok. **Pas de migration** |
| Déconseillé | `odysee` | — | Oui (0) | Média / Vidéo | 0 utilisation. Plateforme très niche. Pas d'investissement |

### Divers & Spécialisé

| Prio | Source | Actor Apify | V9 (utilisations) | Type de veille | Intérêt |
|------|--------|-------------|---------------------|----------------|---------|
| **P3** | `gmail` | — | Oui (69) | Interne | Ingestion email. Pas un cas scraping. Bakus/API Gmail ok. **Pas de migration** |
| **P3** | `wikipedia` | — | Oui (7) | Référence | API Mediawiki ouverte. Bakus ok. **Pas de migration** |
| Déconseillé | `geoconfirmed` | — | Oui (2) | OSINT / Géo | 2 utilisations. Ultra niche (géolocalisation conflits). Pas d'investissement |
| Déconseillé | `mobilize` | — | Oui (1) | Activisme | 1 utilisation. Plateforme marginale |
| Déconseillé | `world_check` | — | Oui (0) | Conformité | 0 utilisation. Refinitiv World-Check sous licence. Pas un cas Apify |

---

## Récapitulatif chiffré

### Par priorité

| Priorité | Sources V9 améliorées | Nouvelles sources | Total |
|----------|----------------------|-------------------|-------|
| **P1** | 7 | 5 | **12** |
| **P2** | 4 | 12 | **16** |
| **P3** | 0 | 6 | **6** |
| Déconseillé | 7 | 0 | **7** |
| Pas de migration (Bakus ok) | 18 | 0 | **18** |

### Par domaine (sources à intégrer Apify, hors "pas de migration")

| Domaine | P1 | P2 | P3 | Total |
|---------|----|----|----|----|
| Réseaux sociaux | 5 | 4 | 0 | 9 |
| Presse & News | 2 | 1 | 0 | 3 |
| Web & Sites | 2 | 2 | 0 | 4 |
| E-réputation (nouveau) | 2 | 2 | 1 | 5 |
| Tendances (nouveau) | 1 | 2 | 3 | 6 |
| RH & Emploi | 0 | 2 | 1 | 3 |
| Brevets & PI | 0 | 2 | 0 | 2 |
| Juridique & Réglementaire | 0 | 4 | 0 | 4 |
| Académique | 0 | 1 | 0 | 1 |
| **Total** | **12** | **20** | **5** | **37** |

### Actors Apify uniques nécessaires

| Actor ID | Sources couvertes |
|----------|------------------|
| `apidojo/tweet-scraper` | twitter |
| `curious_coder/linkedin-profile-scraper` | linkedin (profils) |
| `bebity/linkedin-premium-actor` | linkedin (entreprises) |
| `apify/facebook-posts-scraper` | facebook (posts) |
| `apify/facebook-pages-scraper` | facebook (pages) |
| `apify/facebook-ads-scraper` | facebook_ads |
| `clockworks/tiktok-scraper` | tiktok |
| `streamers/youtube-scraper` | youtube (vidéos) |
| `streamers/youtube-comments-scraper` | youtube (commentaires) |
| `apify/threads-scraper` | threads |
| `apify/bluesky-scraper` | bluesky |
| `trudax/reddit-scraper` | reddit |
| `lhotanova/google-news-scraper` | google_news, newsapi |
| `piotrv1001/bing-news-scraper` | bing_news |
| `apify/website-content-crawler` | link/rss/site, page |
| `tri_angle/website-changes-detector` | website_changes |
| `jupri/rss-xml-scraper` | rss_feeds |
| `automation-lab/trustpilot` | trustpilot |
| `compass/google-maps-reviews-scraper` | google_reviews |
| `memo23/apify-glassdoor-reviews-scraper` | glassdoor_reviews |
| `junglee/amazon-reviews-scraper` | amazon_reviews |
| `epctex/appstore-scraper` | app_store (iOS) |
| `curious_coder/google-play-scraper` | app_store (Android) |
| `apify/google-trends-scraper` | google_trends |
| `curious_coder/crunchbase-scraper` | crunchbase |
| `apify/google-search-scraper` | google_serp |
| `harvest/yahoo-finance-scraper` | yahoo_finance |
| `runtime/producthunt-scraper` | product_hunt |
| `epctex/hackernews-scraper` | hacker_news |
| `misceres/indeed-scraper` | indeed |
| `curious_coder/linkedin-jobs-scraper` | linkedin_jobs |
| `bebity/glassdoor-jobs-scraper` | glassdoor_jobs |
| `mick-johnson/google-patents-scraper` | google_patents |
| `lexis-solutions/data-inpi-fr-scraper` | inpi |
| `lexis-solutions/boamp-fr-scraper` | boamp |
| `lexis-solutions/bodacc-fr-scraper` | bodacc |
| `nlp_data_lni/legifrance-scraper` | legifrance |
| `stagsz/ted-tender-crawler` | ted_europa |
| `marco.gullo/google-scholar-scraper` | google_scholar |

**Total : 39 actors Apify** couvrant 37 sources.

---

## Impact sur les stories existantes

### Stories actuelles (TAR-1257 à TAR-1264)

Les 8 stories existantes constituent le **socle technique** de l'intégration Apify.
Elles restent valides mais certaines doivent être mises à jour pour refléter le périmètre élargi :

| Story | Titre | Impact |
|-------|-------|--------|
| TAR-1257 | Client HTTP Apify et authentification | **Inchangée** — le client est générique |
| TAR-1258 | ApifyProviderGateway | **Inchangée** — les 4 méthodes sont génériques |
| TAR-1259 | StatusMapper et routing provider | **À mettre à jour** — le routing initial ne couvre que 6 SourceTypes. Préciser que c'est la config initiale, les sources additionnelles seront ajoutées par lot dans les US dédiées |
| TAR-1260 | Endpoint webhook callbacks | **Inchangée** — le webhook est générique |
| TAR-1261 | Handlers de données (dataset → Documents) | **À mettre à jour** — ne prévoit que 3 normalizers (Google News, LinkedIn, Website Crawler). Préciser que c'est le lot initial + l'architecture extensible (interface + resolver). Les normalizers additionnels viendront avec les US d'ajout de sources |
| TAR-1262 | Input templates actors | **À mettre à jour** — ne couvre que 3 actors. Même logique : lot initial + extensibilité. Nouveaux templates ajoutés par lot |
| TAR-1263 | Tracking des coûts | **Inchangée** — le tracking est générique par run |
| TAR-1264 | Tests et documentation | **À compléter** — ajouter la documentation du process d'ajout d'une nouvelle source (normalizer + template + routing + N8N) |

### Nouvelles stories nécessaires

Chaque lot de nouvelles sources nécessite :

1. **Actor mapping** : SourceType → actorId dans `apify_provider.yaml`
2. **Input template** : Configuration JSON d'input par actor dans `apify_provider.yaml`
3. **Normalizer** : Transformation dataset items → Document (1 par actor)
4. **Routing** : Ajout dans `provider_routing.yaml` (Apify au lieu de Bakus)
5. **SourceType enum** : Ajout du cas dans `SourceType.php` (pour les nouvelles sources)
6. **Workflow N8N** : Mise à jour des références de sources dans les workflows N8N qui utilisent les SourceTypes (notamment le workflow orchestrator et le workflow watchfile-builder)
7. **Tests** : Tests unitaires du normalizer + test d'intégration du flow

### Point d'attention N8N

Les workflows N8N suivants référencent les types de sources et doivent être mis à jour à chaque ajout :

- `3-tool-watchfile-builder.json` — Propose les sources disponibles lors de la création de WatchFile
- `5-tool-classify-watchfile.json` — Classification par type de source
- `1-orchestrator-router.json` — Routing selon le type de source

Chaque story d'ajout de source doit inclure un critère d'acceptation : **"Les workflows N8N référençant les SourceTypes sont mis à jour"**.
