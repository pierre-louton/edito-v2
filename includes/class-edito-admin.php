<?php
/**
 * Edito_Admin – Page de réglages dans l'administration WordPress
 *
 * Fonctionnalités :
 * - Affichage des URLs des pages créées par le plugin
 * - Upload d'une icône par catégorie
 * - Lien de configuration du login personnalisé
 */
class Edito_Admin {

    private static ?Edito_Admin $instance = null;

    public static function get_instance(): Edito_Admin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu',                  [ $this, 'add_admin_page' ] );
        add_action( 'admin_enqueue_scripts',       [ $this, 'enqueue_admin_assets' ] );
        add_action( 'admin_post_ce_save_settings', [ $this, 'handle_save_settings' ] );
        add_action( 'admin_post_ce_upload_icon',   [ $this, 'handle_upload_icon' ] );
        // Empêche l'accès direct à wp-admin pour les éditeurs/auteurs
        add_action( 'admin_init', [ $this, 'redirect_restricted_users' ] );
    }

    /* -----------------------------------------------------------------------
     * Redirection des rôles restreints depuis wp-admin
     * --------------------------------------------------------------------- */
    public function redirect_restricted_users(): void {
        if ( wp_doing_ajax() || ! is_user_logged_in() ) return;
        $user = wp_get_current_user();
        if (
            ! in_array( 'editor', (array) $user->roles, true ) &&
            ! in_array( 'author', (array) $user->roles, true )
        ) {
            return;
        }
        if ( current_user_can( 'manage_options' ) ) return;

        $screen = get_current_screen();
        // Autoriser la page du plugin (n'existe pas encore lors du check, utiliser $_GET)
        if ( isset( $_GET['page'] ) && 'edito' === $_GET['page'] ) return;

        wp_redirect( Edito_Core::page_url( 'dashboard' ) );
        exit;
    }

    /* -----------------------------------------------------------------------
     * Menu d'administration
     * --------------------------------------------------------------------- */
    public function add_admin_page(): void {
        add_menu_page(
            'Edito',
            'Edito',
            'manage_options',
            'edito',
            [ $this, 'render_admin_page' ],
            'dashicons-edit-page',
            30
        );
    }

    public function enqueue_admin_assets( string $hook ): void {
        if ( 'toplevel_page_edito' !== $hook ) return;
        wp_enqueue_media();
        wp_enqueue_style(
            'ce-admin-style',
            EDITO_URL . 'assets/css/edito-style.css',
            [],
            EDITO_VERSION
        );
    }

    /* -----------------------------------------------------------------------
     * Rendu de la page d'administration
     * --------------------------------------------------------------------- */
    public function render_admin_page(): void {
        $categories   = Edito_Core::categories_config();
        $notice       = get_transient( 'ce_admin_notice' );
        $login_url    = Edito_Core::page_url( 'login' );
        $dashboard_url = Edito_Core::page_url( 'dashboard' );
        $editor_url   = Edito_Core::page_url( 'editor' );
        ?>
        <div class="wrap ce-admin-wrap">
            <h1 style="font-family:'Georgia',serif;color:#1a1a2e;display:flex;align-items:center;gap:10px;">
                <span style="color:#c9a96e;">✦</span> Edito — Réglages
            </h1>

            <?php if ( $notice ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
                <?php delete_transient( 'ce_admin_notice' ); ?>
            <?php endif; ?>

            <!-- Pages créées -->
            <div class="edito-admin-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px 28px;margin:20px 0;max-width:800px;">
                <h2 style="margin-top:0;font-size:16px;color:#1a1a2e;">📄 Pages créées par le plugin</h2>
                <table class="widefat" style="border-radius:8px;overflow:hidden;">
                    <thead><tr>
                        <th>Rôle</th><th>URL</th><th>Actions</th>
                    </tr></thead>
                    <tbody>
                        <tr>
                            <td><strong>Page de connexion</strong></td>
                            <td><code><?php echo esc_url( $login_url ); ?></code></td>
                            <td><a href="<?php echo esc_url( $login_url ); ?>" target="_blank" class="button button-small">Voir</a>
                                <a href="<?php echo esc_url( get_edit_post_link( get_option( 'ce_page_login' ) ) ); ?>" class="button button-small">Éditer</a>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Tableau de bord (Éditeurs)</strong></td>
                            <td><code><?php echo esc_url( $dashboard_url ); ?></code></td>
                            <td><a href="<?php echo esc_url( $dashboard_url ); ?>" target="_blank" class="button button-small">Voir</a></td>
                        </tr>
                        <tr>
                            <td><strong>Éditeur d'article (Auteurs)</strong></td>
                            <td><code><?php echo esc_url( $editor_url ); ?></code></td>
                            <td><a href="<?php echo esc_url( $editor_url ); ?>" target="_blank" class="button button-small">Voir</a></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Icônes des catégories -->
            <div class="edito-admin-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px 28px;margin:20px 0;max-width:800px;">
                <h2 style="margin-top:0;font-size:16px;color:#1a1a2e;">🏷️ Icônes des catégories</h2>
                <p style="color:#6b7280;font-size:13px;">Téléchargez une icône pour chaque catégorie. Formats recommandés : SVG, PNG 64×64 px minimum.</p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'ce_upload_icons', 'ce_icons_nonce' ); ?>
                    <input type="hidden" name="action" value="ce_upload_icon">
                    <table class="widefat" style="border-radius:8px;overflow:hidden;">
                        <thead><tr>
                            <th style="width:40px;"></th>
                            <th>Catégorie</th>
                            <th>Icône actuelle</th>
                            <th>Changer l'icône</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ( $categories as $cat ) :
                            $term      = get_term_by( 'slug', $cat['slug'], 'category' );
                            $term_id   = $term ? $term->term_id : 0;
                            $icon_url  = $term_id ? get_term_meta( $term_id, '_edito_icon_url', true ) : '';
                            ?>
                            <tr>
                                <td><?php if ( $icon_url ) : ?>
                                    <img src="<?php echo esc_url( $icon_url ); ?>" width="32" height="32" alt="" style="object-fit:contain;">
                                <?php else : ?>
                                    <span style="display:inline-block;width:32px;height:32px;background:#f3f4f6;border-radius:4px;text-align:center;line-height:32px;font-size:18px;">📁</span>
                                <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo esc_html( $cat['name'] ); ?></strong><br>
                                    <code style="font-size:11px;color:#9ca3af;"><?php echo esc_html( $cat['slug'] ); ?></code>
                                </td>
                                <td>
                                    <?php if ( $icon_url ) : ?>
                                        <img src="<?php echo esc_url( $icon_url ); ?>" height="48" style="max-width:80px;object-fit:contain;border:1px solid #e5e7eb;border-radius:6px;padding:4px;" alt="">
                                    <?php else : ?>
                                        <em style="color:#9ca3af;font-size:12px;">Aucune icône</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <input type="hidden" name="term_ids[]" value="<?php echo esc_attr( $term_id ); ?>">
                                    <input type="hidden" name="slugs[]"    value="<?php echo esc_attr( $cat['slug'] ); ?>">
                                    <input type="file" name="icon_<?php echo esc_attr( $cat['slug'] ); ?>" accept="image/*" style="font-size:12px;">
                                    <?php if ( $icon_url && $term_id ) : ?>
                                        <label style="display:block;margin-top:6px;font-size:12px;color:#ef4444;cursor:pointer;">
                                            <input type="checkbox" name="remove_icon[]" value="<?php echo esc_attr( $term_id ); ?>"> Supprimer
                                        </label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="margin-top:16px;">
                        <button type="submit" class="button button-primary">Enregistrer les icônes</button>
                    </p>
                </form>
            </div>

            <!-- Réglages généraux -->
            <div class="edito-admin-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px 28px;margin:20px 0;max-width:800px;">
                <h2 style="margin-top:0;font-size:16px;color:#1a1a2e;">⚙️ Réglages généraux</h2>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'ce_save_settings', 'ce_settings_nonce' ); ?>
                    <input type="hidden" name="action" value="ce_save_settings">
                    <table class="form-table">
                        <tr>
                            <th><label for="ce_email_from_name">Expéditeur des e-mails</label></th>
                            <td>
                                <input type="text" id="ce_email_from_name" name="ce_email_from_name"
                                    value="<?php echo esc_attr( get_option( 'ce_email_from_name', get_bloginfo('name') ) ); ?>"
                                    class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="ce_email_from">Adresse expéditeur</label></th>
                            <td>
                                <input type="email" id="ce_email_from" name="ce_email_from"
                                    value="<?php echo esc_attr( get_option( 'ce_email_from', get_option('admin_email') ) ); ?>"
                                    class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th>Accès wp-admin</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="ce_block_admin" value="1"
                                        <?php checked( get_option('ce_block_admin'), '1' ); ?>>
                                    Bloquer l'accès à <code>/wp-admin</code> pour les Auteurs et Éditeurs
                                </label>
                                <p class="description">Les administrateurs conservent toujours l'accès.</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( 'Enregistrer' ); ?>
                </form>
            </div>

            <!-- Shortcodes info -->
            <div class="edito-admin-card" style="background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:20px 28px;margin:20px 0;max-width:800px;">
                <h2 style="margin-top:0;font-size:15px;color:#92400e;">ℹ️ Note technique</h2>
                <p style="margin:0;font-size:13px;color:#78350f;">Les pages sont détectées automatiquement via leur meta <code>_edito_page_type</code>. Ne supprimez pas ces pages ou recréez-les via <strong>Désactiver → Réactiver</strong> le plugin.</p>
            </div>
        </div>
        <?php
    }

    /* -----------------------------------------------------------------------
     * Sauvegarde des réglages généraux
     * --------------------------------------------------------------------- */
    public function handle_save_settings(): void {
        check_admin_referer( 'ce_save_settings', 'ce_settings_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Accès refusé.' );

        update_option( 'ce_email_from_name', sanitize_text_field( wp_unslash( $_POST['ce_email_from_name'] ?? '' ) ) );
        update_option( 'ce_email_from',      sanitize_email( wp_unslash( $_POST['ce_email_from'] ?? '' ) ) );
        update_option( 'ce_block_admin',     isset( $_POST['ce_block_admin'] ) ? '1' : '0' );

        set_transient( 'ce_admin_notice', 'Réglages enregistrés.', 30 );
        wp_redirect( add_query_arg( 'page', 'edito', admin_url( 'admin.php' ) ) );
        exit;
    }

    /* -----------------------------------------------------------------------
     * Upload des icônes de catégories
     * --------------------------------------------------------------------- */
    public function handle_upload_icon(): void {
        check_admin_referer( 'ce_upload_icons', 'ce_icons_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Accès refusé.' );

        if ( ! function_exists( 'media_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $term_ids  = array_map( 'intval', $_POST['term_ids'] ?? [] );
        $slugs     = array_map( 'sanitize_key', $_POST['slugs'] ?? [] );
        $to_remove = array_map( 'intval', $_POST['remove_icon'] ?? [] );

        // Supprimer les icônes cochées
        foreach ( $to_remove as $tid ) {
            if ( $tid ) delete_term_meta( $tid, '_edito_icon_url' );
        }

        foreach ( $slugs as $idx => $slug ) {
            $file_key = "icon_{$slug}";
            if ( empty( $_FILES[ $file_key ]['name'] ) ) continue;

            $term_id = $term_ids[ $idx ] ?? 0;
            if ( ! $term_id ) {
                $term = get_term_by( 'slug', $slug, 'category' );
                $term_id = $term ? $term->term_id : 0;
            }
            if ( ! $term_id ) continue;

            // Uploader dans la médiathèque WP
            $_FILES['ce_icon_upload'] = $_FILES[ $file_key ];
            $att_id = media_handle_upload( 'ce_icon_upload', 0 );

            if ( ! is_wp_error( $att_id ) ) {
                $url = wp_get_attachment_url( $att_id );
                update_term_meta( $term_id, '_edito_icon_url', esc_url_raw( $url ) );
                update_term_meta( $term_id, '_ce_icon_id', $att_id );
            }
        }

        set_transient( 'ce_admin_notice', 'Icônes mises à jour.', 30 );
        wp_redirect( add_query_arg( 'page', 'edito', admin_url( 'admin.php' ) ) );
        exit;
    }

    /* -----------------------------------------------------------------------
     * Helper public : récupère l'URL de l'icône d'une catégorie (par slug)
     * --------------------------------------------------------------------- */
    public static function get_category_icon( string $slug ): string {
        $term = get_term_by( 'slug', $slug, 'category' );
        if ( ! $term ) return '';
        return (string) get_term_meta( $term->term_id, '_edito_icon_url', true );
    }
}
