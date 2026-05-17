<?php
/**
 * class-edito-db.php  —  Fork Edito
 *
 * Gère les tables custom des partenaires et leur liaison aux articles WP.
 *
 * ── Ce qui change par rapport à l'original ───────────────────────────────
 *  • Tables renommées :
 *      wp_contacts      → wp_edito_contacts
 *      wp_contact_post  → wp_edito_contact_post
 *  • Plus d'ENUM :
 *      contact_type      VARCHAR(50) DEFAULT 'partenaire'   (libre)
 *      contact_categorie supprimé → contact_cat_id INT FK → wp_edito_cat_contact
 *  • Nouveau champ : contact_notes TEXT, import_source VARCHAR(20)
 *  • contact_nom élargi VARCHAR(50) → VARCHAR(100)
 *  • Nouvelle méthode import_csv() — upsert sur (contact_nom + contact_email)
 *  • AJAX edito_csv_preview + edito_csv_import
 * ─────────────────────────────────────────────────────────────────────────
 *
 * Intégration dans edito.php :
 *   register_activation_hook : Edito_DB::install()
 *   add_action('init')       : Edito_DB::register_hooks()
 *
 * Compatibilité PHP 8.0+.
 */
defined( 'ABSPATH' ) || exit;

class Edito_DB {

	// ═══════════════════════════════════════════════════════════════════════
	// NOMS DE TABLES
	// ═══════════════════════════════════════════════════════════════════════

	public static function table_contacts(): string {
		global $wpdb;
		return $wpdb->prefix . 'edito_contacts';
	}

	public static function table_liaison(): string {
		global $wpdb;
		return $wpdb->prefix . 'edito_contact_post';
	}

	// ═══════════════════════════════════════════════════════════════════════
	// INSTALLATION
	// ═══════════════════════════════════════════════════════════════════════

