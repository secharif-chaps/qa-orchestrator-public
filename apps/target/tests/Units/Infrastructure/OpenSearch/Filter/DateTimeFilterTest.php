<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\OpenSearch\Filter;

use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Metadata\ResourceClassResolverInterface;
use App\Domain\Document\Document;
use App\Infrastructure\OpenSearch\Filter\DateTimeFilter;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

class DateTimeFilterTest extends TestCase
{
    private DateTimeFilter $filter;
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

        $this->filter = new DateTimeFilter(
            $this->propertyNameCollectionFactory,
            $this->propertyMetadataFactory,
            $this->resourceClassResolver,
            $this->nameConverter,
            [
                'createdAt' => 'datetime',
                'updatedAt' => 'datetime',
            ]
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
        $resourceClass = Document::class;
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
        $resourceClass = Document::class;
        $context = [
            'filters' => [],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $this->assertEquals($clauseBody, $result);
    }

    public function testApplyWithAfterFilterAddsRangeQuery(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'createdAt' => [
                    'after' => '2024-01-01T00:00:00Z',
                ],
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'bool' => [
                'must' => [
                    [
                        'range' => [
                            'createdAt' => [
                                'gte' => '2024-01-01T00:00:00Z',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithBeforeFilterAddsRangeQuery(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'createdAt' => [
                    'before' => '2024-12-31T23:59:59Z',
                ],
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'bool' => [
                'must' => [
                    [
                        'range' => [
                            'createdAt' => [
                                'lte' => '2024-12-31T23:59:59Z',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithStrictlyAfterFilterAddsRangeQuery(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'createdAt' => [
                    'strictly_after' => '2024-01-01T00:00:00Z',
                ],
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'bool' => [
                'must' => [
                    [
                        'range' => [
                            'createdAt' => [
                                'gt' => '2024-01-01T00:00:00Z',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithStrictlyBeforeFilterAddsRangeQuery(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'createdAt' => [
                    'strictly_before' => '2024-12-31T23:59:59Z',
                ],
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'bool' => [
                'must' => [
                    [
                        'range' => [
                            'createdAt' => [
                                'lt' => '2024-12-31T23:59:59Z',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithMultipleDateFiltersAddsMultipleRangeQueries(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'createdAt' => [
                    'after' => '2024-01-01T00:00:00Z',
                    'before' => '2024-12-31T23:59:59Z',
                ],
                'updatedAt' => [
                    'strictly_after' => '2024-06-01T00:00:00Z',
                ],
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'bool' => [
                'must' => [
                    [
                        'range' => [
                            'createdAt' => [
                                'gte' => '2024-01-01T00:00:00Z',
                                'lte' => '2024-12-31T23:59:59Z',
                            ],
                        ],
                    ],
                    [
                        'range' => [
                            'updatedAt' => [
                                'gt' => '2024-06-01T00:00:00Z',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithNonStringFilterValuesIgnoresThem(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'createdAt' => [
                    'after' => 12345, // Non-string value
                    'before' => ['invalid'], // Non-string value
                ],
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $this->assertEquals($clauseBody, $result);
    }

    public function testApplyWithNonArrayFilterValuesIgnoresThem(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'createdAt' => 'not_an_array',
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $this->assertEquals($clauseBody, $result);
    }

    public function testApplyWithEmptyRangeClauseReturnsOriginalClauseBody(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'createdAt' => [
                    'invalid_key' => '2024-01-01T00:00:00Z',
                ],
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $this->assertEquals($clauseBody, $result);
    }

    public function testApplyWithInvalidDateTimeFormatReturnsAsIs(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'createdAt' => [
                    'after' => 'invalid-date-format',
                ],
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'bool' => [
                'must' => [
                    [
                        'range' => [
                            'createdAt' => [
                                'gte' => 'invalid-date-format', // Returns as-is when parsing fails
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithVariousDateTimeFormats(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'createdAt' => [
                    'after' => '2024-01-01 12:30:45', // Common format
                ],
                'updatedAt' => [
                    'before' => '2024-12-31', // Date only
                ],
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'bool' => [
                'must' => [
                    [
                        'range' => [
                            'createdAt' => [
                                'gte' => '2024-01-01T12:30:45Z',
                            ],
                        ],
                    ],
                    [
                        'range' => [
                            'updatedAt' => [
                                'lte' => '2024-12-31T00:00:00Z',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithExistingMustClauseAddsToIt(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [
                    [
                        'term' => [
                            'status' => 'active',
                        ],
                    ],
                ],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'createdAt' => [
                    'after' => '2024-01-01T00:00:00Z',
                ],
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
                            'status' => 'active',
                        ],
                    ],
                    [
                        'range' => [
                            'createdAt' => [
                                'gte' => '2024-01-01T00:00:00Z',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetDescriptionReturnsCorrectDescription(): void
    {
        // Arrange
        $resourceClass = Document::class;

        // Act
        $result = $this->filter->getDescription($resourceClass);

        // Assert
        $expected = [
            'createdAt[after]' => [
                'property' => 'createdAt',
                'type' => 'string',
                'required' => false,
                'description' => 'Filter createdAt after the given date (inclusive). Use ISO 8601 format.',
            ],
            'createdAt[before]' => [
                'property' => 'createdAt',
                'type' => 'string',
                'required' => false,
                'description' => 'Filter createdAt before the given date (inclusive). Use ISO 8601 format.',
            ],
            'createdAt[strictly_after]' => [
                'property' => 'createdAt',
                'type' => 'string',
                'required' => false,
                'description' => 'Filter createdAt strictly after the given date (exclusive). Use ISO 8601 format.',
            ],
            'createdAt[strictly_before]' => [
                'property' => 'createdAt',
                'type' => 'string',
                'required' => false,
                'description' => 'Filter createdAt strictly before the given date (exclusive). Use ISO 8601 format.',
            ],
            'updatedAt[after]' => [
                'property' => 'updatedAt',
                'type' => 'string',
                'required' => false,
                'description' => 'Filter updatedAt after the given date (inclusive). Use ISO 8601 format.',
            ],
            'updatedAt[before]' => [
                'property' => 'updatedAt',
                'type' => 'string',
                'required' => false,
                'description' => 'Filter updatedAt before the given date (inclusive). Use ISO 8601 format.',
            ],
            'updatedAt[strictly_after]' => [
                'property' => 'updatedAt',
                'type' => 'string',
                'required' => false,
                'description' => 'Filter updatedAt strictly after the given date (exclusive). Use ISO 8601 format.',
            ],
            'updatedAt[strictly_before]' => [
                'property' => 'updatedAt',
                'type' => 'string',
                'required' => false,
                'description' => 'Filter updatedAt strictly before the given date (exclusive). Use ISO 8601 format.',
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetDescriptionWithEmptyProperties(): void
    {
        // Arrange
        $filter = new DateTimeFilter(
            $this->propertyNameCollectionFactory,
            $this->propertyMetadataFactory,
            $this->resourceClassResolver,
            $this->nameConverter,
            []
        );

        $resourceClass = Document::class;

        // Act
        $result = $filter->getDescription($resourceClass);

        // Assert
        $this->assertEquals([], $result);
    }

    public function testGetDescriptionWithNullProperties(): void
    {
        // Arrange
        $filter = new DateTimeFilter(
            $this->propertyNameCollectionFactory,
            $this->propertyMetadataFactory,
            $this->resourceClassResolver,
            $this->nameConverter,
            null
        );

        $resourceClass = Document::class;

        // Act
        $result = $filter->getDescription($resourceClass);

        // Assert
        $this->assertEquals([], $result);
    }

    public function testApplyWithSinglePropertyFilter(): void
    {
        // Arrange
        $filter = new DateTimeFilter(
            $this->propertyNameCollectionFactory,
            $this->propertyMetadataFactory,
            $this->resourceClassResolver,
            $this->nameConverter,
            [
                'createdAt' => 'datetime',
            ]
        );

        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'createdAt' => [
                    'after' => '2024-01-01T00:00:00Z',
                    'before' => '2024-12-31T23:59:59Z',
                ],
            ],
        ];

        // Act
        $result = $filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'bool' => [
                'must' => [
                    [
                        'range' => [
                            'createdAt' => [
                                'gte' => '2024-01-01T00:00:00Z',
                                'lte' => '2024-12-31T23:59:59Z',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithMixedValidAndInvalidFilters(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'must' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'createdAt' => [
                    'after' => '2024-01-01T00:00:00Z',
                    'invalid_key' => '2024-12-31T23:59:59Z',
                ],
                'updatedAt' => [
                    'before' => '2024-06-01T00:00:00Z',
                ],
                'nonExistentProperty' => [
                    'after' => '2024-01-01T00:00:00Z',
                ],
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'bool' => [
                'must' => [
                    [
                        'range' => [
                            'createdAt' => [
                                'gte' => '2024-01-01T00:00:00Z',
                            ],
                        ],
                    ],
                    [
                        'range' => [
                            'updatedAt' => [
                                'lte' => '2024-06-01T00:00:00Z',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }
}
