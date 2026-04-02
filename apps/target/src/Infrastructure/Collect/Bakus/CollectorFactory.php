<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Bakus;

use App\Domain\Collect\Exception\InvalidCollectorDefinitionException;
use App\Domain\Collect\Exception\NotSupportedCollectorException;
use App\Domain\Collect\ValueObject\BooleanParameter;
use App\Domain\Collect\ValueObject\ChoiceParameter;
use App\Domain\Collect\ValueObject\Collector;
use App\Domain\Collect\ValueObject\IntegerParameter;
use App\Domain\Collect\ValueObject\Parameter;
use App\Domain\Collect\ValueObject\StringParameter;
use App\Domain\Source\SourceType;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

class CollectorFactory
{
    public function __construct(
        /**
         * @var array<string, list<string>>
         */
        #[Autowire('%app.bakus.collector_mapping%')]
        private readonly array $mapping,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createFromBakusResponse(array $data): Collector
    {
        try {
            $validatedData = $this->validateCollectorData($data);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidCollectorDefinitionException(\sprintf(
                'Invalid collector definition, %s. CollectorObject is: %s',
                $e->getMessage(),
                json_encode($data, \JSON_THROW_ON_ERROR),
            ), previous: $e, );
        }

        if (!\array_key_exists($validatedData['name'], $this->mapping)) {
            throw new NotSupportedCollectorException(\sprintf(
                'Collector "%s" is not supported',
                $validatedData['name']
            ));
        }

        try {
            $parameters = $this->createParameters($this->ensureArray($validatedData['parameters'] ?? []));
        } catch (\InvalidArgumentException $e) {
            throw new InvalidCollectorDefinitionException(\sprintf(
                'Invalid parameters for collector "%s": %s. CollectorObject is: %s',
                $validatedData['name'],
                $e->getMessage(),
                json_encode($data, \JSON_THROW_ON_ERROR),
            ), previous: $e, );
        }

        $supportedSourceTypes = $this->mapping[$validatedData['name']];
        if (empty($supportedSourceTypes)) {
            throw new InvalidCollectorDefinitionException(\sprintf(
                'Collector "%s" has no supported source types defined, it\'s incorrect behavior',
                $validatedData['name'],
            ));
        }

        try {
            $supportedSourceTypes = array_map(
                static fn (string $type) => SourceType::from($type),
                $supportedSourceTypes,
            );
        } catch (\ValueError $e) {
            throw new InvalidCollectorDefinitionException(\sprintf(
                'Collector "%s" has invalid source type in mapping: %s',
                $validatedData['name'],
                $e->getMessage(),
            ), previous: $e, );
        }

        return new Collector(
            name: $validatedData['name'],
            displayName: $this->normalizeStringArray($this->ensureArray($validatedData['display_name'] ?? [])),
            description: $this->normalizeStringArray($this->ensureArray($validatedData['description'] ?? [])),
            type: $validatedData['type'],
            version: $validatedData['version'],
            iconUrl: $validatedData['icon_url'] ?? '',
            parameters: $parameters,
            returnTypes: $this->ensureStringArray($validatedData['return_types'] ?? []),
            supportStream: $validatedData['stream'] ?? false,
            supportBatch: $validatedData['batch'] ?? true,
            supportedSourceTypes: $supportedSourceTypes,
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     name: non-empty-string,
     *     type: non-empty-string,
     *     version: non-empty-string,
     *     display_name?: array<string, string>,
     *     description?: array<string, string>,
     *     icon_url?: ?string,
     *     parameters?: array<string, array<string, mixed>>,
     *     return_types?: list<non-empty-string>,
     *     stream?: bool,
     *     batch?: bool,
     * }
     */
    private function validateCollectorData(array $data): array
    {
        Assert::keyExists($data, 'name', 'Collector name is required');
        Assert::stringNotEmpty($data['name'], 'Collector name cannot be empty');

        Assert::keyExists($data, 'type', 'Collector type is required');
        Assert::stringNotEmpty($data['type'], 'Collector type cannot be empty');

        Assert::keyExists($data, 'version', 'Collector version is required');
        Assert::stringNotEmpty($data['version'], 'Collector version cannot be empty');

        if (isset($data['display_name'])) {
            Assert::isArray($data['display_name'], 'display_name must be an array');
        }

        if (isset($data['description'])) {
            Assert::isArray($data['description'], 'description must be an array');
        }

        if (isset($data['icon_url'])) {
            Assert::nullOrString($data['icon_url'], 'icon_url must be a string or null');
        }

        if (isset($data['parameters'])) {
            Assert::isArray($data['parameters'], 'parameters must be an array');
        }

        if (isset($data['return_types'])) {
            Assert::isArray($data['return_types'], 'return_types must be an array');
            Assert::allString($data['return_types'], 'All return_types must be strings');
            Assert::allNotEmpty($data['return_types'], 'All return_types must be non-empty strings');
            if (!array_is_list($data['return_types'])) {
                $data['return_types'] = array_values($data['return_types']);
            }
        }

        if (isset($data['stream'])) {
            Assert::boolean($data['stream'], 'stream must be a boolean');
        }

        if (isset($data['batch'])) {
            Assert::boolean($data['batch'], 'batch must be a boolean');
        }

        /** @var array{name: non-empty-string, type: non-empty-string, version: non-empty-string, display_name?: array<string, string>, description?: array<string, string>, icon_url?: string|null, parameters?: array<string, array<string, mixed>>, return_types?: list<non-empty-string>, stream?: bool, batch?: bool, ...} $data */
        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, non-empty-string>
     */
    private function normalizeStringArray(array $data): array
    {
        if (empty($data)) {
            return [];
        }

        $normalized = [];
        foreach ($data as $key => $value) {
            if (empty($key)) {
                $this->logger->warning('Invalid key in string array', [
                    'key' => $key,
                ]);
                continue;
            }

            if (\is_string($value) && !empty($value)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $parametersData
     *
     * @return list<Parameter>
     */
    private function createParameters(array $parametersData): array
    {
        $parameters = [];

        foreach ($parametersData as $name => $paramData) {
            if (empty($name) || !\is_array($paramData)) {
                $this->logger->warning('Invalid parameter data format', [
                    'parameter_name' => $name,
                    'parameter_data' => $paramData,
                ]);
                continue;
            }

            try {
                /** @var array<string, mixed> $paramData */
                $parameter = $this->createParameter($name, $paramData);
                $parameters[] = $parameter;
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to create parameter from Bakus data', [
                    'parameter_name' => $name,
                    'parameter_data' => $paramData,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $parameters;
    }

    /**
     * @param non-empty-string     $name
     * @param array<string, mixed> $paramData
     */
    private function createParameter(string $name, array $paramData): Parameter
    {
        Assert::keyExists($paramData, 'type', 'Parameter type is required');
        Assert::stringNotEmpty($paramData['type'], 'Parameter type cannot be empty');

        if (isset($paramData['required'])) {
            Assert::boolean($paramData['required'], 'Parameter required must be a boolean');
        }

        $helpArray = $this->normalizeStringArray($this->ensureArray($paramData['help'] ?? []));
        $baseArguments = [
            'name' => $name,
            'label' => $this->extractParameterLabel(
                $paramData,
                $name
            ), // label are not implemented in Bakus, using help text as fallback
            'required' => (bool) ($paramData['required'] ?? false),
            'help' => !empty($helpArray) ? $helpArray : null,
            'default' => $paramData['default'] ?? null,
            'type' => $paramData['type'],
        ];

        return match ($paramData['type']) {
            'boolean' => $this->createBooleanParameter($baseArguments),
            'string' => $this->createStringParameter($baseArguments, $paramData),
            'integer' => $this->createIntegerParameter($baseArguments, $paramData),
            'choice' => $this->createChoiceParameter($baseArguments, $paramData),
            default => $this->createGenericParameter($baseArguments),
        };
    }

    /**
     * @param array{
     *     name: non-empty-string,
     *     label: string,
     *     required: bool,
     *     help: array<string, string>|null,
     *     default: mixed,
     *     type: non-empty-string,
     * } $baseArguments
     */
    private function createBooleanParameter(array $baseArguments): BooleanParameter
    {
        return new BooleanParameter(
            name: $baseArguments['name'],
            label: $baseArguments['label'],
            required: $baseArguments['required'],
            help: $baseArguments['help'],
            default: $baseArguments['default'],
            type: $baseArguments['type'],
        );
    }

    /**
     * @param array{
     *     name: non-empty-string,
     *     label: string,
     *     required: bool,
     *     help: array<string, string>|null,
     *     default: mixed,
     *     type: non-empty-string,
     * } $baseArguments
     * @param array<string, mixed> $paramData
     */
    private function createStringParameter(array $baseArguments, array $paramData): StringParameter
    {
        return new StringParameter(
            name: $baseArguments['name'],
            label: $baseArguments['label'],
            required: $baseArguments['required'],
            help: $baseArguments['help'],
            default: $baseArguments['default'],
            type: $baseArguments['type'],
            minLength: isset($paramData['min_length']) && is_numeric(
                $paramData['min_length']
            ) ? (int) $paramData['min_length'] : null,
            maxLength: isset($paramData['max_length']) && is_numeric(
                $paramData['max_length']
            ) ? (int) $paramData['max_length'] : null,
            pattern: isset($paramData['regex']) && \is_string($paramData['regex']) ? $paramData['regex'] : null,
        );
    }

    /**
     * @param array{
     *     name: non-empty-string,
     *     label: string,
     *     required: bool,
     *     help: array<string, string>|null,
     *     default: mixed,
     *     type: non-empty-string,
     * } $baseArguments
     * @param array<string, mixed> $paramData
     */
    private function createIntegerParameter(array $baseArguments, array $paramData): IntegerParameter
    {
        return new IntegerParameter(
            name: $baseArguments['name'],
            label: $baseArguments['label'],
            required: $baseArguments['required'],
            help: $baseArguments['help'],
            default: $baseArguments['default'],
            type: $baseArguments['type'],
            min: isset($paramData['min']) && is_numeric($paramData['min']) ? (int) $paramData['min'] : null,
            max: isset($paramData['max']) && is_numeric($paramData['max']) ? (int) $paramData['max'] : null,
        );
    }

    /**
     * @param array{
     *     name: non-empty-string,
     *     label: string,
     *     required: bool,
     *     help: array<string, string>|null,
     *     default: mixed,
     *     type: non-empty-string,
     * } $baseArguments
     * @param array<string, mixed> $paramData
     */
    private function createChoiceParameter(array $baseArguments, array $paramData): ChoiceParameter
    {
        return new ChoiceParameter(
            name: $baseArguments['name'],
            label: $baseArguments['label'],
            required: $baseArguments['required'],
            help: $baseArguments['help'],
            default: $baseArguments['default'],
            type: $baseArguments['type'],
            choices: $this->ensureStringArray($paramData['choices'] ?? []),
        );
    }

    /**
     * @param array{
     *     name: non-empty-string,
     *     label: string,
     *     required: bool,
     *     help: array<string, string>|null,
     *     default: mixed,
     *     type: non-empty-string,
     * } $baseArguments
     */
    private function createGenericParameter(array $baseArguments): Parameter
    {
        $this->logger->info(
            \sprintf('Creating generic parameter for unsupported type "%s"', $baseArguments['type']),
            [
                'parameter_name' => $baseArguments['name'],
            ],
        );

        return new Parameter(
            name: $baseArguments['name'],
            label: $baseArguments['label'],
            required: $baseArguments['required'],
            help: $baseArguments['help'],
            default: $baseArguments['default'],
            type: $baseArguments['type'],
        );
    }

    /**
     * @param array<string, mixed> $paramData
     */
    private function extractParameterLabel(array $paramData, string $name): string
    {
        if (isset($paramData['help']) && \is_array($paramData['help'])) {
            foreach (['en', 'fr'] as $lang) {
                if (!empty($paramData['help'][$lang]) && \is_string($paramData['help'][$lang])) {
                    return $paramData['help'][$lang];
                }
            }
        }

        return $name;
    }

    /**
     * @return array<string, mixed>
     */
    private function ensureArray(mixed $value): array
    {
        return \is_array($value) ? $value : [];
    }

    /**
     * @return list<string>
     */
    private function ensureStringArray(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (\is_string($item)) {
                $result[] = $item;
            }
        }

        return $result;
    }
}
