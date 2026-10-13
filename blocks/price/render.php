<?php
/**
 * Formats `_pc_price_type` + `_pc_price_value` into one display string.
 * Can't be a plain core/post-meta binding — the output depends on a
 * conditional on the type, not just a raw field value.
 *
 * @var array $attributes Block attributes (fontSize).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = get_the_ID();
if ( ! $post_id ) {
	return;
}

$type  = get_post_meta( $post_id, '_pc_price_type', true );
$value = get_post_meta( $post_id, '_pc_price_value', true );

if ( empty( $type ) ) {
	return;
}

switch ( $type ) {
	case 'free':
		$text = 'Бесплатно';
		break;
	case 'subscription':
		// The value may already carry its own unit ("990 ₽/мес" typed by hand
		// for an odd plan), so only a bare number gets the suffix added.
		$text = '' !== $value
			? ( preg_match( '/^[\d\s\xC2\xA0.,–-]+$/', $value ) ? $value . ' ₽/мес' : $value )
			: '';
		break;
	case 'usage':
		$text = '' !== $value ? $value : 'Оплата по потреблению';
		break;
	case 'from':
		$text = '' !== $value ? 'от ' . $value . ' ₽' : '';
		break;
	case 'range':
		$text = '' !== $value ? $value . ' ₽' : '';
		break;
	case 'fixed':
		$text = '' !== $value ? $value . ' ₽ за проект' : '';
		break;
	case 'hourly':
		$text = '' !== $value ? $value . ' ₽/час' : '';
		break;
	case 'on_request':
		$text = 'Цена по запросу';
		break;
	default:
		$text = '';
}

if ( '' === $text ) {
	return;
}

$font_size_class = ! empty( $attributes['fontSize'] ) ? ' has-' . $attributes['fontSize'] . '-font-size has-custom-font-size' : '';
$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'pc-price' . $font_size_class ) );

echo '<p ' . $wrapper_attributes . '>' . esc_html( $text ) . '</p>';
