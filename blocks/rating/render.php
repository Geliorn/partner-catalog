<?php
/**
 * «Оценки посетителей» (v2 mockup C = section of B): the overall score with
 * SVG stars, a bar per criterion, and the "Пользовались? Оцените" form —
 * five star buttons per criterion, always visible, one submit for all.
 * Stars replaced the hearts in 0.5.0 (Google-snippet look, PC_Render::stars).
 *
 * interactive=false is the compact read-only line used on cards
 * ("4,6 ★★★★★ (128)") — kept for back-compat with saved patterns.
 *
 * @var array $attributes heading, interactive, size, className.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = get_the_ID();
if ( ! $post_id ) {
	return;
}

if ( ! ( $attributes['interactive'] ?? true ) ) {
	echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'pc-rating-compact' ) ) . '>' . PC_Render::rating( $post_id ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

$average  = PC_Ratings::get_average( $post_id );
$count    = PC_Ratings::get_count( $post_id );
$voted    = PC_Ratings::has_voted( $post_id );
$criteria = PC_Ratings::criteria();
$heading  = trim( (string) ( $attributes['heading'] ?? 'Оценки посетителей' ) );
$star     = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="' . PC_Render::STAR_PATH . '"/></svg>';

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'         => 'pc-sec pc-rates' . ( $voted ? ' is-voted' : '' ),
		'id'            => 'pc-rating',
		'data-post-id'  => $post_id,
		'data-nonce'    => wp_create_nonce( PC_Ratings::NONCE_ACTION ),
		'data-ajax-url' => admin_url( 'admin-ajax.php' ),
	)
);

echo '<section ' . $wrapper_attributes . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
if ( '' !== $heading ) {
	echo '<h2 class="pc-sec-title">' . esc_html( $heading ) . '</h2>';
}
echo '<div class="pc-rates-box">';

// ---- summary
echo '<div class="pc-rates-sum">';
echo '<span class="pc-r-big">' . ( $count ? esc_html( number_format_i18n( $average, 1 ) ) : '—' ) . '</span>';
echo '<span class="pc-r-stars">' . PC_Render::stars( $count ? $average : 0, 22 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
echo '<span class="pc-r-total">' . esc_html( $count ? $count . ' ' . PC_Ratings::ru_plural( $count, 'оценка', 'оценки', 'оценок' ) : 'Пока нет оценок' ) . '</span>';
echo '</div>';

// ---- per-criterion bars
echo '<div class="pc-r-bars">';
foreach ( $criteria as $key => $label ) {
	$c = PC_Ratings::get_criterion( $post_id, $key );
	printf(
		'<div class="pc-r-bar" data-criterion="%1$s"><span>%2$s</span><b>%3$s</b><span class="pc-r-track"><i style="width:%4$s%%"></i></span></div>',
		esc_attr( $key ),
		esc_html( $label ),
		$c['count'] ? esc_html( number_format_i18n( $c['average'], 1 ) ) : '—',
		$c['count'] ? round( $c['average'] / 5 * 100, 1 ) : 0
	);
}
echo '</div>';

// ---- the form
echo '<div class="pc-rate-me">';
if ( $voted ) {
	echo '<p class="pc-rate-thanks">Спасибо, ваша оценка учтена.</p>';
} else {
	echo '<div class="pc-rate-me-head"><b>Пользовались? Оцените</b><span>Без текста отзыва, можно не все критерии</span></div>';
	echo '<div class="pc-rate-grid">';
	foreach ( $criteria as $key => $label ) {
		printf( '<div class="pc-rate-row" data-criterion="%1$s"><span>%2$s</span><span class="pc-star-input" role="group" aria-label="%3$s">', esc_attr( $key ), esc_html( $label ), esc_attr( $label . ' — оценка от 1 до 5' ) );
		for ( $i = 1; $i <= 5; $i++ ) {
			printf( '<button type="button" data-value="%1$d" aria-label="%2$s: %1$d из 5">%3$s</button>', $i, esc_attr( $label ), $star ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</span></div>';
	}
	echo '</div>';
	echo '<button type="button" class="pc-btn pc-btn-primary pc-btn-sm pc-rate-submit" disabled>Отправить оценку</button>';
	echo '<p class="pc-rate-note">Один голос с одного браузера.</p>';
}
echo '</div>';

echo '</div></section>';
