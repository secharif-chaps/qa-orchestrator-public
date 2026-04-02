<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\OpenSearch\Filter;

use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Metadata\ResourceClassResolverInterface;
use ApiPlatform\OpenApi\Model\Parameter;
use App\Domain\WatchFileEvent\WatchFileEvent;
use App\Infrastructure\OpenSearch\Filter\NestedActorFilter;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

class NestedActorFilterTest extends TestCase
{
    private NestedActorFilter $filter;
    private PropertyNameCollectionFactoryInterface&Stub $propertyNameCollectionFactory;
    private PropertyMetadataFactoryInterface&Stub $propertyMetadataFactory;
    private ResourceClassResolverInterface&Stub $resourceClassResolver;
    private NameConverterInterface&Stub $nameConverter;

    protected function setUp(): void
    {
        $this->propertyNameCollectionFactory = $this->createStub(PropertyNameCollectionFactoryInterface::class);
        $this->propertyMetadataFactory = $this->createStub(PropertyMetadataFactoryInterface::class);
        $this->resourceClassResolver = $this->createStub(ResourceClassResolverInterface::class);
        $this->nameConverter = $this->createStub(NameConverterInterface::class);

        $this->filter = new NestedActorFilter(
            $this->propertyNameCollectionFactory,
            $this->propertyMetadataFactory,
            $this->resourceClassResolver,
            $this->nameConverter,
        );
    }

    public function testApplyWithNoFiltersReturnsOriginalClauseBody(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = WatchFileEvent::class;
        $context = [];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $this->assertEquals($clauseBody, $result);
    }

