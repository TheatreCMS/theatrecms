<?php

namespace TheatreCMS\Twig;

use TheatreCMS\Text\BlockNoteHtmlConverter;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFilter;

class BlockNoteExtension extends AbstractExtension
{
    public function __construct(private BlockNoteHtmlConverter $converter)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter(
                'block_content_to_html',
                [$this, 'convert'],
                ['is_safe' => ['html']]
            ),
        ];
    }

    public function convert(mixed $value): Markup
    {
        $html = $this->converter->toHtml($value);
        return new Markup($html, 'UTF-8');
    }
}
