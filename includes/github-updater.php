<?php
/**
 * Lightweight GitHub updater for the Causeway plugin (public repo).
 *
 * - Checks GitHub releases for a newer version.
 * - Supplies update package and plugin info to WP.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Causeway_GitHub_Updater')) {

    class Causeway_GitHub_Updater
    {
        private string $file;
        private string $slug;
        private string $plugin_basename;
        private string $repo_owner;
        private string $repo_name;
        private string $plugin_version;
        private string $repo_url;

        public function __construct(string $file, string $repo_owner, string $repo_name)
        {
            $this->file            = $file;
            $this->plugin_basename = plugin_basename($file);
            $this->slug            = dirname($this->plugin_basename);
            $this->repo_owner      = $repo_owner;
            $this->repo_name       = $repo_name;
            $this->repo_url        = sprintf('https://github.com/%s/%s', $repo_owner, $repo_name);

            $plugin_data = get_file_data($this->file, [
                'Version' => 'Version',
            ]);
            $this->plugin_version = $plugin_data['Version'] ?? '0.0.0';

            add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
            add_filter('plugins_api', [$this, 'plugins_api'], 10, 3);
            // Ensure extracted GitHub zip is renamed to the plugin directory name (avoids asset zip requirement)
            add_filter('upgrader_source_selection', [$this, 'maybe_rename_source_dir'], 10, 4);
            // After files are moved into place, validate structure & log
            add_filter('upgrader_post_install', [$this, 'post_install'], 10, 3);
        }

        public function check_for_update($transient)
        {
            if (empty($transient->checked) || !is_object($transient)) {
                return $transient;
            }

            $release = $this->get_latest_release();
            if (!$release) {
                return $transient;
            }

            $remote_version = $this->normalize_version($release['tag_name'] ?? '');
            if (!$remote_version) {
                return $transient;
            }

            if (version_compare($remote_version, $this->plugin_version, '>')) {
                $obj = new stdClass();
                $obj->slug        = $this->slug;
                $obj->plugin      = $this->plugin_basename;
                $obj->new_version = $remote_version;
                $obj->url         = $this->repo_url;
                $obj->package     = $this->get_download_url($release);
                $obj->tested      = get_bloginfo('version');

                $transient->response[$this->plugin_basename] = $obj;
            }

            return $transient;
        }

        public function plugins_api($result, $action, $args)
        {
            if ($action !== 'plugin_information') {
                return $result;
            }
            if (!isset($args->slug) || $args->slug !== $this->slug) {
                return $result;
            }

            $release = $this->get_latest_release();
            $remote_version = $this->normalize_version($release['tag_name'] ?? '');

            $info = new stdClass();
            $info->name         = 'Causeway Listings Importer';
            $info->slug         = $this->slug;
            $info->version      = $remote_version ?: $this->plugin_version;
            $info->author       = '<a href="https://novastream.ca">NovaStream</a>';
            $info->homepage     = $this->repo_url;
            $info->requires     = '5.8';
            $info->tested       = get_bloginfo('version');
            $info->download_link = $release ? $this->get_download_url($release) : '';
            $info->sections     = [
                'description' => 'Imports listings and taxonomies from Causeway API into WordPress with WPML and ACF integration.',
                'changelog'   => isset($release['body']) ? wp_kses_post(nl2br($release['body'])) : 'See GitHub releases.',
            ];

            return $info;
        }

        private function get_latest_release(): ?array
        {
            $cache_key = 'causeway_github_latest_release';
            $cached    = get_site_transient($cache_key);
            if (is_array($cached)) {
                return $cached;
            }

            $url = sprintf('https://api.github.com/repos/%s/%s/releases/latest', $this->repo_owner, $this->repo_name);

            $args = [
                'timeout' => 20,
                'headers' => $this->build_headers(),
            ];

            $res = wp_remote_get($url, $args);
            if (is_wp_error($res)) {
                return null;
            }
            $code = (int) wp_remote_retrieve_response_code($res);
            if ($code !== 200) {
                return null;
            }

            $data = json_decode(wp_remote_retrieve_body($res), true);
            if (!is_array($data)) {
                return null;
            }

            // Cache for 30 minutes to avoid rate limits
            set_site_transient($cache_key, $data, 30 * MINUTE_IN_SECONDS);
            return $data;
        }

        private function get_download_url(array $release): string
        {
            // Prefer a release asset named like the plugin slug to avoid renaming issues
            if (!empty($release['assets']) && is_array($release['assets'])) {
                foreach ($release['assets'] as $asset) {
                    $name = $asset['name'] ?? '';
                    $url  = $asset['browser_download_url'] ?? '';
                    if ($name && $url && preg_match('/^' . preg_quote($this->slug, '/') . '.*\.zip$/i', $name)) {
                        error_log('[Causeway Updater] Using release asset for update: ' . $name);
                        return $url;
                    }
                }
            }

            // Fallback to zipball_url from API. This points to codeload.github.com
            $zip_url = $release['zipball_url'] ?? '';
            if (!$zip_url && isset($release['tag_name'])) {
                $zip_url = sprintf('https://api.github.com/repos/%s/%s/zipball/%s', $this->repo_owner, $this->repo_name, $release['tag_name']);
            }
            return $zip_url;
        }

        /**
         * Rename the extracted source directory from GitHub zipball to match the plugin's folder name.
         * This avoids the need to upload a custom asset zip with a specific top-level folder.
         */
        public function maybe_rename_source_dir($source, $remote_source, $upgrader, $hook_extra)
        {
            // Only affect this plugin
            $is_this_plugin = false;
            if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->plugin_basename) {
                $is_this_plugin = true;
            }
            if (isset($hook_extra['plugins']) && is_array($hook_extra['plugins'])) {
                if (in_array($this->plugin_basename, $hook_extra['plugins'], true)) {
                    $is_this_plugin = true;
                }
            }
            if (!$is_this_plugin) {
                return $source;
            }

            // If the extracted dir already matches our slug, nothing to do
            if (basename($source) === $this->slug) {
                error_log('[Causeway Updater] Source already matches slug: ' . $source);
                return $source;
            }

            $desired = trailingslashit($remote_source) . $this->slug;
            error_log('[Causeway Updater] Source selection: source=' . $source . ' remote_source=' . $remote_source . ' desired=' . $desired);

            // Try to rename; suppress warnings and fall back to original on failure
            if (@rename($source, $desired)) {
                error_log('[Causeway Updater] Renamed extracted directory from ' . basename($source) . ' to ' . $this->slug);
                // Ensure plugin main file exists after rename
                $main = trailingslashit($desired) . basename($this->plugin_basename); // causeway.php
                if (!file_exists($main)) {
                    error_log('[Causeway Updater] WARNING: main plugin file not found after rename: ' . $main);
                }
                return trailingslashit($desired);
            } else {
                error_log('[Causeway Updater] Rename failed for ' . $source . ' -> ' . $desired . '. Attempting fallback copy.');
                // Fallback: attempt recursive copy then remove original
                if ($this->recursive_copy($source, $desired)) {
                    error_log('[Causeway Updater] Fallback copy succeeded. Removing original temp dir.');
                    $this->recursive_delete($source);
                    $main = trailingslashit($desired) . basename($this->plugin_basename);
                    if (!file_exists($main)) {
                        error_log('[Causeway Updater] WARNING: main plugin file missing after fallback copy: ' . $main);
                    }
                    return trailingslashit($desired);
                } else {
                    error_log('[Causeway Updater] Fallback copy failed; leaving original directory.');
                }
            }

            return $source; // worst case WP will proceed; may show failure
        }

        /**
         * Runs after WP moves the new version into the plugins directory.
         * We verify that the destination contains the expected plugin file and log diagnostics.
         *
         * @param bool  $result       Install result.
         * @param array $hook_extra   Extra args passed by upgrader (contains 'plugin').
         * @param array $data         Array with destination / source etc.
         * @return bool
         */
        public function post_install($result, $hook_extra, $data)
        {
            if (!$result) {
                error_log('[Causeway Updater] post_install: result=false early');
                return $result;
            }
            if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_basename) {
                return $result; // Not our plugin
            }
            $dest = $data['destination'] ?? '';
            $main = trailingslashit($dest) . basename($this->plugin_basename);
            if (!file_exists($main)) {
                // Look one level deeper in case of nested directory
                $nested_main = '';
                $entries = @scandir($dest);
                if (is_array($entries)) {
                    foreach ($entries as $e) {
                        if ($e === '.' || $e === '..') continue;
                        $candidate = trailingslashit($dest) . $e . '/' . basename($this->plugin_basename);
                        if (file_exists($candidate)) {
                            $nested_main = $candidate;
                            break;
                        }
                    }
                }
                if ($nested_main) {
                    error_log('[Causeway Updater] Detected nested plugin directory. Attempting to promote contents.');
                    $nested_dir = dirname($nested_main);
                    // Move files up a level
                    $items = scandir($nested_dir);
                    if (is_array($items)) {
                        foreach ($items as $item) {
                            if ($item === '.' || $item === '..') continue;
                            @rename($nested_dir . '/' . $item, $dest . '/' . $item);
                        }
                    }
                    @rmdir($nested_dir);
                    if (!file_exists($main)) {
                        error_log('[Causeway Updater] ERROR: main plugin file still missing after promoting nested directory: ' . $main);
                    } else {
                        error_log('[Causeway Updater] Successfully flattened nested directory.');
                    }
                } else {
                    error_log('[Causeway Updater] ERROR: main plugin file not found in destination: ' . $main);
                }
            } else {
                error_log('[Causeway Updater] post_install verification OK.');
            }
            return $result;
        }

        private function build_headers(): array
        {
            // Public repos don't need auth headers
            return [
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url('/'),
            ];
        }

        private function recursive_copy($src, $dst): bool {
            if (!is_dir($src)) return false;
            if (!@mkdir($dst, 0755, true) && !is_dir($dst)) return false;
            $items = scandir($src);
            if ($items === false) return false;
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $from = $src . DIRECTORY_SEPARATOR . $item;
                $to   = $dst . DIRECTORY_SEPARATOR . $item;
                if (is_dir($from)) {
                    if (!$this->recursive_copy($from, $to)) return false;
                } else {
                    if (!@copy($from, $to)) return false;
                }
            }
            return true;
        }

        private function recursive_delete($dir): void {
            if (!is_dir($dir)) return;
            $items = scandir($dir);
            if ($items === false) return;
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                if (is_dir($path)) {
                    $this->recursive_delete($path);
                } else {
                    @unlink($path);
                }
            }
            @rmdir($dir);
        }

        private function normalize_version(string $tag): string
        {
            $tag = trim($tag);
            if ($tag === '') return '';
            if ($tag[0] === 'v' || $tag[0] === 'V') {
                $tag = substr($tag, 1);
            }
            // Keep only digits and dots
            if (!preg_match('/^\d+\.\d+\.\d+(?:[\w.-]*)?$/', $tag)) {
                // Fallback: try to extract semantic version
                if (preg_match('/(\d+\.\d+\.\d+(?:[\w.-]*)?)/', $tag, $m)) {
                    return $m[1];
                }
            }
            return $tag;
        }
    }
}
