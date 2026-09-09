<?php
namespace TheatreCMS\Twig;

use TheatreCMS\Theme\SeoMeta;
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

        // Emit the computed SEO meta tags, if TemplateResolver populated them.
        $headContent .= $this->renderSeoTags($context);

        // Add any relevant schema.org structured data to the head (this already
        // incorporates $headContent into its return value, so assign rather than append).
        $headContent = $this->addStructuredData($headContent, $context);

        return $headContent;
    }

    private function renderSeoTags(array $context): string
    {
        $seo = $context['seo'] ?? null;

        if (!$seo instanceof SeoMeta) {
            return '';
        }

        $tags = [];

        if ($seo->description !== '') {
            $tags[] = sprintf('<meta name="description" content="%s">', htmlspecialchars($seo->description));
        }

        if ($seo->canonicalUrl !== '') {
            $tags[] = sprintf('<link rel="canonical" href="%s">', htmlspecialchars($seo->canonicalUrl));
        }

        $tags[] = sprintf('<meta property="og:title" content="%s">', htmlspecialchars($seo->title));

        if ($seo->description !== '') {
            $tags[] = sprintf('<meta property="og:description" content="%s">', htmlspecialchars($seo->description));
        }

        $tags[] = sprintf('<meta property="og:type" content="%s">', htmlspecialchars($seo->ogType));

        if ($seo->canonicalUrl !== '') {
            $tags[] = sprintf('<meta property="og:url" content="%s">', htmlspecialchars($seo->canonicalUrl));
        }

        if ($seo->ogImage !== '') {
            $tags[] = sprintf('<meta property="og:image" content="%s">', htmlspecialchars($seo->ogImage));
        }

        $tags[] = sprintf('<meta name="twitter:card" content="%s">', htmlspecialchars($seo->twitterCard));

        return implode("\n", $tags) . "\n";
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
