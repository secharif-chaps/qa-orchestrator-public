<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\OpenSearch\Filter;

use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Metadata\ResourceClassResolverInterface;
use App\Domain\Document\Document;
use App\Infrastructure\OpenSearch\Filter\CombinedMatchFilter;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

class CombinedMatchFilterTest extends TestCase
{
    private CombinedMatchFilter $filter;
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

        $this->filter = new CombinedMatchFilter(
            $this->propertyNameCollectionFactory,
            $this->propertyMetadataFactory,
            $this->resourceClassResolver,
            $this->nameConverter,
            [
                'title' => 'title',
                'description' => 'description',
                'content' => 'content',
            ],
            'search'
        );
    }

    public function testApplyWithNoFiltersReturnsOriginalClauseBody(): void
    {
        // Arrange
        $clauseBody = [
            'query' => [
                'match_all' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $this->assertEquals($clauseBody, $result);
    }

    public function testApplyWithEmptySearchFilterReturnsOriginalClauseBody(): void
    {
        // Arrange
        $clauseBody = [
            'query' => [
                'match_all' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'search' => '',
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $this->assertEquals($clauseBody, $result);
    }

    public function testApplyWithNonArrayFiltersReturnsOriginalClauseBody(): void
    {
        // Arrange
        $clauseBody = [
            'query' => [
                'match_all' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => 'not_an_array',
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $this->assertEquals($clauseBody, $result);
    }

    public function testApplyWithSearchFilterAddsMultiMatchQuery(): void
    {
        // Arrange
        $clauseBody = [
            'query' => [
                'match_all' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'search' => 'test query',
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'query' => [
                'match_all' => [],
            ],
            'bool' => [
                'must' => [
                    [
                        'multi_match' => [
                            'query' => 'test query',
                            'fields' => ['title', 'description', 'content'],
                            'type' => 'best_fields',
                            'fuzziness' => 'AUTO',
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithExistingBoolClauseAddsToMustArray(): void
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
                'search' => 'test query',
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
                        'multi_match' => [
                            'query' => 'test query',
                            'fields' => ['title', 'description', 'content'],
                            'type' => 'best_fields',
                            'fuzziness' => 'AUTO',
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithExistingBoolClauseWithoutMustCreatesMustArray(): void
    {
        // Arrange
        $clauseBody = [
            'bool' => [
                'should' => [
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
                'search' => 'test query',
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'bool' => [
                'should' => [
                    [
                        'term' => [
                            'status' => 'active',
                        ],
                    ],
                ],
                'must' => [
                    [
                        'multi_match' => [
                            'query' => 'test query',
                            'fields' => ['title', 'description', 'content'],
                            'type' => 'best_fields',
                            'fuzziness' => 'AUTO',
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithCustomFieldName(): void
    {
        // Arrange
        $filter = new CombinedMatchFilter(
            $this->propertyNameCollectionFactory,
            $this->propertyMetadataFactory,
            $this->resourceClassResolver,
            $this->nameConverter,
            [
                'title' => 'title',
            ],
            'customSearch'
        );

        $clauseBody = [
            'query' => [
                'match_all' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'customSearch' => 'test query',
            ],
        ];

        // Act
        $result = $filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'query' => [
                'match_all' => [],
            ],
            'bool' => [
                'must' => [
                    [
                        'multi_match' => [
                            'query' => 'test query',
                            'fields' => ['title'],
                            'type' => 'best_fields',
                            'fuzziness' => 'AUTO',
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithEmptyPropertiesReturnsOriginalClauseBody(): void
    {
        // Arrange
        $filter = new CombinedMatchFilter(
            $this->propertyNameCollectionFactory,
            $this->propertyMetadataFactory,
            $this->resourceClassResolver,
            $this->nameConverter,
            []
        );

        $clauseBody = [
            'query' => [
                'match_all' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'search' => 'test query',
            ],
        ];

        // Act
        $result = $filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $this->assertEquals($clauseBody, $result);
    }

    public function testApplyWithNullPropertiesReturnsOriginalClauseBody(): void
    {
        // Arrange
        $filter = new CombinedMatchFilter(
            $this->propertyNameCollectionFactory,
            $this->propertyMetadataFactory,
            $this->resourceClassResolver,
            $this->nameConverter,
            null
        );

        $clauseBody = [
            'query' => [
                'match_all' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'search' => 'test query',
            ],
        ];

        // Act
        $result = $filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $this->assertEquals($clauseBody, $result);
    }

    public function testGetDescriptionReturnsCorrectDescription(): void
    {
        // Arrange
        $resourceClass = Document::class;

        // Act
        $result = $this->filter->getDescription($resourceClass);

        // Assert
        $expected = [
            'search' => [
                'property' => 'search',
                'type' => 'string',
                'required' => false,
                'description' => 'Search across multiple fields: title, description, content',
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetDescriptionWithCustomFieldName(): void
    {
        // Arrange
        $filter = new CombinedMatchFilter(
            $this->propertyNameCollectionFactory,
            $this->propertyMetadataFactory,
            $this->resourceClassResolver,
            $this->nameConverter,
            [
                'title' => 'title',
                'content' => 'content',
            ],
            'customSearch'
        );

        $resourceClass = Document::class;

        // Act
        $result = $filter->getDescription($resourceClass);

        // Assert
        $expected = [
            'customSearch' => [
                'property' => 'customSearch',
                'type' => 'string',
                'required' => false,
                'description' => 'Search across multiple fields: title, content',
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetDescriptionWithEmptyProperties(): void
    {
        // Arrange
        $filter = new CombinedMatchFilter(
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
        $expected = [
            'search' => [
                'property' => 'search',
                'type' => 'string',
                'required' => false,
                'description' => 'Search across multiple fields: ',
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithComplexSearchQuery(): void
    {
        // Arrange
        $clauseBody = [
            'query' => [
                'match_all' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'search' => 'complex search query with special characters !@#$%^&*()',
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'query' => [
                'match_all' => [],
            ],
            'bool' => [
                'must' => [
                    [
                        'multi_match' => [
                            'query' => 'complex search query with special characters !@#$%^&*()',
                            'fields' => ['title', 'description', 'content'],
                            'type' => 'best_fields',
                            'fuzziness' => 'AUTO',
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithNumericSearchValue(): void
    {
        // Arrange
        $clauseBody = [
            'query' => [
                'match_all' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'search' => '12345',
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'query' => [
                'match_all' => [],
            ],
            'bool' => [
                'must' => [
                    [
                        'multi_match' => [
                            'query' => '12345',
                            'fields' => ['title', 'description', 'content'],
                            'type' => 'best_fields',
                            'fuzziness' => 'AUTO',
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithEmptyStringSearchValue(): void
    {
        // Arrange
        $clauseBody = [
            'query' => [
                'match_all' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'search' => '',
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $this->assertEquals($clauseBody, $result);
    }
}
