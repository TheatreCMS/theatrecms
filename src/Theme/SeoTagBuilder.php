<?php

namespace TheatreCMS\Theme;

use TheatreCMS\Models\Person;
use TheatreCMS\Models\Post;
use TheatreCMS\Models\Sponsor;
use TheatreCMS\Settings\SiteSettings;

/**
 * Assembles the HTML meta tags (title, description, canonical, Open Graph,
 * Twitter card) for a frontend page, from a mix of per-entity fields and
 * site-wide defaults (`SiteSettings`). There is no per-entity manual
 * override in this iteration — see `documentation/Theme/hooks.md` for the
 * `theatrecms/seo_title`/`theatrecms/seo_meta` filters a theme can use to
 * adjust the computed values instead.
 */
class SeoTagBuilder
{
    private const DEFAULT_TITLE_SEPARATOR = '—';

    public function __construct(
        private readonly SiteSettings $siteSettings,
        private readonly TitleResolver $titleResolver,
        private readonly PermalinkResolver $permalinkResolver,
        private readonly SeoDescriptionResolver $descriptionResolver,
    ) {
    }

    public function forEntity(object $entity, string $type): SeoMeta
    {
        $title = $this->buildTitle($this->titleResolver->resolve($entity));
        $description = $this->buildDescription($this->descriptionResolver->resolve($entity));
        $ogImage = $this->resolveOgImage($entity);

        $seo = new SeoMeta(
            title: $title,
            description: $description,
            canonicalUrl: $this->canonicalUrlFor($entity),
            ogImage: $ogImage,
            ogType: $entity instanceof Post ? 'article' : 'website',
            twitterCard: $ogImage !== '' ? 'summary_large_image' : 'summary',
        );

        return apply_filters('theatrecms/seo_meta', $seo, $entity, $type);
    }

    public function forArchive(string $type, string $label): SeoMeta
    {
        $ogImage = $this->absoluteUrl($this->seoSetting('default_social_image'));

        $seo = new SeoMeta(
            title: $this->buildTitle($label),
            description: $this->buildDescription(''),
            canonicalUrl: $this->absoluteUrl($this->permalinkResolver->archiveUrl($type)),
            ogImage: $ogImage,
            ogType: 'website',
            twitterCard: $ogImage !== '' ? 'summary_large_image' : 'summary',
        );

        return apply_filters('theatrecms/seo_meta', $seo, null, $type);
    }

    /**
     * The site's homepage, which isn't rendered through `TemplateResolver`
     * (there's no single entity or archive behind `/`).
     */
    public function forHome(): SeoMeta
    {
        $siteName = (string) $this->siteSettings->get('name', 'TheatreCMS');
        $ogImage = $this->absoluteUrl($this->seoSetting('default_social_image'));

        $seo = new SeoMeta(
            title: $this->buildTitle($siteName),
            description: $this->buildDescription(''),
            canonicalUrl: $this->absoluteUrl('/'),
            ogImage: $ogImage,
            ogType: 'website',
            twitterCard: $ogImage !== '' ? 'summary_large_image' : 'summary',
        );

        return apply_filters('theatrecms/seo_meta', $seo, null, 'home');
    }

    private function buildTitle(string $base): string
    {
        $base = trim($base);
        $siteName = (string) $this->siteSettings->get('name', 'TheatreCMS');

        if ($base === '') {
            $title = $siteName;
        } elseif (strcasecmp($base, $siteName) === 0) {
            $title = $base;
        } else {
            $separator = $this->seoSetting('title_separator', self::DEFAULT_TITLE_SEPARATOR);
            $title = "$base $separator $siteName";
        }

        return (string) apply_filters('theatrecms/seo_title', $title, $base);
    }

    private function buildDescription(string $derived): string
    {
        return $derived !== '' ? $derived : $this->seoSetting('default_meta_description');
    }

    private function canonicalUrlFor(object $entity): string
    {
        try {
            $path = $this->permalinkResolver->resolve($entity);
        } catch (\InvalidArgumentException) {
            // No URL scheme is defined yet for this entity type (e.g. Event,
            // Venue, Sponsor have no frontend route today) — omit the tag
            // rather than guessing at a URL structure.
            return '';
        }

        return $this->absoluteUrl($path);
    }

    private function resolveOgImage(object $entity): string
    {
        $image = match (true) {
            method_exists($entity, 'getFeaturedImageUrl') => (string) ($entity->getFeaturedImageUrl() ?? ''),
            $entity instanceof Person => $entity->getHeadshotUrl(),
            $entity instanceof Sponsor => (string) ($entity->getLogoUrl() ?? ''),
            default => '',
        };

        if ($image === '') {
            $image = $this->seoSetting('default_social_image');
        }

        return $this->absoluteUrl($image);
    }

    private function seoSetting(string $key, string $default = ''): string
    {
        $seo = $this->siteSettings->get('seo', []);

        if (!is_array($seo) || !isset($seo[$key])) {
            return $default;
        }

        return (string) $seo[$key];
    }

    private function absoluteUrl(string $path): string
    {
        if ($path === '' || preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $base = rtrim((string) $this->siteSettings->get('site_url', ''), '/');

        return $base . $path;
    }
}
