<?php
/**
 * Plugin Name:  Edito v2
 * Plugin URI:   #
 * Description:  Workflow éditorial — fork générique. Interface d'édition personnalisée
 *               contournant l'éditeur classique WordPress. Gestion des articles avec
 *               workflow brouillon → en attente → publié, upload photos, notifications
 *               et interface dédiée. La version 2.0 apporte la gestion des contacts
 *               clients ou partenaires avec catégories dynamiques sur 2 niveaux.
 * Version:      2.0.0
 * Author:       Pierre Beaubié
 * Text Domain:  edito-v2
 * Domain Path:  /languages
 */

defined( 'ABSPATH' ) || exit;

/* ---------- Constantes --------------------------------------------------- */

define( 'EDITO_SLUG',       'edito' );                                   // corrigé : était ' CE_SLUG' (espace + mauvais nom)
define( 'EDITO_PATH',       plugin_dir_path( __FILE__ ) );
define( 'EDITO_URL',        plugin_dir_url( __FILE__ ) );
define( 'EDITO_ASSETS_URL', plugin_dir_url( __FILE__ ) . 'assets/' );
define( 'EDITO_VERSION',    '2.0.0' );

/* ---------- Inclusions --------------------------------------------------- */

foreach ( [
    'class-edito-core',
    'class-edito-auth',
    'class-edito-categories',    // ajouté : requis pour Edito_Categories::install() et register_hooks()
    'class-edito-db',
    'class-edito-editor',
    'class-edito-ajax',
    'class-edito-notifications',
    'class-edito-admin',
] as $file ) {
    require_once EDITO_PATH . "includes/{$file}.php";
}

require_once EDITO_PATH . 'includes/edito-shortcode-carousel.php';
require_once EDITO_PATH . 'includes/edito-shortcode-posts-grid.php';

/* ---------- Hooks d'activation / désactivation --------------------------- */

register_activation_hook( __FILE__, function () {       // un seul hook d'activation
    Edito_Categories::install();
    Edito_DB::install();
    Edito_Core::activate();
} );

register_deactivation_hook( __FILE__, [ 'Edito_Core', 'deactivate' ] );

/* ---------- Hooks de cycle de vie ---------------------------------------- */

add_action( 'plugins_loaded', [ 'Edito_Core', 'get_instance' ] );
add_action( 'plugins_loaded', [ 'Edito_DB',   'maybe_upgrade' ] );

/* ---------- Hooks init --------------------------------------------------- */

add_action( 'init', [ 'Edito_Categories', 'register_hooks' ] );
add_action( 'init', [ 'Edito_DB',         'register_hooks' ] );   // register_ajax intégré dans register_hooks — suppression du doublon

/* ---------- Affichage galerie côté public --------------------------------- */

add_filter( 'the_content', 'edito_inject_gallery_in_content' );

function edito_inject_gallery_in_content( string $content ): string {

    if ( ! is_single() || ! in_the_loop() || ! is_main_query() ) {
        return $content;
    }

    $post_id = get_the_ID();
    $gallery = Edito_Editor::get_gallery( $post_id );
    $extra   = array_slice( $gallery, 1 ); // index 0 = featured image déjà affichée par Blocksy

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
