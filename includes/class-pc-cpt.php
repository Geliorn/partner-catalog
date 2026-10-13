<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin's own post type and taxonomies in code. No dependency
 * on CPT UI / ACF — self-contained, portable to any of the network's sites.
 *
 * contractor_type is deliberately an OPEN, multi-value taxonomy (not a fixed
 * set of 3 hardcoded options) — this is the mechanism that lets the user add
 * new kinds of service providers later (hosting, cloud platforms, etc.)
 * purely by adding a term in the admin, with zero code changes. See
 * docs/partner-catalog-plan.md section 1/3 for the reasoning.
 */
class PC_CPT {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_types' ) );
		add_action( 'init', array( __CLASS__, 'register_taxonomies' ) );
	}

	public static function register_post_types() {
		register_post_type(
			'contractor',
			array(
				'label'        => 'Подрядчики',
				'labels'       => array(
					'name'          => 'Подрядчики',
					'singular_name' => 'Подрядчик',
					'add_new_item'  => 'Добавить подрядчика',
					'edit_item'     => 'Подрядчик',
					'search_items'  => 'Поиск подрядчиков',
					'all_items'     => 'Все подрядчики',
				),
				'public'       => true,
				'has_archive'  => true,
				'rewrite'      => array( 'slug' => 'contractors' ),
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'thumbnail', 'revisions' ),
				'menu_icon'    => 'dashicons-groups',
				'show_in_menu' => 'pc-main',
			)
		);
	}

	public static function register_taxonomies() {
		register_taxonomy(
			'contractor_type',
			array( 'contractor' ),
			array(
				'label'             => 'Тип сервиса',
				'hierarchical'      => false,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'contractor-type' ),
			)
		);

		register_taxonomy(
			'contractor_specialization',
			array( 'contractor' ),
			array(
				'label'             => 'Специализация',
				'hierarchical'      => false,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'specialization' ),
			)
		);

		// contractor_region was registered here until 0.4.0 and is deliberately
		// gone: the catalog turned out to be about AI services/platforms, where
		// "region of presence" is meaningless (an API aggregator has none), and
		// in practice every region facet held 1-2 records — the same long-tail
		// noise the tag cleanup is about. Removed on the user's call 2026-10-13,
		// revising section 3 of docs/partner-catalog-plan.md (which argued for it
		// from workspace.ru — but that is a catalog of *agencies*, not services).
		// Any terms already assigned stay in the DB, simply unregistered; delete
		// them by hand if you want them gone.
	}

	const TERMS_SEEDED_OPTION = 'pc_terms_seeded';

	/**
	 * Seeds a handful of starter terms per taxonomy once on activation (plan
	 * section 3, item 4) — purely an example set to show the taxonomies are
	 * open/extensible, not a fixed list. Pure data, fully editable/deletable
	 * afterwards — never re-applied by code.
	 */
	public static function seed_default_terms_once() {
		if ( get_option( self::TERMS_SEEDED_OPTION ) ) {
			return;
		}

		$terms = array(
			'contractor_type'           => array( 'Интегратор', 'Дистрибьютор', 'Хостинг-провайдер', 'Облачная платформа', 'Разработчик ПО', 'Учебный центр', 'Консалтинг', 'Сопутствующий сервис' ),
			'contractor_specialization' => array( '1С', 'Bitrix24', 'Microsoft 365', 'AWS', 'Yandex Cloud', 'MCP/AI-агенты', 'VMware' ),
		);

		foreach ( $terms as $taxonomy => $names ) {
			foreach ( $names as $name ) {
				if ( ! term_exists( $name, $taxonomy ) ) {
					wp_insert_term( $name, $taxonomy );
				}
			}
		}

		update_option( self::TERMS_SEEDED_OPTION, time(), false );
	}
}
