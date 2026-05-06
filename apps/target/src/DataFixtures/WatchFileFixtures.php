<?php

namespace App\DataFixtures;

use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorType;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileActor;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFile\WatchFileUserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class WatchFileFixtures extends Fixture implements DependentFixtureInterface
{
    public const WATCHFILE_REFERENCE = 'watch_file_';

    public function getDependencies(): array
    {
        return [ActorFixtures::class, UserFixtures::class, OrganisationFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $basilUser = $this->getReference(UserFixtures::BASIL_USER_REFERENCE, User::class);
        $organisation = $this->getReference(OrganisationFixtures::ORGANISATION_REFERENCE, Organisation::class);
        $creationDate = new \DateTimeImmutable('-2 weeks');

        $case1 = new WatchFile(
            name: 'News gouvernementales - Nucléaire',
            userObjective: 'Comprendre et anticiper les évolutions gouvernementales et réglementaires mondiales relative à l\'énergie nucléaire. Trouver des informations évoquant des changements, des évolutions, mais aussi des informations permettant d\'anticiper des changements.',
            organisation: $organisation,
            createdBy: $basilUser,
            createdAt: $creationDate,
        );

        $case1->setReferenceSubject(new TranslatedText(
            "# Veille Politique Nucléaire\n\n**Surveillance Mondiale des Politiques Énergétiques Nucléaires**\n\n*Suivre les réglementations gouvernementales, les changements de politique et les protocoles de sécurité sur les principaux marchés de l'énergie nucléaire. Focus sur les mises à jour de la politique énergétique nucléaire française, les décisions d'abandon progressif allemandes et les changements réglementaires nucléaires américains. Mots-clés à surveiller : politique nucléaire, réglementations gouvernementales, sécurité énergétique.*",
            "# Nuclear Energy Policy Watch\n\n**Global Nuclear Energy Policy Monitoring**\n\n*Track government regulations, policy changes, and safety protocols across major nuclear energy markets. Focus on France's nuclear energy policy updates, Germany's phase-out decisions, and USA's nuclear regulatory changes. Monitor keywords: nuclear policy, government regulations, energy security.*"
        ));
        $case1->setReferenceSubjectLlm(
            'Monitor global nuclear energy policy developments. Focus on: government regulations, policy changes, safety protocols. Priority markets: France (nuclear policy updates), Germany (phase-out decisions), USA (regulatory changes). Track: legislative updates, regulatory announcements, safety protocol revisions. Include: official government statements, regulatory body publications. Exclude: general energy news without nuclear focus.'
        );

        $case1->addWatchFileUser(new WatchFileUser(null, $basilUser, WatchFileUserRole::OWNER));

        $manager->persist($case1);
        $this->addReference(self::WATCHFILE_REFERENCE . '0', $case1);

        $case2 = new WatchFile(
            name: 'Opportunités dans le secteur de la santé connectée',
            userObjective: 'Notre startup développe des solutions d\'IA pour l\'analyse de données de santé. Nous cherchons à identifier les opportunités commerciales dans le secteur de la santé connectée, particulièrement auprès des établissements de soin et des assurances santé en France et en Allemagne. Nous voulons surveiller les appels d\'offres publics, comprendre les besoins non satisfaits des professionnels de santé, et anticiper les évolutions réglementaires concernant la protection des données de santé (RGPD, certification HDS). Notre objectif est également d\'analyser les stratégies de nos concurrents (Doctolib, Alan, Qare, Babylon Health) et d\'identifier les possibilités de partenariats avec des fabricants de dispositifs médicaux connectés. Nous avons besoin de prioriser les opportunités à court terme (6-12 mois) tout en gardant une vision stratégique à 3-5 ans pour orienter notre roadmap produit.',
            organisation: $organisation,
            createdBy: $basilUser,
            createdAt: $creationDate,
        );

        $case2->addWatchFileUser(new WatchFileUser(null, $basilUser, WatchFileUserRole::OWNER));

        $manager->persist($case2);
        $this->addReference(self::WATCHFILE_REFERENCE . '1', $case2);

        $case3 = new WatchFile(
            name: 'Évolution du marché des véhicules électriques',
            userObjective: 'Notre entreprise souhaite se positionner sur le marché des bornes de recharge pour véhicules électriques. Nous avons besoin d\'analyser les principaux acteurs du marché (fabricants de véhicules et d\'infrastructures de recharge), les technologies émergentes en matière de batteries et recharge rapide, ainsi que les tendances d\'adoption par les consommateurs. Nous devons également comprendre les réglementations environnementales qui favorisent la transition vers l\'électromobilité et les subventions disponibles dans différents pays européens. Notre horizon temporel est de 3 ans, avec une attention particulière aux évolutions prévues pour 2025-2026.',
            organisation: $organisation,
            createdBy: $basilUser,
            createdAt: $creationDate,
        );

        $case3->setReferenceSubject(new TranslatedText(
            "# Intelligence du Marché des Véhicules Électriques\n\n**Analyse du Marché VE & Infrastructure de Recharge**\n\n*Surveiller Tesla, Volkswagen Group et BMW comme acteurs clés dans la fabrication de véhicules et l'infrastructure de recharge. Suivre la technologie des batteries à semi-conducteurs, les normes de recharge CCS/CHAdeMO et les développements du réseau Tesla Supercharger. Analyser les tendances d'adoption des consommateurs et les réglementations environnementales européennes soutenant la transition vers l'électromobilité.*",
            "# Electric Vehicle Market Intelligence\n\n**EV Market & Charging Infrastructure Analysis**\n\n*Monitor Tesla, Volkswagen Group, and BMW as key players in vehicle manufacturing and charging infrastructure. Track solid-state battery technology, CCS/CHAdeMO charging standards, and Tesla Supercharger network developments. Analyze consumer adoption trends and European environmental regulations supporting electromobility transition.*"
        ));
        $case3->setReferenceSubjectLlm(
            'Track EV market and charging infrastructure developments. Priority actors: Tesla, Volkswagen Group, BMW. Focus on: solid-state battery technology, charging standards (CCS, CHAdeMO), Tesla Supercharger network expansion. Monitor: battery technology innovations, charging infrastructure investments, consumer adoption trends. Geographic scope: Europe. Include: product announcements, technology partnerships, regulatory updates. Exclude: general automotive news without EV focus.'
        );

        $case3->addWatchFileUser(new WatchFileUser(null, $basilUser, WatchFileUserRole::OWNER));

        $manager->persist($case3);
        $this->addReference(self::WATCHFILE_REFERENCE . '2', $case3);

        $case4 = new WatchFile(
            name: 'Analyse des acteurs de la veille concurrentielle',
            userObjective: 'Je travaille pour Chapsvision, et je souhaite étudier les éditeurs de logiciels concurrents dans le secteur de la Market Intelligence',
            organisation: $organisation,
            createdBy: $basilUser,
            createdAt: $creationDate,
        );

        $case4->setReferenceSubject(new TranslatedText(
            "# Marché des Logiciels d'Intelligence Concurrentielle\n\n**Analyse des Plateformes de Market Intelligence**\n\n*Analyser les solutions d'entreprise comme Crayon (intelligence alimentée par l'IA), Kompyte (suivi concurrentiel), SEMrush (marketing digital) et SimilarWeb (trafic web). Surveiller les outils spécialisés incluant Brand24 (réseaux sociaux), Mention (surveillance de marque) et Awario (écoute sociale). Suivre les fonctionnalités clés : analyse IA, données en temps réel, rapports personnalisés et accès API.*",
            "# Competitive Intelligence Software Market\n\n**Market Intelligence Platform Analysis**\n\n*Analyze enterprise solutions like Crayon (AI-powered intelligence), Kompyte (competitive tracking), SEMrush (digital marketing), and SimilarWeb (web traffic). Monitor specialized tools including Brand24 (social media), Mention (brand monitoring), and Awario (social listening). Track key features: AI analysis, real-time data, custom reports, and API access.*"
        ));
        $case4->setReferenceSubjectLlm(
            'Analyze competitive intelligence software market. Priority competitors: Crayon (AI-powered intelligence), Kompyte (competitive tracking), SEMrush (digital marketing), SimilarWeb (web traffic), Brand24 (social media), Mention (brand monitoring), Awario (social listening). Focus on: product features, AI capabilities, pricing strategies, market positioning. Include: product announcements, feature releases, partnership news, pricing updates. Exclude: general marketing software without CI focus.'
        );

        $case4->addWatchFileUser(new WatchFileUser(null, $basilUser, WatchFileUserRole::OWNER));

        $manager->persist($case4);
        $this->addReference(self::WATCHFILE_REFERENCE . '3', $case4);

        $case5 = new WatchFile(
            name: 'Employer Labels Monitoring in Pharmacy & Cosmetics',
            userObjective: 'identifier les labels les plus reconnus en 2024/2025 "Meilleurs employeurs"',
            organisation: $organisation,
            createdBy: $basilUser,
            createdAt: $creationDate,
        );

        $case5->setReferenceSubject(new TranslatedText(
            "## Contexte\nSurveillance des labels employeurs les plus reconnus en 2024/2025, en différenciant les labels certifiants (ex. \"Top Employers\") et les labels médiatiques (ex. \"Meilleurs Employeurs\" de Forbes). Pour chaque label, décrire son origine, sa longévité, sa portée (locale, européenne, mondiale) et les secteurs ou types d'entreprises qu'il cible.\n\n## Objectifs\n- Identifier les organisations émettant chaque label et coûts potentiels\n- Comprendre les critères d'évaluation ou de certification\n- Analyser l'impact médiatique, économique et réputationnel de ces classements et les tactiques de communication des employeurs autour de ces labels\n\n## Périmètre\n- Focus exclusivement sur le secteur pharmacie/cosmétiques et concurrents directs\n- Principaux concurrents : L'Oréal, Groupe Rocher, Estée Lauder, L'Occitane, SVR, ISDIN, Sanofi, Bio Mérieux, Ipsen, Servier\n- Portée géographique : Mondiale, avec sélection de documents restreinte au français et anglais uniquement\n- Surveillance uniquement pour le secteur pharmacie/cosmétiques\n\n## Type de Surveillance\nType Principal : Veille Image (Surveillance de Réputation)",
            "## Context\nMonitoring the most recognized employer labels in 2024/2025, differentiating between certifying labels (e.g., \"Top Employers\") and media-based labels (e.g., \"Meilleurs Employeurs\" by Forbes). For each label, describe its origin, longevity, reach (local, European, global), and the sectors or company types it targets.\n\n## Objectives\n- Identify the organizations issuing each label and potential costs\n- Understand the evaluation or certification criteria\n- Analyze the media, economic, and reputational impact of these rankings and employers' communication tactics surrounding these labels\n\n## Scope\n- Focus exclusively on the pharmacy/cosmetics sector and direct competitors\n- Main competitors: L'Oréal, Groupe Rocher, Estée Lauder, L'Occitane, SVR, ISDIN, Sanofi, Bio Mérieux, Ipsen, Servier\n- Geographic scope: Global, with document selection restricted to French and English only\n- Monitoring only for pharmacy/cosmetics sector\n\n## Monitoring Type\nPrimary Type: Veille Image (Reputation Monitoring)"
        ));
        $case5->setReferenceSubjectLlm(
            'Monitor employer branding and certification labels in pharmacy/cosmetics sector for 2024/2025. Track: Top Employers certifications, Forbes Best Employers rankings, Great Place to Work awards. Priority competitors: L\'Oreal, Groupe Rocher, Estee Lauder, L\'Occitane, SVR, ISDIN, Sanofi, Bio Merieux, Ipsen, Servier. Include: certification announcements, ranking publications, employer branding initiatives. Exclude: general HR news without label focus, unrelated industries.'
        );

        $case5->addWatchFileUser(new WatchFileUser(null, $basilUser, WatchFileUserRole::OWNER));

        $manager->persist($case5);
        $this->addReference(self::WATCHFILE_REFERENCE . '4', $case5);

        $watchFiles = [$case1, $case2, $case3, $case4, $case5];

        // Add WatchFileActor relations (only for the first 4 watchfiles, not Pharmacy & Cosmetics)
        for ($i = 0; $i < 15; ++$i) {
            /** @var Actor $actor */
            $actor = $this->getReference(ActorFixtures::ACTOR_REFERENCE . $i, Actor::class);
            $watchFile = $watchFiles[$i % 4]; // Only use first 4 watchfiles (indices 0-3)
            $watchFileActor = new WatchFileActor($actor, $watchFile);

            // Randomly select a type
            $types = [
                ActorType::OTHER,
                ActorType::COMPETITOR,
                ActorType::PARTNER,
                ActorType::SUPPLIER,
                ActorType::CUSTOMER,
            ];
            $randomType = $types[array_rand($types)];
            $watchFileActor->setType($randomType);

            $watchFileActor->setScore(mt_rand(50, 100) / 100);

            // Add explanations in both languages for why this actor is relevant
            $watchFileActor->setExplanation(
                match ($watchFile) {
                    $case1 => new TranslatedText(
                        'Cette entreprise est impliquée dans le secteur de l\'énergie nucléaire et pourrait influencer les politiques gouvernementales.',
                        'This company is involved in the nuclear energy sector and could influence government policies.',
                    ),
                    $case2 => new TranslatedText(
                        'Acteur majeur dans le domaine de la santé connectée avec des solutions innovantes.',
                        'Major player in connected healthcare with innovative solutions.',
                    ),
                    $case3 => new TranslatedText(
                        'Cette entreprise développe des technologies liées aux véhicules électriques et aux infrastructures de recharge.',
                        'This company develops technologies related to electric vehicles and charging infrastructure.',
                    ),
                    $case4 => new TranslatedText(
                        'Concurrent direct dans le secteur des solutions de veille stratégique.',
                        'Direct competitor in the strategic intelligence solutions sector.',
                    ),
                    $case5 => new TranslatedText(
                        'Surveillance des pharmaceutiques/cosmétiques et compétiteurs.',
                        'Watch file on pharmacy/cosmetics sector and direct competitors.',
                    ),
                    default => new TranslatedText(
                        'Acteur pertinent pour cette veille stratégique.',
                        'Relevant actor for this strategic intelligence.',
                    ),
                }
            );

            $manager->persist($watchFileActor);
        }

        // Add specific actors for Pharmacy & Cosmetics watchfile
        $pharmacyActors = [
            [
                'actor_index' => 15,
                'type' => ActorType::COMPETITOR,
                'score' => 0.88,
                'explanation' => new TranslatedText(
                    'Servier est un groupe pharmaceutique international souvent mis en avant dans les tableaux de réputation employeur.',
                    'Servier is an international pharmaceutical group often highlighted in employer reputation tables.',
                ),
            ],
            [
                'actor_index' => 16,
                'type' => ActorType::COMPETITOR,
                'score' => 0.95,
                'explanation' => new TranslatedText(
                    'Groupe Rocher est un acteur important de la cosmétique, fréquemment reconnu dans les classements européens de labels employeurs.',
                    'Groupe Rocher is a major player in the cosmetics industry with frequent recognition in European employer label rankings.',
                ),
            ],
            [
                'actor_index' => 17,
                'type' => ActorType::COMPETITOR,
                'score' => 0.85,
                'explanation' => new TranslatedText(
                    'SVR est une marque cosmétique pharmaceutique souvent citée dans les études européennes de labels employeurs.',
                    'SVR is a notable pharmaceutical cosmetic brand often cited in European employer label studies.',
                ),
            ],
            [
                'actor_index' => 18,
                'type' => ActorType::COMPETITOR,
                'score' => 0.90,
                'explanation' => new TranslatedText(
                    'Bio Mérieux, spécialisé dans le diagnostic in vitro, est souvent cité dans les études de réputation employeur.',
                    'Bio Mérieux specializes in in vitro diagnostics and is visible in employer reputation studies.',
                ),
            ],
            [
                'actor_index' => 19,
                'type' => ActorType::COMPETITOR,
                'score' => 0.98,
                'explanation' => new TranslatedText(
                    'Sanofi est un groupe pharmaceutique mondialement présent, régulièrement reconnu dans les classements employeurs du secteur santé.',
                    'Sanofi is a pharmaceutical group operating globally and regularly recognized in rankings as an employer in the healthcare sector.',
                ),
            ],
            [
                'actor_index' => 20,
                'type' => ActorType::COMPETITOR,
                'score' => 0.87,
                'explanation' => new TranslatedText(
                    'ISDIN est une marque dermocosmétique espagnole reconnue dans les classements employeurs du secteur cosmétique.',
                    'ISDIN is a Spanish dermocosmetic brand recognized in employer rankings in the cosmetics sector.',
                ),
            ],
            [
                'actor_index' => 21,
                'type' => ActorType::COMPETITOR,
                'score' => 0.92,
                'explanation' => new TranslatedText(
                    'Estée Lauder est un groupe cosmétique américain souvent présent dans les classements internationaux de réputation employeur.',
                    'Estée Lauder is an American cosmetics group often present in international employer reputation rankings.',
                ),
            ],
            [
                'actor_index' => 22,
                'type' => ActorType::COMPETITOR,
                'score' => 0.96,
                'explanation' => new TranslatedText(
                    'L\'Oréal est le leader mondial de la cosmétique, régulièrement reconnu dans les classements employeurs internationaux.',
                    'L\'Oréal is the world leader in cosmetics, regularly recognized in international employer rankings.',
                ),
            ],
            [
                'actor_index' => 23,
                'type' => ActorType::COMPETITOR,
                'score' => 0.89,
                'explanation' => new TranslatedText(
                    'L\'Occitane est une marque française de cosmétiques naturels souvent citée dans les études de réputation employeur.',
                    'L\'Occitane is a French natural cosmetics brand often cited in employer reputation studies.',
                ),
            ],
            [
                'actor_index' => 24,
                'type' => ActorType::COMPETITOR,
                'score' => 0.91,
                'explanation' => new TranslatedText(
                    'Ipsen est un groupe pharmaceutique français spécialisé en oncologie, visible dans les classements employeurs du secteur santé.',
                    'Ipsen is a French pharmaceutical group specialized in oncology, visible in employer rankings in the healthcare sector.',
                ),
            ],
        ];

        foreach ($pharmacyActors as $actorData) {
            /** @var Actor $actor */
            $actor = $this->getReference(ActorFixtures::ACTOR_REFERENCE . $actorData['actor_index'], Actor::class);
            $watchFileActor = new WatchFileActor($actor, $case5);
            $watchFileActor->setType($actorData['type']);
            $watchFileActor->setScore($actorData['score']);
            $watchFileActor->setExplanation($actorData['explanation']);
            $manager->persist($watchFileActor);
        }

        $manager->flush();
    }
}
