<?php

use TheatreCMS\ContentType\ContentTypeDefinition;
use TheatreCMS\ContentType\ContentTypeRegistry;

/**
 * Declares a content type (e.g. 'press_release'), so its entries get admin screens and
 * frontend routes under its URL prefix.
 *
 * @param string $name machine name: lowercase letters and underscores (see `TheatreCMS\Routing\NamingRules`)
 * @param array{
 *     label?: string,
 *     singularLabel?: string,
 *     urlPrefix?: string,
 *     hasArchive?: bool,
 *     public?: bool,
 *     supports?: string[],
 *     capability?: string
 * } $args
 *        `singularLabel` defaults to the name in title case, and `label` to `singularLabel` plus
 *        "s"; set `label` when that isn't the plural. `urlPrefix` is the path segment its entries
 *        are served under; it defaults to the name with underscores as hyphens and must not be
 *        used by any other content type or taxonomy. A `content_types` override
 *        for the name in `app/config.yaml` wins over these args. Unknown keys raise a notice and
 *        are ignored.
 * @throws \InvalidArgumentException for an invalid or already registered name, or an invalid or
 *        already used URL prefix
 */
function register_content_type(string $name, array $args = []): ContentTypeDefinition
{
    return ContentTypeRegistry::getInstance()->register($name, $args);
}
