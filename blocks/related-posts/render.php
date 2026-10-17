<?php
/**
 * «Альтернативы» at the bottom of the supplier page (v2 mockup C): mini cards
 * with logo, type, stars, two facts, price and «Подробнее →» pinned to the
 * card bottom. Self-gating with its heading — no matches, no section.
 *
 * @var array $attributes Block attributes (limit, heading).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = get_the_ID();
if ( ! $post_id ) {
	return;
}

$limit   = isset( $attributes['limit'] ) ? absint( $attributes['limit'] ) : 3;
$related = PC_Related::get_related( $post_id, $limit ?: 3 );
if ( empty( $related ) ) {
	return;
}

$heading = trim( (string) ( $attributes['heading'] ?? 'Альтернативы' ) );

echo '<section ' . get_block_wrapper_attributes( array( 'class' => 'pc-sec pc-alts' ) ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
if ( '' !== $heading ) {
	echo '<h2 class="pc-sec-title">' . esc_html( $heading ) . '</h2>';
}
echo '<div class="pc-alt-grid">';
foreach ( $related as $related_post ) {
	echo PC_Render::mini( $related_post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput
}
echo '</div></section>';
