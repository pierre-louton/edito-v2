<?php
/**
 * Edito_Core – Classe principale du plugin Edito
 *
 * Gère : activation/désactivation, statuts personnalisés, masquage de la
 * barre d'administration, chargement des templates et assets front-end.
 */
class Edito_Core {

    /* -----------------------------------------------------------------------
     * Singleton
     * --------------------------------------------------------------------- */
    private static ? Edito_Core $instance = null;

    public static function get_instance(): Edito_Core {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init',               [ $this, 'register_post_status' ] );
        add_filter( 'show_admin_bar',     [ $this, 'hide_admin_bar' ] );
        add_filter( 'template_include',   [ $this, 'load_templates' ] );
        add_action( 'template_redirect',  [ $this, 'disable_litespeed_on_edito_pages' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ], 5 );
        add_action( 'wp_head',            [ $this, 'prevent_admin_redirect' ], 1 );

        Edito_Auth::get_instance();
        Edito_Auteur::get_instance();
        Edito_Ajax::get_instance();
        Edito_Notifications::get_instance();
        Edito_Admin::get_instance();
    }

    /* -----------------------------------------------------------------------
     * Activation / Désactivation
     * --------------------------------------------------------------------- */
    public static function activate(): void {
        self::create_pages();
        self::ensure_categories();
        add_image_size( 'ce-thumb',   300, 200, true );
        add_image_size( 'ce-gallery', 800, 600, false );
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    /* -----------------------------------------------------------------------
     * Création des pages WP
     * --------------------------------------------------------------------- */
    private static function create_pages(): void {
        $pages = [
            'login'     => [ 'title' => 'Connexion Edito',  'slug' => 'edito-login'     ],
            'dashboard' => [ 'title' => 'Tableau de bord',  'slug' => 'edito-tableau'   ],
            'editor'    => [ 'title' => 'Espace auteur',     'slug' => 'edito-auteur'   ],
            'contacts'  => [ 'title' => 'Contacts',          'slug' => 'edito-contacts' ], // ← NOUVEAU
    ];
        foreach ( $pages as $type => $data ) {
            $found = get_posts( [
                'post_type'      => 'page',
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'meta_key'       => '_edito_page_type',
                'meta_value'     => $type,
            ] );

            if ( ! empty( $found ) ) {
                update_option( "ce_page_{$type}", $found[0]->ID );
                continue;
            }

            $page_id = wp_insert_post( [
                'post_title'   => $data['title'],
                'post_name'    => $data['slug'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => '',
            ] );

            if ( $page_id && ! is_wp_error( $page_id ) ) {
                update_post_meta( $page_id, '_edito_page_type', $type );
                update_option( "ce_page_{$type}", $page_id );
            }
        }
    }

    /* -----------------------------------------------------------------------
     * Catégories obligatoires
     * --------------------------------------------------------------------- */
    private static function ensure_categories(): void {
        foreach ( self::categories_config() as $cat ) {
            if ( ! term_exists( $cat['slug'], 'category' ) ) {
                wp_insert_term( $cat['name'], 'category', [ 'slug' => $cat['slug'] ] );
            }
        }
    }

    /* -----------------------------------------------------------------------
     * Statut personnalisé "ce_validated"
     * --------------------------------------------------------------------- */
    public function register_post_status(): void {
        register_post_status( 'ce_validated', [
            'label'                     => _x( 'Validé', 'post status', 'edito' ),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Validé <span class="count">(%s)</span>',
                'Validés <span class="count">(%s)</span>',
                'edito'
            ),
        ] );
    }

    /* -----------------------------------------------------------------------
     * Masquage de la barre d'admin pour Éditeurs et Auteurs
     * --------------------------------------------------------------------- */
    public function hide_admin_bar( bool $show ): bool {
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            if ( array_intersect( [ 'editor', 'author' ], (array) $user->roles ) ) {
                return false;
            }
        }
        return $show;
    }

    /* -----------------------------------------------------------------------
     * Redirection depuis wp-admin vers le dashboard éditorial
     * --------------------------------------------------------------------- */
    public function prevent_admin_redirect(): void {
        if ( ! is_user_logged_in() ) return;
        $user = wp_get_current_user();
        if ( array_intersect( [ 'editor', 'author' ], (array) $user->roles ) ) {
            add_action( 'admin_init', function () {
                if ( ! wp_doing_ajax() && ! current_user_can( 'manage_options' ) ) {
                    wp_redirect( self::page_url( 'dashboard' ) );
                    exit;
                }
            } );
        }
    }

