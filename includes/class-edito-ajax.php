<?php
/**
 * Edito_Ajax – Tous les handlers AJAX du plugin
 */
class Edito_Ajax {

    private static ?Edito_Ajax $instance = null;

    public static function get_instance(): Edito_Ajax {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $actions = [
            'edito_save_article',
            'edito_upload_photo',
            'edito_delete_photo',
            'edito_change_status',
            'edito_delete_article',
            'edito_get_article',
        ];
        foreach ( $actions as $action ) {
            add_action( "wp_ajax_{$action}", [ $this, str_replace( 'edito_', 'handle_', $action ) ] );
        }
    }

    /* -----------------------------------------------------------------------
     * Sauvegarde article (brouillon ou soumission)
     * --------------------------------------------------------------------- */
    public function handle_save_article(): void {
        $this->verify_nonce( 'ce_editor_nonce' );

        $user = wp_get_current_user();
        if ( ! $user->ID ) wp_send_json_error( [ 'message' => 'Non connecté.' ] );

        $data = [
            'post_id'   => (int) ( $_POST['post_id'] ?? 0 ),
            'title'     => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
            'content'   => wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) ),

            // ── MODIFIÉ : multi-catégories ────────────────────────────────
            // jQuery envoie cat_ids[]=1&cat_ids[]=2 → PHP reçoit un tableau.
            // Le fallback sur 'category' (ancienne clé scalaire) est conservé
            // pour ne pas casser d'éventuels appels externes.
            'cat_ids'   => isset( $_POST['cat_ids'] ) && is_array( $_POST['cat_ids'] )
                ? array_map( 'absint', $_POST['cat_ids'] )
                : [],
            'category'  => (int) ( $_POST['category'] ?? 0 ),

            // ── AJOUTÉ : date de publication ──────────────────────────────
            // Format attendu depuis le champ datetime-local : 'YYYY-MM-DD HH:MM'
            'post_date' => sanitize_text_field( wp_unslash( $_POST['post_date'] ?? '' ) ),

