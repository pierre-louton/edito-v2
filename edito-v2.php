<?php
/**
 * Plugin Name:  Edito
 * Plugin URI:   #
 * Description:  Interface d'édition personnalisée contournant l'éditeur classique WordPress. Gestion des articles avec workflow brouillon → en attente → publié, upload photos, notifications et interface dédiée. La version 1.9 apporte la gestion des contacts clients ou partenaires.
 * Version:      1.9.0
 * Author:       Pierre Beaubié
 * Text Domain:  edito
 * Domain Path:  /languages
 */

defined('ABSPATH') || exit;
define('EDITO_VERSION', '1.9.0');
define('EDITO_PATH', plugin_dir_path(__FILE__));
define('EDITO_URL', plugin_dir_url(__FILE__));
define('CE_SLUG', 'edito');

/* ---------- Inclusions --------------------------------------------------- */
foreach ([
    'class-edito-core',
    'class-edito-auth',
    'class-edito-db',
    'class-edito-editor',
    'class-edito-ajax',
    'class-edito-notifications',
    'class-edito-admin',
] as $file) {
    require_once EDITO_PATH . "includes/{$file}.php";
}

require_once EDITO_PATH . 'includes/edito-shortcode-carousel.php';
require_once EDITO_PATH . 'includes/edito-shortcode-posts-grid.php';

register_activation_hook( __FILE__, [ 'Edito_DB', 'install' ] );
add_action( 'plugins_loaded', [ 'Edito_DB', 'maybe_upgrade' ] );
add_action( 'init', [ 'Edito_DB', 'register_ajax' ] );

/* ---------- Hooks de cycle de vie ---------------------------------------- */
register_activation_hook(__FILE__,   ['Edito_Core', 'activate']);
register_deactivation_hook(__FILE__, ['Edito_Core', 'deactivate']);

add_action('plugins_loaded', ['Edito_Core', 'get_instance']);

/* ---------- Affichage galerie côté public --------------------------------- */
add_filter( 'the_content', 'edito_inject_gallery_in_content' );

function edito_inject_gallery_in_content( string $content ): string {

    if ( ! is_single() || ! in_the_loop() || ! is_main_query() ) {
        return $content;
    }

    $post_id = get_the_ID();
    $gallery  = Edito_Editor::get_gallery( $post_id );
    $extra    = array_slice( $gallery, 1 ); // index 0 = featured image déjà affichée par Blocksy

    if ( empty( $extra ) ) {
        return $content;
    }

    $items = '';
    foreach ( $extra as $img ) {
        $items .= sprintf(
            '<figure class="edito-public-gallery__item">
                <a href="%s" target="_blank" rel="noopener noreferrer">
                    <img src="%s" alt="%s" loading="lazy">
                </a>
            </figure>',
            esc_url( $img['full'] ),
            esc_url( $img['thumb'] ),
            esc_attr( $img['title'] )
        );
    }

    return $content . sprintf(
        '<div class="edito-public-gallery" data-count="%d">%s</div>',
        count( $extra ),
        $items
    );
}