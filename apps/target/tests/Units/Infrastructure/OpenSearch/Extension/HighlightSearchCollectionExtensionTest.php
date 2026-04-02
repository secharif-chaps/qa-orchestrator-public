<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\OpenSearch\Extension;

use ApiPlatform\Metadata\Operation;
use App\Domain\Document\Document;
use App\Infrastructure\OpenSearch\Extension\HighlightSearchCollectionExtension;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class HighlightSearchCollectionExtensionTest extends TestCase
{
    private HighlightSearchCollectionExtension $extension;
    private Operation&Stub $operation;

    protected function setUp(): void
    {
        $this->extension = new HighlightSearchCollectionExtension();
        $this->operation = $this->createStub(Operation::class);
    }

    public function testApplyToCollectionWithSearchFilterAddsHighlighting(): void
    {
        $requestBody = [
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

        $result = $this->extension->applyToCollection($requestBody, $resourceClass, $this->operation, $context);

        $expected = [
            'query' => [
                'match_all' => [],
            ],
            'highlight' => [
                'fields' => [
                    'title' => new \stdClass(),
                    'content' => new \stdClass(),
                ],
                'pre_tags' => ['<mark class="mark">'],
                'post_tags' => ['</mark>'],
                'fragment_size' => 150,
                'number_of_fragments' => 3,
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyToCollectionWithEmptySearchFilterDoesNotAddHighlighting(): void
    {
        $requestBody = [
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

        $result = $this->extension->applyToCollection($requestBody, $resourceClass, $this->operation, $context);

        $this->assertEquals($requestBody, $result);
    }

    public function testApplyToCollectionWithNoSearchFilterDoesNotAddHighlighting(): void
    {
        $requestBody = [
            'query' => [
                'match_all' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'other_filter' => 'value',
            ],
        ];

        $result = $this->extension->applyToCollection($requestBody, $resourceClass, $this->operation, $context);

        $this->assertEquals($requestBody, $result);
    }

    public function testApplyToCollectionWithNoFiltersDoesNotAddHighlighting(): void
    {
        $requestBody = [
            'query' => [
                'match_all' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [];

        $result = $this->extension->applyToCollection($requestBody, $resourceClass, $this->operation, $context);

        $this->assertEquals($requestBody, $result);
    }

    public function testApplyToCollectionWithNonArrayFiltersDoesNotAddHighlighting(): void
    {
        $requestBody = [
            'query' => [
                'match_all' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => 'not_an_array',
        ];

        $result = $this->extension->applyToCollection($requestBody, $resourceClass, $this->operation, $context);

        $this->assertEquals($requestBody, $result);
    }

    public function testApplyToCollectionWithNullFiltersDoesNotAddHighlighting(): void
    {
        $requestBody = [
            'query' => [
                'match_all' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => null,
        ];

        $result = $this->extension->applyToCollection($requestBody, $resourceClass, $this->operation, $context);

        $this->assertEquals($requestBody, $result);
    }

    public function testApplyToCollectionWithExistingHighlightingReplacesIt(): void
    {
        $requestBody = [
            'query' => [
                'match_all' => [],
            ],
            'highlight' => [
                'fields' => [
                    'old_field' => new \stdClass(),
                ],
                'pre_tags' => ['<old>'],
                'post_tags' => ['</old>'],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'search' => 'test query',
            ],
        ];

        $result = $this->extension->applyToCollection($requestBody, $resourceClass, $this->operation, $context);

        $expected = [
            'query' => [
                'match_all' => [],
            ],
            'highlight' => [
                'fields' => [
                    'title' => new \stdClass(),
                    'content' => new \stdClass(),
                ],
                'pre_tags' => ['<mark class="mark">'],
                'post_tags' => ['</mark>'],
                'fragment_size' => 150,
                'number_of_fragments' => 3,
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyToCollectionWithComplexSearchQueryAddsHighlighting(): void
    {
        $requestBody = [
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'multi_match' => [
                                'query' => 'complex search',
                                'fields' => ['title', 'content'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'search' => 'complex search query with special characters !@#$%^&*()',
            ],
        ];

        $result = $this->extension->applyToCollection($requestBody, $resourceClass, $this->operation, $context);

        $expected = [
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'multi_match' => [
                                'query' => 'complex search',
                                'fields' => ['title', 'content'],
                            ],
                        ],
                    ],
                ],
            ],
            'highlight' => [
                'fields' => [
                    'title' => new \stdClass(),
                    'content' => new \stdClass(),
                ],
                'pre_tags' => ['<mark class="mark">'],
                'post_tags' => ['</mark>'],
                'fragment_size' => 150,
                'number_of_fragments' => 3,
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyToCollectionWithNumericSearchValueAddsHighlighting(): void
    {
        $requestBody = [
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

        $result = $this->extension->applyToCollection($requestBody, $resourceClass, $this->operation, $context);

        $expected = [
            'query' => [
                'match_all' => [],
            ],
            'highlight' => [
                'fields' => [
                    'title' => new \stdClass(),
                    'content' => new \stdClass(),
                ],
                'pre_tags' => ['<mark class="mark">'],
                'post_tags' => ['</mark>'],
                'fragment_size' => 150,
                'number_of_fragments' => 3,
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyToCollectionWithEmptyRequestBodyAddsHighlighting(): void
    {
        $requestBody = [];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'search' => 'test query',
            ],
        ];

        $result = $this->extension->applyToCollection($requestBody, $resourceClass, $this->operation, $context);

        $expected = [
            'highlight' => [
                'fields' => [
                    'title' => new \stdClass(),
                    'content' => new \stdClass(),
                ],
                'pre_tags' => ['<mark class="mark">'],
                'post_tags' => ['</mark>'],
                'fragment_size' => 150,
                'number_of_fragments' => 3,
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testApplyToCollectionWithMultipleFiltersIncludingSearch(): void
    {
        $requestBody = [
            'query' => [
                'match_all' => [],
            ],
        ];
        $resourceClass = Document::class;
        $context = [
            'filters' => [
                'search' => 'test query',
                'status' => 'active',
                'createdAt' => [
                    'after' => '2024-01-01',
                ],
            ],
        ];

        $result = $this->extension->applyToCollection($requestBody, $resourceClass, $this->operation, $context);

        $expected = [
            'query' => [
                'match_all' => [],
            ],
            'highlight' => [
                'fields' => [
                    'title' => new \stdClass(),
                    'content' => new \stdClass(),
                ],
                'pre_tags' => ['<mark class="mark">'],
                'post_tags' => ['</mark>'],
                'fragment_size' => 150,
                'number_of_fragments' => 3,
            ],
        ];

        $this->assertEquals($expected, $result);
    }
}
