<?php
/**
 * «Тарифы» table from `_pc_tariffs` (one line per tariff: name | price |
 * what's included). Self-gating: no tariffs — no heading, no block.
 *
 * @var array $attributes heading.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = get_the_ID();
$items   = $post_id ? PC_Meta_Fields::tariff_items( $post_id ) : array();
if ( ! $items ) {
	return;
}

$heading = trim( (string) ( $attributes['heading'] ?? 'Тарифы' ) );

echo '<section ' . get_block_wrapper_attributes( array( 'class' => 'pc-sec pc-tariffs' ) ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
if ( '' !== $heading ) {
	echo '<h2 class="pc-sec-title">' . esc_html( $heading ) . '</h2>';
}
echo '<div class="pc-tariffs-wrap"><table class="pc-tariffs-tbl"><thead><tr><th>Тариф</th><th>Цена</th><th>Что входит</th></tr></thead><tbody>';
foreach ( $items as $item ) {
	printf( '<tr><td>%1$s</td><td class="pc-tariff-price">%2$s</td><td>%3$s</td></tr>', esc_html( $item['name'] ), esc_html( $item['price'] ), esc_html( $item['desc'] ) );
}
echo '</tbody></table></div>';
$checked = (string) get_post_meta( $post_id, '_pc_checked', true );
if ( '' !== $checked ) {
	echo '<p class="pc-note">Цены с сайта сервиса на ' . esc_html( PC_Meta_Fields::format_date( $checked ) ) . '.</p>';
}
echo '</section>';
