<?php

// Minimal isolated checks for Causeway's WPML retry contract. No WordPress database is used.

$test_meta = [];
$test_filters = [
    'wpml_default_language' => true,
    'wpml_element_language_details' => true,
];
$test_actions = [];
$test_options = [];
$test_transients = [];
$test_post = (object) [
    'ID' => 42,
    'post_type' => 'listing',
    'post_status' => 'publish',
];

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

function get_option($key, $default = false) {
    global $test_options;
    return $test_options[$key] ?? $default;
}

function update_option($key, $value, $autoload = null) {
    global $test_options;
    $test_options[$key] = $value;
    return true;
}

function get_transient($key) {
    global $test_transients;
    return $test_transients[$key] ?? false;
}

function set_transient($key, $value, $expiration = 0) {
    global $test_transients;
    $test_transients[$key] = $value;
    return true;
}

function delete_transient($key) {
    global $test_transients;
    unset($test_transients[$key]);
    return true;
}

function wp_generate_uuid4() {
    return 'generated-run-id';
}

function get_post_meta($post_id, $key, $single = false) {
    global $test_meta;
    return $test_meta[$post_id][$key] ?? '';
}

function update_post_meta($post_id, $key, $value) {
    global $test_meta;
    $test_meta[$post_id][$key] = $value;
    return true;
}

function delete_post_meta($post_id, $key) {
    global $test_meta;
    unset($test_meta[$post_id][$key]);
    return true;
}

function has_filter($name) {
    global $test_filters;
    return !empty($test_filters[$name]);
}

function apply_filters($name, $value, ...$args) {
    if ($name === 'wpml_default_language') {
        return 'en';
    }
    if ($name === 'wpml_element_language_details') {
        return (object) [
            'trid' => 99,
            'language_code' => 'en',
            'source_language_code' => null,
        ];
    }
    if ($name === 'wpml_active_languages') {
        return ['en' => [], 'fr' => []];
    }
    return $value;
}

function get_post($post_id) {
    global $test_post;
    $post = clone $test_post;
    $post->ID = $post_id;
    return $post;
}

function has_action($name) {
    return $name === 'wpml_tm_save_post';
}

function do_action($name, ...$args) {
    global $test_actions;
    if ($name === 'wpml_after_save_post') {
        throw new RuntimeException('The importer must not emit wpml_after_save_post.');
    }
    $test_actions[] = [$name, $args];
}

function wp_json_encode($value) {
    return json_encode($value);
}

function get_field($name, $scope = null) {
    return false;
}

require dirname(__DIR__) . '/includes/class-causeway-importer.php';

final class TestJobFactory {
    public $calls = [];
    public $failure = null;

    public function create_local_post_job($post_id, $language) {
        $this->calls[] = [$post_id, $language];
        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }
        return 321;
    }
}