    /* -----------------------------------------------------------------------
     * Désactive le cache et l'optimisation LiteSpeed sur les pages Edito
     *
     * LiteSpeed Cache combine les CSS/JS des pages en bundles persistants.
     * Si edito-style.css est ajouté après la création du bundle, ou si le
     * fichier n'est pas accessible lors de la phase de combinaison, LiteSpeed
     * le supprime silencieusement du HTML.  La solution la plus fiable est
     * de désactiver complètement le cache et l'optimisation sur ces pages.
     * --------------------------------------------------------------------- */
    public function disable_litespeed_on_edito_pages(): void {
        $page_type = self::get_current_edito_page_type();
        if ( ! $page_type ) return;

        // --- LiteSpeed Cache -------------------------------------------
        // Empêche la mise en cache de la page (HTML) par LiteSpeed.
        do_action( 'litespeed_control_set_nocache', 'edito_page_' . $page_type );

        // Empêche l'optimisation CSS/JS (combinaison, minification) pour
        // cette requête.  Sans ça, edito-style.css et edito-auteur.js
        // peuvent être absorbés dans un bundle qui ne les contient pas.
        do_action( 'litespeed_optm_set_no', 'edito_optm_' . $page_type );

        // Filtres complémentaires pour exclure les handles Edito des
        // processus de combinaison/minification.
        add_filter( 'litespeed_optm_css_excludes',    [ $this, 'litespeed_exclude_edito_assets' ] );
        add_filter( 'litespeed_optm_js_excludes',     [ $this, 'litespeed_exclude_edito_assets' ] );
        add_filter( 'litespeed_optm_ccss_separate_uri', function( $uris ) {
            $uris[] = 'edito-';
            return $uris;
        } );
    }

    /** Callback partagé pour exclure les assets Edito des pipelines LiteSpeed */
    public function litespeed_exclude_edito_assets( array $list ): array {
        $list[] = 'edito-style';
        $list[] = 'edito-contacts'; // ← NOUVEAU
        $list[] = 'edito-auteur';
        $list[] = 'edito-tableau';
        $list[] = 'ce-fonts';
        return $list;
    }
    /* -----------------------------------------------------------------------
     * Chargement des templates personnalisés
     * --------------------------------------------------------------------- */
    public function load_templates( string $template ): string {
        global $post;
        if ( ! is_page() || ! $post ) return $template;

        $page_type = self::get_current_edito_page_type();
        if ( ! $page_type ) return $template;

        // Rediriger vers login si non connecté
        if ( ! is_user_logged_in() && 'login' !== $page_type ) {
            wp_redirect( self::page_url( 'login' ) );
            exit;
        }

        // Vérifier les droits
        if ( is_user_logged_in() && 'login' !== $page_type ) {
            $user = wp_get_current_user();
            if (
                ! in_array( 'editor', (array) $user->roles, true ) &&
                ! in_array( 'author', (array) $user->roles, true ) &&
                ! current_user_can( 'manage_options' )
            ) {
                wp_redirect( home_url() );
                exit;
            }

            if ( 'dashboard' === $page_type && ! current_user_can( 'edit_others_posts' ) ) {
                wp_redirect( self::page_url( 'editor' ) );
                exit;
            }
        }

        $tpl = EDITO_PATH . "templates/{$page_type}.php";
        return file_exists( $tpl ) ? $tpl : $template;
    }

