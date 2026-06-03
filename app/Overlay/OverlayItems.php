<?php

namespace App\Overlay;

class OverlayItems
{
    /** Map a WordPress plugin file (e.g. "akismet/akismet.php") to an overlay key. */
    public static function pluginKey(string $pluginFile): string
    {
        $dir = dirname($pluginFile);

        return 'plugins/' . ($dir === '.' ? $pluginFile : $dir);
    }

    /** Map a theme stylesheet (e.g. "twentytwentyfive") to an overlay key. */
    public static function themeKey(string $stylesheet): string
    {
        return 'themes/' . $stylesheet;
    }

    /** The single overlay key covering the whole languages directory. */
    public static function languagesKey(): string
    {
        return 'languages';
    }

    /** The wp-content-relative directory/file for an overlay key (identity here). */
    public static function relativeDir(string $key): string
    {
        return $key;
    }
}
