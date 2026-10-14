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
 * holding several values (type, specialization, features, clients, faq) separates them with a vertical bar «|».
 */
class PC_Import {

	const SAMPLE_SLUG = 'pc-import';

	/** header column => meta key (plain scalar fields). */
	private static function meta_columns() {
		return array(
			'short_description' => '_pc_short_description',
			'website'           => '_pc_website',
			'price_type'        => '_pc_price_type',
			'price_value'       => '_pc_price_value',
			'promo_code'        => '_pc_promo_code',
			'promo_description' => '_pc_promo_description',
			'promo_expires'     => '_pc_promo_expires',
			'promo_checked'     => '_pc_promo_checked',
		);
	}

	/** header column => flag meta key. Truthy cell = flag on. */
	private static function flag_columns() {
		$out = array();
		foreach ( array_keys( PC_Meta_Fields::flags() ) as $meta_key ) {
			$out[ ltrim( str_replace( '_pc_', '', $meta_key ), '_' ) ] = $meta_key;
		}
		return $out;
	}

	/** Cell values that mean "yes" for a flag column. */
	private static function is_truthy( $raw ) {
		return in_array( strtolower( trim( (string) $raw ) ), array( '1', 'yes', 'y', 'true', 'да', '+' ), true );
	}

	/** header column => meta key (multi-line «|»-separated → newline). */
	private static function multiline_columns() {
		return array(
			'features' => '_pc_features',
			'clients'  => '_pc_clients',
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
			array_keys( self::multiline_columns() ),
			array( 'faq' ),
			array_keys( self::taxonomy_columns() )
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
			'Импорт поставщиков (CSV)',
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
			<h1>Импорт поставщиков из CSV</h1>

			<?php if ( isset( $_GET['imported'] ) ) : ?>
				<div class="notice notice-success"><p>Импортировано записей: <?php echo (int) $_GET['imported']; ?><?php echo isset( $_GET['skipped'] ) && (int) $_GET['skipped'] ? ', пропущено строк: ' . (int) $_GET['skipped'] : ''; ?>.</p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['import_error'] ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( wp_unslash( $_GET['import_error'] ) ); ?></p></div>
			<?php endif; ?>

			<p>Файл: <strong>UTF-8 CSV</strong>, первая строка — заголовки. Разделитель столбцов определяется автоматически (<code>,</code>, <code>;</code> или табуляция). Если в ячейке несколько значений (тип, специализация, особенности, клиенты, FAQ) — разделяйте их вертикальной чертой «<code>|</code>».</p>
			<p><a class="button" href="<?php echo esc_url( $sample_url ); ?>">Скачать образец CSV</a></p>

			<h2>Столбцы</h2>
			<p class="description" style="max-width:820px">
				<code>title</code> (обязательно) — название; <code>short_description</code>; <code>website</code>;
				<code>price_type</code> (<?php echo esc_html( implode( ' | ', array_keys( PC_Meta_Fields::price_types() ) ) ); ?>); <code>price_value</code>;
				<code>promo_code</code>, <code>promo_description</code>, <code>promo_expires</code>, <code>promo_checked</code> (даты — <code>ГГГГ-ММ-ДД</code>);
				<code><?php echo esc_html( implode( '</code>, <code>', array_keys( self::flag_columns() ) ) ); ?></code> — условия, «1» / «да» = включено;
				<code>features</code>, <code>clients</code> (несколько пунктов через «|»);
				<code>faq</code> — пары «вопрос <code>::</code> ответ», сами пары через «|»;
				<code>type</code> — тип сервиса (таксономия <code>contractor_type</code>), <code>specialization</code> — специализация (<code>contractor_specialization</code>); термины через «|», создаются при необходимости.
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
		fputcsv( $out, array(
			'ООО «Пример Интеграции»',
			'Внедряем 1С и Bitrix24 для среднего бизнеса.',
			'https://example.com',
			'from', '150000',
			'', '', '', '',
			'', '', 'да',
			'Работаем 24/7|Фиксированная цена|Бесплатный аудит',
			'X5 Retail Group|Сбер',
			'Нужен ли VPN? :: Нет, сервис работает с российских IP.|Как оплатить? :: Картой РФ или по счёту для юрлиц.',
			'Интегратор|Консалтинг',
			'1С|Bitrix24',
		) );
		fputcsv( $out, array(
			'Cloud Hosting Pro', 'Облачный хостинг и администрирование.', 'https://cloudpro.example',
			'subscription', '990',
			'CLOUD10', 'Скидка 10% на первый месяц', '2026-12-31', '2026-10-13',
			'', 'да', 'да',
			'SLA 99.9%|Поддержка на русском', '',
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

		$imported = 0;
		$skipped  = 0;
		foreach ( $rows as $row ) {
			$title = trim( (string) ( $row['title'] ?? '' ) );
			if ( '' === $title ) {
				++$skipped;
				continue;
			}
			$imported += self::import_row( $row ) ? 1 : 0;
		}

		wp_safe_redirect( add_query_arg( array( 'imported' => $imported, 'skipped' => $skipped ), $redirect ) );
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

	/** Create one published contractor from a mapped row. */
	private static function import_row( array $row ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'contractor',
				'post_status' => 'publish',
				'post_title'  => sanitize_text_field( $row['title'] ),
			),
			true
		);
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return false;
		}

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
			} elseif ( in_array( $key, array( '_pc_promo_expires', '_pc_promo_checked' ), true ) ) {
				$val = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $val ) ? $val : '';
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

		if ( class_exists( 'PC_SEO' ) ) {
			PC_SEO::apply_meta_description( $post_id, (string) get_post_meta( $post_id, '_pc_short_description', true ) );
		}
		return true;
	}
}
