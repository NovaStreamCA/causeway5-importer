<?php
// Overrides acf taxonomy input to return causeway id if selected

// Inject custom "Return Format" setting
add_action('acf/render_field_settings/type=taxonomy', function ($field) {
    acf_render_field_setting($field, [
        'label'        => __('Return Format'),
        'instructions' => __('Specify the value returned'),
        'name'         => 'return_format',
        'type'         => 'select',
        'choices'      => [
            'object'       => 'Term Object',
            'id'           => 'Term ID',
            'name'         => 'Term Name',
            'slug'         => 'Term Slug',
            'causeway_id'  => 'Causeway ID',
        ],
    ]);
}, 10, 1);

// Modify output when causeway_id is selected
add_filter('acf/format_value/type=taxonomy', function ($value, $post_id, $field) {
    if (!isset($field['return_format']) || $field['return_format'] !== 'causeway_id') {
        return $value;
    }

    $taxonomy = $field['taxonomy'];

    if (empty($value)) return $value;

    if (is_array($value)) {
        return array_map(function ($term) use ($taxonomy) {
            $term_id = is_object($term) ? $term->term_id : $term;
            return get_field('causeway_id', "{$taxonomy}_{$term_id}") ?: $term_id;
        }, $value);
    }

    $term_id = is_object($value) ? $value->term_id : $value;
    return get_field('causeway_id', "{$taxonomy}_{$term_id}") ?: $term_id;
}, 10, 3);