    public function testApplyWithEmptyFiltersReturnsOriginalClauseBody(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = WatchFileEvent::class;
        $context = [
            'filters' => [],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $this->assertEquals($clauseBody, $result);
    }

    public function testApplyWithNoActorIdFilterReturnsOriginalClauseBody(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = WatchFileEvent::class;
        $context = [
            'filters' => [
                'otherFilter' => 'value',
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $this->assertEquals($clauseBody, $result);
    }

    public function testApplyWithWrongResourceClassReturnsOriginalClauseBody(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = 'App\Domain\SomeOtherClass';
        $context = [
            'filters' => [
                'actors.id' => 'actor-123',
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $this->assertEquals($clauseBody, $result);
    }

    public function testApplyWithSingleActorIdAddsNestedTermQuery(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = WatchFileEvent::class;
        $context = [
            'filters' => [
                'actors.id' => 'actor-123',
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'bool' => [
                'must' => [
                    [
                        'nested' => [
                            'path' => 'actors',
                            'query' => [
                                'term' => [
                                    'actors.id' => 'actor-123',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithMultipleActorIdsAddsNestedTermsQuery(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = WatchFileEvent::class;
        $context = [
            'filters' => [
                'actors.id' => ['actor-123', 'actor-456', 'actor-789'],
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'bool' => [
                'must' => [
                    [
                        'nested' => [
                            'path' => 'actors',
                            'query' => [
                                'terms' => [
                                    'actors.id' => ['actor-123', 'actor-456', 'actor-789'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithEmptyStringActorIdReturnsOriginalClauseBody(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = WatchFileEvent::class;
        $context = [
            'filters' => [
                'actors.id' => '',
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $this->assertEquals($clauseBody, $result);
    }

    public function testApplyWithWhitespaceOnlyActorIdReturnsOriginalClauseBody(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = WatchFileEvent::class;
        $context = [
            'filters' => [
                'actors.id' => '   ',
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $this->assertEquals($clauseBody, $result);
    }

    public function testApplyWithEmptyArrayReturnsOriginalClauseBody(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = WatchFileEvent::class;
        $context = [
            'filters' => [
                'actors.id' => [],
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $this->assertEquals($clauseBody, $result);
    }

    public function testApplyWithArrayContainingOnlyEmptyStringsReturnsOriginalClauseBody(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = WatchFileEvent::class;
        $context = [
            'filters' => [
                'actors.id' => ['', '   ', ''],
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $this->assertEquals($clauseBody, $result);
    }

    public function testApplyWithArrayContainingMixedValidAndEmptyStringsFiltersOutEmpty(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = WatchFileEvent::class;
        $context = [
            'filters' => [
                'actors.id' => ['actor-123', '', 'actor-456', '   '],
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'bool' => [
                'must' => [
                    [
                        'nested' => [
                            'path' => 'actors',
                            'query' => [
                                'terms' => [
                                    'actors.id' => ['actor-123', 'actor-456'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithNonStringNonArrayValueReturnsOriginalClauseBody(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = WatchFileEvent::class;
        $context = [
            'filters' => [
                'actors.id' => 123, // Invalid type
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $this->assertEquals($clauseBody, $result);
    }

    public function testApplyWithExistingMustClauseAddsToIt(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [
                    [
                        'term' => [
                            'watchFile.id' => 'watch-file-123',
                        ],
                    ],
                ],
            ],
        ];
        $resourceClass = WatchFileEvent::class;
        $context = [
            'filters' => [
                'actors.id' => 'actor-123',
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'bool' => [
                'must' => [
                    [
                        'term' => [
                            'watchFile.id' => 'watch-file-123',
                        ],
                    ],
                    [
                        'nested' => [
                            'path' => 'actors',
                            'query' => [
                                'term' => [
                                    'actors.id' => 'actor-123',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithSingleActorInArrayUsesTermQuery(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = WatchFileEvent::class;
        $context = [
            'filters' => [
                'actors.id' => ['actor-123'], // Single item array
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert - Should use 'term' instead of 'terms' for single value
        $expected = [
            'bool' => [
                'must' => [
                    [
                        'nested' => [
                            'path' => 'actors',
                            'query' => [
                                'term' => [
                                    'actors.id' => 'actor-123',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithMissingBoolMustReturnsOriginalClauseBody(): void
    {
        // Arrange
        $clauseBody = [
            'query' => [
                'match_all' => [],
            ],
        ];
        $resourceClass = WatchFileEvent::class;
        $context = [
            'filters' => [
                'actors.id' => 'actor-123',
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert - Should return unchanged because bool.must is not present
        $this->assertEquals($clauseBody, $result);
    }

    public function testGetDescriptionReturnsCorrectDescriptionForBothVariants(): void
    {
        // Arrange
        $resourceClass = WatchFileEvent::class;

        // Act
        $result = $this->filter->getDescription($resourceClass);

        // Assert
        $this->assertArrayHasKey('actors.id', $result);
        $this->assertArrayHasKey('actors.id[]', $result);

        // Check single value variant
        $singleVariant = $result['actors.id'];
        $this->assertIsArray($singleVariant);
        $this->assertArrayHasKey('property', $singleVariant);
        $this->assertArrayHasKey('type', $singleVariant);
        $this->assertArrayHasKey('required', $singleVariant);
        $this->assertArrayHasKey('openapi', $singleVariant);
        $this->assertEquals('actors.id', $singleVariant['property']);
        $this->assertEquals('string', $singleVariant['type']);
        $this->assertFalse($singleVariant['required']);
        $this->assertInstanceOf(Parameter::class, $singleVariant['openapi']);

        // Check array variant
        $arrayVariant = $result['actors.id[]'];
        $this->assertIsArray($arrayVariant);
        $this->assertArrayHasKey('property', $arrayVariant);
        $this->assertArrayHasKey('type', $arrayVariant);
        $this->assertArrayHasKey('required', $arrayVariant);
        $this->assertArrayHasKey('openapi', $arrayVariant);
        $this->assertEquals('actors.id', $arrayVariant['property']);
        $this->assertEquals('array', $arrayVariant['type']);
        $this->assertFalse($arrayVariant['required']);
        $this->assertInstanceOf(Parameter::class, $arrayVariant['openapi']);
    }

    public function testApplyWithUuidFormatActorIds(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = WatchFileEvent::class;
        $context = [
            'filters' => [
                'actors.id' => ['1f0c6422-ce27-6afc-83d7-b328ebd3dbcf', '2a1d7533-df38-7bfd-94e8-c439fce4ecd0'],
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'bool' => [
                'must' => [
                    [
                        'nested' => [
                            'path' => 'actors',
                            'query' => [
                                'terms' => [
                                    'actors.id' => [
                                        '1f0c6422-ce27-6afc-83d7-b328ebd3dbcf',
                                        '2a1d7533-df38-7bfd-94e8-c439fce4ecd0',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }
}
