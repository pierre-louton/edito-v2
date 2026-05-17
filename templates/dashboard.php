<?php
/**
 * Template : Tableau de bord éditorial (éditeurs uniquement)
 */
defined( 'ABSPATH' ) || exit;

/*
 * Sécurité : s'assurer que les classes du plugin sont bien chargées,
 * même si le template est inclus dans un contexte inhabituel.
 */
if ( defined( 'EDITO_PATH' ) ) {
    foreach ( [ 'class-edito-core', 'class-edito-editor', 'class-edito-admin' ] as $_f ) {
        $__file = EDITO_PATH . "includes/{$_f}.php";
        if ( ! class_exists( str_replace( [ 'class-', '-' ], [ '', '_' ], ucwords( $_f, '-' ) ) )
             && file_exists( $__file ) ) {
            require_once $__file;
        }
    }
    unset( $_f, $__file );
}

$user          = wp_get_current_user();
$is_editor     = current_user_can( 'edit_others_posts' );
$site_name     = get_bloginfo( 'name' );
$dashboard_url = Edito_Core::page_url( 'dashboard' );
$editor_url    = Edito_Core::page_url( 'editor' );
$logout_url    = wp_logout_url( Edito_Core::page_url( 'login' ) );

// Filtres
$filter_status   = sanitize_text_field( $_GET['status'] ?? 'pending' );
$filter_cat      = (int) ( $_GET['cat'] ?? 0 );
$filter_author   = (int) ( $_GET['filter_author'] ?? 0 );
$paged           = max( 1, (int) ( $_GET['paged'] ?? 1 ) );

$valid_statuses  = [ 'all', 'pending', 'draft', 'publish', 'trash' ];
if ( ! in_array( $filter_status, $valid_statuses, true ) ) $filter_status = 'pending';

// Query
$query_args = [
    'post_type'      => 'post',
    'post_status'    => 'all' === $filter_status
        ? [ 'pending', 'draft', 'publish', 'ce_validated' ]
        : [ $filter_status ],
    'posts_per_page' => 12,
    'paged'          => $paged,
    'orderby'        => 'date',
    'order'          => 'DESC',
];

if ( ! $is_editor ) {
    $query_args['author'] = $user->ID;
}
if ( $filter_cat ) {
    $query_args['cat'] = $filter_cat;
}
if ( $filter_author && $is_editor ) {
    $query_args['author'] = $filter_author;
}

$articles = new WP_Query( $query_args );
$categories = get_terms( [ 'taxonomy' => 'category', 'hide_empty' => false ] );

