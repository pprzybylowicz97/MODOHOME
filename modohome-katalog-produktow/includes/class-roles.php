<?php
/**
 * Rola „Pracownik katalogu” oraz własne uprawnienia wtyczki.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wtyczka nie opiera się na roli autora — ma własny zestaw capabilities.
 */
class Roles {

	public const ROLE = 'modohome_catalog_worker';

	/** Zarządzanie ustawieniami, logiem, importem i eksportem. */
	public const CAP_MANAGE = 'manage_modohome_catalog';

	/** Uprawnienia taksonomii. */
	public const CAP_MANAGE_TERMS = 'manage_modohome_product_categories';
	public const CAP_EDIT_TERMS   = 'edit_modohome_product_categories';
	public const CAP_DELETE_TERMS = 'delete_modohome_product_categories';
	public const CAP_ASSIGN_TERMS = 'assign_modohome_product_categories';

	/**
	 * Uprawnienia do typu wpisu w formie wymaganej przez register_post_type().
	 *
	 * @return array<string,string>
	 */
	public static function post_type_caps(): array {
		return array(
			'edit_post'              => 'edit_modohome_product',
			'read_post'              => 'read_modohome_product',
			'delete_post'            => 'delete_modohome_product',
			'edit_posts'             => 'edit_modohome_products',
			'edit_others_posts'      => 'edit_others_modohome_products',
			'publish_posts'          => 'publish_modohome_products',
			'read_private_posts'     => 'read_private_modohome_products',
			'delete_posts'           => 'delete_modohome_products',
			'delete_private_posts'   => 'delete_private_modohome_products',
			'delete_published_posts' => 'delete_published_modohome_products',
			'delete_others_posts'    => 'delete_others_modohome_products',
			'edit_private_posts'     => 'edit_private_modohome_products',
			'edit_published_posts'   => 'edit_published_modohome_products',
			'create_posts'           => 'edit_modohome_products',
		);
	}

	/**
	 * Uprawnienia taksonomii w formie wymaganej przez register_taxonomy().
	 *
	 * @return array<string,string>
	 */
	public static function taxonomy_caps(): array {
		return array(
			'manage_terms' => self::CAP_MANAGE_TERMS,
			'edit_terms'   => self::CAP_EDIT_TERMS,
			'delete_terms' => self::CAP_DELETE_TERMS,
			'assign_terms' => self::CAP_ASSIGN_TERMS,
		);
	}

	/**
	 * Pełny zestaw uprawnień administratora.
	 *
	 * @return array<string>
	 */
	public static function admin_caps(): array {
		$caps = array_values( self::post_type_caps() );

		$caps[] = self::CAP_MANAGE;
		$caps[] = self::CAP_MANAGE_TERMS;
		$caps[] = self::CAP_EDIT_TERMS;
		$caps[] = self::CAP_DELETE_TERMS;
		$caps[] = self::CAP_ASSIGN_TERMS;

		return array_values( array_unique( $caps ) );
	}

	/**
	 * Uprawnienia pracownika katalogu.
	 *
	 * Świadomie brak: edit_others_*, delete_others_*, edit_pages, manage_options,
	 * install_plugins, list_users oraz uprawnień do zarządzania kategoriami.
	 *
	 * @return array<string,bool>
	 */
	public static function worker_caps(): array {
		return array(
			'read'                                => true,
			'upload_files'                        => true,
			'edit_modohome_products'              => true,
			'edit_published_modohome_products'    => true,
			'publish_modohome_products'           => true,
			'delete_modohome_products'            => true,
			'delete_published_modohome_products'  => true,
			self::CAP_ASSIGN_TERMS                => true,
		);
	}

	/**
	 * Tworzy rolę pracownika i nadaje uprawnienia rolom administracyjnym.
	 */
	public static function install(): void {
		remove_role( self::ROLE );

		add_role(
			self::ROLE,
			__( 'Pracownik katalogu', 'modohome-katalog-produktow' ),
			self::worker_caps()
		);

		foreach ( array( 'administrator' ) as $role_name ) {
			$role = get_role( $role_name );

			if ( ! $role instanceof \WP_Role ) {
				continue;
			}

			foreach ( self::admin_caps() as $cap ) {
				$role->add_cap( $cap );
			}
		}

		// Redaktor dostaje pełną obsługę produktów, ale nie ustawienia wtyczki.
		$editor = get_role( 'editor' );

		if ( $editor instanceof \WP_Role ) {
			foreach ( self::post_type_caps() as $cap ) {
				$editor->add_cap( $cap );
			}

			$editor->add_cap( self::CAP_MANAGE_TERMS );
			$editor->add_cap( self::CAP_EDIT_TERMS );
			$editor->add_cap( self::CAP_ASSIGN_TERMS );
		}
	}

