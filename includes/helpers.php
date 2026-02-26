<?php
/**
 * Helper functions for Causeway plugin.
 */

if (!defined('ABSPATH')) { exit; }

if (!function_exists('causeway_parse_occ_dt')) {
    /**
     * Strict parser: always interpret date portion as dd/mm/YYYY (European style) with optional time.
     * Supported formats (in order):
     *   d/m/Y H:i:s a  (12-hour with seconds & am/pm)
     *   d/m/Y H:i:s    (24-hour with seconds)
     *   d/m/Y h:i:s a  (12-hour with seconds)
     *   d/m/Y H:i a    (24-hour + am/pm still accepted, pm ignored)
     *   d/m/Y h:i a    (12-hour with am/pm)
     *   d/m/Y H:i      (24-hour)
     *   d/m/Y h:i a    (redundant but kept for clarity)
     *   d/m/Y          (date only)
     * Standalone time (HH:MM or HH:MM am/pm) attaches to today (used for end times sharing start date context externally).
     * Returns DateTime in site timezone or null if parsing fails.
     */
    function causeway_parse_occ_dt(string $raw): ?DateTime {
        $raw = trim($raw);
        if ($raw === '') return null;
        $siteTz = wp_timezone();
        $utc = new DateTimeZone('UTC');

        // Normalize multiple spaces and ensure am/pm lowercase (previous buggy replacement fixed)
        $raw = preg_replace('/\s+/', ' ', $raw);
        $raw = preg_replace_callback('/\b(am|pm)\b/i', function($m){ return strtolower($m[1]); }, $raw);

        // Define strictly dd/mm/YYYY variants (do NOT include Y-m-d to avoid ambiguity)
        $formats = [
            // Order matters: most specific first.
            'd/m/Y h:i:s a', // 04/12/2025 11:30:00 pm
            'd/m/Y g:i:s a', // 4/12/2025 11:30:00 pm (no leading zero day/hour)
            'd/m/Y h:i a',   // 04/12/2025 11:30 pm
            'd/m/Y g:i a',   // 4/12/2025 11:30 pm
            'd/m/Y H:i:s',   // 04/12/2025 23:30:00
            'd/m/Y H:i',     // 04/12/2025 23:30
            'd/m/Y',         // 04/12/2025 (treated as 00:00 UTC then converted, may shift date locally)
        ];

        foreach ($formats as $fmt) {
            // Parse as UTC first. createFromFormat returns given timezone context.
            $dt = DateTime::createFromFormat($fmt, $raw, $utc);
            if ($dt instanceof DateTime) {
                $errs = DateTime::getLastErrors();
                if (empty($errs['warning_count']) && empty($errs['error_count'])) {
                    // Clone to site timezone; this adjusts the wall time.
                    $dt->setTimezone($siteTz);
                    return $dt;
                }
            }
        }

        // Standalone time -> attach to today (HH:MM with optional am/pm) treated as UTC then converted.
        if (preg_match('/^(\d{1,2}):(\d{2})(?:\s?(am|pm))?$/i', $raw, $m)) {
            // Standalone time: treat as today in UTC first.
            $today = new DateTime('today', $utc);
            $hour = (int)$m[1];
            $min  = (int)$m[2];
            $suffix = isset($m[3]) ? strtolower($m[3]) : '';
            if ($suffix === 'pm' && $hour < 12) { $hour += 12; }
            if ($suffix === 'am' && $hour === 12) { $hour = 0; }
            $today->setTime($hour, $min);
            $today->setTimezone($siteTz);
            return $today;
        }

        // Do NOT fallback to strtotime (ambiguous and US-biased). Return null so caller can decide.
        return null;
    }
}
