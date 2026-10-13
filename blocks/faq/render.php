<?php
/**
 * Q&A list for a contractor. Native <details>/<summary> rather than a scripted
 * accordion: it opens without JS, is keyboard-accessible and searchable in-page
 * by the browser itself, and needs no viewScript at all.
 *
 * Self-gating (renders nothing with an empty field), so it can sit in the
 * single-page pattern unconditionally — same as price/promo/meta-list.
 *
 * @var array $attributes Block attributes (heading).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = get_the_ID();
if ( ! $post_id ) {
	return;
}

$items = PC_Meta_Fields::faq_items( $post_id );
if ( empty( $items ) ) {
	return;
}

$heading = trim( (string) ( $attributes['heading'] ?? 'Частые вопросы' ) );

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'pc-faq' ) );

echo '<div ' . $wrapper_attributes . '>';

if ( '' !== $heading ) {
	echo '<h3 class="pc-faq-heading">' . esc_html( $heading ) . '</h3>';
}

foreach ( $items as $item ) {
	printf(
		'<details class="pc-faq-item"><summary class="pc-faq-q">%1$s</summary><div class="pc-faq-a">%2$s</div></details>',
		esc_html( $item['q'] ),
		esc_html( $item['a'] )
	);
}

echo '</div>';
