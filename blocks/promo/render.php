<?php
/**
 * «Промокоды» (v2 mockup C): every live code from `_pc_promos` as a "ticket" —
 * who it's for, what it gives, checked/expiry dates, and the code blurred
 * until «Показать код» (the click copies it). Self-gating: no live codes — no
 * heading, no block. Expired codes drop out on their own (promo_items()).
 *
 * The «Проверен …» date is the point of the ticket as much as the code is —
 * it is what makes a code believable.
 *
 * @var array $attributes heading.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = get_the_ID();
$items   = $post_id ? PC_Meta_Fields::promo_items( $post_id ) : array();
if ( ! $items ) {
	return;
}

$heading = trim( (string) ( $attributes['heading'] ?? 'Промокоды' ) );

echo '<section ' . get_block_wrapper_attributes( array( 'class' => 'pc-sec pc-promos' ) ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
if ( '' !== $heading ) {
	echo '<h2 class="pc-sec-title">' . esc_html( $heading ) . '</h2>';
}
echo '<div class="pc-tickets">';
foreach ( $items as $item ) {
	$meta = array();
	if ( '' !== $item['checked'] ) {
		$meta[] = 'Проверен ' . PC_Meta_Fields::format_date( $item['checked'] );
	}
	$meta[] = '' !== $item['until'] ? 'действует до ' . PC_Meta_Fields::format_date( $item['until'] ) : 'без срока';

	echo '<div class="pc-ticket"><div class="pc-ticket-text">';
	echo '<div class="pc-ticket-k">' . esc_html( '' !== $item['for'] ? 'Для ' . $item['for'] : 'Промокод' ) . '</div>';
	echo '<div class="pc-ticket-offer">' . esc_html( $item['offer'] ) . '</div>';
	echo '<div class="pc-ticket-meta">' . esc_html( implode( ' · ', $meta ) ) . '</div>';
	echo '</div><div class="pc-ticket-act">';
	printf(
		'<code class="pc-ticket-code" aria-hidden="true">%1$s</code><button type="button" class="pc-btn pc-btn-primary pc-btn-sm pc-promo-copy" data-code="%2$s" data-done="Скопировано ✓">Показать код</button>',
		esc_html( $item['code'] ),
		esc_attr( $item['code'] )
	);
	echo '</div></div>';
}
echo '</div></section>';