    /* -----------------------------------------------------------------------
     * Assets front-end
     *
     * Priorité 5 (avant la plupart des plugins) pour que LiteSpeed voie
     * nos assets tôt dans la file d'attente.
     * --------------------------------------------------------------------- */
    public function enqueue_assets(): void {
        $page_type = self::get_current_edito_page_type();
        if ( ! $page_type ) return;

        // ---- CSS commun à toutes les pages Edito -------------------------
        wp_deregister_style( 'edito-style' );

        wp_enqueue_style(
            'ce-fonts',
            'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap',
            [],
            null
        );

        wp_enqueue_style(
            'edito-style',
            EDITO_URL . 'assets/css/edito-style.css',
            [ 'ce-fonts' ],
            EDITO_VERSION
        );
		wp_enqueue_style( 'edito-categories', EDITO_ASSETS_URL . 'css/categories-style.css', ['edito-style'], EDITO_VERSION );
		wp_enqueue_style( 'edito-contacts',   EDITO_ASSETS_URL . 'css/contacts-style.css',   ['edito-style'], EDITO_VERSION );
		wp_enqueue_style( 'edito-dashboard',  EDITO_ASSETS_URL . 'css/dashboard-style.css',  ['edito-style'], EDITO_VERSION );

        // Marquer le style comme "do not optimize" pour LiteSpeed
        wp_style_add_data( 'edito-style', 'litespeed-noptimize', true );

        // ---- JS spécifique à l'éditeur (editor.php) ----------------------
        if ( 'editor' === $page_type ) {
            wp_enqueue_media();

            wp_enqueue_script(
                'edito-auteur',
                EDITO_URL . 'assets/js/edito-auteur.js',
                [ 'jquery' ],
                EDITO_VERSION,
                true
            );

            wp_script_add_data( 'edito-auteur', 'litespeed-noptimize', true );

            wp_localize_script( 'edito-auteur', 'Edito_Auteur', [
                'ajax_url'      => admin_url( 'admin-ajax.php' ),
                'nonce'         => wp_create_nonce( 'ce_editor_nonce' ),
                'edito_nonce'   => wp_create_nonce( 'edito_nonce' ),  // ← AJOUTER
                'dashboard_url' => self::page_url( 'dashboard' ),
                'editor_url'    => self::page_url( 'editor' ),
                'max_photos'    => 5,
            ] );
        }

        // ---- JS spécifique au tableau de bord (dashboard.php) ------------
        if ( 'dashboard' === $page_type ) {
            wp_enqueue_script(
                'edito-tableau',
                EDITO_URL . 'assets/js/edito-tableau.js',
                [ 'jquery' ],
                EDITO_VERSION,
                true
            );

            wp_script_add_data( 'edito-tableau', 'litespeed-noptimize', true );

            wp_localize_script( 'edito-tableau', 'Edito_Tableau', [
                'ajax_url'   => admin_url( 'admin-ajax.php' ),
                'nonce'      => wp_create_nonce( 'ce_dashboard_nonce' ),
                'editor_url' => self::page_url( 'editor' ),
            ] );
        }
        
        // ---- CSS + JS spécifiques à la page contacts -------------------------
        if ( 'contacts' === $page_type ) {
            wp_enqueue_media(); // médiathèque WP (upload icône contact)
        
            wp_enqueue_style(
                'edito-contacts',
                EDITO_URL . 'assets/css/contacts-style.css',
                [ 'edito-style' ],
                EDITO_VERSION
            );
            wp_style_add_data( 'edito-contacts', 'litespeed-noptimize', true );
        
            // Expose l'objet global `edito` utilisé par le JS inline de contacts.php
            // (ajax_url + nonce) — même nonce que Edito_DB::check_nonce()
            wp_register_script( 'edito-contacts-js', false, [], EDITO_VERSION, true );
            wp_enqueue_script( 'edito-contacts-js' );
            wp_localize_script( 'edito-contacts-js', 'edito', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'edito_nonce' ),
            ] );
        }
    }

    /* -----------------------------------------------------------------------
     * Helpers publics
     * --------------------------------------------------------------------- */

    /**
     * Retourne le type de page Edito en cours ('login', 'dashboard', 'editor')
     * ou une chaîne vide si la page courante n'est pas une page Edito.
     *
     * Centralisé ici pour éviter de dupliquer la logique dans chaque méthode.
     */
    public static function get_current_edito_page_type(): string {
        $slug_map = [
            'edito-login'     => 'login',
            'edito-tableau'   => 'dashboard',
            'edito-auteur'    => 'editor',
            'edito-contacts'  => 'contacts', // ← NOUVEAU
        ];
        // 1. Via le global \$post (chemin normal WP)
        global $post;
        if ( is_page() && $post ) {
            $type = get_post_meta( $post->ID, '_edito_page_type', true );
            if ( ! $type ) {
                $type = $slug_map[ $post->post_name ] ?? '';
            }
            if ( $type ) return $type;
        }

        // 2. Fallback REQUEST_URI — fiable quand WpTiger/o2switch ou Elementor
        //    réinitialisent le query global avant que wp_enqueue_scripts ne tire.
        $path  = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
        $first = explode( '/', $path )[0];
        return $slug_map[ $first ] ?? '';
    }

    public static function page_url( string $type ): string {
        $page_id = get_option( "ce_page_{$type}" );
        return $page_id ? get_permalink( $page_id ) : home_url( "/editorial-{$type}/" );
    }

    public static function categories_config(): array {
        return [
            [ 'name' => 'Actualités',                           'slug' => 'actualites'           ],
            [ 'name' => "Assistance Maîtrise d'Ouvrage",        'slug' => 'amo'                  ],
            [ 'name' => 'Contractant général',                  'slug' => 'contractant-general'  ],
            [ 'name' => "Économie de la construction",          'slug' => 'economie-construction'],
            [ 'name' => "Maîtrise d'Œuvre",                     'slug' => 'moe'                  ],
            [ 'name' => 'Ordonnancement Pilotage Coordination', 'slug' => 'opc'                  ],
            [ 'name' => 'Non catégorisé',                       'slug' => 'uncategorized'        ],
        ];
    }

    /**
     * Traduit un statut WP en libellé français.
     */
    public static function status_label( string $status ): string {
        return [
            'draft'        => 'Brouillon',
            'pending'      => 'En attente de validation',
            'ce_validated' => 'Validé',
            'publish'      => 'Publié',
            'trash'        => 'Supprimé',
        ][ $status ] ?? ucfirst( $status );
    }

    /**
     * Retourne la CLASSE MODIFICATRICE du badge de statut.
     *
     * Correction : les valeurs retournées correspondent désormais exactement
     * aux sélecteurs CSS définis dans edito-style.css.
     * Utilisation dans les templates :
     *   <span class="edito-badge <?php echo Edito_Core::status_badge_class($status); ?>">
     * Produit par exemple : class="edito-badge edito-badge--publish"
     */
    public static function status_badge_class( string $status ): string {
        return [
            'draft'        => 'edito-badge--draft',
            'pending'      => 'edito-badge--pending',
            'ce_validated' => 'edito-badge--validated',
            'publish'      => 'edito-badge--publish',
            'trash'        => 'edito-badge--trash',
        ][ $status ] ?? 'edito-badge--draft';
    }
}
