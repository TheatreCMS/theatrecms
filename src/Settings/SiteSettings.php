<?php

namespace TheatreCMS\Settings;

use Symfony\Component\Yaml\Yaml;

/**
 * Reads and writes site-level settings stored in a YAML config file.
 */
class SiteSettings
{
    /** @var array<string, mixed> */
    private array $data;

    /** @var array<string, mixed> */
    private static array $defaults = [
        'organization_name' => '',
        'name'              => 'TheatreCMS',
        'logo_url'          => '/assets/images/logo.svg',
        'base_path'         => '',
        'contact_email'     => '',
        'social'            => [
            'facebook'  => '',
            'twitter'   => '',
            'instagram' => '',
        ],
    ];

    public function __construct(private readonly string $configPath)
    {
        $this->data = $this->load();
    }

    /**
     * Returns a flat settings array suitable for passing to Twig as globals.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge(self::$defaults, $this->data);
    }

    /**
     * Returns a single top-level setting value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? self::$defaults[$key] ?? $default;
    }

    /**
     * Persists updated settings back to the YAML config file.
     *
     * @param array<string, mixed> $data
     */
    public function save(array $data): void
    {
        $current = $this->all();

        $current['organization_name'] = $data['organization_name'] ?? $current['organization_name'];
        $current['name']              = $data['name']              ?? $current['name'];
        $current['logo_url']          = $data['logo_url']          ?? $current['logo_url'];
        $current['base_path']         = $data['base_path']         ?? $current['base_path'];
        $current['contact_email']     = $data['contact_email']     ?? $current['contact_email'];

        $current['social']['facebook']  = $data['social_facebook']  ?? $current['social']['facebook']  ?? '';
        $current['social']['twitter']   = $data['social_twitter']   ?? $current['social']['twitter']   ?? '';
        $current['social']['instagram'] = $data['social_instagram'] ?? $current['social']['instagram'] ?? '';

        $yaml = Yaml::dump(['site' => $current], 4);
        file_put_contents($this->configPath, $yaml);

        $this->data = $current;
    }

    /** @return array<string, mixed> */
    private function load(): array
    {
        if (!file_exists($this->configPath)) {
            return self::$defaults;
        }

        $parsed = Yaml::parseFile($this->configPath);

        return $parsed['site'] ?? self::$defaults;
    }
}
