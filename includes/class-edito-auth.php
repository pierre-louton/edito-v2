<?php
/**
 * Edito_Auth – Authentification personnalisée
 *
 * - Page de connexion dédiée (edito-login)
 * - Redirection post-login vers le bon tableau de bord
 * - Redirection post-logout vers la page login du plugin
 */
class Edito_Auth {

    private static ?Edito_Auth $instance = null;

    public static function get_instance(): Edito_Auth {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Remplace l'URL de login WP pour les éditeurs/auteurs
        add_filter( 'login_url',          [ $this, 'custom_login_url' ], 10, 3 );
        add_filter( 'logout_url',         [ $this, 'custom_logout_url' ], 10, 2 );

        // Traitement du formulaire de connexion du plugin
        add_action( 'init',               [ $this, 'handle_login_form' ] );
        add_action( 'init',               [ $this, 'handle_logout' ] );

        // Redirection après connexion WP standard
        add_filter( 'login_redirect',     [ $this, 'login_redirect' ], 10, 3 );
    }

    /* -----------------------------------------------------------------------
     * Remplace l'URL de login pour pointer vers la page du plugin
     * --------------------------------------------------------------------- */
    public function custom_login_url( string $login_url, string $redirect, bool $force_reauth ): string {
        $custom = Edito_Core::page_url( 'login' );
        if ( $custom && $redirect ) {
            $custom = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $custom );
        }
        return $custom ?: $login_url;
    }

    public function custom_logout_url( string $logout_url, string $redirect ): string {
        $redirect = $redirect ?: Edito_Core::page_url( 'login' );
        return add_query_arg( [
            'action'      => 'logout',
            '_wpnonce'    => wp_create_nonce( 'log-out' ),
            'redirect_to' => rawurlencode( $redirect ),
        ], site_url( 'wp-login.php' ) );
    }

    /* -----------------------------------------------------------------------
     * Traitement du formulaire de connexion front-end
     * --------------------------------------------------------------------- */
    public function handle_login_form(): void {
        if (
            ! isset( $_POST['ce_login_nonce'] ) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ce_login_nonce'] ) ), 'ce_login' )
        ) {
            return;
        }

        $username = sanitize_user( wp_unslash( $_POST['ce_username'] ?? '' ) );
        $password = wp_unslash( $_POST['ce_password'] ?? '' );
        $remember = isset( $_POST['ce_remember'] );

        $user = wp_signon( [
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember,
        ], is_ssl() );

        if ( is_wp_error( $user ) ) {
            wp_redirect( add_query_arg( 'ce_error', '1', Edito_Core::page_url( 'login' ) ) );
            exit;
        }

        // Redirection selon le rôle
        $redirect = sanitize_url( wp_unslash( $_POST['redirect_to'] ?? '' ) );
        if ( ! $redirect ) {
            $redirect = self::dashboard_for_user( $user );
        }
        wp_redirect( $redirect );
        exit;
    }

    public function handle_logout(): void {
        if ( isset( $_GET['ce_logout'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'ce_logout' ) ) {
            wp_logout();
            wp_redirect( Edito_Core::page_url( 'login' ) );
            exit;
        }
    }

    /* -----------------------------------------------------------------------
     * Redirection post-login WP standard
     * --------------------------------------------------------------------- */
    public function login_redirect( string $redirect_to, string $requested_redirect_to, $user ): string {
        if ( is_wp_error( $user ) ) return $redirect_to;

        if (
            in_array( 'editor', (array) $user->roles, true ) ||
            in_array( 'author', (array) $user->roles, true )
        ) {
            return self::dashboard_for_user( $user );
        }
        return $redirect_to;
    }

    /* -----------------------------------------------------------------------
     * Helper : URL du dashboard selon le rôle
     * --------------------------------------------------------------------- */
    public static function dashboard_for_user( $user ): string {
        if ( in_array( 'editor', (array) $user->roles, true ) ) {
            return Edito_Core::page_url( 'dashboard' );
        }
        return Edito_Core::page_url( 'editor' );
    }
}
