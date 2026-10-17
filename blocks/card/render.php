<?php
/**
 * Archive card (v2 mockup A). One server-rendered block instead of a stack of
 * core blocks with bindings: the card needs logic a binding can't express
 * (≤2 pills in a priority order, «Часто выбирают» from click stats, facts
 * built from several fields, stars). Markup lives in PC_Render::card() so the
 * table and the mini cards stay in step with it.
 *
 * @var array $attributes Unused beyond className.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = get_the_ID();
if ( ! $post_id || 'contractor' !== get_post_type( $post_id ) ) {
	return;
}

echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'pc-card-wrap' ) ) . '>' . PC_Render::card( $post_id ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- PC_Render escapes.
