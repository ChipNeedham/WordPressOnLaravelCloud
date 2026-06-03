<?php

namespace Tests\Unit;

use App\Overlay\OverlayItems;
use PHPUnit\Framework\TestCase;

class OverlayItemsTest extends TestCase
{
    public function test_plugin_key_for_directory_plugin(): void
    {
        $this->assertSame('plugins/akismet', OverlayItems::pluginKey('akismet/akismet.php'));
    }

    public function test_plugin_key_for_single_file_plugin(): void
    {
        $this->assertSame('plugins/hello.php', OverlayItems::pluginKey('hello.php'));
    }

    public function test_theme_key(): void
    {
        $this->assertSame('themes/twentytwentyfive', OverlayItems::themeKey('twentytwentyfive'));
    }

    public function test_languages_key(): void
    {
        $this->assertSame('languages', OverlayItems::languagesKey());
    }

    public function test_relative_dir_for_key(): void
    {
        $this->assertSame('plugins/akismet', OverlayItems::relativeDir('plugins/akismet'));
        $this->assertSame('languages', OverlayItems::relativeDir('languages'));
    }
}
