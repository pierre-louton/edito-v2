<?php
/**
 * Edito_Auteur – Logique métier de l'éditeur d'articles
 *
 * Fournit des méthodes statiques utilisées par les handlers AJAX et les templates.
 */
class Edito_Auteur {

    private static ?Edito_Auteur $instance = null;

    public static function get_instance(): Edito_Auteur {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Ajouter les tailles d'images si l'activation n'a pas encore été faite
        add_action( 'init', [ $this, 'add_image_sizes' ] );
    }

    public function add_image_sizes(): void {
        add_image_size( 'ce-thumb',   300, 200, true );
        add_image_size( 'ce-gallery', 800, 600, false );
    }

    /* -----------------------------------------------------------------------
     * Sauvegarde d'un article (création ou mise à jour)
     *
     * @param array $data {
     *   'post_id'    => int|0      (0 = nouvel article)
     *   'title'      => string
     *   'content'    => string
     *   'cat_ids'    => int[]      (IDs de catégories, 1 à 3 — nouveau)
     *   'category'   => int        (ID catégorie unique — fallback legacy)
     *   'post_date'  => string     Date locale au format 'Y-m-d H:i' (optionnel)
     *   'status'     => string     'draft'|'pending'|'publish'
     *   'photo_ids'  => int[]      (IDs d'attachements WP)
     * }
     * @param WP_User $user Utilisateur courant
     * @return int|WP_Error ID du post
     * --------------------------------------------------------------------- */
    public static function save_article( array $data, WP_User $user ) {
        $post_id = (int) ( $data['post_id'] ?? 0 );
        $allowed_statuses = current_user_can( 'edit_others_posts' )
            ? [ 'draft', 'pending', 'publish' ]
            : [ 'draft', 'pending' ];
        $status = in_array( $data['status'] ?? 'draft', $allowed_statuses, true )
            ? $data['status']
            : 'draft';

        $post_data = [
            'post_title'   => sanitize_text_field( $data['title'] ?? '' ),
            'post_content' => wp_kses_post( $data['content'] ?? '' ),
            'post_status'  => $status,
            'post_type'    => 'post',
            'post_author'  => $user->ID,
        ];

        /*
         * DATE DE CRÉATION
         *
         * Le champ ce_post_date arrive en format 'Y-m-d H:i' (fuseau local du
         * site, car le template convertit le datetime-local HTML côté JS avant
         * d'envoyer, ou le PHP lit directement le format local).
         *
         * On convertit vers UTC pour post_date_gmt, et on conserve la date
         * locale pour post_date.
         */
        $raw_date = trim( $data['post_date'] ?? '' );
        if ( $raw_date ) {
            $tz = wp_timezone();
            try {
                // Accepte les deux formats : 'Y-m-d H:i' et 'Y-m-dTH:i'
                $dt = new DateTimeImmutable(
                    str_replace( 'T', ' ', $raw_date ) . ':00',
                    $tz
                );
                $post_data['post_date']     = $dt->format( 'Y-m-d H:i:s' );
                $post_data['post_date_gmt'] = $dt
                    ->setTimezone( new DateTimeZone( 'UTC' ) )
                    ->format( 'Y-m-d H:i:s' );
                // Sans edit_date = true, wp_update_post ignore post_date_gmt
                $post_data['edit_date'] = true;
            } catch ( Exception $e ) {
                // Date invalide → on laisse WordPress utiliser la date courante
            }
        }

        if ( $post_id > 0 ) {
            // Vérifier que l'auteur est bien le propriétaire (ou éditeur)
            $existing = get_post( $post_id );
            if ( ! $existing ) return new WP_Error( 'not_found', 'Article introuvable.' );

            if (
                (int) $existing->post_author !== $user->ID &&
                ! current_user_can( 'edit_others_posts' )
            ) {
                return new WP_Error( 'forbidden', 'Accès refusé.' );
            }

            // Ne peut pas repasser en brouillon un article publié
            if ( 'publish' === $existing->post_status && ! current_user_can( 'edit_others_posts' ) ) {
                return new WP_Error( 'forbidden', 'Cet article est publié et ne peut plus être modifié.' );
            }
            $post_data['ID'] = $post_id;
            $result = wp_update_post( $post_data, true );
        } else {
            $result = wp_insert_post( $post_data, true );
        }

        if ( is_wp_error( $result ) ) return $result;
        $post_id = $result;

        // Marquer ce post comme un article créé via Edito.
        update_post_meta( $post_id, '_edito_post', '1' );

        /*
         * CATÉGORIES MULTI-SÉLECTION
         *
         * Priorité :
         *   1. $data['cat_ids']  → tableau d'IDs envoyé par les nouvelles checkboxes
         *   2. $data['category'] → ID unique (ancienne sélection, fallback)
         *
         * wp_set_post_categories() remplace toutes les catégories existantes.
         */
        $cat_ids = [];

        if ( ! empty( $data['cat_ids'] ) && is_array( $data['cat_ids'] ) ) {
            // Nouveau format : tableau d'IDs (envoyé comme ce_cat_ids[] ou parsé depuis la chaîne CSV)
            $cat_ids = array_values(
                array_filter(
                    array_slice(
                        array_map( 'absint', $data['cat_ids'] ),
                        0, 3   // limite à 3 catégories max
                    )
                )
            );
        } elseif ( ! empty( $data['category'] ) ) {
            // Fallback : ancienne clé scalaire
            $cat_ids = [ absint( $data['category'] ) ];
        }

        if ( ! empty( $cat_ids ) ) {
            wp_set_post_categories( $post_id, $cat_ids );
        }

        // Galerie photos
        if ( isset( $data['photo_ids'] ) && is_array( $data['photo_ids'] ) ) {
            $ids = array_slice(
                array_map( 'intval', $data['photo_ids'] ),
                0,
                5
            );
            // Filtrer les IDs qui appartiennent bien à l'utilisateur (ou admin/éditeur)
            $clean_ids = [];
            foreach ( $ids as $att_id ) {
                $att = get_post( $att_id );
                if ( $att && 'attachment' === $att->post_type ) {
                    if (
                        (int) $att->post_author === $user->ID ||
                        current_user_can( 'edit_others_posts' )
                    ) {
                        $clean_ids[] = $att_id;
                        // Attacher l'image au post
                        wp_update_post( [ 'ID' => $att_id, 'post_parent' => $post_id ] );
                    }
                }
            }
            update_post_meta( $post_id, '_edito_gallery', $clean_ids );

            // Définir la première image comme image à la une
            if ( ! empty( $clean_ids ) ) {
                set_post_thumbnail( $post_id, $clean_ids[0] );
            } else {
                delete_post_thumbnail( $post_id );
            }
        }

        return $post_id;
    }

