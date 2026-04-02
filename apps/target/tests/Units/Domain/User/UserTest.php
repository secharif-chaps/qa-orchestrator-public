<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\User;

use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testGetDefaultThumbnail(): void
    {
        // Standard case with first name + last name
        $user = new User('id', 'mail', [], 'jdoe', 'Jean', 'Dupont');
        $this->assertSame('JD', $user->getDefaultThumbnail());

        // First name only (displayName will be username since last name is null)
        $user = new User('id', 'mail', [], 'jdoe', 'Jean', null);
        $this->assertSame('J', $user->getDefaultThumbnail());

        // Last name only (displayName will be username since first name is null)
        $user = new User('id', 'mail', [], 'jdoe', null, 'Dupont');
        $this->assertSame('J', $user->getDefaultThumbnail());

        // Username only
        $user = new User('id', 'mail', [], 'jdoe', null, null);
        $this->assertSame('J', $user->getDefaultThumbnail());

        // First name + last name with accents
        $user = new User('id', 'mail', [], 'jdoe', 'Éléonore', 'Évrard');
        $this->assertSame('ÉÉ', $user->getDefaultThumbnail());

        // First name + last name with non-latin characters
        $user = new User('id', 'mail', [], 'jdoe', '李', '雷');
        $this->assertSame('李雷', $user->getDefaultThumbnail());

        // First name + last name with emojis
        $user = new User('id', 'mail', [], 'jdoe', '😀', '🚀');
        $this->assertSame('😀🚀', $user->getDefaultThumbnail());

        // Username with emoji at beginning
        $user = new User('id', 'mail', [], '👾User', null, null);
        $this->assertSame('👾', $user->getDefaultThumbnail());

        // Username with space and special characters
        $user = new User('id', 'mail', [], 'Jean-Pierre Dupont', null, null);
        $this->assertSame('JD', $user->getDefaultThumbnail());

        // Username with more than two words - we should only get initials from first two
        $user = new User('id', 'mail', [], 'Jean Pierre Louis', null, null);
        $this->assertSame('JP', $user->getDefaultThumbnail());

        // Username with multiple spaces between words
        $user = new User('id', 'mail', [], 'Jean  Pierre', null, null);
        $this->assertSame('JP', $user->getDefaultThumbnail());

        // Username with spaces at beginning/end
        $user = new User('id', 'mail', [], '  Jean Pierre  ', null, null);
        $this->assertSame('JP', $user->getDefaultThumbnail());

        // Case where displayName is a single long word (via username)
        $user = new User('id', 'mail', [], 'Supercalifragilisticexpialidocious', null, null);
        $this->assertSame('S', $user->getDefaultThumbnail());

        // Case where first name or last name is empty but not null (so displayName is username)
        $user = new User('id', 'mail', [], 'jdoe', '', 'Dupont');
        $this->assertSame('J', $user->getDefaultThumbnail());

        $user = new User('id', 'mail', [], 'jdoe', 'Jean', '');
        $this->assertSame('J', $user->getDefaultThumbnail());

        // Case where username is empty
        $user = new User('id', 'mail', [], '', null, null);
        $this->assertSame('', $user->getDefaultThumbnail());

        // Case where username is a single emoji
        $user = new User('id', 'mail', [], '😀', null, null);
        $this->assertSame('😀', $user->getDefaultThumbnail());

        // Case where username is two emojis without space
        // Should get only the first character
        $user = new User('id', 'mail', [], '😀🚀', null, null);
        $this->assertSame('😀', $user->getDefaultThumbnail());

        // Case where username is two emojis WITH space
        $user = new User('id', 'mail', [], '😀 🚀', null, null);
        $this->assertSame('😀🚀', $user->getDefaultThumbnail());

        // Additional cases testing the reduce implementation
        // More than 2 words in first+last name
        $user = new User('id', 'mail', [], 'jdoe', 'Jean Pierre', 'Dupont');
        $this->assertSame('JP', $user->getDefaultThumbnail());

        // Empty word in name
        $user = new User('id', 'mail', [], 'jdoe', 'Jean  ', 'Dupont');
        $this->assertSame('JD', $user->getDefaultThumbnail());
    }

    public function testGetDisplayName(): void
    {
        // First name + last name
        $user = new User('id', 'mail', [], 'jdoe', 'Jean', 'Dupont');
        $this->assertSame('Jean Dupont', $user->getDisplayName());

        // First name only
        $user = new User('id', 'mail', [], 'jdoe', 'Jean', null);
        $this->assertSame('jdoe', $user->getDisplayName());

        // Last name only
        $user = new User('id', 'mail', [], 'jdoe', null, 'Dupont');
        $this->assertSame('jdoe', $user->getDisplayName());

        // No first name/last name
        $user = new User('id', 'mail', [], 'jdoe', null, null);
        $this->assertSame('jdoe', $user->getDisplayName());

        // First name + last name with emojis
        $user = new User('id', 'mail', [], 'jdoe', '😀', '🚀');
        $this->assertSame('😀 🚀', $user->getDisplayName());

        // First name + last name with accents
        $user = new User('id', 'mail', [], 'jdoe', 'éléonore', 'évrard');
        $this->assertSame('Éléonore Évrard', $user->getDisplayName());

        // First name + last name with non-latin characters
        $user = new User('id', 'mail', [], 'jdoe', '李', '雷');
        $this->assertSame('李 雷', $user->getDisplayName());

        // First name is empty string, last name present
        $user = new User('id', 'mail', [], 'jdoe', '', 'Dupont');
        $this->assertSame('jdoe', $user->getDisplayName());

        // Last name is empty string, first name present
        $user = new User('id', 'mail', [], 'jdoe', 'Jean', '');
        $this->assertSame('jdoe', $user->getDisplayName());

        // Both first name and last name are empty strings
        $user = new User('id', 'mail', [], 'jdoe', '', '');
        $this->assertSame('jdoe', $user->getDisplayName());

        // Testing title case conversion
        $user = new User('id', 'mail', [], 'jdoe', 'jean', 'dupont');
        $this->assertSame('Jean Dupont', $user->getDisplayName());

        // Testing title case conversion
        $user = new User('id', 'mail', [], 'jdoe', 'JEAN', 'DUPONT');
        $this->assertSame('Jean Dupont', $user->getDisplayName());

        // Testing extra spaces trimming
        $user = new User('id', 'mail', [], 'jdoe', ' Jean ', ' Dupont ');
        $this->assertSame('Jean Dupont', $user->getDisplayName());
    }
}
