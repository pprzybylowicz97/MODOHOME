<?php
/**
 * Ekran logu aktywności.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog\Admin;

use MODOhome\Catalog\Activity_Log;
use MODOhome\Catalog\Post_Type;
use MODOhome\Catalog\Roles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Log widzi wyłącznie administrator.
 */
class Log_Page {

	public const SLUG     = 'modohome-catalog-log';
	private const PER_PAGE = 50;

	/**
	 * Podpina stronę i czyszczenie logu.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_post_modohome_catalog_clear_log', array( $this, 'handle_clear' ) );
	}

	/**
	 * Dodaje podstronę logu.
	 */
	public function add_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . Post_Type::SLUG,
			__( 'Log aktywności', 'modohome-katalog-produktow' ),
			__( 'Log aktywności', 'modohome-katalog-produktow' ),
			Roles::CAP_MANAGE,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Renderuje log.
	 */
	public function render(): void {
		if ( ! current_user_can( Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'Brak uprawnień do tej strony.', 'modohome-katalog-produktow' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- stronicowanie jest odczytem.
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$total  = Activity_Log::count_entries();
		$offset = ( $paged - 1 ) * self::PER_PAGE;
		$rows   = Activity_Log::get_entries( self::PER_PAGE, $offset );
		$labels = Activity_Log::action_labels();
		?>
		<div class="wrap modohome-settings">
			<h1><?php esc_html_e( 'Log aktywności katalogu', 'modohome-katalog-produktow' ); ?></h1>

			<p class="description">
				<?php esc_html_e( 'Log zapisuje wyłącznie autora zmiany, produkt i rodzaj zdarzenia. Nie przechowuje adresów IP ani innych danych osobowych.', 'modohome-katalog-produktow' ); ?>
			</p>

			<?php if ( empty( $rows ) ) : ?>
				<p><?php esc_html_e( 'Log jest pusty.', 'modohome-katalog-produktow' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Data', 'modohome-katalog-produktow' ); ?></th>
							<th><?php esc_html_e( 'Użytkownik', 'modohome-katalog-produktow' ); ?></th>
							<th><?php esc_html_e( 'Produkt', 'modohome-katalog-produktow' ); ?></th>
							<th><?php esc_html_e( 'Zdarzenie', 'modohome-katalog-produktow' ); ?></th>
							<th><?php esc_html_e( 'Szczegóły', 'modohome-katalog-produktow' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$user      = (int) $row->user_id > 0 ? get_userdata( (int) $row->user_id ) : null;
							$user_name = $user instanceof \WP_User
								? $user->display_name
								: __( 'System', 'modohome-katalog-produktow' );

							$product_id    = (int) $row->product_id;
							$product_title = (string) $row->product_title;
							$edit_link     = $product_id > 0 ? get_edit_post_link( $product_id ) : '';
							?>
							<tr>
								<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', (string) $row->created_at ) ); ?></td>
								<td><?php echo esc_html( $user_name ); ?></td>
								<td>
									<?php if ( $edit_link ) : ?>
										<a href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( $product_title ); ?></a>
									<?php else : ?>
										<?php echo esc_html( '' !== $product_title ? $product_title : '—' ); ?>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $labels[ (string) $row->action ] ?? (string) $row->action ); ?></td>
								<td><?php echo esc_html( (string) $row->details ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php
				$pages = (int) ceil( $total / self::PER_PAGE );

				if ( $pages > 1 ) {
					echo '<div class="tablenav"><div class="tablenav-pages">';
					echo wp_kses_post(
						(string) paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'current'   => $paged,
								'total'     => $pages,
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
							)
						)
					);
					echo '</div></div>';
				}
				?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px">
					<?php wp_nonce_field( 'modohome_catalog_clear_log' ); ?>
					<input type="hidden" name="action" value="modohome_catalog_clear_log" />
					<?php submit_button( __( 'Wyczyść log', 'modohome-katalog-produktow' ), 'delete', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Czyści log.
	 */
	public function handle_clear(): void {
		if ( ! current_user_can( Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'Brak uprawnień.', 'modohome-katalog-produktow' ) );
		}

		check_admin_referer( 'modohome_catalog_clear_log' );

		Activity_Log::clear();

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . Post_Type::SLUG . '&page=' . self::SLUG ) );
		exit;
	}
}