            'status'    => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'draft' ) ),
            'photo_ids' => isset( $_POST['photo_ids'] ) && is_array( $_POST['photo_ids'] )
                ? array_map( 'intval', $_POST['photo_ids'] )
                : [],
        ];

        if ( empty( $data['title'] ) ) {
            wp_send_json_error( [ 'message' => 'Le titre est obligatoire.' ] );
        }

        $result = Edito_Auteur::save_article( $data, $user );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [
            'post_id' => $result,
            'message' => 'pending' === $data['status']
                ? 'Article soumis pour validation.'
                : 'Article enregistré en brouillon.',
        ] );
    }

    /* -----------------------------------------------------------------------
     * Upload d'une photo
     * --------------------------------------------------------------------- */
    public function handle_upload_photo(): void {
        $this->verify_nonce( 'ce_editor_nonce' );

        $user = wp_get_current_user();
        if ( ! $user->ID ) wp_send_json_error( [ 'message' => 'Non connecté.' ] );

        if ( empty( $_FILES['photo'] ) ) {
            wp_send_json_error( [ 'message' => 'Aucun fichier reçu.' ] );
        }

        $allowed_mimes = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];
        $file_type = $_FILES['photo']['type'] ?? '';
        if ( ! in_array( $file_type, $allowed_mimes, true ) ) {
            wp_send_json_error( [ 'message' => 'Type de fichier non autorisé. Utilisez JPG, PNG, GIF ou WebP.' ] );
        }

        if ( $_FILES['photo']['size'] > 8 * 1024 * 1024 ) {
            wp_send_json_error( [ 'message' => 'Fichier trop volumineux (max 8 Mo).' ] );
        }

        if ( ! function_exists( 'media_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $attachment_id = media_handle_upload( 'photo', 0 );

        if ( is_wp_error( $attachment_id ) ) {
            wp_send_json_error( [ 'message' => $attachment_id->get_error_message() ] );
        }

        wp_update_post( [ 'ID' => $attachment_id, 'post_author' => $user->ID ] );

        $thumb = wp_get_attachment_image_url( $attachment_id, 'ce-thumb' );
        $full  = wp_get_attachment_image_url( $attachment_id, 'ce-gallery' );

        wp_send_json_success( [
            'attachment_id' => $attachment_id,
            'thumb_url'     => $thumb ?: $full,
            'full_url'      => $full,
            'title'         => get_the_title( $attachment_id ),
        ] );
    }

    /* -----------------------------------------------------------------------
     * Suppression d'une photo de la galerie (pas de la médiathèque)
     * --------------------------------------------------------------------- */
    public function handle_delete_photo(): void {
        $this->verify_nonce( 'ce_editor_nonce' );
        wp_send_json_success();
    }

    /* -----------------------------------------------------------------------
     * Changement de statut par un éditeur (dashboard)
     * --------------------------------------------------------------------- */
    public function handle_change_status(): void {
        $this->verify_nonce( 'ce_dashboard_nonce' );

        $user = wp_get_current_user();
        if ( ! current_user_can( 'edit_others_posts' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Accès refusé.' ] );
        }

        $post_id    = (int) ( $_POST['post_id'] ?? 0 );
        $new_status = sanitize_text_field( wp_unslash( $_POST['new_status'] ?? '' ) );

        if ( ! $post_id ) wp_send_json_error( [ 'message' => 'ID article manquant.' ] );

        $result = Edito_Auteur::change_status( $post_id, $new_status, $user );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [
            'message'    => 'Statut mis à jour : ' . Edito_Core::status_label( $new_status ),
            'new_status' => $new_status,
            'new_label'  => Edito_Core::status_label( $new_status ),
        ] );
    }

    /* -----------------------------------------------------------------------
     * Suppression d'un article (par l'auteur ou l'éditeur)
     * --------------------------------------------------------------------- */
    public function handle_delete_article(): void {
        if (
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'ce_editor_nonce' ) &&
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'ce_dashboard_nonce' )
        ) {
            wp_send_json_error( [ 'message' => 'Nonce invalide.' ] );
        }

        $post_id = (int) ( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) wp_send_json_error( [ 'message' => 'ID article manquant.' ] );

        $post = get_post( $post_id );
        if ( ! $post ) wp_send_json_error( [ 'message' => 'Article introuvable.' ] );

        $user = wp_get_current_user();
        if (
            (int) $post->post_author !== $user->ID &&
            ! current_user_can( 'edit_others_posts' )
        ) {
            wp_send_json_error( [ 'message' => 'Accès refusé.' ] );
        }

        wp_trash_post( $post_id );
        wp_send_json_success( [ 'message' => 'Article déplacé dans la corbeille.' ] );
    }

    /* -----------------------------------------------------------------------
     * Récupère les données d'un article pour pré-remplir le formulaire
     * --------------------------------------------------------------------- */
    public function handle_get_article(): void {
        $this->verify_nonce( 'ce_editor_nonce' );

        $post_id = (int) ( $_GET['post_id'] ?? 0 );
        if ( ! $post_id ) wp_send_json_error( [ 'message' => 'ID manquant.' ] );

        $post = get_post( $post_id );
        if ( ! $post ) wp_send_json_error( [ 'message' => 'Article introuvable.' ] );

        $user = wp_get_current_user();
        if (
            (int) $post->post_author !== $user->ID &&
            ! current_user_can( 'edit_others_posts' )
        ) {
            wp_send_json_error( [ 'message' => 'Accès refusé.' ] );
        }

        $cats    = wp_get_post_categories( $post_id, [ 'fields' => 'ids' ] );
        $gallery = Edito_Auteur::get_gallery( $post_id );

        wp_send_json_success( [
            'post_id'  => $post_id,
            'title'    => $post->post_title,
            'content'  => $post->post_content,
            'status'   => $post->post_status,
            'cat_ids'  => $cats,                              // tableau d'IDs (nouveau)
            'category' => ! empty( $cats ) ? $cats[0] : 0,   // premier ID (legacy)
            'gallery'  => $gallery,
        ] );
    }

    /* -----------------------------------------------------------------------
     * Helper : vérifie le nonce et sort en erreur si invalide
     * --------------------------------------------------------------------- */
    private function verify_nonce( string $action ): void {
        $nonce = sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, $action ) ) {
            wp_send_json_error( [ 'message' => 'Nonce invalide. Rechargez la page.' ], 403 );
        }
    }
}