	/**
	 * Crée ou met à jour les deux tables via dbDelta.
	 * Appelé par register_activation_hook() dans edito.php.
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$tc      = self::table_contacts();
		$tl      = self::table_liaison();

		// Table partenaires — plus d'ENUM, cat_id FK vers wp_edito_cat_contact
		dbDelta( "CREATE TABLE {$tc} (
			contact_id       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
			contact_nom      VARCHAR(100)  NOT NULL DEFAULT '',
			contact_type     VARCHAR(50)   NOT NULL DEFAULT 'partenaire',
			contact_cat_id   INT UNSIGNED  NOT NULL DEFAULT 0,
			contact_icone    VARCHAR(255)  NOT NULL DEFAULT '',
			contact_adr1     VARCHAR(100)  NOT NULL DEFAULT '',
			contact_cp       VARCHAR(10)   NOT NULL DEFAULT '',
			contact_ville    VARCHAR(80)   NOT NULL DEFAULT '',
			contact_tel      VARCHAR(20)   NOT NULL DEFAULT '',
			contact_email    VARCHAR(100)  NOT NULL DEFAULT '',
			contact_web      VARCHAR(255)  NOT NULL DEFAULT '',
			contact_notes    TEXT          NOT NULL,
			contact_actif    TINYINT(1)    NOT NULL DEFAULT 1,
			contact_carousel TINYINT(1)    NOT NULL DEFAULT 0,
			import_source    VARCHAR(20)   NOT NULL DEFAULT 'manual',
			created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (contact_id),
			KEY idx_cat      (contact_cat_id),
			KEY idx_carousel (contact_carousel, contact_actif),
			KEY idx_actif_nom(contact_actif, contact_nom(20)),
			KEY idx_email    (contact_email(50))
		) {$charset};" );

		// Table liaison article ↔ partenaire
		dbDelta( "CREATE TABLE {$tl} (
			id         INT UNSIGNED        NOT NULL AUTO_INCREMENT,
			contact_id INT UNSIGNED        NOT NULL,
			post_id    BIGINT(20) UNSIGNED NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_pair       (contact_id, post_id),
			KEY        idx_post_id   (post_id),
			KEY        idx_contact_id(contact_id)
		) {$charset};" );
	}

	// ═══════════════════════════════════════════════════════════════════════
	// REGISTRATION DES HOOKS
	// ═══════════════════════════════════════════════════════════════════════

	public static function register_hooks(): void {
		add_action( 'wp_ajax_edito_save_contact',      [ __CLASS__, 'ajax_save' ] );
		add_action( 'wp_ajax_edito_delete_contact',    [ __CLASS__, 'ajax_delete' ] );
		add_action( 'wp_ajax_edito_link_contact_post', [ __CLASS__, 'ajax_link_post' ] );
		add_action( 'wp_ajax_edito_unlink_contact_post',[ __CLASS__, 'ajax_unlink_post' ] );
		add_action( 'wp_ajax_edito_get_contacts_json', [ __CLASS__, 'ajax_contacts_json' ] );
		add_action( 'wp_ajax_edito_csv_preview',       [ __CLASS__, 'ajax_csv_preview' ] );
		add_action( 'wp_ajax_edito_csv_import',        [ __CLASS__, 'ajax_csv_import' ] );
	}

	// ═══════════════════════════════════════════════════════════════════════
	// LECTURE
	// ═══════════════════════════════════════════════════════════════════════

	/**
	 * Compteurs globaux + par catégorie (une seule requête GROUP BY).
	 *
	 * @return array{
	 *   total: int,
	 *   actifs: int,
	 *   archives: int,
	 *   carousel: int,
	 *   by_cat: array<int,int>
	 * }
	 */
	public static function get_counts(): array {
		global $wpdb;
		$tc = self::table_contacts();

		// Compteurs globaux en une requête
		$row = $wpdb->get_row(
			"SELECT
				COUNT(*)                                   AS total,
				SUM(contact_actif = 1)                    AS actifs,
				SUM(contact_actif = 0)                    AS archives,
				SUM(contact_carousel = 1 AND contact_actif = 1) AS carousel
			FROM {$tc}",
			ARRAY_A
		);

		// Compteurs par catégorie (index idx_cat)
		$by_cat_rows = $wpdb->get_results(
			"SELECT contact_cat_id, COUNT(*) AS n
			 FROM   {$tc}
			 WHERE  contact_actif = 1
			 GROUP  BY contact_cat_id",
			ARRAY_A
		);

		$by_cat = [];
		foreach ( $by_cat_rows as $r ) {
			$by_cat[ (int) $r['contact_cat_id'] ] = (int) $r['n'];
		}

		return [
			'total'    => (int) ( $row['total']    ?? 0 ),
			'actifs'   => (int) ( $row['actifs']   ?? 0 ),
			'archives' => (int) ( $row['archives'] ?? 0 ),
			'carousel' => (int) ( $row['carousel'] ?? 0 ),
			'by_cat'   => $by_cat,
		];
	}

	/**
	 * Liste légère pour alimenter un select/dropdown.
	 *
	 * @param int    $cat_id  0 = toutes les catégories
	 * @param string $type    '' = tous | ex. 'partenaire'
	 * @return array[]  [contact_id, contact_nom, contact_type, contact_cat_id]
	 */
	public static function get_contacts_list( int $cat_id = 0, string $type = '' ): array {
		global $wpdb;
		$tc     = self::table_contacts();
		$where  = [ 'contact_actif = 1' ];
		$params = [];

		if ( $cat_id > 0 ) {
			$where[]  = 'contact_cat_id = %d';
			$params[] = $cat_id;
		}

		if ( '' !== $type ) {
			$where[]  = 'contact_type = %s';
			$params[] = $type;
		}

		$sql = 'SELECT contact_id, contact_nom, contact_type, contact_cat_id
		        FROM   ' . $tc . '
		        WHERE  ' . implode( ' AND ', $where ) . '
		        ORDER  BY contact_nom ASC';

		$rows = $params
			? $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A )
			: $wpdb->get_results( $sql, ARRAY_A );

