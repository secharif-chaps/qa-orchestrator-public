<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Dev;

use App\Domain\AI\Prompt\PromptTemplateEngineInterface;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class PromptController
{
    public function __construct(
        private readonly WatchFileGatewayInterface $watchFileGateway,
        private readonly PromptTemplateEngineInterface $promptTemplateEngine,
    ) {
    }

    #[Route('/_dev/prompt/{name}', name: 'dev_prompt', env: 'dev')]
    public function __invoke(Request $request, string $name): Response
    {
        $watchFileId = $request->query->get('watchFileId');
        $watchFile = $this->watchFileGateway->get($watchFileId);

        $prompt = ($this->promptTemplateEngine)($name, [
            'watchfile' => $watchFile,
        ]);

        return new Response($prompt, headers: [
            'Content-Type' => 'text/plain',
        ]);
    }
}
