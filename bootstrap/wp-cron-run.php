<?php
/**
 * Run all DUE WordPress cron events. Mirrors public/wp/wp-cron.php's core loop
 * (reschedule recurring, unschedule, fire). Requires WordPress to be loaded and
 * DOING_CRON defined. Returns the number of events fired.
 */

if (! function_exists('wpcloud_run_due_cron')) {
    function wpcloud_run_due_cron(): int
    {
        $crons = _get_cron_array();
        if (empty($crons)) {
            return 0;
        }

        $now = microtime(true);
        $ran = 0;

        foreach ($crons as $timestamp => $cronhooks) {
            if ($timestamp > $now) {
                break; // cron array is time-ordered; nothing else is due
            }
            foreach ($cronhooks as $hook => $keys) {
                foreach ($keys as $event) {
                    if (! empty($event['schedule'])) {
                        wp_reschedule_event($timestamp, $event['schedule'], $hook, $event['args'], true);
                    }
                    wp_unschedule_event($timestamp, $hook, $event['args'], true);
                    do_action_ref_array($hook, $event['args']);
                    $ran++;
                }
            }
        }

        return $ran;
    }
}
