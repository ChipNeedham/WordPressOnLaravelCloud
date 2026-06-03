<?php
/**
 * Hybrid code overlay: persist runtime plugin/theme/language changes to S3 and
 * hydrate them onto cold/stale replicas. Loads after 10-object-storage.
 *
 * Hydration runs before WordPress loads active plugins, so a just-hydrated plugin
 * is usable in the same request. No-ops when object storage is unconfigured.
 */

use App\Overlay\CodeOverlay;
use App\Overlay\OverlayItems;

$overlay = CodeOverlay::fromConfig();

if (! $overlay->isActive()) {
    return;
}

// Bring this replica up to the current overlay version (cheap when already current).
$overlay->hydrate();

// In read-only mode WordPress blocks file mods (DISALLOW_FILE_MODS); register no write hooks.
if (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS) {
    return;
}

add_action('upgrader_process_complete', function ($upgrader, $hook_extra) use ($overlay) {
    $type = $hook_extra['type'] ?? '';

    if ($type === 'plugin') {
        $files = $hook_extra['plugins']
            ?? array_filter([$hook_extra['plugin'] ?? null]);
        if (! $files && method_exists($upgrader, 'plugin_info') && $upgrader->plugin_info()) {
            $files = [$upgrader->plugin_info()];
        }
        foreach ($files as $file) {
            $overlay->syncItem(OverlayItems::pluginKey($file));
        }
    } elseif ($type === 'theme') {
        $themes = $hook_extra['themes']
            ?? array_filter([$hook_extra['theme'] ?? null]);
        if (! $themes && method_exists($upgrader, 'theme_info') && $upgrader->theme_info()) {
            $themes = [$upgrader->theme_info()->get_stylesheet()];
        }
        foreach ($themes as $stylesheet) {
            $overlay->syncItem(OverlayItems::themeKey($stylesheet));
        }
    } elseif ($type === 'translation') {
        $overlay->syncItem(OverlayItems::languagesKey());
    }
}, 10, 2);

add_action('deleted_plugin', function ($plugin_file, $deleted) use ($overlay) {
    if ($deleted) {
        $overlay->removeItem(OverlayItems::pluginKey($plugin_file));
    }
}, 10, 2);

add_action('deleted_theme', function ($stylesheet, $deleted) use ($overlay) {
    if ($deleted) {
        $overlay->removeItem(OverlayItems::themeKey($stylesheet));
    }
}, 10, 2);
