<?php
/**
 * The usage badges the reference catalog leads its listing with. Server-rendered
 * because it reads several meta keys plus a date comparison (the promo badge
 * must disappear on its own when the code expires) — a core/post-meta binding
 * can only echo one raw field.
 *
 * @var array $attributes Block attributes (unused beyond className).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = get_the_ID();
if ( ! $post_id ) {
	return;
}

$badges = array();
foreach ( PC_Meta_Fields::flags() as $meta_key => $label ) {
	if ( '1' === (string) get_post_meta( $post_id, $meta_key, true ) ) {
		$badges[] = array( 'label' => $label, 'class' => 'pc-badge-' . str_replace( array( '_pc_is_', '_pc_has_' ), '', $meta_key ) );
	}
}

if ( PC_Meta_Fields::has_live_promo( $post_id ) ) {
	$badges[] = array( 'label' => 'Промокод', 'class' => 'pc-badge-promo' );
}

if ( empty( $badges ) ) {
	return;
}

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'pc-badges' ) );

echo '<ul ' . $wrapper_attributes . '>';
foreach ( $badges as $badge ) {
	printf(
		'<li class="pc-badge %1$s">%2$s</li>',
		esc_attr( $badge['class'] ),
		esc_html( $badge['label'] )
	);
}
echo '</ul>';
