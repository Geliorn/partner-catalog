<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers post meta (register_post_meta -> show_in_rest, needed for Block
 * Bindings in patterns) and renders the plain-PHP meta box for `contractor`.
 * No ACF dependency, no repeater UI — contacts are a small fixed set of
 * common fields (phone/email/Telegram/VK) rather than a generic repeater,
 * to stay consistent with the rest of the stack (llm-agent-github-repos has
 * no repeater fields either) and keep manual entry simple.
 */
class PC_Meta_Fields {

	/** Price type — see docs/partner-catalog-plan.md section 4 ("Стоимость услуг — универсальное поле"). */
	public static function price_types() {
		return array(
			'from'       => 'От',
			'range'      => 'Диапазон',
			'fixed'      => 'Фиксированная сумма (за проект)',
			'hourly'     => 'Почасовая ставка',
			'on_request' => 'По запросу',
		);
	}

	/** Meta keys shown/edited on a contractor post. */
	public static function meta_keys() {
		return array(
			'_pc_short_description' => 'string',
			'_pc_website'           => 'string',
			'_pc_phone'             => 'string',
			'_pc_email'             => 'string',
			'_pc_telegram'          => 'string',
			'_pc_vk'                => 'string',
			'_pc_price_type'        => 'string',
			'_pc_price_value'       => 'string',
			'_pc_features'          => 'string',
			'_pc_partnership'       => 'string',
			'_pc_founded_year'      => 'integer',
			'_pc_team_size'         => 'string',
			'_pc_clients'           => 'string',
			'_pc_utm_override'      => 'string',
		);
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_contractor', array( __CLASS__, 'save_meta' ) );
		add_filter( 'is_protected_meta', array( __CLASS__, 'unprotect_for_bindings' ), 10, 2 );
	}

	/**
	 * All our meta keys are underscore-prefixed (stays out of the default
	 * Custom Fields metabox — we render our own). Block Bindings' core/post-meta
	 * source refuses "protected" meta (is_protected_meta() — true for any
	 * leading underscore) regardless of show_in_rest, so every binding used in
	 * class-pc-patterns.php / the blocks in class-pc-blocks.php would silently
	 * fall back to its placeholder without this filter — same fix already
	 * applied in llm-agent-github-repos.
	 */
	public static function unprotect_for_bindings( $protected, $meta_key ) {
		if ( array_key_exists( $meta_key, self::meta_keys() ) ) {
			return false;
		}
		return $protected;
	}

	public static function register_meta() {
		foreach ( self::meta_keys() as $key => $type ) {
			register_post_meta(
				'contractor',
				$key,
				array(
					'show_in_rest' => true,
					'single'       => true,
					'type'         => $type,
				)
			);
		}
	}

	public static function add_meta_boxes() {
		add_meta_box( 'pc_contractor_data', 'Данные подрядчика', array( __CLASS__, 'render_box' ), 'contractor', 'normal', 'high' );
	}

	private static function field( $post_id, $key, $label, $type = 'text', $extra = array() ) {
		$value = get_post_meta( $post_id, $key, true );
		echo '<p style="margin:10px 0"><label style="display:block;font-weight:600;margin-bottom:3px">' . esc_html( $label ) . '</label>';

		if ( 'select' === $type ) {
			echo '<select name="' . esc_attr( $key ) . '">';
			foreach ( $extra['choices'] as $choice_value => $choice_label ) {
				printf(
					'<option value="%1$s" %2$s>%3$s</option>',
					esc_attr( $choice_value ),
					selected( $value, $choice_value, false ),
					esc_html( $choice_label )
				);
			}
			echo '</select>';
		} elseif ( 'textarea' === $type ) {
			printf(
				'<textarea name="%1$s" rows="%2$d" style="width:100%%">%3$s</textarea>',
				esc_attr( $key ),
				intval( $extra['rows'] ?? 3 ),
				esc_textarea( $value )
			);
		} else {
			printf(
				'<input type="%1$s" name="%2$s" value="%3$s" style="width:100%%">',
				esc_attr( $type ),
				esc_attr( $key ),
				esc_attr( $value )
			);
		}
		if ( ! empty( $extra['description'] ) ) {
			echo '<span class="description" style="display:block;margin-top:3px">' . wp_kses_post( $extra['description'] ) . '</span>';
		}
		echo '</p>';
	}

	public static function render_box( $post ) {
		wp_nonce_field( 'pc_save_contractor', 'pc_contractor_nonce' );

		echo '<h3 style="margin-top:0">Основное</h3>';
		self::field( $post->ID, '_pc_short_description', 'Короткое описание (1–2 предложения, для карточки в архиве)', 'textarea', array( 'rows' => 2 ) );

		if ( class_exists( 'PC_LLM_Client' ) ) {
			$gen_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=pc_generate_description&post_id=' . $post->ID ),
				'pc_generate_desc_' . $post->ID
			);
			$ready = PC_LLM_Client::ready();
			echo '<p style="margin:4px 0 14px">';
			echo '<a class="button" href="' . esc_url( $gen_url ) . '"' . ( $ready ? '' : ' onclick="alert(\'Укажите API-ключ AI-провайдера в настройках Partner Catalog.\');return false;"' ) . '>✨ Сгенерировать описание (AI)</a>';
			echo ' <span class="description">по сохранённым данным (тип, специализация, особенности…); перезапишет короткое и полное описание. Сначала сохраните запись.</span>';
			echo '</p>';
		}

