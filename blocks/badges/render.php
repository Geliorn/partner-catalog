<?php
/**
 * The card pills: «Часто выбирают» (top by clicks, PC_Stats::popular_ids())
 * then free > free tier > trial — at most 2 (decided 2026-10-17). The promo
 * pill is gone: promo codes are shown only on the supplier page now.
 *
 * @var array $attributes Block attributes (max, className).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = get_the_ID();
if ( ! $post_id ) {
	return;
}

$html = PC_Render::badges( $post_id, max( 1, (int) ( $attributes['max'] ?? 2 ) ) );
if ( '' === $html ) {
	return;
}

echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'pc-badges-block' ) ) . '>' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
