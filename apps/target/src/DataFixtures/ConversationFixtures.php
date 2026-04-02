<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageRole;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ConversationFixtures extends Fixture implements DependentFixtureInterface
{
    public const CONVERSATION_REFERENCE = 'conversation_';

    public function getDependencies(): array
    {
        return [WatchFileFixtures::class, UserFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        // Get existing watch files
        $watchFiles = [];
        for ($i = 0; $i < 5; ++$i) {
            $watchFiles[] = $this->getReference(WatchFileFixtures::WATCHFILE_REFERENCE . $i, WatchFile::class);
        }

        // Get the Basil user to use as message author
        $basilUser = $this->getReference(UserFixtures::BASIL_USER_REFERENCE, User::class);

        // Create conversations for each watch file with ping-pong messages
        foreach ($watchFiles as $index => $watchFile) {
            $conversation = $this->createConversationWithMessages($watchFile, $index, $basilUser);
            $manager->persist($conversation);
            $this->addReference(self::CONVERSATION_REFERENCE . $index, $conversation);
        }

        $manager->flush();
    }

    private function createConversationWithMessages(WatchFile $watchFile, int $index, User $author): Conversation
    {
        $conversation = new Conversation($watchFile);
        $conversation->setTitle($this->getConversationTitle($index));
        $conversation->setLanguage(0 === $index % 2 ? 'fr' : 'en');

        // Create ping-pong style messages
        $messages = $this->getPingPongMessages($index);

        $createDate = \DateTimeImmutable::createFromInterface($watchFile->getCreatedAt());

        foreach ($messages as $index => $messageData) {
            $message = new Message();
            $message->setRole($messageData['role']);
            $message->setTextContent($messageData['content']);
            $message->setConversation($conversation);
            $message->setCreatedAt($createDate->modify('+' . $index . ' minutes'));

            if (MessageRole::User === $messageData['role']) {
                $message->setCreatedBy($author);
            }

            // Add some metadata for model messages
            if (MessageRole::Model === $messageData['role']) {
                $message->setMetadata([
                    'modelName' => 'gemini-pro',
                    'usage' => [
                        'promptTokens' => rand(50, 200),
                        'completionTokens' => rand(100, 500),
                        'totalTokens' => rand(150, 700),
                    ],
                ]);
            }

            // Add metadata for system messages
            if (MessageRole::System === $messageData['role']) {
                $message->setMetadata([
                    'trigger' => 'reference_subject_updated',
                ]);
            }

            $conversation->addMessage($message);
        }

        return $conversation;
    }

    private function getConversationTitle(int $index): string
    {
        $titles = [
            'Analyse des tendances nucléaires',
            'Veille santé connectée',
            'Marché véhicules électriques',
            'Concurrence Market Intelligence',
            'Monitoring Labels Employeur Pharma/Cosmétique',
        ];

        return $titles[$index] ?? 'Conversation ' . ($index + 1);
    }

    /**
     * Returns an array of message data for simulating conversations.
     * Each message contains a role (user or model) and content.
     *
     * @return array<int, array{role: MessageRole, content: string}> Array of message data with role and content
     */
    private function getPingPongMessages(int $index): array
    {
        $messageSets = [
            // French conversation (index 0)
            [
                [
                    'role' => MessageRole::User,
                    'content' => 'Bonjour, pouvez-vous m\'aider à analyser les dernières évolutions dans le secteur nucléaire ?',
                ],
                [
                    'role' => MessageRole::System,
                    'content' => 'Sujet de référence mis à jour.',
                ],
                [
                    'role' => MessageRole::Model,
                    'content' => 'Bonjour ! Je serais ravi de vous aider à analyser les évolutions du secteur nucléaire. Je vais examiner les données disponibles et identifier les tendances clés, les acteurs majeurs et les développements récents qui pourraient impacter ce secteur.',
                ],
                [
                    'role' => MessageRole::User,
                    'content' => 'Parfait, pouvez-vous me donner un aperçu des principaux acteurs à surveiller ?',
                ],
                [
                    'role' => MessageRole::Model,
                    'content' => 'Bien sûr ! Voici les principaux acteurs du secteur nucléaire à surveiller :\n\n**Énergéticiens :** EDF, Engie, RWE, Vattenfall\n**Constructeurs :** Framatome, Westinghouse, GE Hitachi\n**Régulateurs :** ASN, IRSN, Commission européenne\n**Recherche :** CEA, CNRS, universités spécialisées\n\nJe recommande de surveiller particulièrement leurs annonces, projets et positions réglementaires.',
                ],
                [
                    'role' => MessageRole::User,
                    'content' => 'Merci ! Et quelles sont les tendances technologiques émergentes ?',
                ],
                [
                    'role' => MessageRole::Model,
                    'content' => 'Excellente question ! Les tendances technologiques émergentes incluent :\n\n**SMR (Small Modular Reactors)** : Développement de réacteurs plus petits et modulaires\n**Fusion nucléaire** : Progrès dans ITER et projets privés\n**Génération IV** : Réacteurs à neutrons rapides et haute température\n**Cycles du combustible** : Recyclage et gestion des déchets\n\nCes innovations pourraient transformer le paysage énergétique dans les 10-15 prochaines années.',
                ],
                [
                    'role' => MessageRole::Model,
                    'content' => "Parfait, je vous propose maintenant une **approche de veille structurée** pour le secteur nucléaire.\n\nVoici les sources clés que je recommande d'ajouter à votre dossier :\n\n1. *World Nuclear News (WNN)*\n   - Publication officielle du secteur avec des mises à jour quotidiennes.\n   - Couvre les évolutions réglementaires, les nouveaux projets et les décisions politiques.\n   - Particulièrement fort sur les **développements SMR** et les accords internationaux.\n\n2. *Agence Internationale de l'Énergie Atomique (AIEA)*\n   - Communiqués de presse et rapports techniques.\n   - Essentiel pour suivre :\n     - Les mises à jour des normes de sûreté\n     - Les engagements des États membres\n     - Les évaluations technologiques nucléaires\n\n3. *Société Nucléaire Européenne & Foratom*\n   - Développements politiques au niveau européen.\n   - Utile pour surveiller :\n     - Les réglementations sur la taxonomie\n     - Les programmes de financement (Euratom)\n     - Les projets transfrontaliers\n\n4. *Salles de presse des entreprises* (sources directes) :\n   - *EDF* – Actualités du parc nucléaire français\n   - *Framatome* – Nouvelles de fabrication de composants\n   - *NuScale Power* – Leader du développement SMR\n   - *Westinghouse* – AP1000 et services combustible\n\nVoulez-vous que j'ajoute ces sources maintenant, ou préférez-vous **prioriser certaines catégories** (politique vs. technologie vs. entreprises) ?",
                ],
            ],
            // English conversation (index 1)
            [
                [
                    'role' => MessageRole::User,
                    'content' => 'Hi, I need help analyzing opportunities in the connected health sector. Can you assist me?',
                ],
                [
                    'role' => MessageRole::System,
                    'content' => 'Subject reference updated.',
                ],
                [
                    'role' => MessageRole::Model,
                    'content' => 'Hello! I\'d be happy to help you analyze opportunities in the connected health sector. I\'ll examine the available data to identify key trends, major players, and recent developments that could impact this market.',
                ],
                [
                    'role' => MessageRole::User,
                    'content' => 'Great! What are the main market segments we should focus on?',
                ],
                [
                    'role' => MessageRole::Model,
                    'content' => 'Excellent question! The main market segments to focus on include:\n\n**Wearable Devices** : Smartwatches, fitness trackers, medical sensors\n**Telemedicine Platforms** : Remote consultation and monitoring solutions\n**Health Data Analytics** : AI-powered insights and predictive analytics\n**Regulatory Compliance** : GDPR, HIPAA, and medical device regulations\n\nI recommend monitoring these segments for emerging opportunities and competitive intelligence.',
                ],
                [
                    'role' => MessageRole::User,
                    'content' => 'What about regulatory challenges in this sector?',
                ],
                [
                    'role' => MessageRole::Model,
                    'content' => 'Regulatory challenges are indeed significant in connected health:\n\n**Data Protection** : GDPR compliance for health data processing\n**Medical Device Regulations** : CE marking, FDA approval requirements\n**Interoperability Standards** : HL7 FHIR, DICOM for data exchange\n**Cybersecurity** : Protection against data breaches and cyber attacks\n\nUnderstanding these regulations is crucial for market entry and product development.',
                ],
                [
                    'role' => MessageRole::Model,
                    'content' => "Let me now propose a **comprehensive monitoring strategy** for connected health opportunities.\n\nHere are the key sources I recommend for your watch file:\n\n1. *MobiHealthNews*\n   - Leading digital health news platform.\n   - Strong coverage of:\n     - Startup funding rounds and acquisitions\n     - FDA clearances for digital therapeutics\n     - Partnerships between tech giants and healthcare providers\n\n2. *Rock Health Reports*\n   - Quarterly investment analyses and trend reports.\n   - Essential for tracking:\n     - Venture capital flows in digital health\n     - Emerging therapeutic areas\n     - Market size projections\n\n3. *Healthcare IT News & HIMSS*\n   - Enterprise health IT developments.\n   - Key topics include:\n     - EHR integration trends\n     - Interoperability mandates\n     - Hospital system digital strategies\n\n4. *Company blogs and newsrooms* (direct sources):\n   - *Apple Health* – HealthKit and Apple Watch updates\n   - *Google Health* – AI diagnostics and Fitbit integration\n   - *Philips Healthcare* – Connected care solutions\n   - *Teladoc / Amwell* – Telehealth market leaders\n\nWould you like me to add these sources now, or should we **focus on specific segments first** (wearables vs. telehealth vs. data analytics)?",
                ],
            ],
            // French conversation (index 2)
            [
                [
                    'role' => MessageRole::User,
                    'content' => 'Salut ! Je travaille sur le marché des véhicules électriques. Peux-tu m\'aider à analyser les tendances ?',
                ],
                [
                    'role' => MessageRole::System,
                    'content' => 'Sujet de référence mis à jour.',
                ],
                [
                    'role' => MessageRole::Model,
                    'content' => 'Salut ! Bien sûr, je peux vous aider à analyser les tendances du marché des véhicules électriques. Je vais examiner les données disponibles pour identifier les évolutions clés, les acteurs majeurs et les développements récents.',
                ],
                [
                    'role' => MessageRole::User,
                    'content' => 'Parfait ! Quels sont les principaux constructeurs à surveiller en Europe ?',
                ],
                [
                    'role' => MessageRole::Model,
                    'content' => 'Voici les principaux constructeurs européens à surveiller :\n\n**Allemands :** Volkswagen Group, BMW, Mercedes-Benz, Audi\n**Français :** Renault, Peugeot (Stellantis), Citroën\n**Suédois :** Volvo, Polestar\n**Nouveaux entrants :** Tesla, Lucid, Rivian\n\nJe recommande de surveiller leurs stratégies de batteries, autonomie et infrastructure de recharge.',
                ],
                [
                    'role' => MessageRole::User,
                    'content' => 'Et qu\'en est-il des technologies de batteries émergentes ?',
                ],
                [
                    'role' => MessageRole::Model,
                    'content' => 'Les technologies de batteries émergentes incluent :\n\n**Batteries à semi-conducteurs** : Plus de densité énergétique et sécurité\n**Batteries sodium-ion** : Alternative moins chère au lithium\n**Batteries à charge ultra-rapide** : Technologies de 800V et plus\n**Recyclage avancé** : Récupération des matériaux critiques\n\nCes innovations pourraient révolutionner l\'adoption des véhicules électriques.',
                ],
                [
                    'role' => MessageRole::Model,
                    'content' => "Parfait, je te propose maintenant une **stratégie de veille complète** pour le marché des véhicules électriques.\n\nVoici les sources clés que je recommande pour ton dossier :\n\n1. *Electrek*\n   - Site de référence pour l'actualité EV et énergie propre.\n   - Couverture forte sur :\n     - Les annonces Tesla et nouveaux modèles\n     - Les infrastructures de recharge\n     - Les politiques gouvernementales (bonus, malus, ZFE)\n\n2. *InsideEVs*\n   - Analyses détaillées et comparatifs de véhicules.\n   - Essentiel pour suivre :\n     - Les chiffres de ventes mensuels par marché\n     - Les tests d'autonomie réelle\n     - Les nouvelles technologies embarquées\n\n3. *Automobile Propre* (focus France)\n   - Actualité française et européenne.\n   - Points forts :\n     - Réglementations françaises (bonus, leasing social)\n     - Essais en conditions réelles\n     - Comparatifs prix/autonomie\n\n4. *Salles de presse constructeurs* (sources directes) :\n   - *Volkswagen Group* – Stratégie MEB et batteries\n   - *Renault / Ampere* – Nouveaux modèles et Gigafactory\n   - *Tesla* – Mises à jour produits et Supercharger\n   - *BYD Europe* – Expansion sur le marché européen\n\nTu veux que j'ajoute ces sources maintenant, ou on **priorise certains axes** (technologie vs. marché vs. réglementation) ?",
                ],
            ],
            // English conversation (index 3)
            [
                [
                    'role' => MessageRole::User,
                    'content' => 'Hello! I work for Chapsvision and need to analyze competitors in the Market Intelligence software sector. Can you help?',
                ],
                [
                    'role' => MessageRole::System,
                    'content' => 'Subject reference updated.',
                ],
                [
                    'role' => MessageRole::Model,
                    'content' => 'Hello! I\'d be happy to help you analyze competitors in the Market Intelligence software sector. I\'ll examine the competitive landscape to identify key players, their strengths, and market positioning.',
                ],
                [
                    'role' => MessageRole::User,
                    'content' => 'Great! Who are the main competitors we should be aware of?',
                ],
                [
                    'role' => MessageRole::Model,
                    'content' => 'Here are the main competitors in the Market Intelligence software sector:\n\n**Enterprise Players :** Crayon, Kompyte, Klue, Contify\n**AI-Powered Solutions :** Brand24, Mention, Brandwatch\n**Traditional Vendors :** Meltwater, Cision, PR Newswire\n**Emerging Startups :** Sprinklr, Hootsuite Insights\n\nI recommend monitoring their product features, pricing strategies, and market positioning.',
                ],
                [
                    'role' => MessageRole::User,
                    'content' => 'What are the key differentiators in this market?',
                ],
                [
                    'role' => MessageRole::Model,
                    'content' => 'Key differentiators in the Market Intelligence market include:\n\n**AI/ML Capabilities** : Advanced analytics and predictive insights\n**Real-time Monitoring** : Speed and accuracy of data collection\n**Integration Capabilities** : CRM, BI tools, and workflow systems\n**Compliance & Security** : GDPR, SOC2, and data protection\n**Customization** : Industry-specific solutions and white-label options\n\nUnderstanding these factors is crucial for competitive positioning.',
                ],
                [
                    'role' => MessageRole::Model,
                    'content' => "Great, let me now propose a **systematic competitive monitoring approach** for Market Intelligence vendors.\n\nHere are the key sources I recommend for your watch file:\n\n1. *G2 Crowd & Gartner Peer Insights*\n   - User reviews and satisfaction scores.\n   - Critical for tracking:\n     - Feature comparisons by actual users\n     - Pricing transparency signals\n     - Customer pain points with competitors\n\n2. *Analyst reports* (strategic intelligence):\n   - *Forrester Wave* – Competitive positioning quadrants\n   - *Gartner Magic Quadrant* – Market leader identification\n   - *IDC MarketScape* – Vendor capability assessments\n\n3. *Industry publications & events*:\n   - *SCIP (Strategic & Competitive Intelligence Professionals)*\n   - Key topics include:\n     - Best practices and case studies\n     - Emerging methodologies\n     - Vendor announcements at conferences\n\n4. *Competitor direct sources*:\n   - *Crayon* – Blog and product updates\n   - *Klue* – Battlecard strategies and releases\n   - *Contify* – AI news monitoring features\n   - *Meltwater* – Enterprise positioning moves\n\nWould you like me to add these sources now, or should we **prioritize by competitor tier** (direct competitors vs. adjacent players)?",
                ],
            ],
            // English conversation (index 4) - Pharmacy & Cosmetics
            [
                [
                    'role' => MessageRole::User,
                    'content' => 'Hello! I need help monitoring employer labels in the pharmacy and cosmetics sector. Can you assist me?',
                ],
                [
                    'role' => MessageRole::System,
                    'content' => 'Subject reference updated.',
                ],
                [
                    'role' => MessageRole::Model,
                    'content' => 'Hello! I\'d be happy to help you monitor employer labels in the pharmacy and cosmetics sector. I\'ll analyze the available data to identify key employer recognition programs, their criteria, and their impact on reputation in this industry.',
                ],
                [
                    'role' => MessageRole::User,
                    'content' => 'Great! What are the main employer labels we should focus on?',
                ],
                [
                    'role' => MessageRole::Model,
                    'content' => 'Here are the main employer labels to focus on in the pharmacy and cosmetics sector:\n\n**Certifying Labels :** Top Employers Institute, Great Place to Work, Best Workplaces\n**Media-based Labels :** Forbes "Meilleurs Employeurs", Fortune "Best Companies to Work For"\n**Industry-specific :** Pharmaceutical industry awards, Cosmetic industry recognition programs\n**Regional Labels :** European, French, and global employer recognition programs\n\nI recommend monitoring these labels for their criteria, costs, and reputational impact.',
                ],
                [
                    'role' => MessageRole::User,
                    'content' => 'What about the specific companies we should monitor?',
                ],
                [
                    'role' => MessageRole::Model,
                    'content' => 'Here are the key companies to monitor in the pharmacy and cosmetics sector:\n\n**Pharmaceutical :** Sanofi, Servier, Bio Mérieux, Ipsen\n**Cosmetics :** L\'Oréal, Groupe Rocher, Estée Lauder, L\'Occitane, SVR, ISDIN\n\nThese companies are regularly featured in employer rankings and have strong reputations in their respective sectors. Monitoring their employer branding strategies and label achievements will provide valuable competitive intelligence.',
                ],
                [
                    'role' => MessageRole::User,
                    'content' => 'How can we track the impact of these labels on their reputation?',
                ],
                [
                    'role' => MessageRole::Model,
                    'content' => 'To track the impact of employer labels on reputation, consider these approaches:\n\n**Media Coverage Analysis :** Monitor news articles, press releases, and social media mentions\n**Employee Reviews :** Track Glassdoor, Indeed, and other review platforms\n**Financial Impact :** Analyze stock performance and investor sentiment around label announcements\n**Recruitment Metrics :** Monitor job application rates and talent acquisition success\n**Brand Perception :** Track brand sentiment and awareness surveys\n\nThis comprehensive approach will help you understand the true value of employer labels in the pharmacy and cosmetics sector.',
                ],
                [
                    'role' => MessageRole::Model,
                    'content' => "Perfect, let me now propose a **structured monitoring approach** for employer labels in the pharmacy and cosmetics sector.\n\nHere are the key sources I recommend for your watch file:\n\n1. *Label certification bodies* (official announcements):\n   - *Top Employers Institute* – Annual certification releases\n   - *Great Place to Work* – Best Workplaces rankings\n   - Key metrics to track:\n     - Certification criteria changes\n     - New certified companies in pharma/cosmetics\n     - Regional vs. global certifications\n\n2. *Employee review platforms*:\n   - *Glassdoor* – Company ratings and CEO approval\n   - *Indeed Company Reviews* – Work-life balance scores\n   - Watch for:\n     - Rating trends over time\n     - Review volume spikes (positive or negative)\n     - Comparison with direct competitors\n\n3. *Industry HR publications*:\n   - *HR Magazine* – Employer branding trends\n   - *People Management* – Case studies and best practices\n   - Focus areas:\n     - Pharma talent war insights\n     - Cosmetics industry employer initiatives\n     - Diversity and inclusion programs\n\n4. *Competitor newsrooms* (direct sources):\n   - *L'Oréal Careers* – Employer branding campaigns\n   - *Sanofi News* – HR initiatives and awards\n   - *Estée Lauder Companies* – Culture and inclusion updates\n   - *Servier* – Talent acquisition strategies\n\nWould you like me to add these sources now, or should we **focus on specific label types first** (certifications vs. media rankings)?",
                ],
            ],
        ];

        return $messageSets[$index] ?? $messageSets[0];
    }
}
