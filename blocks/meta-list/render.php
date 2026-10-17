<?php
/**
 * Server-rendered — reads the current post's meta at request time, so it
 * renders correctly per-item inside a Query Loop (unlike a shortcode, which
 * resolves once against the wrong post — see PC_Links' docblock for the
 * same issue elsewhere in this plugin).
 *
 * Styles: bullet (plain list), checks (✓ icons in columns — «Особенности» on
 * the supplier page), badges (row of pills). The optional heading is printed
 * only when there is something to list, so an empty field leaves no orphan
 * section title on the page.
 *
 * @var array  $attributes Block attributes (metaKey, style, heading).
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
$lines    = PC_Meta_Fields::lines( $post_id, $meta_key );
if ( empty( $lines ) ) {
	return;
}

$style   = in_array( $attributes['style'] ?? 'bullet', array( 'bullet', 'checks', 'badges' ), true ) ? $attributes['style'] : 'bullet';
$heading = trim( (string) ( $attributes['heading'] ?? '' ) );

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'pc-meta-list-block' . ( '' !== $heading ? ' pc-sec' : '' ) ) );

echo '<div ' . $wrapper_attributes . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
if ( '' !== $heading ) {
	echo '<h2 class="pc-sec-title">' . esc_html( $heading ) . '</h2>';
}
echo '<ul class="pc-meta-list pc-meta-list-' . esc_attr( $style ) . '">';
foreach ( $lines as $line ) {
	echo '<li>' . ( 'checks' === $style ? PC_Render::icon( 'check' ) : '' ) . '<span>' . esc_html( $line ) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
}
echo '</ul></div>';
