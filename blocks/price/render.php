<?php
/**
 * The record's price (`_pc_price_type` + `_pc_price_value`) as one display
 * string — formatting lives in PC_Meta_Fields::price_text() so the card, the
 * comparison table and the page header all say the same thing.
 *
 * @var array $attributes Block attributes (fontSize).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = get_the_ID();
if ( ! $post_id ) {
	return;
}

$text = PC_Meta_Fields::price_text( $post_id );
if ( '' === $text ) {
	return;
}

$font_size_class    = ! empty( $attributes['fontSize'] ) ? ' has-' . $attributes['fontSize'] . '-font-size has-custom-font-size' : '';
$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'pc-price-block' . $font_size_class ) );

echo '<p ' . $wrapper_attributes . '>' . esc_html( $text ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