    /* -----------------------------------------------------------------------
     * Changement de statut par un éditeur
     * --------------------------------------------------------------------- */
    public static function change_status( int $post_id, string $new_status, WP_User $user ): bool|WP_Error {
        if ( ! current_user_can( 'edit_others_posts' ) && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'forbidden', 'Accès refusé.' );
        }

        $allowed = [ 'publish', 'draft', 'trash' ];
        if ( ! in_array( $new_status, $allowed, true ) ) {
            return new WP_Error( 'invalid_status', 'Statut invalide.' );
        }

        $post = get_post( $post_id );
        if ( ! $post ) return new WP_Error( 'not_found', 'Article introuvable.' );

        if ( 'trash' === $new_status ) {
            wp_trash_post( $post_id );
        } else {
            wp_update_post( [ 'ID' => $post_id, 'post_status' => $new_status ] );
        }

        // La notification est gérée par Edito_Notifications via transition_post_status
        return true;
    }

    /* -----------------------------------------------------------------------
     * Récupère les articles visibles par l'utilisateur courant
     * --------------------------------------------------------------------- */
    public static function get_articles( WP_User $user, array $args = [] ): array {
        $defaults = [
            'post_type'      => 'post',
            'posts_per_page' => 20,
            'paged'          => 1,
        ];

        // Éditeurs voient tout, auteurs voient les leurs
        if ( current_user_can( 'edit_others_posts' ) ) {
            $defaults['post_status'] = [ 'pending', 'draft', 'publish', 'ce_validated' ];
        } else {
            $defaults['post_status'] = [ 'draft', 'pending', 'publish', 'ce_validated' ];
            $defaults['author']      = $user->ID;
        }

        return get_posts( array_merge( $defaults, $args ) );
    }

    /* -----------------------------------------------------------------------
     * Récupère la galerie d'un article
     *
     * Ordre de priorité :
     *   1. Meta _edito_gallery (IDs stockés par le plugin)
     *   2. Image à la une (thumbnail WordPress)
     *   3. Médias attachés au post (images uploadées via WP classique)
     * --------------------------------------------------------------------- */
    public static function get_gallery( int $post_id ): array {

        /* ── 1. Meta plugin ── */
        $ids = get_post_meta( $post_id, '_edito_gallery', true );

        /* ── 2. Fallback image à la une ── */
        if ( empty( $ids ) || ! is_array( $ids ) ) {
            $thumb_id = get_post_thumbnail_id( $post_id );
            $ids      = $thumb_id ? [ (int) $thumb_id ] : [];
        }

        /* ── 3. Fallback médias attachés ── */
        if ( empty( $ids ) ) {
            $attached = get_posts( [
                'post_type'      => 'attachment',
                'post_parent'    => $post_id,
                'post_mime_type' => 'image',
                'posts_per_page' => 5,
                'post_status'    => 'inherit',
                'fields'         => 'ids',
                'orderby'        => 'menu_order',
                'order'          => 'ASC',
            ] );
            $ids = $attached ?: [];
        }

        if ( empty( $ids ) ) return [];

        $images = [];
        foreach ( $ids as $att_id ) {
            $att_id = (int) $att_id;
            if ( ! $att_id ) continue;

            $att = get_post( $att_id );
            if ( ! $att || 'attachment' !== $att->post_type ) continue;

            $full  = wp_get_attachment_image_url( $att_id, 'ce-gallery' )
                  ?: wp_get_attachment_image_url( $att_id, 'large' )
                  ?: wp_get_attachment_image_url( $att_id, 'full' );

            $thumb = wp_get_attachment_image_url( $att_id, 'ce-thumb' )
                  ?: wp_get_attachment_image_url( $att_id, 'medium' )
                  ?: $full;

            if ( $full ) {
                $images[] = [
                    'id'    => $att_id,
                    'full'  => $full,
                    'thumb' => $thumb ?: $full,
                    'title' => esc_attr( $att->post_title ?: basename( $full ) ),
                ];
            }
        }

        return array_slice( $images, 0, 5 );
    }
}

// Alias de compatibilité — les templates appellent Edito_Editor,
// le reste du plugin utilise Edito_Auteur. Les deux sont désormais valides.
if ( ! class_exists( 'Edito_Editor' ) ) {
    class_alias( 'Edito_Auteur', 'Edito_Editor' );
}
