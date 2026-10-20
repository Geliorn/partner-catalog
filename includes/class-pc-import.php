<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CSV import for contractors. Adds an "Импорт CSV" submenu, a sample-file
 * download, and the upload handler. Imported contractors are created published
 * (per the chosen workflow); descriptions can be AI-generated afterwards.
 *
 * CSV: UTF-8, first row = header. Delimiter auto-detected (, ; or tab). A cell
 * holding several values (type, specialization, features, platforms,
 * tariffs, promos, faq) separates them with a vertical bar «|». Columns of
 * older sample files (clients, promo_code…) are still accepted.
 */
class PC_Import {

	const SAMPLE_SLUG = 'pc-import';

	/** header column => meta key (plain scalar fields). */
	private static function meta_columns() {
		return array(
			'short_description' => '_pc_short_description',
			'website'           => '_pc_website',
			'checked'           => '_pc_checked',
			'price_type'        => '_pc_price_type',
			'price_value'       => '_pc_price_value',
			'vpn'               => '_pc_vpn',
			'priority'          => '_pc_priority',
		);
	}

	/** header column => flag meta key. Truthy cell = flag on. */
	private static function flag_columns() {
		$out = array();
		foreach ( array_keys( PC_Meta_Fields::flags() ) as $meta_key ) {
			$out[ ltrim( str_replace( '_pc_', '', $meta_key ), '_' ) ] = $meta_key;
		}
		$out['data_ru'] = '_pc_data_ru';
		return $out;
	}

	/** «|»-list columns whose values switch checkboxes on: column => [value => meta key]. */
	private static function set_columns() {
		return array(
			'platforms' => array(
				'web'      => '_pc_plat_web',
				'telegram' => '_pc_plat_telegram',
				'api'      => '_pc_plat_api',
				'app'      => '_pc_plat_app',
				'selfhost' => '_pc_plat_selfhost',
			),
		);
	}

	/** Cell values that mean "yes" for a flag column. */
	private static function is_truthy( $raw ) {
		return in_array( strtolower( trim( (string) $raw ) ), array( '1', 'yes', 'y', 'true', 'да', '+' ), true );
	}

	/** header column => meta key (multi-line «|»-separated → newline). */
	private static function multiline_columns() {
		return array(
			'features' => '_pc_features',
		);
	}

	/** header column => taxonomy (multi-value «|»-separated term names). */
	private static function taxonomy_columns() {
		return array(
			'type'           => 'contractor_type',
			'specialization' => 'contractor_specialization',
		);
	}

	/** Full ordered header for the sample file. */
	private static function header() {
		return array_merge(
			array( 'title' ),
			array_keys( self::meta_columns() ),
			array_keys( self::flag_columns() ),
			array_keys( self::set_columns() ),
			array_keys( self::multiline_columns() ),
			array( 'tariffs', 'promos', 'faq' ),
			array_keys( self::taxonomy_columns() ),
			array( 'logo' )
		);
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 20 );
		add_action( 'admin_post_pc_import_csv', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_pc_sample_csv', array( __CLASS__, 'handle_sample' ) );
	}

