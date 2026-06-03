<?php

namespace Tests\Unit;

use App\WordPress\WpConfig;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class WpConfigTest extends TestCase
{
    private function validEnv(array $overrides = []): array
    {
        return array_merge([
            'DB_DATABASE' => 'wpdb',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => '',
            'DB_HOST'     => '127.0.0.1',
            'DB_PORT'     => 3306,
            'APP_KEY'     => 'base64:abc',
            'APP_URL'     => 'http://example.test/',
            'APP_ENV'     => 'local',
            'APP_DEBUG'   => true,
            'AUTH_KEY'    => 'k1',
        ], $overrides);
    }

    public function test_maps_database_and_urls(): void
    {
        $c = WpConfig::map($this->validEnv());

        $this->assertSame('wpdb', $c['DB_NAME']);
        $this->assertSame('root', $c['DB_USER']);
        $this->assertSame('', $c['DB_PASSWORD']);
        $this->assertSame('127.0.0.1', $c['DB_HOST']);          // default port omitted
        $this->assertSame('utf8mb4', $c['DB_CHARSET']);
        $this->assertSame('http://example.test', $c['WP_HOME']); // trailing slash trimmed
        $this->assertSame('http://example.test/wp', $c['WP_SITEURL']);
        $this->assertTrue($c['WP_DEBUG']);
        $this->assertSame('local', $c['WP_ENVIRONMENT_TYPE']);
        $this->assertSame('k1', $c['AUTH_KEY']);
    }

    public function test_appends_non_default_db_port(): void
    {
        $c = WpConfig::map($this->validEnv(['DB_PORT' => 3307]));
        $this->assertSame('127.0.0.1:3307', $c['DB_HOST']);
    }

    public function test_unknown_environment_defaults_to_production(): void
    {
        $c = WpConfig::map($this->validEnv(['APP_ENV' => 'weird']));
        $this->assertSame('production', $c['WP_ENVIRONMENT_TYPE']);
    }

    public function test_throws_on_missing_required_value(): void
    {
        $this->expectException(RuntimeException::class);
        $env = $this->validEnv();
        unset($env['DB_DATABASE']);
        WpConfig::map($env);
    }

    public function test_table_prefix_falls_back_to_wp(): void
    {
        $this->assertSame('wp_', WpConfig::normalizePrefix(''));
        $this->assertSame('site_', WpConfig::normalizePrefix('site_'));
    }
}
