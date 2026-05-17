<?php
/**
 * Template : Page de connexion éditoriale
 */
defined( 'ABSPATH' ) || exit;

$has_error = isset( $_GET['ce_error'] ) && '1' === $_GET['ce_error'];
$redirect  = sanitize_url( wp_unslash( $_GET['redirect_to'] ?? '' ) );

// Si déjà connecté avec le bon rôle → rediriger
if ( is_user_logged_in() ) {
    $user = wp_get_current_user();
    wp_redirect( Edito_Auth::dashboard_for_user( $user ) );
    exit;
}

$site_name = get_bloginfo( 'name' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — <?php echo esc_html( $site_name ); ?></title>
    <?php wp_head(); ?>
    <style>
        body { margin:0; background:#0f0e17; min-height:100vh; display:flex; align-items:center; justify-content:center; }
    </style>
</head>
<body class="edito-login-body">

<div class="edito-login-bg">
    <div class="edito-login-bg__shape edito-login-bg__shape--1"></div>
    <div class="edito-login-bg__shape edito-login-bg__shape--2"></div>
</div>

<div class="edito-login-card">
    <!-- Logo / Marque -->
    <div class="edito-login-card__brand">
        <div class="edito-login-card__logo-mark">
            <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="28" height="28" rx="7" fill="#c9a96e"/>
                <path d="M8 9h12M8 14h8M8 19h10" stroke="#1a1a2e" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </div>
        <div>
            <p class="edito-login-card__site"><?php echo esc_html( $site_name ); ?></p>
            <h1 class="edito-login-card__title">Espace éditorial</h1>
        </div>
    </div>

    <?php if ( $has_error ) : ?>
    <div class="edito-alert edito-alert--error">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5"/><path d="M8 5v3.5M8 11v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        Identifiants incorrects. Veuillez réessayer.
    </div>
    <?php endif; ?>

    <form class="edito-login-form" method="post" action="">
        <?php wp_nonce_field( 'ce_login', 'ce_login_nonce' ); ?>
        <?php if ( $redirect ) : ?>
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect ); ?>">
        <?php endif; ?>

        <div class="edito-field">
            <label class="edito-label" for="ce_username">Identifiant</label>
            <div class="edito-input-wrap">
                <svg class="edito-input-icon" width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="5.5" r="2.5" stroke="currentColor" stroke-width="1.5"/><path d="M2.5 13.5c0-2.485 2.462-4.5 5.5-4.5s5.5 2.015 5.5 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                <input
                    class="edito-input"
                    type="text"
                    id="ce_username"
                    name="ce_username"
                    placeholder="Votre identifiant"
                    required
                    autocomplete="username"
                    value="<?php echo esc_attr( $_POST['ce_username'] ?? '' ); ?>"
                >
            </div>
        </div>

        <div class="edito-field">
            <label class="edito-label" for="ce_password">Mot de passe</label>
            <div class="edito-input-wrap">
                <svg class="edito-input-icon" width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="3" y="7" width="10" height="7" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                <input
                    class="edito-input"
                    type="password"
                    id="ce_password"
                    name="ce_password"
                    placeholder="Votre mot de passe"
                    required
                    autocomplete="current-password"
                >
            </div>
        </div>

        <div class="edito-login-remember">
            <label class="edito-checkbox-label">
                <input type="checkbox" name="ce_remember" value="1">
                <span>Se souvenir de moi</span>
            </label>
            <a class="edito-login-forgot" href="<?php echo esc_url( wp_lostpassword_url() ); ?>">Mot de passe oublié ?</a>
        </div>

        <button type="submit" class="edito-btn edito-btn--primary edito-btn--full">
            <span>Se connecter</span>
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
    </form>

    <p class="edito-login-card__footer">
        Accès réservé aux membres de la rédaction.
    </p>
</div>

<?php wp_footer(); ?>
</body>
</html>
