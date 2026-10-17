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

	/**
	 * Public URL bases (decided 2026-10-17: the section is called «Сервисы»).
	 * Until 0.5.0 they were /contractors/ and /contractor-type/ — those old
	 * paths 301 to the new ones (legacy_redirect()), so links and search
	 * positions carry over. Types live on their own base, not under
	 * /services/type/: a non-hierarchical post type registers an attachment
	 * rule `services/[^/]+/([^/]+)/?$` that would swallow /services/type/x/.
	 */
	const SLUG_ARCHIVE = 'services';
	const SLUG_TYPE    = 'service-type';
	const SLUG_SPEC    = 'specialization';

	/** Old path prefix => new one. */
	const LEGACY_PREFIXES = array(
		'contractors'     => self::SLUG_ARCHIVE,
		'contractor-type' => self::SLUG_TYPE,
	);

	/** Bump to make maybe_flush_rewrites() flush once after a slug change. */
	const REWRITE_VERSION        = '0.5.0-services';
	const REWRITE_VERSION_OPTION = 'pc_rewrite_version';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_types' ) );
		add_action( 'init', array( __CLASS__, 'register_taxonomies' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrites' ), 99 );
		add_action( 'template_redirect', array( __CLASS__, 'legacy_redirect' ), 1 );
	}

	/**
	 * Updating the plugin doesn't run the activation hook, so new slugs would
	 * 404 until someone re-saved permalinks — flush once, on the first request
	 * after the slugs changed (after every rule, incl. PC_Links', is registered).
	 */
	public static function maybe_flush_rewrites() {
		if ( get_option( self::REWRITE_VERSION_OPTION ) === self::REWRITE_VERSION ) {
			return;
		}
		flush_rewrite_rules( false );
		update_option( self::REWRITE_VERSION_OPTION, self::REWRITE_VERSION, false );
	}

	/** /contractors/… and /contractor-type/… → 301 to /services/… and /service-type/…, query string kept. */
	public static function legacy_redirect() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$path = substr( (string) strtok( $uri, '?' ), strlen( rtrim( $home, '/' ) ) );
		foreach ( self::LEGACY_PREFIXES as $old => $new ) {
			if ( preg_match( '#^/' . preg_quote( $old, '#' ) . '(/.*)?$#', $path, $m ) ) {
				$query = (string) wp_parse_url( $uri, PHP_URL_QUERY );
				$target = home_url( '/' . $new . ( $m[1] ?? '/' ) ) . ( '' !== $query ? '?' . $query : '' );
				wp_safe_redirect( $target, 301 );
				exit;
			}
		}
	}

	public static function register_post_types() {
		register_post_type(
			'contractor',
			array(
				'label'        => 'Сервисы',
				'labels'       => array(
					'name'          => 'Сервисы',
					'singular_name' => 'Сервис',
					'add_new_item'  => 'Добавить сервис',
					'edit_item'     => 'Сервис',
					'search_items'  => 'Поиск сервисов',
					'all_items'     => 'Все сервисы',
				),
				'public'       => true,
				'has_archive'  => true,
				'rewrite'      => array( 'slug' => self::SLUG_ARCHIVE ),
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
				'rewrite'           => array( 'slug' => self::SLUG_TYPE ),
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
				'rewrite'           => array( 'slug' => self::SLUG_SPEC ),
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
