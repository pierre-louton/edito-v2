<?php
/**
 * class-edito-categories.php
 *
 * Gère deux référentiels de catégories distincts :
 *
 *  1. ARTICLES     → WP taxonomy 'category' (native)
 *                    Enrichi via term_meta 'edito_icon' (URL médiathèque WP).
 *                    Le second niveau utilise le champ parent natif de WP.
 *                    Remplace Edito_Admin::get_category_icon().
 *
 *  2. PARTENAIRES  → Table custom {prefix}edito_cat_contact.
 *                    Deux niveaux via cat_parent (0 = niveau 1).
 *                    Maximum 2 niveaux d'imbrication (contrôlé à l'écriture).
 *
 * Intégration dans edito.php :
 *   - register_activation_hook : ajouter Edito_Categories::install()
 *   - add_action('init')       : ajouter Edito_Categories::register_hooks()
 *
 * Compatibilité PHP 8.0+.
 */
defined( 'ABSPATH' ) || exit;

class Edito_Categories {

	// ═══════════════════════════════════════════════════════════════════════
	// CONSTANTE TABLE
	// ═══════════════════════════════════════════════════════════════════════

	/**
	 * Nom complet de la table (avec préfixe WP).
	 * Utiliser cette méthode partout — jamais la chaîne brute.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'edito_cat_contact';
	}

	// ═══════════════════════════════════════════════════════════════════════
	// INSTALLATION
	// ═══════════════════════════════════════════════════════════════════════

	/**
	 * Crée ou met à jour la table via dbDelta.
	 * À appeler dans register_activation_hook() dans edito.php.
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$table   = self::table();

		$sql = "CREATE TABLE {$table} (
			cat_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
			cat_parent INT UNSIGNED NOT NULL DEFAULT 0,
			cat_name   VARCHAR(100) NOT NULL DEFAULT '',
			cat_slug   VARCHAR(100) NOT NULL DEFAULT '',
			cat_order  SMALLINT     NOT NULL DEFAULT 0,
			created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (cat_id),
			UNIQUE KEY   uq_slug   (cat_slug),
			KEY          idx_parent(cat_parent)
		) {$charset};";

		dbDelta( $sql );
	}

	// ═══════════════════════════════════════════════════════════════════════
	// REGISTRATION DES HOOKS
	// ═══════════════════════════════════════════════════════════════════════

	/**
	 * Enregistre tous les hooks AJAX de la classe.
	 * À appeler via add_action('init', [Edito_Categories::class, 'register_hooks']).
	 */
	public static function register_hooks(): void {
		// Catégories partenaires
		add_action( 'wp_ajax_edito_save_contact_cat',    [ __CLASS__, 'ajax_save_contact_cat' ] );
		add_action( 'wp_ajax_edito_delete_contact_cat',  [ __CLASS__, 'ajax_delete_contact_cat' ] );
		add_action( 'wp_ajax_edito_get_contact_cats',    [ __CLASS__, 'ajax_get_contact_cats' ] );
		add_action( 'wp_ajax_edito_reorder_contact_cats',[ __CLASS__, 'ajax_reorder_contact_cats' ] );

		// Catégories articles (term_meta)
		add_action( 'wp_ajax_edito_save_article_icon',   [ __CLASS__, 'ajax_save_article_icon' ] );
		add_action( 'wp_ajax_edito_get_article_cats',    [ __CLASS__, 'ajax_get_article_cats' ] );
	}

	// ═══════════════════════════════════════════════════════════════════════
	// RÉFÉRENTIEL PARTENAIRES — LECTURE
	// ═══════════════════════════════════════════════════════════════════════

