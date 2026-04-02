<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFileActivity;

use App\Domain\WatchFileActivity\WatchFileActivityActionType;
use App\Infrastructure\WatchFileActivity\WatchFileActivityLogger;
use PHPUnit\Framework\TestCase;

/**
 * Test to verify that all required WatchFile activities are properly covered
 * by the activity logging system as specified in the story requirements.
 */
class WatchFileActivityCoverageTest extends TestCase
{
    /**
     * Test that all required action types are defined in the enum.
     */
    public function testAllRequiredActionTypesAreDefined(): void
    {
        $requiredActionTypes = [
            'CREATED' => 'Création d\'un watchFile',
            'UPDATED' => 'Modification du titre (manuelle ou par chat)',
            'STATUS_CHANGED' => 'Changement de status du watchfile',
            'ACTOR_STATUS_CHANGED' => 'Ajout/Suppression/Activation/Désactivation d\'un acteur',
            'SOURCE_STATUS_CHANGED' => 'Activation/Désactivation d\'une source',
            'SHARED_MODE_CHANGED' => 'Changement du mode de partage (ajout/suppression d\'utilisateur)',
        ];

        $reflection = new \ReflectionClass(WatchFileActivityActionType::class);
        $availableActionTypes = $reflection->getConstants();

        foreach ($requiredActionTypes as $actionType => $description) {
            $this->assertArrayHasKey($actionType, $availableActionTypes,
                "Action type '{$actionType}' should be defined for: {$description}");
        }
    }

    /**
     * Test that all required actions can be logged by the logger.
     */
    public function testAllRequiredActionsCanBeLogged(): void
    {
        $reflection = new \ReflectionClass(WatchFileActivityLogger::class);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        $requiredMethods = [
            'logCreate' => 'Création d\'un watchFile',
            'logUpdate' => 'Modification du titre (manuelle ou par chat)',
            'logStatusChange' => 'Changement de status du watchfile',
            'logActorStatusChange' => 'Ajout/Suppression/Activation/Désactivation d\'un acteur',
            'logSourceStatusChange' => 'Activation/Désactivation d\'une source',
            'logShare' => 'Partage d\'un dossier à un utilisateur',
            'logUnshare' => 'Enlever le partage du dossier à un utilisateur',
        ];

        $availableMethods = array_map(fn ($method) => $method->getName(), $methods);

        foreach ($requiredMethods as $methodName => $description) {
            $this->assertContains($methodName, $availableMethods,
                "Method '{$methodName}' should exist for: {$description}");
        }
    }
}
