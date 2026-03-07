<?php

namespace TheatreCMS\Twig;

use TheatreCMS\Text\EditorJsHtmlConverter;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFilter;

class EditorJsExtension extends AbstractExtension
{
    public function __construct(private EditorJsHtmlConverter $converter)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter(
                'editorjs_to_html',
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