	/**
	 * Toutes les catégories partenaires (une seule requête).
	 * Triées : parents d'abord (cat_parent=0), puis enfants ; dans chaque groupe,
	 * par cat_order ASC puis cat_name ASC.
	 *
	 * @return array[] Chaque entrée : cat_id, cat_parent, cat_name, cat_slug, cat_order
	 */
	public static function get_all(): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			'SELECT cat_id, cat_parent, cat_name, cat_slug, cat_order
			 FROM   ' . self::table() . '
			 ORDER  BY cat_parent ASC, cat_order ASC, cat_name ASC',
			ARRAY_A
		);
		return $rows ?: [];
	}

	/**
	 * Arborescence imbriquée à deux niveaux.
	 * Utile pour le rendu HTML de la page de gestion.
	 *
	 * @return array[] [
	 *   ['cat' => row, 'children' => [row, …]],
	 *   …
	 * ]
	 */
	public static function get_tree(): array {
		$all      = self::get_all();
		$parents  = [];
		$orphans  = [];   // enfants dont le parent est absent (sécurité)

		foreach ( $all as $row ) {
			if ( 0 === (int) $row['cat_parent'] ) {
				$parents[ (int) $row['cat_id'] ] = [ 'cat' => $row, 'children' => [] ];
			}
		}

		foreach ( $all as $row ) {
			if ( 0 !== (int) $row['cat_parent'] ) {
				$pid = (int) $row['cat_parent'];
				if ( isset( $parents[ $pid ] ) ) {
					$parents[ $pid ]['children'][] = $row;
				} else {
					$orphans[] = $row;   // parent supprimé ; on expose quand même la ligne
				}
			}
		}

		$tree = array_values( $parents );

		// Orphelins exposés à la racine pour ne pas les perdre silencieusement
		foreach ( $orphans as $o ) {
			$tree[] = [ 'cat' => $o, 'children' => [] ];
		}

		return $tree;
	}

	/**
	 * Fiche d'une catégorie partenaire.
	 *
	 * @return array|null  Toutes les colonnes, ou null si introuvable.
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE cat_id = %d LIMIT 1',
				$id
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Enfants directs d'un parent donné.
	 *
	 * @return array[]
	 */
	public static function get_children( int $parent_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT cat_id, cat_name, cat_slug, cat_order
				 FROM   ' . self::table() . '
				 WHERE  cat_parent = %d
				 ORDER  BY cat_order ASC, cat_name ASC',
				$parent_id
			),
			ARRAY_A
		);
		return $rows ?: [];
	}

	/**
	 * Liste aplatie pour alimenter un <select>.
	 * Format : [cat_id => label], les enfants préfixés par "— ".
	 *
	 * @return array<int, string>
	 */
	public static function get_for_select(): array {
		$tree   = self::get_tree();
		$result = [];

		foreach ( $tree as $node ) {
			$p = $node['cat'];
			$result[ (int) $p['cat_id'] ] = $p['cat_name'];

			foreach ( $node['children'] as $child ) {
				$result[ (int) $child['cat_id'] ] = '— ' . $child['cat_name'];
			}
		}

		return $result;
	}

	/**
	 * Nombre de partenaires attachés à une catégorie.
	 * Appelé avant de permettre la suppression.
	 */
	public static function count_contacts( int $cat_id ): int {
		global $wpdb;
		$tbl = $wpdb->prefix . 'edito_contacts';
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$tbl} WHERE contact_cat_id = %d",
				$cat_id
			)
		);
	}

	// ═══════════════════════════════════════════════════════════════════════
	// RÉFÉRENTIEL PARTENAIRES — ÉCRITURE
	// ═══════════════════════════════════════════════════════════════════════

	/**
	 * Crée ou met à jour une catégorie partenaire.
	 *
	 * Règle de profondeur : un enfant (cat_parent > 0) ne peut pas devenir
	 * parent d'un autre enfant — on se limite à 2 niveaux.
	 *
	 * @param array{
	 *   cat_id?:     int,
	 *   cat_parent?: int,
	 *   cat_name:    string,
	 *   cat_slug?:   string,
	 *   cat_order?:  int
	 * } $data
	 *
	 * @return int|false  ID inséré/mis à jour, false si données invalides.
	 */
	public static function save( array $data ): int|false {
		global $wpdb;
		$table = self::table();

		$cat_id  = (int) ( $data['cat_id']    ?? 0 );
		$parent  = (int) ( $data['cat_parent'] ?? 0 );
		$name    = sanitize_text_field( $data['cat_name']  ?? '' );
		$order   = (int) ( $data['cat_order']  ?? 0 );
		$slug_in = sanitize_title( $data['cat_slug'] ?? $name );

		if ( '' === $name ) return false;

		// Règle de profondeur max 2
		if ( $parent > 0 ) {
			$grand = self::get( $parent );
			if ( ! $grand ) return false;                   // Parent inexistant
			if ( (int) $grand['cat_parent'] > 0 ) return false; // Niveau 3 refusé
		}

		// Slug unique (suffixe numérique en cas de conflit)
		$slug = self::unique_slug( $slug_in ?: sanitize_title( $name ), $cat_id );

		$payload = [
			'cat_parent' => $parent,
			'cat_name'   => $name,
			'cat_slug'   => $slug,
			'cat_order'  => $order,
		];
		$formats = [ '%d', '%s', '%s', '%d' ];

		if ( $cat_id > 0 ) {
			$ok = $wpdb->update( $table, $payload, [ 'cat_id' => $cat_id ], $formats, [ '%d' ] );
			return ( false !== $ok ) ? $cat_id : false;
		}

		$ok = $wpdb->insert( $table, $payload, $formats );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Supprime une catégorie partenaire.
	 *
	 * Refuse si :
	 *   - la catégorie a des enfants (supprimer les sous-catégories d'abord)
	 *   - des partenaires y sont rattachés
	 *
	 * @return true|string  true = succès, string = message d'erreur lisible.
	 */
	public static function delete( int $id ): true|string {
		global $wpdb;
		$table = self::table();

		// Vérification enfants
		$nb_children = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . $table . ' WHERE cat_parent = %d',
				$id
			)
		);
		if ( $nb_children > 0 ) {
			return 'Cette catégorie contient des sous-catégories. Supprimez-les d\'abord.';
		}

		// Vérification partenaires attachés
		$nb_contacts = self::count_contacts( $id );
		if ( $nb_contacts > 0 ) {
			return sprintf(
				'Impossible : %d partenaire%s est rattaché à cette catégorie.',
				$nb_contacts,
				$nb_contacts > 1 ? 's' : ''
			);
		}

		$ok = $wpdb->delete( $table, [ 'cat_id' => $id ], [ '%d' ] );
		return $ok ? true : 'Erreur lors de la suppression en base.';
	}

	// ═══════════════════════════════════════════════════════════════════════
	// RÉFÉRENTIEL ARTICLES — WP taxonomy enrichie
	// ═══════════════════════════════════════════════════════════════════════

	/**
	 * Catégories WP (taxonomy 'category') pour un niveau donné,
	 * enrichies de la meta 'edito_icon'.
	 *
	 * @param int $parent  0 = racine, sinon term_id du parent.
	 * @return array[]  WP_Term converti en tableau + clé 'edito_icon'.
	 */
	public static function get_article_categories( int $parent = 0 ): array {
		$terms = get_terms( [
			'taxonomy'   => 'category',
			'hide_empty' => false,
			'parent'     => $parent,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		if ( is_wp_error( $terms ) || empty( $terms ) ) return [];

		$result = [];
		foreach ( $terms as $term ) {
			$row               = (array) $term;
			$row['edito_icon'] = (string) ( get_term_meta( $term->term_id, 'edito_icon', true ) ?: '' );
			$result[]          = $row;
		}

		return $result;
	}

	/**
	 * Arborescence complète des catégories WP avec icônes.
	 *
	 * @return array[] [
	 *   ['term' => [..., 'edito_icon' => '…'], 'children' => [...]],
	 *   …
	 * ]
	 */
	public static function get_article_tree(): array {
		$roots = self::get_article_categories( 0 );
		$tree  = [];

		foreach ( $roots as $root ) {
			$tree[] = [
				'term'     => $root,
				'children' => self::get_article_categories( (int) $root['term_id'] ),
			];
		}

		return $tree;
	}

	/**
	 * URL de l'icône d'une catégorie article.
	 *
	 * Remplace Edito_Admin::get_category_icon().
	 * Accepte un term_id (int) ou un slug (string).
	 *
	 * @param int|string $identifier
	 * @return string  URL ou '' si aucune icône configurée.
	 */
	public static function get_article_icon( int|string $identifier ): string {
		if ( is_int( $identifier ) || ctype_digit( (string) $identifier ) ) {
			$term_id = (int) $identifier;
		} else {
			$term    = get_term_by( 'slug', (string) $identifier, 'category' );
			$term_id = $term ? (int) $term->term_id : 0;
		}

		if ( ! $term_id ) return '';

		return (string) ( get_term_meta( $term_id, 'edito_icon', true ) ?: '' );
	}

	/**
	 * Enregistre (ou supprime) l'icône d'une catégorie article.
	 *
	 * @param int    $term_id
	 * @param string $url  URL médiathèque WP. Vide = suppression de la meta.
	 * @return bool
	 */
	public static function set_article_icon( int $term_id, string $url ): bool {
		if ( '' === $url ) {
			return (bool) delete_term_meta( $term_id, 'edito_icon' );
		}
		return (bool) update_term_meta( $term_id, 'edito_icon', esc_url_raw( $url ) );
	}

	// ═══════════════════════════════════════════════════════════════════════
	// AJAX — PARTENAIRES
	// ═══════════════════════════════════════════════════════════════════════

	/**
	 * Crée ou met à jour une catégorie partenaire.
	 *
	 * POST : nonce, cat_id (0=création), cat_parent, cat_name, cat_slug?, cat_order?
	 * Réponse success : { cat_id, cat, tree }
	 */
	public static function ajax_save_contact_cat(): void {
		self::check_nonce();
		self::check_editor();

		$id = self::save( [
			'cat_id'     => (int) ( $_POST['cat_id']     ?? 0 ),
			'cat_parent' => (int) ( $_POST['cat_parent']  ?? 0 ),
			'cat_name'   => sanitize_text_field( $_POST['cat_name']  ?? '' ),
			'cat_slug'   => sanitize_title( $_POST['cat_slug'] ?? '' ),
			'cat_order'  => (int) ( $_POST['cat_order']   ?? 0 ),
		] );

		if ( false === $id ) {
			wp_send_json_error(
				[ 'message' => 'Données invalides ou niveau d\'imbrication non autorisé (max 2 niveaux).' ],
				422
			);
		}

		wp_send_json_success( [
			'cat_id' => $id,
			'cat'    => self::get( $id ),
			'tree'   => self::get_tree(),
		] );
	}

	/**
	 * Supprime une catégorie partenaire.
	 *
	 * POST : nonce, cat_id
	 * Réponse success : { cat_id, tree }
	 */
	public static function ajax_delete_contact_cat(): void {
		self::check_nonce();
		self::check_editor();

		$id     = (int) ( $_POST['cat_id'] ?? 0 );
		$result = self::delete( $id );

		if ( true !== $result ) {
			wp_send_json_error( [ 'message' => $result ], 422 );
		}

		wp_send_json_success( [
			'cat_id' => $id,
			'tree'   => self::get_tree(),
		] );
	}

	/**
	 * Retourne l'arborescence ou la liste à plat des catégories partenaires.
	 *
	 * POST : nonce, mode ('tree'|'select')
	 * Réponse success : tree[] ou select{cat_id: label}
	 */
	public static function ajax_get_contact_cats(): void {
		self::check_nonce();

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => 'Non connecté.' ], 403 );
		}

		$mode = sanitize_text_field( $_POST['mode'] ?? 'tree' );

		if ( 'select' === $mode ) {
			wp_send_json_success( self::get_for_select() );
		}

		wp_send_json_success( self::get_tree() );
	}

	/**
	 * Met à jour l'ordre (cat_order) d'une liste de catégories.
	 * Utilisé après un drag-and-drop dans l'interface de gestion.
	 *
	 * POST : nonce, items[] = [{ cat_id, cat_order }, …]
	 * Réponse success : { tree }
	 */
	public static function ajax_reorder_contact_cats(): void {
		self::check_nonce();
		self::check_editor();

		global $wpdb;
		$table = self::table();
		$items = $_POST['items'] ?? [];

		if ( ! is_array( $items ) ) {
			wp_send_json_error( [ 'message' => 'Format invalide.' ], 400 );
		}

		foreach ( $items as $item ) {
			$cat_id    = (int) ( $item['cat_id']    ?? 0 );
			$cat_order = (int) ( $item['cat_order'] ?? 0 );

			if ( $cat_id > 0 ) {
				$wpdb->update(
					$table,
					[ 'cat_order' => $cat_order ],
					[ 'cat_id'   => $cat_id ],
					[ '%d' ],
					[ '%d' ]
				);
			}
		}

		wp_send_json_success( [ 'tree' => self::get_tree() ] );
	}

	// ═══════════════════════════════════════════════════════════════════════
	// AJAX — ARTICLES
	// ═══════════════════════════════════════════════════════════════════════

	/**
	 * Enregistre l'icône d'une catégorie WP.
	 *
	 * POST : nonce, term_id, icon_url (vide = suppression)
	 * Réponse success : { term_id, icon_url }
	 */
	public static function ajax_save_article_icon(): void {
		self::check_nonce();
		self::check_editor();

		$term_id  = (int) ( $_POST['term_id'] ?? 0 );
		$icon_url = esc_url_raw( sanitize_text_field( $_POST['icon_url'] ?? '' ) );

		if ( ! $term_id || is_wp_error( get_term( $term_id, 'category' ) ) ) {
			wp_send_json_error( [ 'message' => 'Catégorie WP introuvable.' ], 404 );
		}

		self::set_article_icon( $term_id, $icon_url );

		wp_send_json_success( [
			'term_id'  => $term_id,
			'icon_url' => $icon_url,
		] );
	}

	/**
	 * Retourne l'arborescence des catégories WP avec icônes.
	 *
	 * POST : nonce
	 * Réponse success : get_article_tree()
	 */
	public static function ajax_get_article_cats(): void {
		self::check_nonce();

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => 'Non connecté.' ], 403 );
		}

		wp_send_json_success( self::get_article_tree() );
	}

	// ═══════════════════════════════════════════════════════════════════════
	// HELPERS PRIVÉS
	// ═══════════════════════════════════════════════════════════════════════

	/**
	 * Vérifie le nonce 'edito_nonce' (depuis $_POST['nonce']).
	 * Envoie une erreur 403 et coupe l'exécution si invalide.
	 */
	private static function check_nonce(): void {
		$nonce = $_POST['nonce'] ?? '';
		if ( ! wp_verify_nonce( $nonce, 'edito_nonce' ) ) {
			wp_send_json_error( [ 'message' => 'Non autorisé.' ], 403 );
		}
	}

	/**
	 * Vérifie que l'utilisateur courant a le droit éditeur.
	 * Envoie une erreur 403 et coupe l'exécution sinon.
	 */
	private static function check_editor(): void {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_send_json_error( [ 'message' => 'Permissions insuffisantes.' ], 403 );
		}
	}

	/**
	 * Génère un slug unique dans la table partenaires.
	 * Ajoute un suffixe numérique en cas de conflit avec un autre enregistrement.
	 *
	 * @param string $base        Slug de départ (déjà sanitize_title-é).
	 * @param int    $exclude_id  ID à exclure de la vérification (mise à jour).
	 * @return string
	 */
	private static function unique_slug( string $base, int $exclude_id = 0 ): string {
		global $wpdb;
		$table  = self::table();
		$slug   = $base;
		$suffix = 1;

		while ( true ) {
			$existing_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT cat_id FROM ' . $table . ' WHERE cat_slug = %s LIMIT 1',
					$slug
				)
			);

			// Pas de conflit, ou conflit avec l'enregistrement qu'on met à jour
			if ( ! $existing_id || $existing_id === $exclude_id ) {
				break;
			}

			$slug = $base . '-' . $suffix;
			$suffix++;
		}

		return $slug;
	}
}
