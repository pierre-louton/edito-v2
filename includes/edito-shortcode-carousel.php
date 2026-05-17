<?php
/**
 * Edito — Shortcode [edito_contacts_carousel]
 * ─────────────────────────────────────────────────────────────────────────
 * Affiche un carrousel horizontal des contacts ayant contact_carousel = 1.
 * Compatible Elementor Free + thème Blocksy (CSS custom, zéro JS externe).
 *
 * Utilisation :
 *   [edito_contacts_carousel]
 *   [edito_contacts_carousel type="client" cat="agrement" speed="30"]
 *   [edito_contacts_carousel type="partenaire" titre="Nos partenaires"]
 *
 * Paramètres disponibles :
 *   type    → 'client' | 'partenaire' | '' (tous)
 *   cat     → 'agrement' | 'nco' | '' (toutes)
 *   speed   → vitesse défilement en secondes (défaut : 35, ralentir = nombre plus grand)
 *   titre   → titre h2 au-dessus du carrousel (vide = pas de titre)
 *   lien    → '1' pour rendre chaque logo cliquable vers contact_web (défaut : 1)
 *
 * Intégration dans le plugin :
 *   require_once EDITO_PATH . 'includes/edito-shortcode-carousel.php';
 *   // Le shortcode est enregistré automatiquement sur 'init'.
 *
 * Pour placer dans Elementor :
 *   Widget → "Shortcode" → coller [edito_contacts_carousel]
 * ─────────────────────────────────────────────────────────────────────────
 */
defined( 'ABSPATH' ) || exit;

add_action( 'init', 'edito_register_carousel_shortcode' );

function edito_register_carousel_shortcode(): void {
    add_shortcode( 'edito_contacts_carousel', 'edito_carousel_render' );
}

/**
 * Rendu du shortcode.
 *
 * @param array  $atts  Attributs du shortcode
 * @param string|null $content Contenu intérieur (non utilisé)
 * @return string HTML du carrousel
 */
