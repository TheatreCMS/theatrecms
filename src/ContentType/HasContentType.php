<?php

namespace TheatreCMS\ContentType;

/**
 * An item of a registered content type. `PermalinkResolver` builds its URL from its type's
 * definition (`urlPrefix`, `public`) and its slug, so no content type needs its own URL code.
 */
interface HasContentType
{
    /**
     * The registry key of the item's content type, e.g. 'season' or 'press_release'.
     */
    public function getContentType(): string;

    public function getSlug(): string;
}
