<?php
/**
 * Edito — Shortcode [edito_posts_grid]
 * ─────────────────────────────────────────────────────────────────────────
 * Affiche les N derniers articles du blog en mode grille responsive.
 * Chaque cellule : badge catégorie cliquable → thumbnail → titre → extrait → bouton.
 *
 * Utilisation :
 *   [edito_posts_grid]
 *   [edito_posts_grid n="3" cols="3" titre="Nos dernières actualités"]
 *   [edito_posts_grid n="8" cols="4" cat="conseils" words="20"]
 *
 * Paramètres disponibles :
 *   n      → nombre d'articles (défaut : 6, max : 24)
 *   cols   → colonnes de la grille (défaut : 3, entre 1 et 4)
 *   words  → mots dans l'extrait (défaut : 30)
 *   cat    → slug de catégorie WP pour filtrer (vide = tous)
 *   titre  → titre h2 au-dessus de la grille (vide = pas de titre)
 *
 * Personnalisation couleur bouton :
 *   Surcharger --edito-grid-btn-color dans le CSS personnalisé Blocksy.
 * ─────────────────────────────────────────────────────────────────────────
 */
defined( 'ABSPATH' ) || exit;

add_action( 'init', 'edito_pg_register_shortcode' );
add_action( 'init', 'edito_pg_register_term_meta' );
add_action( 'category_edit_form_fields', 'edito_pg_cat_icon_field' );
add_action( 'edited_category', 'edito_pg_save_cat_icon' );

function edito_pg_register_shortcode(): void {
    add_shortcode( 'edito_posts_grid', 'edito_pg_render' );
}

function edito_pg_register_term_meta(): void {
    register_term_meta( 'category', 'edito_cat_icon', [
        'type'              => 'string',
        'single'            => true,
        'sanitize_callback' => 'sanitize_text_field',
        'show_in_rest'      => false,
    ] );
}

function edito_pg_cat_icon_field( WP_Term $term ): void {
    $icon = get_term_meta( $term->term_id, 'edito_cat_icon', true );
    wp_nonce_field( 'edito_pg_cat_icon_' . $term->term_id, 'edito_pg_cat_icon_nonce' );
    ?>
    <tr class="form-field">
        <th scope="row">
            <label for="edito_pg_cat_icon"><?php esc_html_e( 'Icône (emoji)', 'edito' ); ?></label>
        </th>
        <td>
            <input type="text" id="edito_pg_cat_icon" name="edito_pg_cat_icon"
                   value="<?php echo esc_attr( $icon ); ?>" maxlength="10" style="width:80px;">
            <p class="description">
                <?php esc_html_e( 'Emoji affiché dans le badge catégorie (ex. : 🏠 💡 📋)', 'edito' ); ?>
            </p>
        </td>
    </tr>
    <?php
}

function edito_pg_save_cat_icon( int $term_id ): void {
    if ( ! isset( $_POST['edito_pg_cat_icon_nonce'] ) ) return;
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['edito_pg_cat_icon_nonce'] ) ), 'edito_pg_cat_icon_' . $term_id ) ) return;
    if ( ! current_user_can( 'manage_categories' ) ) return;

    $icon = isset( $_POST['edito_pg_cat_icon'] ) ? sanitize_text_field( $_POST['edito_pg_cat_icon'] ) : '';
    update_term_meta( $term_id, 'edito_cat_icon', $icon );
}

