<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile\SearchQuery;

use App\Application\WatchFile\SearchQuery\AddSearchQueryAction;
use App\Application\WatchFile\SearchQuery\AddSearchQueryHandler;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\User\User;
use App\Domain\WatchFile\MonitoringType;
use App\Domain\WatchFile\SearchQuery;
use App\Domain\WatchFile\SearchQueryGatewayInterface;
use App\Domain\WatchFile\StrategicQuestion;
use App\Domain\WatchFile\StrategicQuestionGatewayInterface;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Units\Infrastructure\WatchFile\NullSearchQueryGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullStrategicQuestionGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class AddSearchQueryHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private StrategicQuestionGatewayInterface $strategicQuestionGateway;
    private SearchQueryGatewayInterface $searchQueryGateway;
    private AddSearchQueryHandler $handler;

    protected function setUp(): void
    {
        $this->strategicQuestionGateway = new NullStrategicQuestionGateway();
        $this->searchQueryGateway = new NullSearchQueryGateway();

        $this->handler = new AddSearchQueryHandler($this->strategicQuestionGateway, $this->searchQueryGateway);
    }

    public function testAddSearchQuery(): void
    {
        $strategicQuestion = $this->createStrategicQuestion();
        $this->strategicQuestionGateway->save($strategicQuestion);

        $action = new AddSearchQueryAction(
            strategicQuestionId: (string) $strategicQuestion->getId(),
            country: 'FR',
            language: 'fr',
            query: 'test search query',
            queryType: 'general',
            rationale: 'This is a test rationale',
        );

        $result = ($this->handler)($action);

        $this->assertNotNull($result->getId());
        $this->assertEquals('test search query', $result->getSearchTerm());
        $this->assertEquals('FR', $result->getCountry());
        $this->assertEquals('fr', $result->getLanguage());
        $this->assertEquals('general', $result->getQueryType());
        $this->assertEquals('This is a test rationale', $result->getRationale());
        $this->assertSame($strategicQuestion, $result->getStrategicQuestion());
    }

    public function testAddSearchQueryReturnExistingWhenDuplicate(): void
    {
        $strategicQuestion = $this->createStrategicQuestion();
        $this->strategicQuestionGateway->save($strategicQuestion);

        $existingQuery = new SearchQuery(
            searchTerm: 'existing query',
            country: 'FR',
            language: 'fr',
            queryType: 'specific',
            rationale: 'Original rationale',
            strategicQuestion: $strategicQuestion,
        );
        $this->searchQueryGateway->save($existingQuery);

        $action = new AddSearchQueryAction(
            strategicQuestionId: (string) $strategicQuestion->getId(),
            country: 'FR',
            language: 'fr',
            query: 'existing query',
            queryType: 'general',
            rationale: 'New rationale that should be ignored',
        );

        $result = ($this->handler)($action);

        $this->assertSame($existingQuery, $result);
        $this->assertEquals('specific', $result->getQueryType());
        $this->assertEquals('Original rationale', $result->getRationale());
    }

    public function testAddSearchQueryAllowsSameQueryForDifferentStrategicQuestions(): void
    {
        $strategicQuestion1 = $this->createStrategicQuestion('question-1');
        $this->strategicQuestionGateway->save($strategicQuestion1);

        $strategicQuestion2 = $this->createStrategicQuestion('question-2');
        $this->strategicQuestionGateway->save($strategicQuestion2);

        $existingQuery = new SearchQuery(
            searchTerm: 'same query',
            country: 'FR',
            language: 'fr',
            queryType: 'general',
            rationale: 'Rationale 1',
            strategicQuestion: $strategicQuestion1,
        );
        $this->searchQueryGateway->save($existingQuery);

        $action = new AddSearchQueryAction(
            strategicQuestionId: (string) $strategicQuestion2->getId(),
            country: 'FR',
            language: 'fr',
            query: 'same query',
            queryType: 'general',
            rationale: 'Rationale 2',
        );

        $result = ($this->handler)($action);

        $this->assertNotSame($existingQuery, $result);
        $this->assertSame($strategicQuestion2, $result->getStrategicQuestion());
    }

    public function testAddSearchQueryAllowsSameQueryForDifferentCountries(): void
    {
        $strategicQuestion = $this->createStrategicQuestion();
        $this->strategicQuestionGateway->save($strategicQuestion);

        $existingQuery = new SearchQuery(
            searchTerm: 'same query',
            country: 'FR',
            language: 'fr',
            queryType: 'general',
            rationale: 'French version',
            strategicQuestion: $strategicQuestion,
        );
        $this->searchQueryGateway->save($existingQuery);

        $action = new AddSearchQueryAction(
            strategicQuestionId: (string) $strategicQuestion->getId(),
            country: 'US',
            language: 'fr',
            query: 'same query',
            queryType: 'general',
            rationale: 'US version',
        );

        $result = ($this->handler)($action);

        $this->assertNotSame($existingQuery, $result);
        $this->assertEquals('US', $result->getCountry());
    }

    public function testAddSearchQueryAllowsSameQueryForDifferentLanguages(): void
    {
        $strategicQuestion = $this->createStrategicQuestion();
        $this->strategicQuestionGateway->save($strategicQuestion);

        $existingQuery = new SearchQuery(
            searchTerm: 'same query',
            country: 'FR',
            language: 'fr',
            queryType: 'general',
            rationale: 'French version',
            strategicQuestion: $strategicQuestion,
        );
        $this->searchQueryGateway->save($existingQuery);

        $action = new AddSearchQueryAction(
            strategicQuestionId: (string) $strategicQuestion->getId(),
            country: 'FR',
            language: 'en',
            query: 'same query',
            queryType: 'general',
            rationale: 'English version',
        );

        $result = ($this->handler)($action);

        $this->assertNotSame($existingQuery, $result);
        $this->assertEquals('en', $result->getLanguage());
    }

    public function testAddSearchQueryWithStrategicQuestionNotFoundThrowsException(): void
    {
        $action = new AddSearchQueryAction(
            strategicQuestionId: 'non-existent-id',
            country: 'FR',
            language: 'fr',
            query: 'test query',
            queryType: 'general',
            rationale: 'Test rationale',
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('StrategicQuestion not found');

        ($this->handler)($action);
    }

    public function testAddSearchQueryWithEmptyStrategicQuestionIdThrowsException(): void
    {
        $action = new AddSearchQueryAction(
            strategicQuestionId: '',
            country: 'FR',
            language: 'fr',
            query: 'test query',
            queryType: 'general',
            rationale: 'Test rationale',
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Invalid strategic question ID provided.');

        ($this->handler)($action);
    }

    public function testAddSearchQueryWithInvalidCountryCodeThrowsException(): void
    {
        $strategicQuestion = $this->createStrategicQuestion();
        $this->strategicQuestionGateway->save($strategicQuestion);

        $action = new AddSearchQueryAction(
            strategicQuestionId: (string) $strategicQuestion->getId(),
            country: 'FRA',
            language: 'fr',
            query: 'test query',
            queryType: 'general',
            rationale: 'Test rationale',
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Invalid country or language code');

        ($this->handler)($action);
    }

    public function testAddSearchQueryWithInvalidLanguageCodeThrowsException(): void
    {
        $strategicQuestion = $this->createStrategicQuestion();
        $this->strategicQuestionGateway->save($strategicQuestion);

        $action = new AddSearchQueryAction(
            strategicQuestionId: (string) $strategicQuestion->getId(),
            country: 'FR',
            language: 'fra',
            query: 'test query',
            queryType: 'general',
            rationale: 'Test rationale',
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Invalid country or language code');

        ($this->handler)($action);
    }

    private function createStrategicQuestion(string $id = 'strategic-question-id'): StrategicQuestion
    {
        $user = new User('user-id', 'user@example.com');
        $watchFile = new WatchFile('Test WatchFile', 'objective', new Organisation('Test Org', 'test-org-id'), $user);
        $this->forcePropertyValue($watchFile, 'watch-file-id');

        $strategicQuestion = new StrategicQuestion(
            question: new TranslatedText('Question FR', 'Question EN'),
            context: new TranslatedText('Contexte FR', 'Context EN'),
            monitoringDimension: MonitoringType::COMPETITIVE,
            priority: 1,
            expectedOutputType: 'analysis',
            watchFile: $watchFile,
        );
        $this->forcePropertyValue($strategicQuestion, $id);

        return $strategicQuestion;
    }
}