		return $rows ?: [];
	}

	/**
	 * Liste paginée avec recherche full-text (nom, email, ville, notes).
	 *
	 * @param array{
	 *   cat_id?:   int,
	 *   type?:     string,
	 *   search?:   string,
	 *   actif?:    int,         -1 = tous, 0 = archivés, 1 = actifs (défaut)
	 *   per_page?: int,
	 *   page?:     int
	 * } $args
	 *
	 * @return array{ contacts: array[], total: int, pages: int }
	 */
	public static function get_contacts_paged( array $args = [] ): array {
		global $wpdb;
		$tc = self::table_contacts();

		$cat_id   = (int)    ( $args['cat_id']   ?? 0 );
		$type     = (string) ( $args['type']     ?? '' );
		$search   = (string) ( $args['search']   ?? '' );
		$actif    = isset( $args['actif'] ) ? (int) $args['actif'] : 1;
		$per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$page     = max( 1, (int) ( $args['page']     ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = [];
		$params = [];

		if ( -1 !== $actif ) {
			$where[]  = 'contact_actif = %d';
			$params[] = $actif;
		}

		if ( $cat_id > 0 ) {
			$where[]  = 'contact_cat_id = %d';
			$params[] = $cat_id;
		}

		if ( '' !== $type ) {
			$where[]  = 'contact_type = %s';
			$params[] = $type;
		}

		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(contact_nom LIKE %s OR contact_email LIKE %s OR contact_ville LIKE %s OR contact_notes LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

		// Compte total (évite FOUND_ROWS() déprécié)
		$count_sql = "SELECT COUNT(*) FROM {$tc} {$where_sql}";
		$total     = (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) )
			: $wpdb->get_var( $count_sql )
		);

		// Données paginées — SELECT * autorisé uniquement ici (fiche complète)
		$data_params   = array_merge( $params, [ $per_page, $offset ] );
		$data_sql      = "SELECT * FROM {$tc} {$where_sql} ORDER BY contact_nom ASC LIMIT %d OFFSET %d";
		$contacts      = (array) $wpdb->get_results(
			$wpdb->prepare( $data_sql, ...$data_params ),
			ARRAY_A
		);

		return [
			'contacts' => $contacts,
			'total'    => $total,
			'pages'    => (int) ceil( $total / $per_page ),
		];
	}

	/**
	 * Fiche complète d'un partenaire.
	 *
	 * @return array|null  Toutes les colonnes, null si introuvable.
	 */
	public static function get_contact( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table_contacts() . ' WHERE contact_id = %d LIMIT 1',
				$id
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Partenaires liés à un article (pour affichage dans le dashboard / l'éditeur).
	 *
	 * @return array[]  Colonnes : contact_id, contact_nom, contact_icone, contact_type, contact_cat_id
	 */
	public static function get_contacts_for_post( int $post_id ): array {
		global $wpdb;
		$tc = self::table_contacts();
		$tl = self::table_liaison();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.contact_id, c.contact_nom, c.contact_icone, c.contact_type, c.contact_cat_id
				 FROM   {$tc} c
				 JOIN   {$tl} cp ON cp.contact_id = c.contact_id
				 WHERE  cp.post_id = %d
				 ORDER  BY c.contact_nom ASC",
				$post_id
			),
			ARRAY_A
		);

		return $rows ?: [];
	}

	/**
	 * Articles liés à un partenaire (sens inverse).
	 *
	 * @return array[]  Colonnes : ID, post_title, post_status, post_modified
	 */
	public static function get_posts_for_contact( int $contact_id ): array {
		global $wpdb;
		$tl = self::table_liaison();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_status, p.post_modified
				 FROM   {$wpdb->posts} p
				 JOIN   {$tl} cp ON cp.post_id = p.ID
				 WHERE  cp.contact_id = %d
				   AND  p.post_type   = 'post'
				   AND  p.post_status IN ('pending','draft','publish','ce_validated')
				 ORDER  BY p.post_modified DESC",
				$contact_id
			),
			ARRAY_A
		);

		return $rows ?: [];
	}

	/**
	 * IDs des partenaires liés à un article (pré-cocher les checkboxes éditeur).
	 *
	 * @return int[]
	 */
	public static function get_linked_contact_ids( int $post_id ): array {
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT contact_id FROM ' . self::table_liaison() . ' WHERE post_id = %d',
				$post_id
			)
		);
		return array_map( 'intval', $ids ?: [] );
	}

	/**
	 * Partenaires visibles dans le carrousel front.
	 * Colonnes limitées volontairement (pas de SELECT *).
	 *
	 * @return array[]
	 */
	public static function get_carousel_contacts(): array {
		global $wpdb;
		$tc   = self::table_contacts();
		$rows = $wpdb->get_results(
			"SELECT contact_id, contact_nom, contact_icone, contact_type, contact_cat_id, contact_web
			 FROM   {$tc}
			 WHERE  contact_actif = 1 AND contact_carousel = 1
			 ORDER  BY contact_nom ASC",
			ARRAY_A
		);
		return $rows ?: [];
	}

	// ═══════════════════════════════════════════════════════════════════════
	// ÉCRITURE
	// ═══════════════════════════════════════════════════════════════════════

	/**
	 * Crée ou met à jour un partenaire.
	 *
	 * @param array $data  Données sanitisées (utiliser sanitize_contact() en amont).
	 * @return int|false   ID du partenaire, false sur erreur.
	 */
	public static function save_contact( array $data ): int|false {
		global $wpdb;
		$tc = self::table_contacts();

		$id = (int) ( $data['contact_id'] ?? 0 );
		unset( $data['contact_id'] );

		$formats = self::column_formats( $data );

		if ( $id > 0 ) {
			$ok = $wpdb->update( $tc, $data, [ 'contact_id' => $id ], $formats, [ '%d' ] );
			return ( false !== $ok ) ? $id : false;
		}

		$ok = $wpdb->insert( $tc, $data, $formats );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Supprime un partenaire et toutes ses liaisons articles.
	 *
	 * @return bool
	 */
	public static function delete_contact( int $id ): bool {
		global $wpdb;

		// Suppression des liaisons d'abord (intégrité référentielle)
		$wpdb->delete( self::table_liaison(), [ 'contact_id' => $id ], [ '%d' ] );

		$ok = $wpdb->delete( self::table_contacts(), [ 'contact_id' => $id ], [ '%d' ] );
		return (bool) $ok;
	}

	/**
	 * Synchronise les liaisons article ↔ partenaires en une transaction.
	 * Remplace toutes les liaisons existantes de l'article.
	 *
	 * @param int   $post_id
	 * @param int[] $contact_ids
	 */
	public static function sync_post_contacts( int $post_id, array $contact_ids ): void {
		global $wpdb;
		$tl = self::table_liaison();

		$wpdb->delete( $tl, [ 'post_id' => $post_id ], [ '%d' ] );

		if ( empty( $contact_ids ) ) return;

		$ids     = array_map( 'intval', $contact_ids );
		$ids     = array_filter( $ids );
		if ( empty( $ids ) ) return;

		$values  = implode(
			', ',
			array_map( fn( $cid ) => $wpdb->prepare( '(%d, %d)', $cid, $post_id ), $ids )
		);

		$wpdb->query( "INSERT IGNORE INTO {$tl} (contact_id, post_id) VALUES {$values}" );
	}

	// ═══════════════════════════════════════════════════════════════════════
	// IMPORT CSV
	// ═══════════════════════════════════════════════════════════════════════

	/**
	 * Importe un tableau de lignes CSV dans la table partenaires.
	 *
	 * Stratégie upsert : si (contact_nom + contact_email) existe déjà,
	 * la fiche est mise à jour ; sinon elle est créée.
	 *
	 * @param array<int, array<string, string>> $rows
	 *   Tableau de lignes CSV sous forme associative (clé = en-tête CSV).
	 *
	 * @param array<string, string> $col_map
	 *   Mapping colonnes DB → colonnes CSV.
	 *   Exemple : ['contact_nom' => 'Nom', 'contact_email' => 'E-mail', …]
	 *   'contact_nom' est obligatoire.
	 *
	 * @param int $default_cat_id  Catégorie par défaut si non mappée.
	 *
	 * @return array{
	 *   inserted: int,
	 *   updated:  int,
	 *   skipped:  int,
	 *   errors:   array<int, array{row: int, message: string}>
	 * }
	 */
	public static function import_csv(
		array $rows,
		array $col_map,
		int   $default_cat_id = 0
	): array {
		$inserted = 0;
		$updated  = 0;
		$skipped  = 0;
		$errors   = [];

		foreach ( $rows as $i => $raw ) {
			$row_num = $i + 2;   // +2 : ligne 1 = en-têtes, humainement 1-indexé

			// ── Extraction + sanitisation ──────────────────────────────
			$data = self::map_csv_row( $raw, $col_map, $default_cat_id );

			if ( '' === $data['contact_nom'] ) {
				$errors[] = [ 'row' => $row_num, 'message' => 'Nom manquant — ligne ignorée.' ];
				$skipped++;
				continue;
			}

			// ── Recherche de doublon (nom + email) ─────────────────────
			$existing_id = self::find_duplicate(
				$data['contact_nom'],
				$data['contact_email']
			);

			if ( $existing_id ) {
				$data['contact_id']      = $existing_id;
				$data['import_source']   = 'csv';
				$ok = self::save_contact( $data );

				if ( false !== $ok ) {
					$updated++;
				} else {
					$errors[] = [ 'row' => $row_num, 'message' => 'Échec de la mise à jour.' ];
				}
			} else {
				$data['import_source'] = 'csv';
				$ok = self::save_contact( $data );

				if ( false !== $ok ) {
					$inserted++;
				} else {
					$errors[] = [ 'row' => $row_num, 'message' => 'Échec de l\'insertion.' ];
				}
			}
		}

		return compact( 'inserted', 'updated', 'skipped', 'errors' );
	}

	// ═══════════════════════════════════════════════════════════════════════
	// AJAX
	// ═══════════════════════════════════════════════════════════════════════

	/**
	 * Crée ou met à jour un partenaire.
	 *
	 * POST : nonce + tous les champs contact_*
	 * Réponse success : { contact_id, contact }
	 */
	public static function ajax_save(): void {
		self::check_nonce();
		self::check_editor();

		$data = self::sanitize_contact( $_POST );
		$id   = self::save_contact( $data );

		if ( false === $id ) {
			wp_send_json_error( [ 'message' => 'Erreur lors de l\'enregistrement.' ], 500 );
		}

		wp_send_json_success( [
			'contact_id' => $id,
			'contact'    => self::get_contact( $id ),
		] );
	}

	/**
	 * Supprime un partenaire et ses liaisons.
	 *
	 * POST : nonce, contact_id
	 * Réponse success : { contact_id }
	 */
	public static function ajax_delete(): void {
		self::check_nonce();
		self::check_editor();

		$id = (int) ( $_POST['contact_id'] ?? 0 );

		if ( ! $id || ! self::get_contact( $id ) ) {
			wp_send_json_error( [ 'message' => 'Partenaire introuvable.' ], 404 );
		}

		self::delete_contact( $id );
		wp_send_json_success( [ 'contact_id' => $id ] );
	}

	/**
	 * Lie un partenaire à un article.
	 *
	 * POST : nonce, contact_id, post_id
	 */
	public static function ajax_link_post(): void {
		self::check_nonce();
		self::check_editor();

		$contact_id = (int) ( $_POST['contact_id'] ?? 0 );
		$post_id    = (int) ( $_POST['post_id']    ?? 0 );

		if ( ! $contact_id || ! $post_id ) {
			wp_send_json_error( [ 'message' => 'Paramètres manquants.' ], 400 );
		}

		global $wpdb;
		$wpdb->replace(
			self::table_liaison(),
			[ 'contact_id' => $contact_id, 'post_id' => $post_id ],
			[ '%d', '%d' ]
		);

		wp_send_json_success( [ 'contact_id' => $contact_id, 'post_id' => $post_id ] );
	}

	/**
	 * Délie un partenaire d'un article.
	 *
	 * POST : nonce, contact_id, post_id
	 */
	public static function ajax_unlink_post(): void {
		self::check_nonce();
		self::check_editor();

		$contact_id = (int) ( $_POST['contact_id'] ?? 0 );
		$post_id    = (int) ( $_POST['post_id']    ?? 0 );

		if ( ! $contact_id || ! $post_id ) {
			wp_send_json_error( [ 'message' => 'Paramètres manquants.' ], 400 );
		}

		global $wpdb;
		$wpdb->delete(
			self::table_liaison(),
			[ 'contact_id' => $contact_id, 'post_id' => $post_id ],
			[ '%d', '%d' ]
		);

		wp_send_json_success( [ 'contact_id' => $contact_id, 'post_id' => $post_id ] );
	}

	/**
	 * Retourne la liste JSON des partenaires pour les selects et l'autocomplétion.
	 *
	 * POST : nonce, cat_id (optionnel), type (optionnel)
	 */
	public static function ajax_contacts_json(): void {
		self::check_nonce();

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => 'Non connecté.' ], 403 );
		}

		$cat_id = (int)    ( $_POST['cat_id'] ?? 0 );
		$type   = sanitize_text_field( $_POST['type'] ?? '' );

		wp_send_json_success( self::get_contacts_list( $cat_id, $type ) );
	}

	/**
	 * Étape 1 import CSV : parse le fichier uploadé et retourne
	 * les en-têtes + les 5 premières lignes pour l'UI de mapping.
	 *
	 * FILE  : csv_file (multipart)
	 * POST  : nonce
	 * Réponse success : { headers: string[], preview: array[], total_rows: int }
	 */
	public static function ajax_csv_preview(): void {
		self::check_nonce();
		self::check_editor();

		if ( empty( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_send_json_error( [ 'message' => 'Aucun fichier reçu.' ], 400 );
		}

		$file = $_FILES['csv_file'];

		// Validation type MIME (CSV ou texte brut)
		$allowed_types = [ 'text/csv', 'text/plain', 'application/csv', 'application/octet-stream' ];
		if ( ! in_array( $file['type'], $allowed_types, true ) ) {
			wp_send_json_error( [ 'message' => 'Format non autorisé. Utilisez un fichier .csv.' ], 415 );
		}

		$parsed = self::parse_csv_file( $file['tmp_name'] );

		if ( is_string( $parsed ) ) {
			wp_send_json_error( [ 'message' => $parsed ], 422 );
		}

		[ $headers, $all_rows ] = $parsed;

		wp_send_json_success( [
			'headers'    => $headers,
			'preview'    => array_slice( $all_rows, 0, 5 ),
			'total_rows' => count( $all_rows ),
		] );
	}

	/**
	 * Étape 2 import CSV : exécute l'import avec le mapping validé par l'utilisateur.
	 *
	 * FILE  : csv_file (multipart, re-soumis)
	 * POST  : nonce, col_map (JSON), default_cat_id
	 * Réponse success : { inserted, updated, skipped, errors, total }
	 */
	public static function ajax_csv_import(): void {
		self::check_nonce();
		self::check_editor();

		if ( empty( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_send_json_error( [ 'message' => 'Aucun fichier reçu.' ], 400 );
		}

		$col_map_raw    = sanitize_text_field( $_POST['col_map']        ?? '' );
		$default_cat_id = (int) ( $_POST['default_cat_id'] ?? 0 );
		$col_map        = json_decode( stripslashes( $col_map_raw ), true );

		if ( ! is_array( $col_map ) || empty( $col_map['contact_nom'] ) ) {
			wp_send_json_error(
				[ 'message' => 'Mapping invalide. La colonne "Nom" est obligatoire.' ],
				422
			);
		}

		$parsed = self::parse_csv_file( $_FILES['csv_file']['tmp_name'] );

		if ( is_string( $parsed ) ) {
			wp_send_json_error( [ 'message' => $parsed ], 422 );
		}

		[ , $rows ] = $parsed;

		$result          = self::import_csv( $rows, $col_map, $default_cat_id );
		$result['total'] = $result['inserted'] + $result['updated'] + $result['skipped'] + count( $result['errors'] );

		wp_send_json_success( $result );
	}

	// ═══════════════════════════════════════════════════════════════════════
	// HELPERS PRIVÉS
	// ═══════════════════════════════════════════════════════════════════════

	/**
	 * Vérifie le nonce 'edito_nonce' depuis $_POST['nonce'].
	 * Stoppe l'exécution avec 403 si invalide.
	 */
	private static function check_nonce(): void {
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'edito_nonce' ) ) {
			wp_send_json_error( [ 'message' => 'Non autorisé.' ], 403 );
		}
	}

	/**
	 * Vérifie les droits éditeur. Stoppe avec 403 si insuffisants.
	 */
	private static function check_editor(): void {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_send_json_error( [ 'message' => 'Permissions insuffisantes.' ], 403 );
		}
	}

	/**
	 * Sanitise tous les champs d'un partenaire depuis $_POST ou un tableau brut.
	 *
	 * @param  array $raw  Données brutes (ex: $_POST)
	 * @return array       Données sanitisées prêtes pour save_contact()
	 */
	private static function sanitize_contact( array $raw ): array {
		return [
			'contact_id'       => (int) ( $raw['contact_id']       ?? 0 ),
			'contact_nom'      => sanitize_text_field( $raw['contact_nom']      ?? '' ),
			'contact_type'     => sanitize_text_field( $raw['contact_type']     ?? 'partenaire' ),
			'contact_cat_id'   => (int) ( $raw['contact_cat_id']   ?? 0 ),
			'contact_icone'    => esc_url_raw( sanitize_text_field( $raw['contact_icone']    ?? '' ) ),
			'contact_adr1'     => sanitize_text_field( $raw['contact_adr1']     ?? '' ),
			'contact_cp'       => sanitize_text_field( $raw['contact_cp']       ?? '' ),
			'contact_ville'    => sanitize_text_field( $raw['contact_ville']    ?? '' ),
			'contact_tel'      => sanitize_text_field( $raw['contact_tel']      ?? '' ),
			'contact_email'    => sanitize_email( $raw['contact_email']    ?? '' ),
			'contact_web'      => esc_url_raw( sanitize_text_field( $raw['contact_web']      ?? '' ) ),
			'contact_notes'    => sanitize_textarea_field( $raw['contact_notes']    ?? '' ),
			'contact_actif'    => (int) ( $raw['contact_actif']    ?? 1 ) > 0 ? 1 : 0,
			'contact_carousel' => (int) ( $raw['contact_carousel'] ?? 0 ) > 0 ? 1 : 0,
			'import_source'    => in_array( $raw['import_source'] ?? '', [ 'manual', 'csv' ], true )
			                          ? $raw['import_source']
			                          : 'manual',
		];
	}

	/**
	 * Applique le mapping CSV → colonnes DB sur une ligne CSV.
	 * Retourne un tableau sanitisé utilisable par save_contact().
	 *
	 * @param  array<string,string> $raw_row  Ligne CSV (clé = en-tête CSV)
	 * @param  array<string,string> $col_map  Mapping DB col → CSV col
	 * @param  int                  $default_cat_id
	 * @return array  Données sanitisées
	 */
	private static function map_csv_row( array $raw_row, array $col_map, int $default_cat_id ): array {
		$get = static function ( string $db_col ) use ( $raw_row, $col_map ): string {
			$csv_col = $col_map[ $db_col ] ?? '';
			return isset( $raw_row[ $csv_col ] ) ? trim( $raw_row[ $csv_col ] ) : '';
		};

		return self::sanitize_contact( [
			'contact_nom'      => $get( 'contact_nom' ),
			'contact_type'     => $get( 'contact_type' ) ?: 'partenaire',
			'contact_cat_id'   => (int) ( $get( 'contact_cat_id' ) ?: $default_cat_id ),
			'contact_icone'    => $get( 'contact_icone' ),
			'contact_adr1'     => $get( 'contact_adr1' ),
			'contact_cp'       => $get( 'contact_cp' ),
			'contact_ville'    => $get( 'contact_ville' ),
			'contact_tel'      => $get( 'contact_tel' ),
			'contact_email'    => $get( 'contact_email' ),
			'contact_web'      => $get( 'contact_web' ),
			'contact_notes'    => $get( 'contact_notes' ),
			'contact_actif'    => 1,
			'contact_carousel' => 0,
		] );
	}

	/**
	 * Cherche un doublon par (contact_nom + contact_email).
	 * Retourne l'ID existant ou 0.
	 */
	private static function find_duplicate( string $nom, string $email ): int {
		global $wpdb;
		$tc = self::table_contacts();

		if ( '' !== $email ) {
			// Doublon prioritaire sur email (plus fiable que le nom)
			$id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT contact_id FROM {$tc} WHERE contact_email = %s LIMIT 1",
					$email
				)
			);
			if ( $id ) return (int) $id;
		}

		// Fallback sur le nom exact
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT contact_id FROM {$tc} WHERE contact_nom = %s LIMIT 1",
				$nom
			)
		);

		return (int) ( $id ?: 0 );
	}

	/**
	 * Parse un fichier CSV en tableau associatif.
	 * Gère : encodage UTF-8, BOM, séparateurs , ; ou tabulation.
	 *
	 * @param  string $path  Chemin absolu du fichier (tmp_name ou disque)
	 * @return array{0: string[], 1: array[]}|string
	 *   Tableau [headers, rows] ou string message d'erreur.
	 */
	private static function parse_csv_file( string $path ): array|string {
		if ( ! is_readable( $path ) ) {
			return 'Fichier illisible.';
		}

		$content = file_get_contents( $path );
		if ( false === $content ) {
			return 'Impossible de lire le fichier.';
		}

		// Suppression BOM UTF-8
		$content = ltrim( $content, "\xEF\xBB\xBF" );

		// Détection encodage — conversion si nécessaire
		if ( ! mb_check_encoding( $content, 'UTF-8' ) ) {
			$content = mb_convert_encoding( $content, 'UTF-8', 'ISO-8859-1' );
		}

		// Normalisation retours chariot
		$content = str_replace( [ "\r\n", "\r" ], "\n", $content );
		$lines   = explode( "\n", trim( $content ) );

		if ( count( $lines ) < 2 ) {
			return 'Le fichier CSV doit contenir au moins une ligne d\'en-tête et une ligne de données.';
		}

		// Détection séparateur sur la première ligne
		$first_line = $lines[0];
		$sep = ',';
		foreach ( [ ';', "\t", ',' ] as $candidate ) {
			if ( substr_count( $first_line, $candidate ) > 0 ) {
				$sep = $candidate;
				break;
			}
		}

		// Parsing via str_getcsv (gère les guillemets correctement)
		$headers = array_map( 'trim', str_getcsv( $lines[0], $sep ) );

		if ( empty( $headers ) || ( count( $headers ) === 1 && '' === $headers[0] ) ) {
			return 'En-têtes CSV introuvables.';
		}

		$rows = [];
		$nb   = count( $headers );

		for ( $i = 1, $total = count( $lines ); $i < $total; $i++ ) {
			$line = trim( $lines[ $i ] );
			if ( '' === $line ) continue;

			$values = str_getcsv( $line, $sep );

			// Alignement en cas de colonnes manquantes
			while ( count( $values ) < $nb ) {
				$values[] = '';
			}

			$rows[] = array_combine( $headers, array_slice( $values, 0, $nb ) );
		}

		return [ $headers, $rows ];
	}

	/**
	 * Retourne le tableau de formats wpdb (%s/%d) selon les colonnes présentes.
	 *
	 * @param  array $data  Colonnes à insérer/modifier (sans contact_id)
	 * @return string[]
	 */
	private static function column_formats( array $data ): array {
		$int_cols = [ 'contact_cat_id', 'contact_actif', 'contact_carousel' ];
		$formats  = [];

		foreach ( array_keys( $data ) as $col ) {
			$formats[] = in_array( $col, $int_cols, true ) ? '%d' : '%s';
		}

		return $formats;
	}
}
