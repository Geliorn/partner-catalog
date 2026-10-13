<?php
/**
 * Promo code box. Self-gating: renders nothing when there is no code or the
 * code has expired, so it can sit in the single-page pattern unconditionally
 * (same approach as meta-list/price).
 *
 * The "Проверено …" line is the point of the block as much as the code is —
 * it is what makes a code believable, and it's the one thing the reference
 * catalog puts next to every promo.
 *
 * @var array $attributes Block attributes (unused beyond className).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = get_the_ID();
if ( ! $post_id || ! PC_Meta_Fields::has_live_promo( $post_id ) ) {
	return;
}

$code        = trim( (string) get_post_meta( $post_id, '_pc_promo_code', true ) );
$description = trim( (string) get_post_meta( $post_id, '_pc_promo_description', true ) );
$expires     = (string) get_post_meta( $post_id, '_pc_promo_expires', true );
$checked     = (string) get_post_meta( $post_id, '_pc_promo_checked', true );

// Fixed d.m.Y rather than the site's date_format: the rest of this plugin is
// Russian-only hardcoded copy, and the option is usually left at WP's English
// default ("October 13, 2026"), which reads wrong next to «Проверено».
$date_format = 'd.m.Y';

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'pc-promo' ) );

echo '<div ' . $wrapper_attributes . '>';

echo '<p class="pc-promo-title">Промокод</p>';

if ( '' !== $description ) {
	echo '<p class="pc-promo-desc">' . esc_html( $description ) . '</p>';
}

printf(
	'<div class="pc-promo-code-row"><code class="pc-promo-code">%1$s</code>'
		. '<button type="button" class="pc-promo-copy" data-code="%2$s" data-done="Скопировано">Скопировать</button></div>',
	esc_html( $code ),
	esc_attr( $code )
);

$notes = array();
if ( '' !== $checked ) {
	$notes[] = 'Проверено ' . wp_date( $date_format, strtotime( $checked . ' 12:00:00' ) );
}
if ( '' !== $expires ) {
	$notes[] = 'действует до ' . wp_date( $date_format, strtotime( $expires . ' 12:00:00' ) );
}
if ( $notes ) {
	echo '<p class="pc-promo-note">' . esc_html( implode( ' · ', $notes ) ) . '</p>';
}

printf(
	'<p class="pc-promo-cta"><a class="pc-outbound-link" href="%1$s" rel="nofollow noreferrer noopener">Перейти на сайт и применить →</a></p>',
	esc_url( PC_Links::build_redirect_url( $post_id ) )
);

echo '</div>';