// Compteurs par statut
$counts = [];
foreach ( [ 'pending', 'draft', 'publish', 'all' ] as $s ) {
    $c_args = [
        'post_type'      => 'post',
        'post_status'    => 'all' === $s ? [ 'pending', 'draft', 'publish', 'ce_validated' ] : [ $s ],
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ];
    if ( ! $is_editor ) $c_args['author'] = $user->ID;
    $counts[ $s ] = count( get_posts( $c_args ) );
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord — <?php echo esc_html( $site_name ); ?></title>
    <?php wp_head(); ?>
</head>
<body class="edito-app-body">

<!-- =====================================================================
     SIDEBAR
     ===================================================================== -->
<aside class="edito-sidebar">
    <div class="edito-sidebar__brand">
        <div class="edito-sidebar__logo">
            <svg width="22" height="22" viewBox="0 0 28 28" fill="none"><rect width="28" height="28" rx="7" fill="#c9a96e"/><path d="M8 9h12M8 14h8M8 19h10" stroke="#1a1a2e" stroke-width="2" stroke-linecap="round"/></svg>
        </div>
        <span class="edito-sidebar__site"><?php echo esc_html( $site_name ); ?></span>
    </div>
    <nav class="edito-sidebar__nav">
        <a class="edito-nav-item edito-nav-item--active" href="<?php echo esc_url( $dashboard_url ); ?>">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="1" y="1" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/><rect x="1" y="11" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/><rect x="11" y="1" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/><rect x="11" y="11" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/></svg>
            Tableau de bord
            <?php if ( $counts['pending'] ) : ?>
            <span class="edito-nav-badge"><?php echo esc_html( $counts['pending'] ); ?></span>
            <?php endif; ?>
        </a>
        <a class="edito-nav-item" href="<?php echo esc_url( $editor_url ); ?>">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M3 13.5 12.5 4l2 2L5 15.5H3v-2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M10.5 6 12.5 8" stroke="currentColor" stroke-width="1.5"/></svg>
            Nouvel article
        </a>
    </nav>
    <div class="edito-sidebar__footer">
        <div class="edito-sidebar__user">
            <div class="edito-sidebar__avatar"><?php echo esc_html( mb_substr( $user->display_name, 0, 1 ) ); ?></div>
            <div>
                <p class="edito-sidebar__user-name"><?php echo esc_html( $user->display_name ); ?></p>
                <p class="edito-sidebar__user-role"><?php echo esc_html( $is_editor ? 'Éditeur' : 'Auteur' ); ?></p>
            </div>
        </div>
        <a class="edito-sidebar__logout" href="<?php echo esc_url( $logout_url ); ?>" title="Déconnexion">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M6 2H3a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3M11 11l3-3-3-3M14 8H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
    </div>
</aside>

<!-- =====================================================================
     CONTENU PRINCIPAL
     ===================================================================== -->
<main class="edito-main">
    <div class="edito-toast" id="ce-toast" role="alert" aria-live="polite"></div>

    <!-- En-tête -->
    <header class="edito-page-header">
        <div>
            <div class="edito-breadcrumb"><span>Tableau de bord</span></div>
            <h1 class="edito-page-title">
                <?php echo $is_editor ? 'Articles à valider' : 'Mes articles'; ?>
            </h1>
        </div>
        <a href="<?php echo esc_url( $editor_url ); ?>" class="edito-btn edito-btn--primary">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            Nouvel article
        </a>
    </header>

    <!-- Statistiques rapides -->
    <div class="edito-stats-row">
        <?php
        $stat_items = [
            [ 'label' => 'En attente',    'key' => 'pending', 'icon' => '⏳', 'color' => '#3b82f6' ],
            [ 'label' => 'Brouillons',    'key' => 'draft',   'icon' => '📝', 'color' => '#f59e0b' ],
            [ 'label' => 'Publiés',       'key' => 'publish', 'icon' => '✅', 'color' => '#22c55e' ],
            [ 'label' => 'Total',         'key' => 'all',     'icon' => '📊', 'color' => '#8b5cf6' ],
        ];
        foreach ( $stat_items as $s ) : ?>
        <a class="edito-stat-card <?php echo $filter_status === $s['key'] ? 'ce-stat-card--active' : ''; ?>"
           href="<?php echo esc_url( add_query_arg( 'status', $s['key'], $dashboard_url ) ); ?>">
            <div class="edito-stat-card__icon" style="color:<?php echo esc_attr( $s['color'] ); ?>"><?php echo $s['icon']; ?></div>
            <div class="edito-stat-card__count"><?php echo esc_html( $counts[ $s['key'] ] ); ?></div>
            <div class="edito-stat-card__label"><?php echo esc_html( $s['label'] ); ?></div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Filtres -->
    <div class="edito-filters">
        <form method="get" class="edito-filters-form" action="<?php echo esc_url( $dashboard_url ); ?>">
            <input type="hidden" name="status" value="<?php echo esc_attr( $filter_status ); ?>">
            <div class="edito-select-wrap edito-select-wrap--sm">
                <select name="cat" class="edito-select" onchange="this.form.submit()">
                    <option value="">Toutes les catégories</option>
                    <?php foreach ( $categories as $cat ) : ?>
                    <option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $cat->term_id, $filter_cat ); ?>>
                        <?php echo esc_html( $cat->name ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <svg class="edito-select-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </form>
    </div>

    <!-- Liste des articles -->
    <?php if ( $articles->have_posts() ) : ?>
    <div class="edito-articles-grid">
        <?php while ( $articles->have_posts() ) : $articles->the_post();
            $pid       = get_the_ID();
            $status    = get_post_status();
            $author_id = get_the_author_meta( 'ID' );
            $gallery   = Edito_Editor::get_gallery( $pid );

            /*
             * MULTI-CATÉGORIES
             * On récupère toutes les catégories de l'article.
             * WordPress supporte nativement plusieurs catégories par article.
             */
            $post_cats = get_the_terms( $pid, 'category' ) ?: [];
        ?>
        <article class="edito-article-card" data-id="<?php echo esc_attr( $pid ); ?>">

            <!-- Galerie vignettes -->
            <?php if ( ! empty( $gallery ) ) : ?>
            <div class="edito-article-gallery">
                <?php foreach ( array_slice( $gallery, 0, 5 ) as $i => $img ) : ?>
                <div
                    class="edito-article-gallery__thumb <?php echo $i === 0 ? 'ce-article-gallery__thumb--featured' : ''; ?>"
                    data-full="<?php echo esc_url( $img['full'] ); ?>"
                    data-title="<?php echo esc_attr( $img['title'] ); ?>"
                    tabindex="0"
                    role="button"
                    aria-label="Agrandir la photo <?php echo esc_attr( $i + 1 ); ?>"
                    onclick="Edito_Tableau.openLightbox('<?php echo esc_js( $img['full'] ); ?>', '<?php echo esc_js( $img['title'] ); ?>')"
                    onkeydown="if(event.key==='Enter') Edito_Tableau.openLightbox('<?php echo esc_js( $img['full'] ); ?>', '<?php echo esc_js( $img['title'] ); ?>')"
                >
                    <img src="<?php echo esc_url( $img['thumb'] ); ?>" alt="<?php echo esc_attr( $img['title'] ); ?>" loading="lazy">
                    <?php if ( count($gallery) > 5 && $i === 4 ) : ?>
                    <div class="edito-gallery-overflow">+<?php echo count($gallery) - 5; ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else : ?>
            <div class="edito-article-gallery edito-article-gallery--empty">
                <div class="edito-article-gallery__placeholder">
                    <svg width="32" height="32" viewBox="0 0 32 32" fill="none" opacity=".3"><rect x="2" y="6" width="28" height="20" rx="3" stroke="currentColor" stroke-width="1.5"/><circle cx="10" cy="13" r="2.5" stroke="currentColor" stroke-width="1.5"/><path d="M2 22l8-6 6 4.5 4-3 10 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                    <span>Aucune photo</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Corps de la carte -->
            <div class="edito-article-card__body">
                <div class="edito-article-card__meta">

                    <!-- ─── MULTI-CATÉGORIES ────────────────────────────────────
                         Chaque catégorie de l'article s'affiche en ligne avec
                         son icône si elle existe. La liste est limitée à 3 pour
                         ne pas déborder dans la card.
                    ──────────────────────────────────────────────────────────── -->
                    <div class="edito-article-cats">
                        <?php if ( ! empty( $post_cats ) ) :
                            $displayed = 0;
                            foreach ( $post_cats as $_cat ) :
                                if ( $displayed >= 3 ) break;
                                $_icon = Edito_Admin::get_category_icon( $_cat->slug );
                                $displayed++;
                        ?>
                        <div class="edito-article-cat">
                            <?php if ( $_icon ) : ?>
                            <img
                                src="<?php echo esc_url( $_icon ); ?>"
                                width="14" height="14"
                                alt=""
                                class="edito-article-cat__icon"
                            >
                            <?php endif; ?>
                            <span><?php echo esc_html( $_cat->name ); ?></span>
                        </div>
                        <?php endforeach;
                            // Indicateur si plus de 3 catégories
                            if ( count( $post_cats ) > 3 ) : ?>
                        <div class="edito-article-cat">
                            <span style="color:var(--edito-primary);font-weight:600;">+<?php echo count( $post_cats ) - 3; ?></span>
                        </div>
                        <?php endif;
                        else : ?>
                        <div class="edito-article-cat"><span>—</span></div>
                        <?php endif; ?>
                    </div>

                    <span class="edito-badge <?php echo esc_attr( Edito_Core::status_badge_class( $status ) ); ?>">
                        <?php echo esc_html( Edito_Core::status_label( $status ) ); ?>
                    </span>
                </div>

                <h2 class="edito-article-card__title">
                    <?php echo esc_html( get_the_title() ?: '(Sans titre)' ); ?>
                </h2>

                <p class="edito-article-card__excerpt">
                    <?php echo esc_html( wp_trim_words( get_the_content(), 20, '…' ) ); ?>
                </p>

                <div class="edito-article-card__footer">
                    <div class="edito-article-card__author">
                        <?php echo get_avatar( $author_id, 20 ); ?>
                        <span><?php echo esc_html( get_the_author() ); ?></span>
                        <span class="edito-article-card__date">· <?php echo esc_html( get_the_modified_date( 'd/m/Y' ) ); ?></span>
                    </div>

                    <!-- Actions -->
                    <div class="edito-article-actions">
                        <!-- Modifier -->
                        <a
                            href="<?php echo esc_url( add_query_arg( 'post_id', $pid, $editor_url ) ); ?>"
                            class="edito-action-btn edito-action-btn--edit"
                            title="Modifier"
                        >
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 10.5 9.5 3l2 2L4 12.5H2v-2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M8 5 10 7" stroke="currentColor" stroke-width="1.5"/></svg>
                        </a>

                        <?php if ( $is_editor ) : ?>

                        <?php if ( 'pending' === $status || 'draft' === $status ) : ?>
                        <!-- Publier -->
                        <button
                            type="button"
                            class="edito-action-btn edito-action-btn--publish"
                            title="Publier"
                            data-action="publish"
                            data-id="<?php echo esc_attr( $pid ); ?>"
                        >
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7.5 5.5 11 12 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                        <?php endif; ?>

                        <?php if ( 'pending' === $status || 'publish' === $status ) : ?>
                        <!-- Remettre en brouillon -->
                        <button
                            type="button"
                            class="edito-action-btn edito-action-btn--draft"
                            title="Remettre en brouillon"
                            data-action="draft"
                            data-id="<?php echo esc_attr( $pid ); ?>"
                        >
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M7 2v5l3 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M12.7 5.3A6 6 0 1 0 12 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                        </button>
                        <?php endif; ?>

                        <!-- Supprimer -->
                        <button
                            type="button"
                            class="edito-action-btn edito-action-btn--delete"
                            title="Supprimer"
                            data-action="trash"
                            data-id="<?php echo esc_attr( $pid ); ?>"
                        >
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2.5 4h9l-.8 7H3.3L2.5 4ZM5 6.5v3M9 6.5v3M1 4h12M5.5 4l.5-1.5h2L8.5 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>

                        <?php endif; // is_editor ?>
                    </div>
                </div>
            </div>
        </article>
        <?php endwhile; wp_reset_postdata(); ?>
    </div>

    <!-- Pagination -->
    <?php if ( $articles->max_num_pages > 1 ) : ?>
    <div class="edito-pagination">
        <?php
        echo paginate_links( [
            'total'     => $articles->max_num_pages,
            'current'   => $paged,
            'base'      => add_query_arg( 'paged', '%#%', $dashboard_url ),
            'format'    => '',
            'prev_text' => '← Précédent',
            'next_text' => 'Suivant →',
        ] );
        ?>
    </div>
    <?php endif; ?>

    <?php else : ?>
    <div class="edito-empty-state">
        <div class="edito-empty-state__icon">📭</div>
        <h2>Aucun article trouvé</h2>
        <p>
            <?php echo 'pending' === $filter_status
                ? 'Aucun article en attente de validation pour le moment.'
                : 'Aucun article dans ce filtre.'; ?>
        </p>
        <a href="<?php echo esc_url( $editor_url ); ?>" class="edito-btn edito-btn--primary">Rédiger un article</a>
    </div>
    <?php endif; ?>

</main>

<!-- =====================================================================
     LIGHTBOX
     ===================================================================== -->
<div class="edito-lightbox" id="ce-lightbox" role="dialog" aria-modal="true" aria-label="Aperçu de la photo" style="display:none;">
    <button class="edito-lightbox__close" id="ce-lightbox-close" aria-label="Fermer">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M4 4l12 12M16 4L4 16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    </button>
    <div class="edito-lightbox__inner">
        <img class="edito-lightbox__img" id="ce-lightbox-img" src="" alt="">
        <p class="edito-lightbox__caption" id="ce-lightbox-caption"></p>
    </div>
</div>

<!-- Modal de confirmation -->
<div class="edito-modal-overlay" id="ce-confirm-overlay" style="display:none;">
    <div class="edito-modal" role="dialog" aria-modal="true" aria-labelledby="ce-confirm-title">
        <h3 class="edito-modal__title" id="ce-confirm-title">Confirmer l'action</h3>
        <p class="edito-modal__text" id="ce-confirm-text"></p>
        <div class="edito-modal__actions">
            <button type="button" class="edito-btn edito-btn--outline" id="ce-confirm-cancel">Annuler</button>
            <button type="button" class="edito-btn edito-btn--danger" id="ce-confirm-ok">Confirmer</button>
        </div>
    </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
