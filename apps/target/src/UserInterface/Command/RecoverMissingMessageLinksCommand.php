<?php

declare(strict_types=1);

namespace App\UserInterface\Command;

use App\Domain\Chat\Content\TextContent;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageRole;
use App\Domain\Source\Source;
use App\Domain\WatchFile\WatchFileActor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to recover missing message links for WatchFileActors and Sources.
 *
 * This command correlates entity creation timestamps with Message timestamps
 * to recover the addedByMessage relationship that was lost due to a bug where
 * Message.id was confused with MessageContent.id.
 *
 * Strategy (in order of priority):
 * 1. Look for system message "Actor added"/"Source added" after entity creation,
 *    then find the user message that triggered it (most reliable)
 * 2. Fallback: Find the most recent user message before entity creation (within time window)
 */
#[AsCommand(
    name: 'app:recover-message-links',
    description: 'Recover missing addedByMessage links for WatchFileActors and Sources using timestamp correlation',
)]
class RecoverMissingMessageLinksCommand extends Command
{
    /**
     * Time window in seconds to search for matching messages before entity creation.
     */
    private const int TIME_WINDOW_BEFORE_SECONDS = 300; // 5 minutes

    /**
     * Time window in seconds to search for system message after entity creation.
     */
    private const int TIME_WINDOW_AFTER_SECONDS = 60; // 1 minute

    /**
     * Patterns for system messages indicating actor/source was added.
     * These are sent by N8N workflows after successful add operations.
     */
    private const array ACTOR_ADDED_PATTERNS = ['Acteur ajouté', 'Actor added'];
    private const array SOURCE_ADDED_PATTERNS = ['Source ajoutée', 'Source added'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without making changes')
            ->addOption('actors-only', null, InputOption::VALUE_NONE, 'Only process actors')
            ->addOption('sources-only', null, InputOption::VALUE_NONE, 'Only process sources')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limit number of records to process', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $actorsOnly = (bool) $input->getOption('actors-only');
        $sourcesOnly = (bool) $input->getOption('sources-only');
        $limit = (int) $input->getOption('limit');
        $verbose = $output->isVerbose();

        $io->title('Recover Missing Message Links');

        // Display configuration
        $io->text([
            \sprintf('Time window before entity creation: <info>%d seconds</info>', self::TIME_WINDOW_BEFORE_SECONDS),
            \sprintf(
                'Time window after entity creation (for system messages): <info>%d seconds</info>',
                self::TIME_WINDOW_AFTER_SECONDS
            ),
            \sprintf('Limit: <info>%s</info>', $limit > 0 ? (string) $limit : 'none'),
        ]);

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No changes will be made');
        }

        $actorStats = [
            'found' => 0,
            'recovered' => 0,
            'failed' => 0,
        ];
        $sourceStats = [
            'found' => 0,
            'recovered' => 0,
            'failed' => 0,
        ];

        if (!$sourcesOnly) {
            $io->section('Processing WatchFileActors');
            $actorStats = $this->processActors($io, $dryRun, $limit, $verbose);
        }

        if (!$actorsOnly) {
            $io->section('Processing Sources');
            $sourceStats = $this->processSources($io, $dryRun, $limit, $verbose);
        }

        $io->section('Summary');
        $io->table(
            ['Entity', 'Found without link', 'Recovered', 'Failed'],
            [
                ['WatchFileActor', $actorStats['found'], $actorStats['recovered'], $actorStats['failed']],
                ['Source', $sourceStats['found'], $sourceStats['recovered'], $sourceStats['failed']],
            ]
        );

        if (!$dryRun) {
            $this->entityManager->flush();
            $io->success('Changes have been persisted to the database.');
        }

