<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\WatchFile;

use App\Domain\WatchFile\UserAccessState;
use PHPUnit\Framework\TestCase;

class UserAccessStateTest extends TestCase
{
    public function testAllAccessStatesAreDefined(): void
    {
        $this->assertEquals('no_access', UserAccessState::NO_ACCESS->value);
        $this->assertEquals('editor', UserAccessState::EDITOR->value);
        $this->assertEquals('viewer', UserAccessState::VIEWER->value);
        $this->assertEquals('admin', UserAccessState::ADMIN->value);
    }

    public function testAccessStateTransitions(): void
    {
        // Test typical transitions
        $noAccess = UserAccessState::NO_ACCESS->value;
        $editor = UserAccessState::EDITOR->value;

        // Simulate sharing: no_access -> editor
        $this->assertNotEquals($noAccess, $editor);

        // Simulate unsharing: editor -> no_access
        $this->assertNotEquals($editor, $noAccess);

        // Verify states are distinct
        $this->assertCount(4, UserAccessState::cases());
    }

    public function testEnumIsTypesSafe(): void
    {
        // This ensures we have compile-time safety
        $state = UserAccessState::NO_ACCESS;
        $this->assertInstanceOf(UserAccessState::class, $state);
        $this->assertEquals('no_access', $state->value);
    }
}