		self::field( $post->ID, '_pc_website', 'Сайт', 'url' );

		echo '<h3>Контакты</h3>';
		echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px">';
		self::field( $post->ID, '_pc_phone', 'Телефон', 'text' );
		self::field( $post->ID, '_pc_email', 'Email', 'email' );
		self::field( $post->ID, '_pc_telegram', 'Telegram (ссылка)', 'url' );
		self::field( $post->ID, '_pc_vk', 'VK (ссылка)', 'url' );
		echo '</div>';

		echo '<h3>Стоимость услуг</h3>';
		echo '<div style="display:flex;gap:12px;align-items:flex-start">';
		self::field( $post->ID, '_pc_price_type', 'Тип цены', 'select', array( 'choices' => self::price_types() ) );
		self::field( $post->ID, '_pc_price_value', 'Значение (без ₽ и без единиц — они добавляются автоматически по типу, кроме почасовой/подписки, где единицу можно дописать самому)', 'text' );
		echo '</div>';
		echo '<p class="description">Примеры по типу: «От» → 150000; «Диапазон» → 150000–500000; «Фиксированная сумма» → 800000; «Почасовая ставка» → 3000; «По запросу» — значение можно оставить пустым. Для тарифов вида «990 ₽/мес» единицу измерения допишите прямо в значении.</p>';

		echo '<h3>Дополнительно</h3>';
		echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px">';
		self::field( $post->ID, '_pc_founded_year', 'Год основания', 'number' );
		self::field( $post->ID, '_pc_team_size', 'Размер команды (например «10–50 человек»)', 'text' );
		echo '</div>';
		self::field( $post->ID, '_pc_partnership', 'Партнёрства и сертификации (каждая — с новой строки)', 'textarea', array( 'rows' => 2, 'description' => 'Например: «Официальный партнёр 1С», «Microsoft Gold Partner».' ) );
		self::field( $post->ID, '_pc_features', 'Особенности сервиса (тезисно, каждая мысль — с новой строки; на карточке/странице выводится немаркированным списком)', 'textarea', array( 'rows' => 5, 'description' => 'Например: «Работаем 24/7», «Бесплатный аудит перед стартом», «Фиксированная цена без доплат».' ) );
		self::field( $post->ID, '_pc_clients', 'Ключевые клиенты (каждый — с новой строки)', 'textarea', array( 'rows' => 3 ) );

		echo '<h3>UTM-метки</h3>';
		self::field(
			$post->ID,
			'_pc_utm_override',
			'Ручные UTM-параметры для этой записи (необязательно)',
			'text',
			array( 'description' => 'Если заполнено — полностью заменяет автосгенерированные метки для этого подрядчика. Формат — строка query-параметров, например: <code>utm_source=vk&amp;utm_medium=cpc&amp;utm_campaign=osen2026</code>. Если оставить пустым — метки подставляются автоматически по шаблону из настроек плагина.' )
		);
	}

	public static function save_meta( $post_id ) {
		if ( ! isset( $_POST['pc_contractor_nonce'] ) || ! wp_verify_nonce( $_POST['pc_contractor_nonce'], 'pc_save_contractor' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$textareas   = array( '_pc_short_description', '_pc_partnership', '_pc_features', '_pc_clients' );
		$urls        = array( '_pc_website', '_pc_telegram', '_pc_vk' );
		$price_types = array_keys( self::price_types() );

		foreach ( self::meta_keys() as $key => $type ) {
			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}
			$raw = wp_unslash( $_POST[ $key ] );

			if ( in_array( $key, $textareas, true ) ) {
				update_post_meta( $post_id, $key, sanitize_textarea_field( $raw ) );
			} elseif ( in_array( $key, $urls, true ) ) {
				update_post_meta( $post_id, $key, esc_url_raw( $raw ) );
			} elseif ( '_pc_email' === $key ) {
				update_post_meta( $post_id, $key, sanitize_email( $raw ) );
			} elseif ( '_pc_price_type' === $key ) {
				update_post_meta( $post_id, $key, in_array( $raw, $price_types, true ) ? $raw : '' );
			} elseif ( 'integer' === $type ) {
				update_post_meta( $post_id, $key, absint( $raw ) );
			} else {
				update_post_meta( $post_id, $key, sanitize_text_field( $raw ) );
			}
		}

		if ( class_exists( 'PC_SEO' ) ) {
			PC_SEO::apply_meta_description( $post_id, get_post_meta( $post_id, '_pc_short_description', true ) );
		}
	}
}
