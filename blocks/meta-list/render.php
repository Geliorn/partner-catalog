<?php
/**
 * Server-rendered — reads the current post's meta at request time, so it
 * renders correctly per-item inside a Query Loop (unlike a shortcode, which
 * resolves once against the wrong post — see PC_Links' docblock for the
 * same issue elsewhere in this plugin).
 *
 * @var array  $attributes Block attributes (metaKey, style).
 * @var string $content    Unused — dynamic block, no saved inner content.
 * @var WP_Block $block    Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = get_the_ID();
if ( ! $post_id ) {
	return;
}

$meta_key = $attributes['metaKey'] ?? '_pc_features';
$raw      = get_post_meta( $post_id, $meta_key, true );
$lines    = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $raw ) ) );

if ( empty( $lines ) ) {
	return;
}

$is_badges = 'badges' === ( $attributes['style'] ?? 'bullet' );
$classes   = 'pc-meta-list' . ( $is_badges ? ' pc-meta-list-badges' : ' pc-meta-list-bullet' );

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => $classes ) );

echo '<ul ' . $wrapper_attributes . '>';
foreach ( $lines as $line ) {
	echo '<li>' . esc_html( $line ) . '</li>';
}
echo '</ul>';