	public static function register_menu() {
		add_submenu_page(
			PC_Admin::MAIN_SLUG,
			'Импорт сервисов (CSV)',
			'Импорт CSV',
			'manage_options',
			self::SAMPLE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Недостаточно прав.' );
		}
		$sample_url = wp_nonce_url( admin_url( 'admin-post.php?action=pc_sample_csv' ), 'pc_sample_csv' );
		?>
		<div class="wrap">
			<h1>Импорт сервисов из CSV</h1>

			<?php if ( isset( $_GET['imported'] ) ) : ?>
				<div class="notice notice-success"><p>Создано записей: <?php echo (int) $_GET['imported']; ?>, обновлено существующих: <?php echo (int) ( $_GET['updated'] ?? 0 ); ?><?php echo isset( $_GET['skipped'] ) && (int) $_GET['skipped'] ? ', пропущено строк (нет названия или сайта): ' . (int) $_GET['skipped'] : ''; ?>.</p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['import_error'] ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( wp_unslash( $_GET['import_error'] ) ); ?></p></div>
			<?php endif; ?>

			<p>Файл: <strong>UTF-8 CSV</strong>, первая строка — заголовки. Разделитель столбцов определяется автоматически (<code>,</code>, <code>;</code> или табуляция). Если в ячейке несколько значений (тип, специализация, особенности, платформы, тарифы, промокоды, FAQ) — разделяйте их вертикальной чертой «<code>|</code>».</p>
			<p><a class="button" href="<?php echo esc_url( $sample_url ); ?>">Скачать образец CSV</a></p>

			<h2>Столбцы</h2>
			<p class="description" style="max-width:820px">
				<code>title</code> и <code>website</code> (оба обязательны; по ним ищется уже существующий сервис — совпало название <em>или</em> сайт → запись обновляется, а не создаётся дубль; пустые ячейки при обновлении не стирают сохранённые значения); <code>short_description</code> — короткое описание (шапка страницы и карточка);
				<code>checked</code> — дата проверки условий редакцией (<code>ГГГГ-ММ-ДД</code>);
				<code>price_type</code> (<?php echo esc_html( implode( ' | ', array_keys( PC_Meta_Fields::price_types() ) ) ); ?>); <code>price_value</code>;
				<code>vpn</code> — <code>no</code> (работает без VPN) или <code>yes</code> (нужен VPN);
				<code>platforms</code> — <code>web|telegram|api|app|selfhost</code>;
				<code><?php echo esc_html( implode( '</code>, <code>', array_keys( self::flag_columns() ) ) ); ?></code> — условия, «1» / «да» = включено;
				<code>features</code> (несколько пунктов через «|»);
				<code>tariffs</code> — «название <code>::</code> цена <code>::</code> что входит», тарифы через «|»;
				<code>promos</code> — «код <code>::</code> что даёт <code>::</code> для кого <code>::</code> до <code>::</code> проверен» (даты <code>ДД.ММ.ГГГГ</code>, последние поля можно опустить), промокоды через «|»;
				<code>priority</code> — приоритет в блоке «Где попробовать» (0–100);
				<code>faq</code> — пары «вопрос <code>::</code> ответ», сами пары через «|»;
				<code>type</code> — тип сервиса (таксономия <code>contractor_type</code>), <code>specialization</code> — специализация (<code>contractor_specialization</code>); термины через «|», создаются при необходимости;
				<code>logo</code> — ссылка на картинку логотипа (PNG, JPG, WebP или GIF; ICO не принимается): файл скачивается в медиатеку и становится изображением записи.
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( 'pc_import_csv' ); ?>
				<input type="hidden" name="action" value="pc_import_csv">
				<table class="form-table">
					<tr><th>Файл CSV</th><td><input type="file" name="csv" accept=".csv,text/csv" required></td></tr>
				</table>
				<?php submit_button( 'Импортировать' ); ?>
			</form>
		</div>
		<?php
	}

