<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Bakus;

use App\Application\Collect\Auth\GenerateCollectTaskTokenAction;
use App\Application\Collect\Collector\GetCollectorListAction;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\Exception\NotSupportedCollectorException;
use App\Domain\Collect\ValueObject\Collector;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class BakusCollectTaskMapper
{
    use HandleTrait;
    private const int RESULT_LIMIT = 100_000_000; // very high limit to effectively disable it

    /**
     * @var list<Collector>|null
     */
    private ?array $collectors = null;

    public function __construct(
        #[Autowire('%app.bakus.callback_type%')]
        private readonly string $callbackType,
        #[Autowire('%app.bakus.callback_url%')]
        private readonly ?string $callbackUrl,
        #[Autowire('%app.bakus.callback_verify_ssl%')]
        private readonly ?bool $callbackVerifySsl,
        /**
         * @var list<string>|null
         */
        #[Autowire('%app.bakus.callback_proxy_tags%')]
        private readonly ?array $callbackProxyTags,
        /**
         * @var array<string, array<string, mixed>>
         */
        #[Autowire('%app.bakus.collector_default_parameters%')]
        private readonly array $collectorDefaultParameters,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @return Collector[]
     */
    private function getCollectors(): array
    {
        if (null === $this->collectors) {
            /** @var list<Collector> $collectors */
            $collectors = $this->handle(new GetCollectorListAction());
            $this->collectors = $collectors;
        }

        return $this->collectors;
    }

    /**
     * @return Collector[]
     */
    private function findSupportedCollectors(SourceType $sourceType): array
    {
        return array_filter(
            $this->getCollectors(),
            static fn (Collector $collector) => $collector->isSupportedSourceType($sourceType),
        );
    }

    /**
     * @return list<array{
     *     name: string,
     *     version: string,
     *     key: string,
     *     value: string,
     *     parameters: array<string, mixed>,
     *     max_cache_age: null,
     * }>
     */
    private function mapSourceToBakusCollectorModule(Source $source): array
    {
        $collectors = $this->findSupportedCollectors($source->getType());
        if (empty($collectors)) {
            throw new NotSupportedCollectorException(
                'No supported collectors found for source type: ' . $source->getType()->value,
            );
        }

        $bakusCollectorModules = [];

        foreach ($collectors as $collector) {
            $bakusParameters = [];
            foreach ($collector->parameters as $parameter) {
                if ($source->hasParameter($parameter->name)) {
                    $bakusParameters[$parameter->name] = $source->getParameter($parameter->name);
                    continue;
                }

                if (isset($this->collectorDefaultParameters[$collector->name][$parameter->name])) {
                    $bakusParameters[$parameter->name] = $this->collectorDefaultParameters[$collector->name][$parameter->name];
                    continue;
                }

                if ($parameter->required) {
                    if (null !== $parameter->default) {
                        $bakusParameters[$parameter->name] = $parameter->default;
                        continue;
                    }

                    throw new NotSupportedCollectorException(
                        'Required parameter "' . $parameter->name . '" is missing for collector: ' . $collector->name,
                    );
                }
            }

            $bakusCollectorModules[] = [
                'name' => $collector->name,
                'version' => $collector->version,
                'key' => $collector->type,
                'value' => $source->getUrl(),
                'parameters' => $bakusParameters,
                'max_cache_age' => null,
            ];
        }

        return $bakusCollectorModules;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCallbackBakus(CollectTask $collectTask): array
    {
        $callback = [
            'type' => $this->callbackType,
            'include_status_update' => true,
            'result_format' => 'raw',
        ];

        if (null !== $this->callbackUrl) {
            $collectTaskId = $collectTask->getId();
            if (null === $collectTaskId) {
                throw new \RuntimeException('Collect task ID is required for callback authentication');
            }

            /** @var string $token */
            $token = $this->handle(new GenerateCollectTaskTokenAction($collectTaskId));

            $callback['url'] = $this->callbackUrl;
            if (false === $this->callbackVerifySsl) {
                $callback['verify_ssl'] = false;
            }

            $callback['headers'] = [
                'Authorization' => \sprintf('Bearer %s', $token),
            ];

            if (\is_array($this->callbackProxyTags) && \count($this->callbackProxyTags) > 0) {
                $callback['proxy_tags'] = $this->callbackProxyTags;
            }
        }

        return $callback;
    }

    /**
     * @return array<string, mixed>
     */
    public function mapCollectTaskToBakusQuery(CollectTask $collectTask): array
    {
        try {
            $bakusCollectorModules = $this->mapSourceToBakusCollectorModule($collectTask->getSource());
        } catch (NotSupportedCollectorException $e) {
            throw new \RuntimeException('Failed to map collect task to Bakus query: ' . $e->getMessage(), 0, $e);
        }

        return [
            'collector_modules' => $bakusCollectorModules,
            'postprocess_modules' => [],
            'mode' => 'stream',
            'result_limit' => self::RESULT_LIMIT,
            'callback' => $this->buildCallbackBakus($collectTask),
        ];
    }
}
