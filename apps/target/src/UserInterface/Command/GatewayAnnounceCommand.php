<?php

declare(strict_types=1);

namespace App\UserInterface\Command;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use App\Domain\Shared\GatewayRegistryClientInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\SerializerInterface;

#[AsCommand(
    name: 'app:gateway:announce',
    description: 'Announce this module to the global-service gateway registry',
)]
class GatewayAnnounceCommand extends Command
{
    public function __construct(
        private readonly GatewayRegistryClientInterface $registryClient,
        private readonly OpenApiFactoryInterface $openApiFactory,
        private readonly SerializerInterface $serializer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $schemaHash = $this->computeOpenApiHash();
        $action = $this->registryClient->announce($schemaHash);

        match ($action) {
            'skipped' => $io->note('Registry announce skipped (missing configuration).'),
            'error' => $io->warning('Registry announce failed (gateway will detect via healthcheck).'),
            default => $io->success(\sprintf('Registry announce completed: %s', $action)),
        };

        return Command::SUCCESS;
    }

    private function computeOpenApiHash(): string
    {
        $openApi = $this->openApiFactory->__invoke();
        $json = $this->serializer->serialize($openApi, 'json');
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        $this->sortRecursive($data);

        return hash('sha256', json_encode($data, \JSON_THROW_ON_ERROR));
    }

    private function sortRecursive(mixed &$data): void
    {
        if (\is_array($data)) {
            if (!array_is_list($data)) {
                ksort($data);
            }
            foreach ($data as &$value) {
                $this->sortRecursive($value);
            }
        }
    }
}
