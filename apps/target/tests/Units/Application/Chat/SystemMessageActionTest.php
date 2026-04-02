<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Chat;

use App\Application\Chat\SystemMessageAction;
use PHPUnit\Framework\TestCase;

class SystemMessageActionTest extends TestCase
{
    public function testGetTrimmedMessageRemovesWhitespace(): void
    {
        $action = new SystemMessageAction(
            conversationId: 'conversation-id',
            message: '  System message with whitespace  '
        );

        $this->assertEquals('System message with whitespace', $action->getTrimmedMessage());
    }

    public function testIsValidMessageReturnsTrueForNonEmptyMessage(): void
    {
        $action = new SystemMessageAction(conversationId: 'conversation-id', message: 'System message');

        $this->assertTrue($action->isValidMessage());
    }

    public function testIsValidMessageReturnsFalseForEmptyMessage(): void
    {
        $action = new SystemMessageAction(conversationId: 'conversation-id', message: '');

        $this->assertFalse($action->isValidMessage());
    }

    public function testIsValidMessageReturnsFalseForWhitespaceOnlyMessage(): void
    {
        $action = new SystemMessageAction(conversationId: 'conversation-id', message: '   ');

        $this->assertFalse($action->isValidMessage());
    }
}
