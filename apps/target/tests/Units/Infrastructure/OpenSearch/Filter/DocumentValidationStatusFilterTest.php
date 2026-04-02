<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\OpenSearch\Filter;

use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Metadata\ResourceClassResolverInterface;
use App\Domain\Document\Document;
use App\Infrastructure\OpenSearch\Filter\DocumentValidationStatusFilter;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

class DocumentValidationStatusFilterTest extends TestCase
{
    private DocumentValidationStatusFilter $filter;
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

        $this->filter = new DocumentValidationStatusFilter(
            $this->propertyNameCollectionFactory,
            $this->propertyMetadataFactory,
            $this->resourceClassResolver,
            $this->nameConverter
        );
    }

    public function testApplyOnlyHandlesDocumentResourceClass(): void
    {
        // Arrange
        $clauseBody = [
            'query' => [
                'match_all' => [],
            ],
        ];
        $resourceClass = 'App\Domain\SomeOther\Entity';
        $context = [
            'filters' => [
                'validationStatus' => 'ai_validated',
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert - Should return original clause body without modification
        $this->assertEquals($clauseBody, $result);
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

    public function testApplyWithEmptyValidationStatusArrayReturnsOriginalClauseBody(): void
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
                'validationStatus' => [],
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $this->assertEquals($clauseBody, $result);
    }

    public function testApplyWithSingleAiValidatedStatusAddsTermQuery(): void
    {
        // Arrange
        $clauseBody = [];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'validationStatus' => 'ai_validated',
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'bool' => [
                'must' => [
                    [
                        'bool' => [
                            'should' => [
                                [
                                    'term' => [
                                        'aiValidation.status' => 'validated',
                                    ],
                                ],
                            ],
                            'minimum_should_match' => 1,
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithSingleAiEmptyStatusAddsExistsQuery(): void
    {
        // Arrange
        $clauseBody = [];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'validationStatus' => 'ai_empty',
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'bool' => [
                'must' => [
                    [
                        'bool' => [
                            'should' => [
                                [
                                    'bool' => [
                                        'must_not' => [
                                            'exists' => [
                                                'field' => 'aiValidation',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'minimum_should_match' => 1,
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithSingleManualAcceptStatusAddsTermQuery(): void
    {
        // Arrange
        $clauseBody = [];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'validationStatus' => 'manual_accept',
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'bool' => [
                'must' => [
                    [
                        'bool' => [
                            'should' => [
                                [
                                    'term' => [
                                        'manualStatus' => 'accept',
                                    ],
                                ],
                            ],
                            'minimum_should_match' => 1,
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithSingleManualEmptyStatusAddsExistsQuery(): void
    {
        // Arrange
        $clauseBody = [];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'validationStatus' => 'manual_empty',
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'bool' => [
                'must' => [
                    [
                        'bool' => [
                            'should' => [
                                [
                                    'bool' => [
                                        'must_not' => [
                                            'exists' => [
                                                'field' => 'manualStatus',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'minimum_should_match' => 1,
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithMultipleAiStatusesUsesOrLogic(): void
    {
        // Arrange
        $clauseBody = [];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'validationStatus' => ['ai_validated', 'ai_rejected'],
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'bool' => [
                'must' => [
                    [
                        'bool' => [
                            'should' => [
                                [
                                    'term' => [
                                        'aiValidation.status' => 'validated',
                                    ],
                                ],
                                [
                                    'term' => [
                                        'aiValidation.status' => 'rejected',
                                    ],
                                ],
                            ],
                            'minimum_should_match' => 1,
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithCombinedAiAndManualStatusesUsesOrLogic(): void
    {
        // Arrange
        $clauseBody = [];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'validationStatus' => ['ai_empty', 'manual_accept'],
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'bool' => [
                'must' => [
                    [
                        'bool' => [
                            'should' => [
                                [
                                    'bool' => [
                                        'must_not' => [
                                            'exists' => [
                                                'field' => 'aiValidation',
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'term' => [
                                        'manualStatus' => 'accept',
                                    ],
                                ],
                            ],
                            'minimum_should_match' => 1,
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
                'validationStatus' => 'ai_validated',
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
                        'bool' => [
                            'should' => [
                                [
                                    'term' => [
                                        'aiValidation.status' => 'validated',
                                    ],
                                ],
                            ],
                            'minimum_should_match' => 1,
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithAllAiValidationStatuses(): void
    {
        // Arrange
        $clauseBody = [];
        $resourceClass = Document::class;

        $statuses = ['ai_pending', 'ai_validated', 'ai_rejected', 'ai_uncertain', 'ai_failed'];

        foreach ($statuses as $status) {
            $context = [
                'filters' => [
                    'validationStatus' => $status,
                ],
            ];

            // Act
            $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

            // Assert
            $expectedStatus = substr($status, 3); // Remove 'ai_' prefix
            $this->assertArrayHasKey('bool', $result);
            $this->assertIsArray($result['bool']);
            $this->assertArrayHasKey('must', $result['bool']);
            $this->assertIsArray($result['bool']['must']);
            $this->assertCount(1, $result['bool']['must']);
            $this->assertIsArray($result['bool']['must'][0]);
            $this->assertArrayHasKey('bool', $result['bool']['must'][0]);
            $this->assertIsArray($result['bool']['must'][0]['bool']);
            $this->assertArrayHasKey('should', $result['bool']['must'][0]['bool']);
            $this->assertIsArray($result['bool']['must'][0]['bool']['should']);
            $this->assertCount(1, $result['bool']['must'][0]['bool']['should']);
            $this->assertIsArray($result['bool']['must'][0]['bool']['should'][0]);
            $this->assertArrayHasKey('term', $result['bool']['must'][0]['bool']['should'][0]);
            $this->assertIsArray($result['bool']['must'][0]['bool']['should'][0]['term']);
            $this->assertArrayHasKey('aiValidation.status', $result['bool']['must'][0]['bool']['should'][0]['term']);
            $this->assertEquals(
                $expectedStatus,
                $result['bool']['must'][0]['bool']['should'][0]['term']['aiValidation.status']
            );
        }
    }

    public function testApplyWithAllManualValidationStatuses(): void
    {
        // Arrange
        $clauseBody = [];
        $resourceClass = Document::class;

        $statuses = ['manual_accept', 'manual_refuse'];

        foreach ($statuses as $status) {
            $context = [
                'filters' => [
                    'validationStatus' => $status,
                ],
            ];

            // Act
            $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

            // Assert
            $expectedStatus = substr($status, 7); // Remove 'manual_' prefix
            $this->assertArrayHasKey('bool', $result);
            $this->assertIsArray($result['bool']);
            $this->assertArrayHasKey('must', $result['bool']);
            $this->assertIsArray($result['bool']['must']);
            $this->assertCount(1, $result['bool']['must']);
            $this->assertIsArray($result['bool']['must'][0]);
            $this->assertArrayHasKey('bool', $result['bool']['must'][0]);
            $this->assertIsArray($result['bool']['must'][0]['bool']);
            $this->assertArrayHasKey('should', $result['bool']['must'][0]['bool']);
            $this->assertIsArray($result['bool']['must'][0]['bool']['should']);
            $this->assertCount(1, $result['bool']['must'][0]['bool']['should']);
            $this->assertIsArray($result['bool']['must'][0]['bool']['should'][0]);
            $this->assertArrayHasKey('term', $result['bool']['must'][0]['bool']['should'][0]);
            $this->assertIsArray($result['bool']['must'][0]['bool']['should'][0]['term']);
            $this->assertArrayHasKey('manualStatus', $result['bool']['must'][0]['bool']['should'][0]['term']);
            $this->assertEquals(
                $expectedStatus,
                $result['bool']['must'][0]['bool']['should'][0]['term']['manualStatus']
            );
        }
    }

    public function testApplyIgnoresNonStringStatusValues(): void
    {
        // Arrange
        $clauseBody = [];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'validationStatus' => [123, 'ai_validated', null, true],
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert - Should only process the string 'ai_validated'
        $expected = [
            'bool' => [
                'must' => [
                    [
                        'bool' => [
                            'should' => [
                                [
                                    'term' => [
                                        'aiValidation.status' => 'validated',
                                    ],
                                ],
                            ],
                            'minimum_should_match' => 1,
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyIgnoresUnknownStatusPrefixes(): void
    {
        // Arrange
        $clauseBody = [];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'validationStatus' => ['unknown_status', 'ai_validated'],
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert - Should only process 'ai_validated', ignore 'unknown_status'
        $expected = [
            'bool' => [
                'must' => [
                    [
                        'bool' => [
                            'should' => [
                                [
                                    'term' => [
                                        'aiValidation.status' => 'validated',
                                    ],
                                ],
                            ],
                            'minimum_should_match' => 1,
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyConvertsStringToArray(): void
    {
        // Arrange
        $clauseBody = [];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'validationStatus' => 'manual_refuse',
            ],
        ];

        // Act
        $result = $this->filter->apply($clauseBody, $resourceClass, null, $context);

        // Assert
        $expected = [
            'bool' => [
                'must' => [
                    [
                        'bool' => [
                            'should' => [
                                [
                                    'term' => [
                                        'manualStatus' => 'refuse',
                                    ],
                                ],
                            ],
                            'minimum_should_match' => 1,
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyWithComplexScenario(): void
    {
        // Arrange - Simulate a complex query with multiple filters
        $clauseBody = [
            'bool' => [
                'must' => [
                    [
                        'term' => [
                            'status' => 'active',
                        ],
                    ],
                ],
                'should' => [
                    [
                        'match' => [
                            'title' => 'test',
                        ],
                    ],
                ],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'validationStatus' => ['ai_validated', 'manual_empty'],
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
                        'bool' => [
                            'should' => [
                                [
                                    'term' => [
                                        'aiValidation.status' => 'validated',
                                    ],
                                ],
                                [
                                    'bool' => [
                                        'must_not' => [
                                            'exists' => [
                                                'field' => 'manualStatus',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'minimum_should_match' => 1,
                        ],
                    ],
                ],
                'should' => [
                    [
                        'match' => [
                            'title' => 'test',
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }
}
