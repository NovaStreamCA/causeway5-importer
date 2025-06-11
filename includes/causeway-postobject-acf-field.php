<?php
// Overrides acf post object input to return causeway id if selected

// Inject custom "Return Format" setting
add_filter('acf/prepare_field/name=return_format', function ($field) {
    // Only modify for post_object field settings screen
    if (isset($field['wrapper']['data-setting']) && $field['wrapper']['data-setting'] === 'post_object') {
        $field['choices']['causeway_id'] = 'Causeway ID';
    }

    return $field;
});


// Modify output when causeway_id is selected
add_filter('acf/format_value/type=post_object', function ($value, $post_id, $field) {

    if (!isset($field['return_format']) || $field['return_format'] !== 'causeway_id') {
        return $value;
    }

    if (empty($value)) {
        return $value;
    }

    if (is_array($value)) {
        return array_map(function ($post) {
            $post_id = is_object($post) ? $post->ID : $post;
            return get_field('causeway_id', $post_id) ?: $post_id;
        }, $value);
    }

    $post_id = is_object($value) ? $value->ID : $value;

    return get_field('causeway_id', $post_id) ?: $post_id;
}, 10, 3);
