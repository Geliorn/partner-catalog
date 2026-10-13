<?php
/**
 * @var array $attributes Block attributes (size: 'small'|'large', interactive: bool).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = get_the_ID();
if ( ! $post_id ) {
	return;
}

$average     = PC_Ratings::get_average( $post_id );
$count       = PC_Ratings::get_count( $post_id );
$voted       = PC_Ratings::has_voted( $post_id );
$size        = 'large' === ( $attributes['size'] ?? 'small' ) ? 'large' : 'small';
$interactive = (bool) ( $attributes['interactive'] ?? true );

$class = 'pc-rating pc-rating-' . $size . ( $voted ? ' pc-rating-voted' : '' ) . ( $interactive ? '' : ' pc-rating-readonly' );

if ( ! $interactive ) {
	// A card's rating is a plain current-score display, not a voting
	// control — one heart + the number, no criteria rows (see
	// lagr/rating's render.php for the identical reasoning).
	$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => $class ) );
	echo '<div ' . $wrapper_attributes . '>';
	echo '<span class="pc-rating-star" aria-hidden="true">♡</span>';
	if ( $count > 0 ) {
		printf(
			'<span class="pc-rating-score"><b>%1$s</b> <span class="pc-rating-of">/ 5</span> · %2$d</span>',
			esc_html( number_format_i18n( $average, 1 ) ),
			$count
		);
	} else {
		echo '<span class="pc-rating-score pc-rating-empty">—</span>';
	}
	echo '</div>';
	return;
}

$criteria = PC_Ratings::criteria();

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'         => $class,
		'data-post-id'  => $post_id,
		'data-nonce'    => wp_create_nonce( PC_Ratings::NONCE_ACTION ),
		'data-ajax-url' => admin_url( 'admin-ajax.php' ),
	)
);

echo '<div ' . $wrapper_attributes . '>';

echo '<div class="pc-rating-overall">';
echo '<span class="pc-rating-star" aria-hidden="true">♡</span>';
if ( $count > 0 ) {
	printf(
		'<span class="pc-rating-score"><b>%1$s</b> / 5 · %2$d %3$s</span>',
		esc_html( number_format_i18n( $average, 1 ) ),
		$count,
		esc_html( PC_Ratings::ru_plural( $count, 'оценка', 'оценки', 'оценок' ) )
	);
} else {
	echo '<span class="pc-rating-score pc-rating-empty">пока нет оценок</span>';
}
echo '</div>';

echo '<div class="pc-rating-criteria">';
foreach ( $criteria as $key => $label ) {
	$current = PC_Ratings::get_criterion( $post_id, $key );

	printf( '<div class="pc-rating-row" data-criterion="%s">', esc_attr( $key ) );
	printf( '<span class="pc-rating-label">%s</span>', esc_html( $label ) );

	printf(
		'<span class="pc-rating-stars" role="group" aria-label="%s">',
		esc_attr( $label . ' — оценка от 1 до 5' )
	);
	for ( $i = 1; $i <= 5; $i++ ) {
		printf(
			'<button type="button" class="pc-rating-star" data-value="%1$d"%2$s aria-label="%3$s: %1$d из 5">♡</button>',
			$i,
			$voted ? ' disabled' : '',
			esc_attr( $label )
		);
	}
	echo '</span>';

	printf(
		'<span class="pc-rating-crit-score">%s</span>',
		$current['count'] > 0 ? esc_html( number_format_i18n( $current['average'], 1 ) ) : '—'
	);
	echo '</div>';
}
echo '</div>';

if ( $voted ) {
	echo '<p class="pc-rating-note pc-rating-thanks">Спасибо, ваша оценка учтена.</p>';
} else {
	echo '<button type="button" class="pc-rating-submit" disabled>Оценить</button>';
	echo '<p class="pc-rating-note">Оценки без текста отзыва — один голос с одного браузера. Можно заполнить не все критерии.</p>';
}

echo '</div>';
