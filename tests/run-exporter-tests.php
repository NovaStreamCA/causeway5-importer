<?php

// Isolated relationship-formatting checks. No WordPress database is used.

function add_action($hook, $callback) {}

class WP_Term {
    public $term_id;
    public $name;

    public function __construct($term_id, $name = '') {
        $this->term_id = $term_id;
        $this->name = $name;
    }
}

function get_term($term_id, $taxonomy = '') {
    $terms = [
        101 => new WP_Term(101, 'Things to Do'),
        102 => new WP_Term(102, 'Places to Stay'),
    ];
    return $terms[(int) $term_id] ?? false;
}

function is_wp_error($value) {
    return false;
}

function get_field($name, $scope = null) {
    if ($name !== 'causeway_id') {
        return false;
    }

    return [
        'listing-type_101' => 4,
        'listing-type_102' => 1,
    ][$scope] ?? false;
}

require dirname(__DIR__) . '/includes/causeway-exporter.php';

function assert_exporter_test($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$map = ['listing_type' => 'listing-type'];

$numeric = format_related_acf_ids($map, ['listing_type' => '101']);
assert_exporter_test(($numeric['type']['id'] ?? null) === 4, 'Numeric-string relationship was not formatted.');

$object = format_related_acf_ids($map, ['listing_type' => new WP_Term(101)]);
assert_exporter_test(($object['type']['id'] ?? null) === 4, 'WP_Term relationship was not formatted.');

$objects = format_related_acf_ids($map, ['listing_type' => [new WP_Term(101), new WP_Term(102)]]);
assert_exporter_test(($objects['type'][0]['id'] ?? null) === 4, 'First WP_Term in relationship array was not formatted.');
assert_exporter_test(($objects['type'][1]['id'] ?? null) === 1, 'Second WP_Term in relationship array was not formatted.');

$term_array = format_related_acf_ids($map, ['listing_type' => ['term_id' => 101]]);
assert_exporter_test(($term_array['type']['id'] ?? null) === 4, 'Associative term array was not formatted as a single relationship.');

$ids = format_related_acf_ids($map, ['listing_type' => [101, '102']]);
assert_exporter_test(count($ids['type'] ?? []) === 2, 'ID array relationship changed shape.');

fwrite(STDOUT, "Causeway exporter relationship tests passed.\n");
