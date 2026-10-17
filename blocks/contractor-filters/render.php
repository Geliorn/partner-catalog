<?php
/**
 * Archive sidebar filters (v2 mockup A): «Доступ и оплата», «Условия»,
 * «Платформы», then type and specialization. Plain GET form — works without
 * JS (the «Применить» button), view.js just submits on change. On phones the
 * same form becomes a drawer opened from the toolbar's «Фильтры» button.
 *
 * Sorting moved to the toolbar above the list (partner-catalog/archive-toolbar);
 * the current sort is kept here as a hidden field so filtering doesn't reset it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! PC_Settings::integration_enabled( 'filters' ) ) {
	return;
}

/** How many options a group shows before the rest fold under «Ещё N». */
$visible_limit = 6;

$facets = array(
	'contractor_type'           => 'Тип сервиса',
	'contractor_specialization' => 'Специализация',
);

// Skip the facet matching the taxonomy we're already on — the URL itself
// is that filter, showing it again in the sidebar would just duplicate it.
$current_taxonomy = is_tax() ? get_queried_object()->taxonomy ?? '' : '';

$base_url           = PC_Filters::base_url();
$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'pc-filters', 'id' => 'pc-filters' ) );

$print_options = function ( array $options ) use ( $visible_limit ) {
	$i = 0;
	$more = count( $options ) - $visible_limit;
	foreach ( $options as $opt ) {
		if ( $i === $visible_limit && $more > 0 ) {
			echo '<details class="pc-f-more"><summary>Ещё ' . (int) $more . '</summary>';
		}
		printf(
			'<label class="pc-f-opt" for="%1$s"><input type="checkbox" id="%1$s" name="%2$s[]" value="%3$s"%4$s> <span>%5$s</span> <span class="pc-f-n">%6$d</span></label>',
			esc_attr( $opt['id'] ),
			esc_attr( $opt['name'] ),
			esc_attr( $opt['value'] ),
			$opt['checked'] ? ' checked' : '',
			esc_html( $opt['label'] ),
			(int) $opt['count']
		);
		++$i;
	}
	if ( $more > 0 ) {
		echo '</details>';
	}
};

echo '<form ' . $wrapper_attributes . ' method="get" action="' . esc_url( $base_url ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput -- core-escaped wrapper attributes.
echo '<div class="pc-filters-top"><b>Фильтры</b><button type="submit" class="pc-btn pc-btn-primary pc-btn-sm pc-filters-close">Готово</button></div>';

if ( 'default' !== PC_Filters::current_sort() ) {
	echo '<input type="hidden" name="' . esc_attr( PC_Filters::SORT_PARAM ) . '" value="' . esc_attr( PC_Filters::current_sort() ) . '">';
}

// Usage/access/platform flags — meta-based, so unlike the taxonomy facets a
// zero-count option is still shown while selected (unticking must stay possible).
$flag_counts    = PC_Filters::flag_counts();
$selected_flags = PC_Filters::selected_flags();
foreach ( PC_Filters::flag_groups() as $group_key => $group ) {
	$options = array();
	foreach ( $group['flags'] as $flag => $label ) {
		$checked = in_array( $flag, $selected_flags, true );
		$count   = (int) ( $flag_counts[ $flag ] ?? 0 );
		if ( ! $count && ! $checked ) {
			continue;
		}
		$options[] = array(
			'id'      => 'pc-f-flag-' . $flag,
			'name'    => PC_Filters::FLAG_PARAM,
			'value'   => $flag,
			'checked' => $checked,
			'label'   => $label,
			'count'   => $count,
		);
	}
	if ( ! $options ) {
		continue;
	}
	echo '<fieldset class="pc-f-group pc-f-group-' . esc_attr( $group_key ) . '"><legend>' . esc_html( $group['label'] ) . '</legend>';
	$print_options( $options );
	echo '</fieldset>';
}

foreach ( $facets as $taxonomy => $label ) {
	if ( $taxonomy === $current_taxonomy ) {
		continue;
	}
	$param = PC_Filters::TAXONOMY_PARAMS[ $taxonomy ];
	$terms = PC_Filters::facet_counts( $taxonomy );
	if ( empty( $terms ) ) {
		continue;
	}
	$selected = PC_Filters::selected( $param );
	$options  = array();
	foreach ( $terms as $term ) {
		$options[] = array(
			'id'      => 'pc-f-' . $param . '-' . $term->term_id,
			'name'    => $param,
			'value'   => $term->slug,
			'checked' => in_array( $term->slug, $selected, true ),
			'label'   => $term->name,
			'count'   => $term->cnt,
		);
	}
	echo '<fieldset class="pc-f-group"><legend>' . esc_html( $label ) . '</legend>';
	$print_options( $options );
	echo '</fieldset>';
}

echo '<div class="pc-f-actions"><button type="submit" class="pc-btn pc-btn-primary pc-btn-sm pc-f-apply">Применить</button>';
if ( PC_Filters::is_active() ) {
	echo '<a class="pc-f-reset" href="' . esc_url( $base_url ) . '">Сбросить</a>';
}
echo '</div>';

echo '</form>';