function edito_carousel_render( $atts, $content = null ): string {
    if ( ! class_exists( 'Edito_DB' ) ) {
        if ( defined('EDITO_PATH') && file_exists( EDITO_PATH . 'includes/class-edito-db.php' ) ) {
            require_once EDITO_PATH . 'includes/class-edito-db.php';
        } else {
            return '<!-- Edito : classe Edito_DB introuvable -->';
        }
    }

    $atts = shortcode_atts( [
        'type'  => '',       // filtre type
        'cat'   => '',       // filtre catégorie
        'speed' => '35',     // durée animation (secondes)
        'titre' => '',       // titre affiché au-dessus
        'lien'  => '1',      // rendre les logos cliquables
    ], $atts, 'edito_contacts_carousel' );

    $speed   = max( 5, (int) $atts['speed'] );
    $do_lien = $atts['lien'] !== '0';

    // ── Requête ─────────────────────────────────────────────────
    // SELECT contact_id, contact_nom, contact_icone, contact_type,
    //        contact_categorie, contact_web
    // FROM   wp_contacts
    // WHERE  contact_actif = 1 AND contact_carousel = 1
    //   [AND contact_type = …] [AND contact_categorie = …]
    // ORDER BY contact_nom ASC
    // Index utilisé : idx_carousel (contact_carousel, contact_actif)
    global $wpdb;
    $tc     = $wpdb->prefix . 'contacts';
    $where  = [ 'contact_actif = 1', 'contact_carousel = 1' ];
    $values = [];

    $valid_types = [ 'client', 'partenaire' ];
    $valid_cats  = [ 'agrement', 'nco' ];

    if ( ! empty( $atts['type'] ) && in_array( $atts['type'], $valid_types, true ) ) {
        $where[]  = 'contact_type = %s';
        $values[] = $atts['type'];
    }

    if ( ! empty( $atts['cat'] ) && in_array( $atts['cat'], $valid_cats, true ) ) {
        $where[]  = 'contact_categorie = %s';
        $values[] = $atts['cat'];
    }

    $sql = "SELECT contact_id, contact_nom, contact_icone, contact_type, contact_categorie, contact_web
            FROM   `{$tc}`
            WHERE  " . implode( ' AND ', $where ) . "
            ORDER  BY contact_nom ASC";

    if ( $values ) {
        $sql = $wpdb->prepare( $sql, ...$values ); // phpcs:ignore
    }

    $contacts = (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore

    if ( empty( $contacts ) ) {
        return '<!-- Edito carrousel : aucun contact à afficher -->';
    }

    // ── ID unique pour éviter les conflits CSS si plusieurs shortcodes ──
    static $instance = 0;
    $instance++;
    $uid = 'edito-carousel-' . $instance;

    // ── CSS inline (injecté une seule fois via wp_add_inline_style) ────
    edito_carousel_enqueue_styles();

    // ── Construction HTML ───────────────────────────────────────────────
    ob_start();
    ?>
    <div class="edito-carousel-wrap" id="<?php echo esc_attr( $uid ); ?>">
        <?php if ( ! empty( $atts['titre'] ) ) : ?>
        <h2 class="edito-carousel-titre"><?php echo esc_html( $atts['titre'] ); ?></h2>
        <?php endif; ?>

        <div class="edito-carousel" aria-label="Carrousel des contacts"
             style="--edito-carousel-speed: <?php echo esc_attr( $speed ); ?>s;">

            <?php
            // Duplique les items pour créer une boucle infinie sans JS
            // (technique "seamless loop" pure CSS)
            $items_html = edito_carousel_items_html( $contacts, $do_lien );
            echo $items_html; // passe 1
            echo $items_html; // passe 2 (double = boucle)
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Génère le HTML d'une passe d'items (logo + nom).
 */
function edito_carousel_items_html( array $contacts, bool $do_lien ): string {
    $html = '<div class="edito-carousel__track" aria-hidden="true">';

    foreach ( $contacts as $c ) {
        $has_logo  = ! empty( $c['contact_icone'] );
        $has_url   = $do_lien && ! empty( $c['contact_web'] );
        $nom       = esc_html( $c['contact_nom'] );
        $initiales = strtoupper(
            mb_substr( $c['contact_nom'], 0, 1 )
            . ( mb_strpos( $c['contact_nom'], ' ' ) !== false
                ? mb_substr( strstr( $c['contact_nom'], ' ' ), 1, 1 )
                : '' )
        );

        // Type pour la couleur d'avatar fallback
        $type_cls = 'client' === $c['contact_type'] ? 'edito-carousel__avatar--client' : 'edito-carousel__avatar--partenaire';

        $inner = '';

        if ( $has_logo ) {
            $inner .= '<div class="edito-carousel__logo">'
                . '<img src="' . esc_url( $c['contact_icone'] ) . '" alt="' . $nom . '" loading="lazy">'
                . '</div>';
        } else {
            // Avatar fallback initiales
            $inner .= '<div class="edito-carousel__logo">'
                . '<div class="edito-carousel__avatar ' . esc_attr( $type_cls ) . '">' . esc_html( $initiales ) . '</div>'
                . '</div>';
        }

        $inner .= '<span class="edito-carousel__nom">' . $nom . '</span>';

        if ( $has_url ) {
            $html .= '<a class="edito-carousel__item" href="' . esc_url( $c['contact_web'] ) . '" target="_blank" rel="noopener noreferrer" title="' . $nom . '">' . $inner . '</a>';
        } else {
            $html .= '<div class="edito-carousel__item" title="' . $nom . '">' . $inner . '</div>';
        }
    }

    $html .= '</div>';
    return $html;
}

/**
 * Injecte le CSS du carrousel dans la page (une seule fois).
 * Attaché à wp_footer pour être sûr que wp_enqueue_style a eu lieu.
 */
function edito_carousel_enqueue_styles(): void {
    static $done = false;
    if ( $done ) return;
    $done = true;

    // Blocksy expose ses variables CSS en front — on les réutilise
    $css = '
/* ── Edito Contacts Carousel ──────────────────────────────── */

.edito-carousel-wrap {
    width: 100%;
    overflow: hidden;
    position: relative;
}

.edito-carousel-titre {
    text-align: center;
    font-size: clamp(1.1rem, 2.5vw, 1.5rem);
    font-weight: 700;
    margin-bottom: 28px;
    color: var(--theme-palette-color-4, #1e293b);
}

.edito-carousel {
    display: flex;
    overflow: hidden;
    /* Masque le surplus avec un fondu sur les bords */
    -webkit-mask-image: linear-gradient(
        to right,
        transparent 0%,
        black 8%,
        black 92%,
        transparent 100%
    );
    mask-image: linear-gradient(
        to right,
        transparent 0%,
        black 8%,
        black 92%,
        transparent 100%
    );
    padding: 12px 0;
}

/* Pause au survol */
.edito-carousel:hover .edito-carousel__track {
    animation-play-state: paused;
}

/* Les deux tracks en boucle infinie */
.edito-carousel__track {
    display: flex;
    align-items: center;
    gap: 32px;
    flex-shrink: 0;
    padding-right: 32px;
    animation: edito-scroll var(--edito-carousel-speed, 35s) linear infinite;
    will-change: transform;
}

@keyframes edito-scroll {
    from { transform: translateX(0); }
    to   { transform: translateX(-100%); }
}

/* Item individuel */
.edito-carousel__item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
    text-decoration: none;
    color: var(--theme-palette-color-4, #1e293b);
    padding: 16px 20px;
    border-radius: 12px;
    border: 1px solid var(--theme-palette-color-5, #e2e8f0);
    background: #fff;
    transition: box-shadow .2s, border-color .2s, transform .2s;
    min-width: 120px;
    max-width: 160px;
    cursor: default;
}

a.edito-carousel__item { cursor: pointer; }

a.edito-carousel__item:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,.10);
    border-color: var(--theme-palette-color-1, #2563eb);
    transform: translateY(-2px);
}

/* Zone logo */
.edito-carousel__logo {
    width: 72px;
    height: 52px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.edito-carousel__logo img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    display: block;
}

/* Avatar fallback */
.edito-carousel__avatar {
    width: 52px;
    height: 52px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
    text-transform: uppercase;
    letter-spacing: .02em;
}

.edito-carousel__avatar--client {
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
}

.edito-carousel__avatar--partenaire {
    background: #fdf8f0;
    color: #92660a;
    border: 1px solid #f0d9a8;
}

/* Nom */
.edito-carousel__nom {
    font-size: .78rem;
    font-weight: 600;
    text-align: center;
    line-height: 1.3;
    color: var(--theme-palette-color-4, #1e293b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 120px;
}

/* Accessibilité : respecte prefers-reduced-motion */
@media (prefers-reduced-motion: reduce) {
    .edito-carousel__track {
        animation: none;
    }
    .edito-carousel {
        overflow-x: auto;
        -webkit-mask-image: none;
        mask-image: none;
        scroll-snap-type: x mandatory;
        -webkit-overflow-scrolling: touch;
    }
    .edito-carousel__item {
        scroll-snap-align: start;
    }
}

/* Responsive : réduit les items sur mobile */
@media (max-width: 480px) {
    .edito-carousel__item {
        min-width: 90px;
        max-width: 110px;
        padding: 12px 14px;
    }
    .edito-carousel__logo { width: 54px; height: 40px; }
    .edito-carousel__avatar { width: 40px; height: 40px; font-size: .9rem; }
    .edito-carousel__nom { font-size: .72rem; max-width: 86px; }
}
';

    // On l'injecte via un handle WP propre (pas wp_add_inline_style seul,
    // car ça nécessite un handle parent enregistré)
    wp_register_style( 'edito-carousel', false, [], EDITO_VERSION ?? '1.0' );
    wp_enqueue_style( 'edito-carousel' );
    wp_add_inline_style( 'edito-carousel', $css );
}
