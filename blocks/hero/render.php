<?php
/**
 * Supplier page header (v2 mockup C = header of B): breadcrumbs, logo, name,
 * type line, «Подойдёт, если…», the tracked «Перейти на сайт» button, and
 * three tiles — visitors' score, price, strongest criterion.
 *
 * The CTA goes through /out/{id}/ (PC_Links: click count + UTM) and carries
 * pc-outbound-link for the Metrika goal in assets/js/pc-analytics.js.
 *
 * @var array $attributes align/className.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = get_the_ID();
if ( ! $post_id || 'contractor' !== get_post_type( $post_id ) ) {
	return;
}

$types  = get_the_terms( $post_id, 'contractor_type' );
$crumbs = array( '<a href="' . esc_url( get_post_type_archive_link( 'contractor' ) ) . '">Сервисы</a>' );
if ( is_array( $types ) && $types ) {
	$crumbs[] = '<a href="' . esc_url( get_term_link( $types[0] ) ) . '">' . esc_html( $types[0]->name ) . '</a>';
}
$crumbs[] = '<span>' . esc_html( get_the_title( $post_id ) ) . '</span>';

$fit     = trim( (string) get_post_meta( $post_id, '_pc_fit', true ) );
$website = trim( (string) get_post_meta( $post_id, '_pc_website', true ) );

// ---- tiles
$tiles = array();
if ( PC_Settings::integration_enabled( 'ratings' ) ) {
	$count = PC_Ratings::get_count( $post_id );
	if ( $count > 0 ) {
		$avg     = PC_Ratings::get_average( $post_id );
		$tiles[] = '<a class="pc-score" href="#pc-rating"><span class="pc-score-k">Посетители</span>'
			. '<span class="pc-score-v">' . esc_html( number_format_i18n( $avg, 1 ) ) . ' ' . PC_Render::stars( $avg, 14 ) . '</span>'
			. '<span class="pc-score-s">' . esc_html( $count . ' ' . PC_Ratings::ru_plural( $count, 'оценка', 'оценки', 'оценок' ) ) . '</span></a>';
	} else {
		$tiles[] = '<a class="pc-score" href="#pc-rating"><span class="pc-score-k">Посетители</span>'
			. '<span class="pc-score-v">' . PC_Render::stars( 0, 16 ) . '</span><span class="pc-score-s">Пока нет оценок</span></a>';
	}
}
$price = PC_Meta_Fields::price_text( $post_id );
if ( '' !== $price ) {
	$note    = PC_Meta_Fields::price_note( $post_id );
	$tiles[] = '<div class="pc-score"><span class="pc-score-k">Цена</span><span class="pc-score-v pc-score-price">' . esc_html( $price ) . '</span>'
		. ( '' !== $note ? '<span class="pc-score-s">' . esc_html( $note ) . '</span>' : '' ) . '</div>';
}
$best = PC_Settings::integration_enabled( 'ratings' ) ? PC_Ratings::best_criterion( $post_id ) : null;
if ( $best ) {
	$tiles[] = '<div class="pc-score"><span class="pc-score-k">Сильная сторона</span><span class="pc-score-v pc-score-best">' . esc_html( number_format_i18n( $best['average'], 1 ) ) . '</span>'
		. '<span class="pc-score-s">' . esc_html( $best['label'] ) . '</span></div>';
}

echo '<header ' . get_block_wrapper_attributes( array( 'class' => 'pc-hero' ) ) . '><div class="pc-hero-in">'; // phpcs:ignore WordPress.Security.EscapeOutput
echo '<nav class="pc-crumbs" aria-label="Хлебные крошки">' . implode( '<span aria-hidden="true">›</span>', $crumbs ) . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput
echo '<div class="pc-hero-grid"><div class="pc-hero-main">';
echo '<div class="pc-hero-id">' . PC_Render::logo( $post_id, 'pc-logo-lg' ) . '<div>'; // phpcs:ignore WordPress.Security.EscapeOutput
echo '<h1 class="pc-hero-title">' . esc_html( get_the_title( $post_id ) ) . '</h1>';
echo PC_Render::meta_line( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput
echo '</div></div>';
if ( '' !== $fit ) {
	echo '<p class="pc-hero-fit"><b>Подойдёт, если</b> ' . esc_html( rtrim( $fit, '.' ) ) . '.</p>';
}
if ( '' !== $website ) {
	printf(
		'<div class="pc-hero-cta"><a class="pc-btn pc-btn-primary pc-btn-lg pc-outbound-link" href="%1$s" rel="nofollow sponsored noopener" target="_blank">Перейти на сайт %2$s</a></div>',
		esc_url( PC_Links::build_redirect_url( $post_id ) ),
		PC_Render::icon( 'ext' ) // phpcs:ignore WordPress.Security.EscapeOutput
	);
}
echo '</div>';
if ( $tiles ) {
	echo '<div class="pc-scores">' . implode( '', $tiles ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
}
echo '</div></div></header>';