	public static function handle_sample() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'pc_sample_csv' ) ) {
			wp_die( 'Недостаточно прав.' );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="contractors-sample.csv"' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM so Excel opens it correctly.
		fputcsv( $out, self::header() );
		// Order = header(): title, meta_columns, flag_columns, set_columns, features, tariffs, promos, faq, type, specialization.
		fputcsv( $out, array(
			'ООО «Пример Интеграции»',
			'Внедряем 1С и Bitrix24 для среднего бизнеса.', 'https://example.com', '2026-10-13',
			'from', '150000', 'no', '20',
			'', '', 'да', 'да',
			'',
			'Работаем 24/7|Фиксированная цена|Бесплатный аудит',
			'',
			'',
			'Нужен ли VPN? :: Нет, сервис работает с российских IP.|Как оплатить? :: Картой РФ или по счёту для юрлиц.',
			'Интегратор|Консалтинг',
			'1С|Bitrix24',
		) );
		fputcsv( $out, array(
			'Cloud Hosting Pro', 'Облачный хостинг и администрирование.', 'https://cloudpro.example', '2026-10-13',
			'subscription', '990', 'no', '60',
			'', 'да', 'да', 'да',
			'web|api|selfhost',
			'SLA 99.9%|Поддержка на русском',
			'Старт :: 290 ₽/мес :: 2 vCPU, 4 ГБ|Про :: 990 ₽/мес :: 8 vCPU, 16 ГБ',
			'CLOUD10 :: −10% на первый месяц :: новых клиентов :: 31.12.2026 :: 13.10.2026',
			'',
			'Хостинг-провайдер', 'AWS|Yandex Cloud',
		) );
		fclose( $out );
		exit;
	}

	public static function handle_import() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'pc_import_csv' ) ) {
			wp_die( 'Недостаточно прав.' );
		}

		$redirect = admin_url( 'admin.php?page=' . self::SAMPLE_SLUG );

		if ( empty( $_FILES['csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv']['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( 'import_error', rawurlencode( 'Файл не загружен.' ), $redirect ) );
			exit;
		}

		$rows = self::read_csv( $_FILES['csv']['tmp_name'] );
		if ( null === $rows ) {
			wp_safe_redirect( add_query_arg( 'import_error', rawurlencode( 'Не удалось прочитать CSV или нет столбца «title».' ), $redirect ) );
			exit;
		}

		$index    = self::existing_index();
		$imported = 0;
		$updated  = 0;
		$skipped  = 0;
		foreach ( $rows as $row ) {
			$title   = trim( (string) ( $row['title'] ?? '' ) );
			$website = trim( (string) ( $row['website'] ?? '' ) );
			if ( '' === $title || '' === $website ) {
				++$skipped;
				continue;
			}
			$url_key   = self::url_key( $website );
			$title_key = self::title_key( $title );
			$existing  = $index['url'][ $url_key ] ?? ( $index['title'][ $title_key ] ?? 0 );

			$post_id = self::import_row( $row, $existing );
			if ( ! $post_id ) {
				++$skipped;
				continue;
			}
			$existing ? ++$updated : ++$imported;
			// Later rows of the same file match this record too.
			$index['url'][ $url_key ]     = $post_id;
			$index['title'][ $title_key ] = $post_id;
		}

		wp_safe_redirect( add_query_arg( array( 'imported' => $imported, 'updated' => $updated, 'skipped' => $skipped ), $redirect ) );
		exit;
	}

	/** Parse the uploaded file into an array of associative rows, or null. */
	private static function read_csv( $path ) {
		$raw = file_get_contents( $path );
		if ( false === $raw ) {
			return null;
		}
		$raw = preg_replace( '/^\xEF\xBB\xBF/', '', $raw ); // strip BOM

		$first_line = strtok( $raw, "\r\n" );
		$delims     = array( ',' => substr_count( $first_line, ',' ), ';' => substr_count( $first_line, ';' ), "\t" => substr_count( $first_line, "\t" ) );
		arsort( $delims );
		$delimiter = key( $delims );

		$fh = fopen( 'php://temp', 'r+' );
		fwrite( $fh, $raw );
		rewind( $fh );

		$header = fgetcsv( $fh, 0, $delimiter );
		if ( ! $header ) {
			fclose( $fh );
			return null;
		}
		$header = array_map( function ( $h ) {
			return strtolower( trim( (string) $h ) );
		}, $header );
		if ( ! in_array( 'title', $header, true ) ) {
			fclose( $fh );
			return null;
		}

		$rows = array();
		while ( ( $data = fgetcsv( $fh, 0, $delimiter ) ) !== false ) {
			if ( array( null ) === $data ) {
				continue; // blank line
			}
			$row = array();
			foreach ( $header as $i => $col ) {
				$row[ $col ] = $data[ $i ] ?? '';
			}
			$rows[] = $row;
		}
		fclose( $fh );
		return $rows;
	}

	/**
	 * Duplicate key of a website: host without www + path without trailing
	 * slash, lower-cased; scheme, query and fragment ignored.
	 */
	public static function url_key( $url ) {
		$url   = trim( (string) $url );
		$parts = wp_parse_url( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $url ) ? $url : 'https://' . $url );
		if ( empty( $parts['host'] ) ) {
			return '';
		}
		$host = preg_replace( '/^www\./', '', strtolower( $parts['host'] ) );
		return $host . rtrim( strtolower( $parts['path'] ?? '' ), '/' );
	}

	/** Duplicate key of a title: lower-cased, whitespace collapsed. */
	public static function title_key( $title ) {
		return mb_strtolower( trim( preg_replace( '/\s+/u', ' ', (string) $title ) ) );
	}

	/** Existing contractors (not trashed) by url_key() and title_key(); the oldest wins. */
	public static function existing_index() {
		$index = array( 'url' => array(), 'title' => array() );
		$ids   = get_posts( array(
			'post_type'      => 'contractor',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		) );
		foreach ( $ids as $id ) {
			$u = self::url_key( (string) get_post_meta( $id, '_pc_website', true ) );
			$t = self::title_key( get_the_title( $id ) );
			if ( '' !== $u && ! isset( $index['url'][ $u ] ) ) {
				$index['url'][ $u ] = $id;
			}
			if ( '' !== $t && ! isset( $index['title'][ $t ] ) ) {
				$index['title'][ $t ] = $id;
			}
		}
		return $index;
	}

	/**
	 * Create a published contractor from a mapped row, or update $post_id when
	 * the row matches an existing one. On update an empty cell keeps the stored
	 * value. Returns the post ID, or 0 on failure.
	 */
	private static function import_row( array $row, $post_id = 0 ) {
		if ( $post_id ) {
			$res = wp_update_post( array( 'ID' => $post_id, 'post_title' => sanitize_text_field( $row['title'] ) ), true );
		} else {
			$res = wp_insert_post(
				array(
					'post_type'   => 'contractor',
					'post_status' => 'publish',
					'post_title'  => sanitize_text_field( $row['title'] ),
				),
				true
			);
		}
		if ( is_wp_error( $res ) || ! $res ) {
			return 0;
		}
		$post_id = (int) $res;

		$price_types = array_keys( PC_Meta_Fields::price_types() );
		foreach ( self::meta_columns() as $col => $key ) {
			if ( ! isset( $row[ $col ] ) || '' === trim( (string) $row[ $col ] ) ) {
				continue;
			}
			$val = trim( (string) $row[ $col ] );
			if ( '_pc_website' === $key ) {
				$val = esc_url_raw( $val );
			} elseif ( '_pc_price_type' === $key ) {
				$val = in_array( $val, $price_types, true ) ? $val : '';
			} elseif ( '_pc_checked' === $key ) {
				$val = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $val ) ? $val : '';
			} elseif ( '_pc_vpn' === $key ) {
				$val = in_array( strtolower( $val ), array( 'no', 'yes' ), true ) ? strtolower( $val ) : '';
			} elseif ( '_pc_priority' === $key ) {
				$val = min( 100, absint( $val ) );
			} else {
				$val = sanitize_text_field( $val );
			}
			update_post_meta( $post_id, $key, $val );
		}

		foreach ( self::flag_columns() as $col => $key ) {
			if ( isset( $row[ $col ] ) && self::is_truthy( $row[ $col ] ) ) {
				update_post_meta( $post_id, $key, '1' );
			}
		}

		foreach ( self::set_columns() as $col => $map ) {
			if ( empty( $row[ $col ] ) ) {
				continue;
			}
			foreach ( array_map( 'trim', explode( '|', strtolower( (string) $row[ $col ] ) ) ) as $value ) {
				if ( isset( $map[ $value ] ) ) {
					update_post_meta( $post_id, $map[ $value ], '1' );
				}
			}
		}

		// «название :: цена :: что входит | …» → one tariff per line, « | »-separated (PC_Meta_Fields::tariff_items()).
		if ( ! empty( $row['tariffs'] ) ) {
			$lines = array();
			foreach ( explode( '|', (string) $row['tariffs'] ) as $tariff ) {
				$parts = array_map( 'trim', explode( '::', $tariff ) );
				if ( '' !== $parts[0] ) {
					$lines[] = implode( ' | ', $parts );
				}
			}
			update_post_meta( $post_id, '_pc_tariffs', sanitize_textarea_field( implode( "\n", $lines ) ) );
		}

		// Promo codes: the new «promos» column, or the four promo_* columns of
		// older sample files as a single code — both end up in _pc_promos.
		$promo_blocks = array();
		if ( ! empty( $row['promos'] ) ) {
			foreach ( explode( '|', (string) $row['promos'] ) as $promo ) {
				$p = array_map( 'trim', explode( '::', $promo ) );
				if ( count( $p ) < 2 || '' === $p[0] || '' === $p[1] ) {
					continue;
				}
				$lines = array( $p[0], $p[1] );
				if ( ! empty( $p[2] ) ) {
					$lines[] = 'Для: ' . $p[2];
				}
				if ( ! empty( $p[3] ) ) {
					$lines[] = 'До: ' . $p[3];
				}
				if ( ! empty( $p[4] ) ) {
					$lines[] = 'Проверен: ' . $p[4];
				}
				$promo_blocks[] = implode( "\n", $lines );
			}
		} elseif ( ! empty( $row['promo_code'] ) ) {
			$lines = array( trim( (string) $row['promo_code'] ), trim( (string) ( $row['promo_description'] ?? '' ) ) ?: 'Промокод' );
			if ( ! empty( $row['promo_expires'] ) ) {
				$lines[] = 'До: ' . trim( (string) $row['promo_expires'] );
			}
			if ( ! empty( $row['promo_checked'] ) ) {
				$lines[] = 'Проверен: ' . trim( (string) $row['promo_checked'] );
			}
			$promo_blocks[] = implode( "\n", $lines );
		}
		if ( $promo_blocks ) {
			update_post_meta( $post_id, '_pc_promos', sanitize_textarea_field( implode( "\n\n", $promo_blocks ) ) );
		}

		// «вопрос :: ответ | вопрос :: ответ» → the blank-line-separated format
		// PC_Meta_Fields::faq_items() parses (one notepad field, no repeater).
		if ( ! empty( $row['faq'] ) ) {
			$pairs = array();
			foreach ( explode( '|', (string) $row['faq'] ) as $pair ) {
				$parts = array_map( 'trim', explode( '::', $pair, 2 ) );
				if ( 2 === count( $parts ) && '' !== $parts[0] && '' !== $parts[1] ) {
					$pairs[] = $parts[0] . "\n" . $parts[1];
				}
			}
			if ( $pairs ) {
				update_post_meta( $post_id, '_pc_faq', sanitize_textarea_field( implode( "\n\n", $pairs ) ) );
			}
		}

		foreach ( self::multiline_columns() as $col => $key ) {
			if ( empty( $row[ $col ] ) ) {
				continue;
			}
			$lines = array_filter( array_map( 'trim', explode( '|', (string) $row[ $col ] ) ) );
			update_post_meta( $post_id, $key, sanitize_textarea_field( implode( "\n", $lines ) ) );
		}

		foreach ( self::taxonomy_columns() as $col => $taxonomy ) {
			if ( empty( $row[ $col ] ) ) {
				continue;
			}
			$names = array_filter( array_map( 'trim', explode( '|', (string) $row[ $col ] ) ) );
			if ( $names ) {
				wp_set_object_terms( $post_id, $names, $taxonomy ); // creates missing terms by name
			}
		}

		if ( ! empty( $row['logo'] ) && ! has_post_thumbnail( $post_id ) ) {
			self::set_logo( $post_id, trim( (string) $row['logo'] ), (string) $row['title'] );
		}

		PC_Meta_Fields::refresh_derived( $post_id );
		PC_Ratings::ensure_sort_meta( $post_id );

		if ( class_exists( 'PC_SEO' ) ) {
			PC_SEO::apply_meta_description( $post_id, (string) get_post_meta( $post_id, '_pc_short_description', true ) );
		}
		return $post_id;
	}

	/**
	 * Downloads the image at $url into the media library and makes it the
	 * contractor's featured image (= the logo PC_Render::logo() shows). The file
	 * name is built from the detected image type, so extension-less URLs
	 * (favicon services, CDN links) work too. Failures are silent: the card
	 * falls back to the letter logo.
	 */
	private static function set_logo( $post_id, $url, $title ) {
		$url = esc_url_raw( $url );
		if ( '' === $url ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url, 20 );
		if ( is_wp_error( $tmp ) ) {
			return;
		}
		$exts = array(
			'image/png'  => 'png',
			'image/jpeg' => 'jpg',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
		);
		$mime = wp_get_image_mime( $tmp ); // .ico is rejected: the media library refuses it
		if ( ! isset( $exts[ $mime ] ) ) {
			wp_delete_file( $tmp );
			return;
		}
		$file = array(
			'name'     => sanitize_title( $title ) . '-logo.' . $exts[ $mime ],
			'tmp_name' => $tmp,
		);
		$att_id = media_handle_sideload( $file, $post_id, $title );
		if ( is_wp_error( $att_id ) ) {
			wp_delete_file( $tmp );
			return;
		}
		set_post_thumbnail( $post_id, $att_id );
	}
}
