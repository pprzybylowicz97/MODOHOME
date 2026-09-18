<?php
/**
 * Ekran narzędzi: eksport i import produktów w formacie CSV.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog\Admin;

use MODOhome\Catalog\Activity_Log;
use MODOhome\Catalog\Post_Type;
use MODOhome\Catalog\Product;
use MODOhome\Catalog\Product_Actions;
use MODOhome\Catalog\Roles;
use MODOhome\Catalog\Taxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Eksport buduje plik w locie; import czyta przesłany plik i waliduje każdy wiersz.
 */
class Tools_Page {

	public const SLUG = 'modohome-catalog-tools';

	/**
	 * Nagłówki kolumn pliku CSV.
	 *
	 * @return array<int,string>
	 */
	public static function columns(): array {
		return array(
			'nazwa',
			'opis',
			'cena',
			'cena_poprzednia',
			'kategoria',
			'status',
			'etykieta',
			'autor',
			'data',
			'zdjecie',
		);
	}

	/**
	 * Podpina stronę i obsługę formularzy.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_post_modohome_catalog_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_modohome_catalog_import', array( $this, 'handle_import' ) );
	}

	/**
	 * Dodaje podstronę narzędzi.
	 */
	public function add_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . Post_Type::SLUG,
			__( 'Import i eksport', 'modohome-katalog-produktow' ),
			__( 'Import i eksport', 'modohome-katalog-produktow' ),
			Roles::CAP_MANAGE,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Renderuje stronę narzędzi.
	 */
	public function render(): void {
		if ( ! current_user_can( Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'Brak uprawnień do tej strony.', 'modohome-katalog-produktow' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- komunikaty są informacyjne.
		$imported  = isset( $_GET['imported'] ) ? absint( $_GET['imported'] ) : 0;
		$skipped   = isset( $_GET['skipped'] ) ? absint( $_GET['skipped'] ) : 0;
		$invalid   = isset( $_GET['invalid'] ) ? absint( $_GET['invalid'] ) : 0;
		$error_key = isset( $_GET['import_error'] ) ? sanitize_key( wp_unslash( (string) $_GET['import_error'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap modohome-settings">
			<h1><?php esc_html_e( 'Import i eksport produktów', 'modohome-katalog-produktow' ); ?></h1>

			<?php if ( '' !== $error_key ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $this->error_message( $error_key ) ); ?></p></div>
			<?php endif; ?>

			<?php if ( $imported > 0 || $skipped > 0 || $invalid > 0 ) : ?>
				<div class="notice notice-success">
					<p>
						<?php
						printf(
							/* translators: 1: liczba dodanych, 2: liczba pominiętych duplikatów, 3: liczba błędnych wierszy. */
							esc_html__( 'Zaimportowano: %1$d. Pominięte duplikaty: %2$d. Wiersze z błędami: %3$d.', 'modohome-katalog-produktow' ),
							(int) $imported,
							(int) $skipped,
							(int) $invalid
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Eksport', 'modohome-katalog-produktow' ); ?></h2>
			<p><?php esc_html_e( 'Pobiera wszystkie produkty jako plik CSV (kodowanie UTF-8, separator: średnik).', 'modohome-katalog-produktow' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'modohome_catalog_export' ); ?>
				<input type="hidden" name="action" value="modohome_catalog_export" />
				<?php submit_button( __( 'Pobierz plik CSV', 'modohome-katalog-produktow' ), 'primary', 'submit', false ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Import', 'modohome-katalog-produktow' ); ?></h2>
			<p>
				<?php esc_html_e( 'Plik musi zawierać nagłówek z kolumnami:', 'modohome-katalog-produktow' ); ?>
				<code><?php echo esc_html( implode( '; ', self::columns() ) ); ?></code>
			</p>
			<p class="description">
				<?php esc_html_e( 'Wymagane są: nazwa, cena i kategoria. Kategoria może być nazwą lub slugiem — nieistniejące kategorie zostaną utworzone. Kolumna „zdjecie” przyjmuje adres URL obrazka już obecnego w bibliotece mediów.', 'modohome-katalog-produktow' ); ?>
			</p>

			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'modohome_catalog_import' ); ?>
				<input type="hidden" name="action" value="modohome_catalog_import" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="modohome_import_file"><?php esc_html_e( 'Plik CSV', 'modohome-katalog-produktow' ); ?></label></th>
						<td><input type="file" id="modohome_import_file" name="import_file" accept=".csv,text/csv" required /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Duplikaty', 'modohome-katalog-produktow' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="allow_duplicates" value="1" />
								<?php esc_html_e( 'Dodaj mimo istniejącego produktu o tej samej nazwie', 'modohome-katalog-produktow' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Domyślnie produkty o nazwie już obecnej w katalogu są pomijane, a ich liczba pokazana w podsumowaniu.', 'modohome-katalog-produktow' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Importuj produkty', 'modohome-katalog-produktow' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Eksport do pliku CSV.
	 */
	public function handle_export(): void {
		if ( ! current_user_can( Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'Brak uprawnień.', 'modohome-katalog-produktow' ) );
		}

		check_admin_referer( 'modohome_catalog_export' );

		$query = new \WP_Query(
			array(
				'post_type'      => Post_Type::SLUG,
				'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=modohome-produkty-' . gmdate( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );

		if ( false === $output ) {
			wp_die( esc_html__( 'Nie udało się przygotować pliku.', 'modohome-katalog-produktow' ) );
		}

		// BOM, żeby Excel poprawnie odczytał polskie znaki.
		fwrite( $output, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		fputcsv( $output, self::columns(), ';', '"', '' );

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$terms = get_the_terms( $post->ID, Taxonomy::SLUG );
			$terms = ( is_wp_error( $terms ) || ! is_array( $terms ) ) ? array() : $terms;

			$thumb_id = (int) get_post_thumbnail_id( $post->ID );

			fputcsv(
				$output,
				array(
					$post->post_title,
					Product::get_description( $post->ID ),
					(string) get_post_meta( $post->ID, Product::META_PRICE, true ),
					(string) get_post_meta( $post->ID, Product::META_OLD_PRICE, true ),
					implode( '|', array_map( static fn( \WP_Term $t ): string => $t->name, $terms ) ),
					Product::get_availability( $post->ID ),
					Product::get_badge( $post->ID ),
					(string) get_the_author_meta( 'display_name', (int) $post->post_author ),
					get_the_date( 'Y-m-d H:i:s', $post ),
					$thumb_id > 0 ? (string) wp_get_attachment_url( $thumb_id ) : '',
				),
				';',
				'"',
				''
			);
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		exit;
	}

	/**
	 * Import z pliku CSV.
	 */
	public function handle_import(): void {
		if ( ! current_user_can( Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'Brak uprawnień.', 'modohome-katalog-produktow' ) );
		}

		check_admin_referer( 'modohome_catalog_import' );

		$redirect = admin_url( 'edit.php?post_type=' . Post_Type::SLUG . '&page=' . self::SLUG );

		if ( empty( $_FILES['import_file'] ) || ! isset( $_FILES['import_file']['tmp_name'] ) ) {
			$this->redirect_with_error( $redirect, 'no_file' );
		}

		$error = isset( $_FILES['import_file']['error'] ) ? (int) $_FILES['import_file']['error'] : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_OK !== $error ) {
			$this->redirect_with_error( $redirect, 'upload' );
		}

		$tmp = (string) $_FILES['import_file']['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! is_uploaded_file( $tmp ) ) {
			$this->redirect_with_error( $redirect, 'upload' );
		}

		$name  = isset( $_FILES['import_file']['name'] ) ? sanitize_file_name( wp_unslash( (string) $_FILES['import_file']['name'] ) ) : '';
		$check = wp_check_filetype( $name, array( 'csv' => 'text/csv' ) );

		if ( 'csv' !== $check['ext'] ) {
			$this->redirect_with_error( $redirect, 'not_csv' );
		}

		$handle = fopen( $tmp, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( false === $handle ) {
			$this->redirect_with_error( $redirect, 'unreadable' );
		}

		$header = fgetcsv( $handle, 0, ';', '"', '' );

		if ( ! is_array( $header ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			$this->redirect_with_error( $redirect, 'empty' );
		}

		// Usuwamy BOM z pierwszej kolumny nagłówka.
		$header[0] = preg_replace( '/^\x{FEFF}/u', '', (string) $header[0] ) ?? '';
		$header    = array_map( static fn( $value ): string => strtolower( trim( (string) $value ) ), $header );

		$map = array_flip( $header );

		foreach ( array( 'nazwa', 'cena', 'kategoria' ) as $required ) {
			if ( ! isset( $map[ $required ] ) ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				$this->redirect_with_error( $redirect, 'columns' );
			}
		}

		$allow_duplicates = ! empty( $_POST['allow_duplicates'] );

		$imported = 0;
		$skipped  = 0;
		$invalid  = 0;

		while ( false !== ( $row = fgetcsv( $handle, 0, ';', '"', '' ) ) ) {
			if ( ! is_array( $row ) || array_filter( $row, static fn( $v ): bool => '' !== trim( (string) $v ) ) === array() ) {
				continue;
			}

			$value = static function ( string $key ) use ( $row, $map ): string {
				if ( ! isset( $map[ $key ] ) || ! isset( $row[ $map[ $key ] ] ) ) {
					return '';
				}

				return trim( (string) $row[ $map[ $key ] ] );
			};

			$title = sanitize_text_field( $value( 'nazwa' ) );
			$price = Product::sanitize_price( $value( 'cena' ) );
			$cats  = $value( 'kategoria' );

			if ( '' === $title || '' === $price || '' === $cats ) {
				++$invalid;
				continue;
			}

			if ( ! $allow_duplicates && $this->title_exists( $title ) ) {
				++$skipped;
				continue;
			}

			$post_id = wp_insert_post(
				array(
					'post_type'    => Post_Type::SLUG,
					'post_title'   => $title,
					'post_status'  => 'publish',
					'post_author'  => get_current_user_id(),
					'post_content' => '',
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				++$invalid;
				continue;
			}

			$post_id = (int) $post_id;

			update_post_meta( $post_id, Product::META_PRICE, $price );
			update_post_meta( $post_id, Product::META_OLD_PRICE, Product::sanitize_price( $value( 'cena_poprzednia' ) ) );
			update_post_meta( $post_id, Product::META_DESCRIPTION, sanitize_textarea_field( $value( 'opis' ) ) );
			update_post_meta( $post_id, Product::META_BADGE, Product::sanitize_badge( $value( 'etykieta' ) ) );
			update_post_meta( $post_id, Product::META_AVAILABILITY, Product::sanitize_availability( $value( 'status' ) ) );

			$term_ids = $this->resolve_terms( $cats );

			if ( ! empty( $term_ids ) ) {
				wp_set_object_terms( $post_id, $term_ids, Taxonomy::SLUG, false );
			}

			$image_url = esc_url_raw( $value( 'zdjecie' ) );

			if ( '' !== $image_url ) {
				$attachment_id = attachment_url_to_postid( $image_url );

				if ( $attachment_id > 0 ) {
					set_post_thumbnail( $post_id, $attachment_id );
				}
			}

			Activity_Log::record( Activity_Log::ACTION_IMPORTED, $post_id, __( 'Import z pliku CSV', 'modohome-katalog-produktow' ) );

			++$imported;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		Product_Actions::flush_counts();

		wp_safe_redirect(
			add_query_arg(
				array(
					'imported' => $imported,
					'skipped'  => $skipped,
					'invalid'  => $invalid,
				),
				$redirect
			)
		);
		exit;
	}

	/**
	 * Czy istnieje już produkt o takiej nazwie.
	 *
	 * @param string $title Nazwa produktu.
	 */
	private function title_exists( string $title ): bool {
		$existing = get_posts(
			array(
				'post_type'      => Post_Type::SLUG,
				'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
				'title'          => $title,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		return ! empty( $existing );
	}

	/**
	 * Zamienia listę kategorii na identyfikatory, tworząc brakujące.
	 *
	 * @param string $raw Kategorie rozdzielone znakiem „|” lub przecinkiem.
	 *
	 * @return array<int>
	 */
	private function resolve_terms( string $raw ): array {
		$names = preg_split( '/[|,]/', $raw ) ?: array();
		$ids   = array();

		foreach ( $names as $name ) {
			$name = trim( (string) $name );

			if ( '' === $name ) {
				continue;
			}

			$term = get_term_by( 'name', $name, Taxonomy::SLUG );

			if ( ! $term instanceof \WP_Term ) {
				$term = get_term_by( 'slug', sanitize_title( $name ), Taxonomy::SLUG );
			}

			if ( $term instanceof \WP_Term ) {
				$ids[] = $term->term_id;
				continue;
			}

			$created = wp_insert_term( $name, Taxonomy::SLUG );

			if ( ! is_wp_error( $created ) && isset( $created['term_id'] ) ) {
				$ids[] = (int) $created['term_id'];
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Przekierowanie z kodem błędu.
	 *
	 * @param string $redirect Adres docelowy.
	 * @param string $code     Kod błędu.
	 */
	private function redirect_with_error( string $redirect, string $code ): never {
		wp_safe_redirect( add_query_arg( array( 'import_error' => $code ), $redirect ) );
		exit;
	}

	/**
	 * Komunikat dla kodu błędu importu.
	 *
	 * @param string $code Kod błędu.
	 */
	private function error_message( string $code ): string {
		return match ( $code ) {
			'no_file'    => __( 'Nie wybrano pliku.', 'modohome-katalog-produktow' ),
			'upload'     => __( 'Nie udało się przesłać pliku.', 'modohome-katalog-produktow' ),
			'not_csv'    => __( 'Plik musi mieć rozszerzenie .csv.', 'modohome-katalog-produktow' ),
			'unreadable' => __( 'Nie udało się odczytać pliku.', 'modohome-katalog-produktow' ),
			'empty'      => __( 'Plik jest pusty.', 'modohome-katalog-produktow' ),
			'columns'    => __( 'Brakuje wymaganych kolumn: nazwa, cena, kategoria.', 'modohome-katalog-produktow' ),
			default      => __( 'Import nie powiódł się.', 'modohome-katalog-produktow' ),
		};
	}
}