function edito_pg_enqueue_styles(): void {
    static $done = false;
    if ( $done ) return;
    $done = true;

    $css = '
/* ── Edito Posts Grid ──────────────────────────────────────── */

.edito-pg__wrap { width: 100%; }

.edito-pg__titre {
    text-align: center;
    font-size: clamp(1.1rem, 2.5vw, 1.5rem);
    font-weight: 700;
    margin-bottom: 28px;
    color: var(--theme-palette-color-4, #1e293b);
}

.edito-pg__grid {
    display: grid;
    grid-template-columns: repeat(var(--edito-pg-cols, 3), 1fr);
    gap: 24px;
}

@media (max-width: 900px) { .edito-pg__grid { --edito-pg-cols: 2; } }
@media (max-width: 560px) { .edito-pg__grid { --edito-pg-cols: 1; } }

.edito-pg__card {
    display: flex;
    flex-direction: column;
    background: #fff;
    border: 1px solid var(--theme-palette-color-5, #e2e8f0);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
    transition: box-shadow .2s, transform .2s;
}

.edito-pg__card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,.10);
    transform: translateY(-2px);
}

/* Badge catégorie */
.edito-pg__cat {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin: 10px 14px 6px;
    padding: 4px 10px 4px 6px;
    font-size: .62rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    border-radius: 20px;
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
    text-decoration: none;
    align-self: flex-start;
    transition: background .15s;
}

.edito-pg__cat:hover { background: #dbeafe; }

.edito-pg__cat--empty {
    visibility: hidden;
    pointer-events: none;
}

.edito-pg__cat-icon { font-size: .9rem; line-height: 1; }

/* Thumbnail */
.edito-pg__thumb-wrap { display: block; overflow: hidden; }

.edito-pg__thumb {
    width: 100%;
    aspect-ratio: 16 / 9;
    object-fit: cover;
    display: block;
    transition: transform .3s;
}

.edito-pg__thumb-wrap:hover .edito-pg__thumb { transform: scale(1.03); }

.edito-pg__thumb--placeholder {
    width: 100%;
    aspect-ratio: 16 / 9;
    background: var(--theme-palette-color-7, #f1f5f9);
    display: block;
}

/* Corps */
.edito-pg__body {
    display: flex;
    flex-direction: column;
    flex: 1;
    padding: 12px 14px 14px;
}

.edito-pg__title {
    font-size: .9rem;
    font-weight: 700;
    line-height: 1.35;
    margin: 0 0 6px;
}

.edito-pg__title a {
    color: var(--theme-palette-color-4, #1e293b);
    text-decoration: none;
}

.edito-pg__title a:hover { color: var(--theme-palette-color-1, #2563eb); }

.edito-pg__excerpt {
    flex: 1;
    font-size: .78rem;
    color: #64748b;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin: 0 0 12px;
}

/* Bouton */
.edito-pg__btn {
    display: inline-block;
    align-self: flex-start;
    padding: 7px 16px;
    font-size: .75rem;
    font-weight: 600;
    border-radius: 6px;
    background: var(--edito-grid-btn-color, var(--theme-palette-color-1, #2563eb));
    color: #fff;
    text-decoration: none;
    transition: opacity .15s;
    margin-top: auto;
}

.edito-pg__btn:hover { opacity: .85; color: #fff; }
';

    wp_register_style( 'edito-posts-grid', false, [], EDITO_VERSION ?? '1.0' );
    wp_enqueue_style( 'edito-posts-grid' );
    wp_add_inline_style( 'edito-posts-grid', $css );
}

function edito_pg_render( $atts, $content = null ): string {
    $atts = shortcode_atts( [
        'n'     => '6',
        'cols'  => '3',
        'words' => '30',
        'cat'   => '',
        'titre' => '',
    ], $atts, 'edito_posts_grid' );

    $n     = max( 1, min( 24, (int) $atts['n'] ) );
    $cols  = max( 1, min( 4, (int) $atts['cols'] ) );
    $words = max( 5, (int) $atts['words'] );
    $cat   = sanitize_title( $atts['cat'] );
    $titre = sanitize_text_field( $atts['titre'] );

    $query_args = [
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => $n,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => true,
    ];

    if ( ! empty( $cat ) ) {
        $query_args['category_name'] = $cat;
    }

    $query = new WP_Query( $query_args );

    if ( ! $query->have_posts() ) {
        return '<!-- edito_posts_grid : aucun article -->';
    }

    edito_pg_enqueue_styles();

    static $instance = 0;
    $instance++;
    $uid = 'edito-pg-' . $instance;

    ob_start();
    ?>
    <div class="edito-pg__wrap" id="<?php echo esc_attr( $uid ); ?>">
        <?php if ( ! empty( $titre ) ) : ?>
        <h2 class="edito-pg__titre"><?php echo esc_html( $titre ); ?></h2>
        <?php endif; ?>
        <div class="edito-pg__grid" style="--edito-pg-cols:<?php echo esc_attr( $cols ); ?>;">
            <?php while ( $query->have_posts() ) : $query->the_post(); ?>
            <?php echo edito_pg_card_html( $words ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function edito_pg_card_html( int $words ): string {
    $post_id   = get_the_ID();
    $permalink = get_permalink();
    $title     = get_the_title();

    // ── Badge catégorie ──────────────────────────────────────────────────
    $cats = get_the_category();
    $cat  = ! empty( $cats ) ? $cats[0] : null;

    if ( $cat ) {
        $icon      = get_term_meta( $cat->term_id, 'edito_cat_icon', true );
        $icon_html = $icon
            ? '<span class="edito-pg__cat-icon">' . esc_html( $icon ) . '</span>'
            : '';
        $cat_html  = sprintf(
            '<a class="edito-pg__cat" href="%s">%s%s</a>',
            esc_url( get_category_link( $cat->term_id ) ),
            $icon_html,
            esc_html( $cat->name )
        );
    } else {
        $cat_html = '<span class="edito-pg__cat edito-pg__cat--empty">&nbsp;</span>';
    }

    // ── Thumbnail ────────────────────────────────────────────────────────
    if ( has_post_thumbnail( $post_id ) ) {
        $thumb_html = sprintf(
            '<a class="edito-pg__thumb-wrap" href="%s">%s</a>',
            esc_url( $permalink ),
            get_the_post_thumbnail( $post_id, 'medium', [
                'class'   => 'edito-pg__thumb',
                'loading' => 'lazy',
                'alt'     => $title,
            ] )
        );
    } else {
        $thumb_html = sprintf(
            '<a class="edito-pg__thumb-wrap" href="%s"><div class="edito-pg__thumb edito-pg__thumb--placeholder"></div></a>',
            esc_url( $permalink )
        );
    }

    // ── Extrait ──────────────────────────────────────────────────────────
    $excerpt = wp_trim_words(
        wp_strip_all_tags( strip_shortcodes( get_the_content() ) ),
        $words,
        '&hellip;'
    );

    ob_start();
    ?>
    <article class="edito-pg__card">
        <?php echo $cat_html; // phpcs:ignore WordPress.Security.EscapeOutput ?>
        <?php echo $thumb_html; // phpcs:ignore WordPress.Security.EscapeOutput ?>
        <div class="edito-pg__body">
            <h3 class="edito-pg__title">
                <a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
            </h3>
            <p class="edito-pg__excerpt"><?php echo wp_kses_post( $excerpt ); ?></p>
            <a class="edito-pg__btn" href="<?php echo esc_url( $permalink ); ?>">
                <?php esc_html_e( 'Lire la suite', 'edito' ); ?>
            </a>
        </div>
    </article>
    <?php
    return ob_get_clean();
}
