<?php
/**
 * Plugin Name: Partner Catalog
 * Description: Каталог поставщиков AI-сервисов и подрядчиков (интеграторы, дистрибьюторы, хостинги, облачные платформы и т.д.) с ручным наполнением карточек. Без парсинга/сборщика — самодостаточен, не зависит от ACF/CPT UI.
 * Version: 0.6.1
 * Author: llm-agent.ru
 * Text Domain: partner-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PC_VERSION', '0.6.1' );
define( 'PC_PLUGIN_FILE', __FILE__ );
define( 'PC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once PC_PLUGIN_DIR . 'includes/class-pc-cpt.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-meta-fields.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-settings.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-seo.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-schema.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-stats.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-links.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-ratings.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-related.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-render.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-reports.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-recommend.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-blocks.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-patterns.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-admin.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-filters.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-llm-client.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-import.php';

/**
 * Boot the plugin — every class hooks itself in its own init() method.
 */
function pc_boot() {
	PC_CPT::init();
	PC_Meta_Fields::init();
	PC_Settings::init();
	PC_Schema::init();
	PC_Stats::init();
	PC_Links::init();
	PC_Ratings::init();
	PC_Reports::init();
	PC_Recommend::init();
	PC_Blocks::init();
	PC_Patterns::init();
	PC_Admin::init();
	PC_Filters::init();
	PC_Import::init();
}
add_action( 'plugins_loaded', 'pc_boot' );

/**
 * Activation: register post types/taxonomies + the /out/ rewrite rule (so
 * rewrite rules flush correctly), create the stats table.
 */
function pc_activate() {
	PC_CPT::register_post_types();
	PC_CPT::register_taxonomies();
	PC_CPT::seed_default_terms_once();
	PC_Links::register_rewrite_rule();
	PC_Stats::create_table();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'pc_activate' );

function pc_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'pc_deactivate' );
