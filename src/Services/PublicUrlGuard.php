<?php

namespace TheatreCMS\Services;

/**
 * SSRF guard for server-side fetches of editor-supplied URLs (link previews, remote image
 * uploads): accepts only http(s) URLs whose host resolves to public, non-reserved IP
 * addresses, rejecting loopback, private and link-local targets such as cloud metadata
 * endpoints. Callers must not follow redirects, since a redirect could reach an address
 * this check never saw.
 */
final class PublicUrlGuard
{
    public static function isPublicHttpUrl(string $url): bool
    {
        $normalized = filter_var($url, FILTER_VALIDATE_URL);
        if ($normalized === false) {
            return false;
        }

        $scheme = parse_url($normalized, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = parse_url($normalized, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }

        // parse_url keeps the brackets around an IPv6 literal.
        $host = trim($host, '[]');

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if (empty($ips)) {
            return false;
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }
}
