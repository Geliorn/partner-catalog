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
	// control — one heart + the number, no 1-5 "scale" row (see
	// lagr/rating's render.php for the identical reasoning).
	$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => $class ) );
	echo '<div ' . $wrapper_attributes . '>';
	echo '<span class="pc-rating-star" aria-hidden="true">♡</span>';
	if ( $count > 0 ) {
		printf( '<span class="pc-rating-score"><b>%s</b></span>', esc_html( number_format_i18n( $average, 1 ) ) );
	} else {
		echo '<span class="pc-rating-score pc-rating-empty">—</span>';
	}
	echo '</div>';
	return;
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'         => $class,
		'data-post-id'  => $post_id,
		'data-nonce'    => wp_create_nonce( PC_Ratings::NONCE_ACTION ),
		'data-ajax-url' => admin_url( 'admin-ajax.php' ),
	)
);

echo '<div ' . $wrapper_attributes . '>';
echo '<span class="pc-rating-stars" role="group" aria-label="Оценить подрядчика">';
for ( $i = 1; $i <= 5; $i++ ) {
	printf(
		'<button type="button" class="pc-rating-star" data-value="%1$d"%2$s aria-label="Оценка %1$d из 5">♡</button>',
		$i,
		$voted ? ' disabled' : ''
	);
}
echo '</span>';

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
