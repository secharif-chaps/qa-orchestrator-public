<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile;

use App\Application\WatchFile\GetWatchFileTrait;
use App\Domain\Organisation\Organisation;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\HandleTrait;

#[UsesTrait(GetWatchFileTrait::class)]
class GetWatchFileTraitTest extends TestCase
{
    use EntityUtilsTrait;
    private NullWatchFileGateway $watchFileGateway;
    private object $traitObject;

    protected function setUp(): void
    {
        $this->watchFileGateway = new NullWatchFileGateway();

        $this->traitObject = new class {
            use GetWatchFileTrait {
                GetWatchFileTrait::getWatchFile as public;
            }
            use HandleTrait;
            private ?LoggerInterface $logger;

            public function __construct(?LoggerInterface $logger = null)
            {
                $this->logger = $logger;
            }

            public function getLogger(): ?LoggerInterface
            {
                return $this->logger;
            }
        };

        $this->traitObject->setWatchFileGateway($this->watchFileGateway);
    }

    public function testGetWatchFileWithoutUserReturnsWatchFile(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');

        $this->watchFileGateway->save($watchFile);

        $result = $this->traitObject->getWatchFile('watch_file_id');

        $this->assertSame($watchFile, $result);
    }

    public function testGetWatchFileWithEmptyIdThrowsException(): void
    {
        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Invalid watch file ID provided.');

        $this->traitObject->getWatchFile('');
    }

    public function testGetWatchFileNotFoundThrowsException(): void
    {
        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('WatchFile not found');

        $this->traitObject->getWatchFile('nonexistent_id');
    }
}