function assert_test($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function invoke_refresh($post_id, array $payload) {
    $method = new ReflectionMethod(Causeway_Importer::class, 'maybe_trigger_wpml_translation_refresh');
    $method->setAccessible(true);
    $method->invoke(null, $post_id, $payload);
}

$wpml_translation_job_factory = new TestJobFactory();
$payload = ['post_title' => 'New listing', 'post_content' => 'Content'];

// Successful refresh: completion is written after one verified factory result.
invoke_refresh(42, $payload);
assert_test(($test_meta[42]['_causeway_translation_state'] ?? '') === 'completed', 'Successful refresh was not marked completed.');
assert_test(!isset($test_meta[42]['_causeway_translation_pending_fingerprint']), 'Successful refresh left pending metadata.');
assert_test(count($wpml_translation_job_factory->calls) === 1, 'Successful refresh did not create exactly one target-language job.');
assert_test(($test_actions[0][0] ?? '') === 'wpml_tm_save_post', 'Translation Management state was not refreshed.');
assert_test(count($test_actions[0][1] ?? []) === 3, 'wpml_tm_save_post did not receive its three required arguments.');

// Unchanged payload: no duplicate job is created.
$wpml_translation_job_factory->calls = [];
invoke_refresh(42, $payload);
assert_test(count($wpml_translation_job_factory->calls) === 0, 'Unchanged payload created a duplicate translation job.');

// Failed changed payload: old completion remains, failure stays pending, and the next call retries.
$failed_payload = ['post_title' => 'Changed listing', 'post_content' => 'Changed content'];
$completed_before_failure = $test_meta[42]['_causeway_translation_fingerprint'];
$wpml_translation_job_factory->failure = new RuntimeException('Injected job failure');
$thrown = false;
try {
    invoke_refresh(42, $failed_payload);
} catch (RuntimeException $e) {
    $thrown = strpos($e->getMessage(), 'Injected job failure') !== false;
}
assert_test($thrown, 'Injected translation failure did not propagate.');
assert_test(($test_meta[42]['_causeway_translation_state'] ?? '') === 'failed', 'Translation failure was not marked failed.');
assert_test($test_meta[42]['_causeway_translation_fingerprint'] === $completed_before_failure, 'Failure overwrote the last completed fingerprint.');
assert_test(!empty($test_meta[42]['_causeway_translation_pending_fingerprint']), 'Failure did not preserve a pending fingerprint.');

$wpml_translation_job_factory->failure = null;
$wpml_translation_job_factory->calls = [];
invoke_refresh(42, $failed_payload);
assert_test(count($wpml_translation_job_factory->calls) === 1, 'Failed translation was not retried.');
assert_test(($test_meta[42]['_causeway_translation_state'] ?? '') === 'completed', 'Successful retry was not marked completed.');

// Missing WPML integration is deferred, not falsely marked complete.
$test_filters['wpml_element_language_details'] = false;
invoke_refresh(43, $payload);
assert_test(($test_meta[43]['_causeway_translation_state'] ?? '') === 'pending', 'Unavailable WPML integration was not left pending.');
assert_test(empty($test_meta[43]['_causeway_translation_fingerprint']), 'Deferred translation was incorrectly marked complete.');
$test_filters['wpml_element_language_details'] = true;

// Run ownership prevents an older process from releasing a newer run's lock.
$test_options['causeway_import_status'] = [
    'running' => true,
    'state' => 'queued',
    'run_id' => 'owned-run-id',
    'updated_at' => time(),
];
$test_transients['causeway_import_lock'] = time();
$begin_run = new ReflectionMethod(Causeway_Importer::class, 'begin_run');
$begin_run->setAccessible(true);
$begin_run->invoke(null);

$test_options['causeway_import_status']['run_id'] = 'newer-run-id';
$release_lock = new ReflectionMethod(Causeway_Importer::class, 'release_import_lock');
$release_lock->setAccessible(true);
$release_lock->invoke(null);
assert_test(isset($test_transients['causeway_import_lock']), 'An older run released a newer run\'s lock.');

$test_options['causeway_import_status']['run_id'] = 'owned-run-id';
$release_lock->invoke(null);
assert_test(!isset($test_transients['causeway_import_lock']), 'The owning run could not release its lock.');

// Historical activity is persisted separately from the live progress status.
$run_id_property = new ReflectionProperty(Causeway_Importer::class, 'run_id');
$run_id_property->setAccessible(true);
$run_id_property->setValue(null, 'tracking-run-id');
$start_property = new ReflectionProperty(Causeway_Importer::class, 'start');
$start_property->setAccessible(true);
$start_property->setValue(null, microtime(true) - 2);
$processed_property = new ReflectionProperty(Causeway_Importer::class, 'processed_listings');
$processed_property->setAccessible(true);
$processed_property->setValue(null, 3);
$total_property = new ReflectionProperty(Causeway_Importer::class, 'total_listings');
$total_property->setAccessible(true);
$total_property->setValue(null, 4);

$track_start = new ReflectionMethod(Causeway_Importer::class, 'track_import_start');
$track_start->setAccessible(true);
$track_start->invoke(null, 'listings');
$track_success = new ReflectionMethod(Causeway_Importer::class, 'track_import_success');
$track_success->setAccessible(true);
$track_success->invoke(null);

$tracking = $test_options['causeway_import_tracking'] ?? [];
assert_test(($tracking['last_import_status'] ?? '') === 'success', 'Successful import activity was not recorded.');
assert_test(($tracking['last_import_mode'] ?? '') === 'listings', 'Import mode was not recorded.');
assert_test(($tracking['last_import_processed'] ?? 0) === 3, 'Processed listing count was not recorded.');
assert_test(($tracking['last_import_total'] ?? 0) === 4, 'Total listing count was not recorded.');
assert_test(!empty($tracking['last_import_succeeded_at']), 'Successful import timestamp was not recorded.');
assert_test(($tracking['last_import_duration_seconds'] ?? 0) >= 1.9, 'Import duration was not recorded.');

$run_id_property->setValue(null, 'stale-run-id');
$track_failure = new ReflectionMethod(Causeway_Importer::class, 'track_import_failure');
$track_failure->setAccessible(true);
$track_failure->invoke(null, new RuntimeException('Stale run failure'));
assert_test(
    ($test_options['causeway_import_tracking']['last_import_status'] ?? '') === 'success',
    'A stale run overwrote newer import activity.'
);

$export_result = Causeway_Importer::export_listings();
assert_test($export_result === false, 'A skipped export was reported as successful.');
assert_test(
    ($test_options['causeway_import_tracking']['last_export_status'] ?? '') === 'skipped',
    'A skipped export was not recorded.'
);
assert_test(
    !empty($test_options['causeway_import_tracking']['last_export_requested_at']),
    'The export request timestamp was not recorded.'
);

fwrite(STDOUT, "Causeway importer WPML tests passed.\n");
