<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\Intl\Languages;
use Twig\Environment;

/**
 * Decorates the OpenAPI factory to add internationalization documentation.
 *
 * This factory adds:
 * - Accept-Language header documentation
 * - Supported languages information
 */
final readonly class InternationalizationOpenApiFactory implements OpenApiFactoryInterface
{
    /**
     * @param list<string> $enabledLocales
     */
    public function __construct(
        private OpenApiFactoryInterface $decorated,
        private Environment $twig,
        private array $enabledLocales,
        private string $defaultLocale,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        return $this->addInternationalizationDescription($openApi);
    }

    private function addInternationalizationDescription(OpenApi $openApi): OpenApi
    {
        $info = $openApi->getInfo();
        $description = $info->getDescription();

        $languages = array_map(
            function (string $locale): array {
                $name = $locale;
                // Try to get language name if Symfony Intl is available
                if (class_exists(Languages::class)) {
                    try {
                        $name = Languages::getName($locale, 'en');
                    } catch (\Throwable) {
                        // Fallback to locale code if language name cannot be retrieved
                        $name = $locale;
                    }
                }

                return [
                    'code' => $locale,
                    'name' => $name,
                    'default' => $locale === $this->defaultLocale,
                ];
            },
            $this->enabledLocales
        );

        $i18nInfo = $this->twig->render('openapi/internationalization.md.twig', [
            'languages' => $languages,
        ]);

        return $openApi->withInfo($info->withDescription($description . "\n" . $i18nInfo));
    }
}
