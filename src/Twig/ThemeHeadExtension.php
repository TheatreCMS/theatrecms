<?php
namespace TheatreCMS\Twig;

use TheatreCMS\Theme\StructuredDataBuilder;
use Twig\TwigFunction;

class ThemeHeadExtension extends \Twig\Extension\AbstractExtension
{
    public function __construct(private readonly StructuredDataBuilder $structuredDataBuilder)
    {
    }

    /**
     * This method returns an array of TwigFunction instances that define
     * the custom functions provided by this extension.
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('theme_head', [$this, 'renderThemeHead'], [
                'is_safe' => ['html'],
                'needs_context' => true,
            ]),
        ];
    }

    public function renderThemeHead(array $context = []): string
    {
        $headContent = '';

        // Apply filters to allow themes and plugins to modify the head content
        $headContent = \TheatreCMS\Theme\HookManager::getInstance()->applyFilters('theme_head', $headContent);

        // Add any relevant schema.org structured data to the head
        $headContent = $this->addStructuredData($headContent, $context);

        return $headContent;
    }

    private function addStructuredData(string $headContent, array $context): string
    {
        $schema = match (true) {
            isset($context['production']) => $this->structuredDataBuilder->forProduction(
                $context['production'],
                $context['performances'] ?? []
            ),
            isset($context['season']) => $this->structuredDataBuilder->forSeason($context['season']),
            isset($context['person']) => $this->structuredDataBuilder->forPerson($context['person']),
            isset($context['work']) => $this->structuredDataBuilder->forWork($context['work']),
            default => null,
        };

        if ($schema === null || $schema === []) {
            return $headContent;
        }

        $jsonLd = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return $headContent . "<script type=\"application/ld+json\">\n" . $jsonLd . "\n</script>\n";
    }
}