	/**
	 * Usuwa uprawnienia wtyczki (używane przy deinstalacji).
	 */
	public static function uninstall(): void {
		remove_role( self::ROLE );

		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );

			if ( ! $role instanceof \WP_Role ) {
				continue;
			}

			foreach ( self::admin_caps() as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}

	/**
	 * Rejestruje filtry modyfikujące uprawnienia w locie.
	 */
	public function register(): void {
		add_filter( 'user_has_cap', array( $this, 'filter_dynamic_caps' ), 10, 4 );
		add_filter( 'map_meta_cap', array( $this, 'filter_meta_caps' ), 10, 4 );
	}

	/**
	 * Uprawnienia zależne od ustawień wtyczki.
	 *
	 * Dzięki temu zmiana ustawienia działa natychmiast i nie wymaga przepisywania roli.
	 *
	 * @param array<string,bool> $allcaps Uprawnienia użytkownika.
	 * @param array<string>      $caps    Wymagane uprawnienia.
	 * @param array<mixed>       $args    Argumenty wywołania.
	 * @param \WP_User           $user    Użytkownik.
	 *
	 * @return array<string,bool>
	 */
	public function filter_dynamic_caps( array $allcaps, array $caps, array $args, \WP_User $user ): array {
		if ( ! in_array( self::ROLE, (array) $user->roles, true ) ) {
			return $allcaps;
		}

		// Tworzenie kategorii przez pracowników — tylko gdy administrator na to pozwoli.
		if ( Settings::bool( 'workers_can_create_terms' ) ) {
			$allcaps[ self::CAP_MANAGE_TERMS ] = true;
			$allcaps[ self::CAP_EDIT_TERMS ]   = true;
		} else {
			unset( $allcaps[ self::CAP_MANAGE_TERMS ], $allcaps[ self::CAP_EDIT_TERMS ] );
		}

		// Gdy produkty wymagają zatwierdzenia, pracownik traci prawo publikacji.
		if ( ! Settings::bool( 'worker_autopublish' ) ) {
			unset( $allcaps['publish_modohome_products'] );
		}

		return $allcaps;
	}

	/**
	 * Twarda blokada: pracownik nigdy nie dotyka cudzych produktów.
	 *
	 * map_meta_cap z primitive caps radzi sobie z tym samodzielnie, ale dokładamy
	 * jawne sprawdzenie autora, żeby żadna wtyczka trzecia tego nie rozluźniła.
	 *
	 * @param array<string> $caps    Wymagane uprawnienia.
	 * @param string        $cap     Sprawdzane uprawnienie.
	 * @param int           $user_id Identyfikator użytkownika.
	 * @param array<mixed>  $args    Argumenty (zwykle ID wpisu).
	 *
	 * @return array<string>
	 */
	public function filter_meta_caps( array $caps, string $cap, int $user_id, array $args ): array {
		$watched = array( 'edit_modohome_product', 'delete_modohome_product', 'read_modohome_product' );

		if ( ! in_array( $cap, $watched, true ) || empty( $args[0] ) ) {
			return $caps;
		}

		$user = get_userdata( $user_id );

		if ( ! $user instanceof \WP_User || ! in_array( self::ROLE, (array) $user->roles, true ) ) {
			return $caps;
		}

		$post = get_post( (int) $args[0] );

		if ( ! $post instanceof \WP_Post || Post_Type::SLUG !== $post->post_type ) {
			return $caps;
		}

		if ( (int) $post->post_author !== $user_id ) {
			return array( 'do_not_allow' );
		}

		return $caps;
	}

	/**
	 * Czy użytkownik może korzystać z formularza frontendowego.
	 *
	 * @param int|null $user_id Identyfikator użytkownika (domyślnie bieżący).
	 */
	public static function can_submit( ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();

		if ( $user_id <= 0 ) {
			return false;
		}

		return user_can( $user_id, 'edit_modohome_products' ) && user_can( $user_id, 'upload_files' );
	}
}