        return Command::SUCCESS;
    }

    /**
     * @return array{found: int, recovered: int, failed: int}
     */
    private function processActors(SymfonyStyle $io, bool $dryRun, int $limit, bool $verbose): array
    {
        $stats = [
            'found' => 0,
            'recovered' => 0,
            'failed' => 0,
        ];

        // Find WatchFileActors without addedByMessage
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('wfa')
            ->from(WatchFileActor::class, 'wfa')
            ->where('wfa.addedByMessage IS NULL')
            ->orderBy('wfa.createdAt', 'DESC');

        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }

        /** @var list<WatchFileActor> $actors */
        $actors = $qb->getQuery()
            ->getResult();
        $stats['found'] = \count($actors);

        $io->text(\sprintf('Found <info>%d</info> WatchFileActors without addedByMessage link', $stats['found']));

        if (!$verbose) {
            $io->progressStart($stats['found']);
        }

        foreach ($actors as $actor) {
            $actorLabel = $actor
                ->getActor()
                ->getLabel();

            $watchFileId = $actor
                ->getWatchFile()
                ->getId();

            $result = $this->findUserMessageForActorWithStrategy(
                $watchFileId,
                $actorLabel,
                $actor->getCreatedAt()
            );

            if (null !== $result['message']) {
                if (!$dryRun) {
                    $actor->setAddedByMessage($result['message']);
                }

                ++$stats['recovered'];

                if ($verbose) {
                    $io->text(\sprintf(
                        '  <info>✓</info> Actor "<comment>%s</comment>" recovered via %s (message: %s)',
                        $actorLabel,
                        $result['strategy'],
                        $result['message']->getId()
                    ));
                }
            } else {
                ++$stats['failed'];

                if ($verbose) {
                    $io->text(\sprintf(
                        '  <error>✗</error> Actor "<comment>%s</comment>" - no matching message found (WatchFile: %s)',
                        $actorLabel,
                        $watchFileId
                    ));
                }
            }

            if (!$verbose) {
                $io->progressAdvance();
            }
        }

        if (!$verbose) {
            $io->progressFinish();
        }

        return $stats;
    }

    /**
     * @return array{found: int, recovered: int, failed: int}
     */
    private function processSources(SymfonyStyle $io, bool $dryRun, int $limit, bool $verbose): array
    {
        $stats = [
            'found' => 0,
            'recovered' => 0,
            'failed' => 0,
        ];

        // Find Sources without addedByMessage
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('s')
            ->from(Source::class, 's')
            ->where('s.addedByMessage IS NULL')
            ->orderBy('s.createdAt', 'DESC');

        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }

        /** @var list<Source> $sources */
        $sources = $qb->getQuery()
            ->getResult();
        $stats['found'] = \count($sources);

        $io->text(\sprintf('Found <info>%d</info> Sources without addedByMessage link', $stats['found']));

        if (!$verbose) {
            $io->progressStart($stats['found']);
        }

        foreach ($sources as $source) {
            $sourceName = $source->getName();
            $watchFileId = $source
                ->getWatchFile()
                ->getId();

            $result = $this->findUserMessageForSourceWithStrategy(
                $watchFileId,
                $sourceName,
                $source->getCreatedAt()
            );

            if (null !== $result['message']) {
                if (!$dryRun) {
                    $source->setAddedByMessage($result['message']);
                }
                ++$stats['recovered'];

                if ($verbose) {
                    $io->text(\sprintf(
                        '  <info>✓</info> Source "<comment>%s</comment>" recovered via %s (message: %s)',
                        $sourceName,
                        $result['strategy'],
                        $result['message']->getId()
                    ));
                }
            } else {
                ++$stats['failed'];

                if ($verbose) {
                    $io->text(\sprintf(
                        '  <error>✗</error> Source "<comment>%s</comment>" - no matching message found (WatchFile: %s)',
                        $sourceName,
                        $watchFileId
                    ));
                }
            }

            if (!$verbose) {
                $io->progressAdvance();
            }
        }

        if (!$verbose) {
            $io->progressFinish();
        }

        return $stats;
    }

    /**
     * Find the user message that triggered an actor addition, returning the strategy used.
     *
     * Strategy:
     * 1. Look for system message "Actor added" after creation (most reliable)
     * 2. Fallback: Look for user message within time window before entity creation
     *
     * @return array{message: Message|null, strategy: string}
     */
    private function findUserMessageForActorWithStrategy(
        string $watchFileId,
        string $actorLabel,
        \DateTimeImmutable $entityCreatedAt,
    ): array {
        // Strategy 1: Find via system message "Actor added" (most reliable)
        $message = $this->findUserMessageViaSystemMessage(
            $watchFileId,
            $actorLabel,
            $entityCreatedAt,
            self::ACTOR_ADDED_PATTERNS
        );
        if (null !== $message) {
            return [
                'message' => $message,
                'strategy' => 'system message',
            ];
        }

        // Strategy 2: Fallback to direct timestamp correlation
        $message = $this->findUserMessageBeforeTime($watchFileId, $entityCreatedAt);

        return [
            'message' => $message,
            'strategy' => 'timestamp fallback',
        ];
    }

    /**
     * Find the user message that triggered a source addition, returning the strategy used.
     *
     * Strategy:
     * 1. Look for system message "Source added" after creation (most reliable)
     * 2. Fallback: Look for user message within time window before entity creation
     *
     * @return array{message: Message|null, strategy: string}
     */
    private function findUserMessageForSourceWithStrategy(
        string $watchFileId,
        string $sourceName,
        \DateTimeImmutable $entityCreatedAt,
    ): array {
        // Strategy 1: Find via system message "Source added" (most reliable)
        $message = $this->findUserMessageViaSystemMessage(
            $watchFileId,
            $sourceName,
            $entityCreatedAt,
            self::SOURCE_ADDED_PATTERNS
        );
        if (null !== $message) {
            return [
                'message' => $message,
                'strategy' => 'system message',
            ];
        }

        // Strategy 2: Fallback to direct timestamp correlation
        $message = $this->findUserMessageBeforeTime($watchFileId, $entityCreatedAt);

        return [
            'message' => $message,
            'strategy' => 'timestamp fallback',
        ];
    }

    /**
     * Find the most recent user message before a given time.
     */
    private function findUserMessageBeforeTime(string $watchFileId, \DateTimeImmutable $referenceTime): ?Message
    {
        $startTime = $referenceTime->modify('-' . self::TIME_WINDOW_BEFORE_SECONDS . ' seconds');
        $endTime = $referenceTime;

        $messageQb = $this->entityManager->createQueryBuilder();
        $messageQb->select('m')
            ->from(Message::class, 'm')
            ->join('m.conversation', 'c')
            ->where('c.watchFile = :watchFileId')
            ->andWhere('m.role = :role')
            ->andWhere('m.createdAt BETWEEN :startTime AND :endTime')
            ->setParameter('watchFileId', $watchFileId)
            ->setParameter('role', MessageRole::User)
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(1);

        /** @var Message|null */
        return $messageQb->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find user message by locating the system message confirmation first.
     *
     * N8N sends a system message like "Acteur ajouté : X" or "Source ajoutée : X"
     * after successfully adding an entity. We can use this to find the user message
     * that preceded it.
     *
     * @param array<string> $patterns Patterns to match in system message content
     */
    private function findUserMessageViaSystemMessage(
        string $watchFileId,
        string $entityName,
        \DateTimeImmutable $entityCreatedAt,
        array $patterns,
    ): ?Message {
        // Look for system message created shortly after the entity
        $searchStart = $entityCreatedAt->modify('-' . self::TIME_WINDOW_BEFORE_SECONDS . ' seconds');
        $searchEnd = $entityCreatedAt->modify('+' . self::TIME_WINDOW_AFTER_SECONDS . ' seconds');

        // Build pattern for LIKE query (any of the patterns followed by entity name)
        // Join TextContent directly since MessageContent is abstract and doesn't have content field
        $systemMessageQb = $this->entityManager->createQueryBuilder();
        $systemMessageQb->select('m')
            ->from(Message::class, 'm')
            ->join('m.conversation', 'c')
            ->join(TextContent::class, 'tc', 'WITH', 'tc.message = m')
            ->where('c.watchFile = :watchFileId')
            ->andWhere('m.role = :role')
            ->andWhere('m.createdAt BETWEEN :startTime AND :endTime')
            ->setParameter('watchFileId', $watchFileId)
            ->setParameter('role', MessageRole::System)
            ->setParameter('startTime', $searchStart)
            ->setParameter('endTime', $searchEnd);

        // Add pattern matching for entity name in content
        $orConditions = $systemMessageQb
            ->expr()
            ->orX();

        foreach ($patterns as $index => $pattern) {
            $orConditions->add($systemMessageQb->expr()->like('tc.content', ':pattern' . $index));
            $systemMessageQb->setParameter('pattern' . $index, '%' . $pattern . '%' . $entityName . '%');
        }

        $systemMessageQb
            ->andWhere($orConditions)
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults(1);

        /** @var Message|null $systemMessage */
        $systemMessage = $systemMessageQb
            ->getQuery()
            ->getOneOrNullResult();

        if (null === $systemMessage) {
            return null;
        }

        // Found the system message, now find the user message that preceded it
        $userMessageQb = $this->entityManager->createQueryBuilder();
        $userMessageQb->select('m')
            ->from(Message::class, 'm')
            ->andWhere('m.conversation = :conversation')
            ->andWhere('m.role = :role')
            ->andWhere('m.createdAt < :systemMessageTime')
            ->setParameter('conversation', $systemMessage->getConversation())
            ->setParameter('role', MessageRole::User)
            ->setParameter('systemMessageTime', $systemMessage->getCreatedAt())
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(1);

        /** @var Message|null */
        return $userMessageQb->getQuery()
            ->getOneOrNullResult();
    }
}
