<?php
/**
 * Template : Gestion des partenaires
 * Accessible aux éditeurs uniquement.
 * CSS : edito-style.css + contacts-style.css (aucun style inline dans ce fichier)
 */
defined( 'ABSPATH' ) || exit;

/* ── Authentification ────────────────────────────────────────────────── */
if ( ! is_user_logged_in() ) {
    wp_redirect( Edito_Core::page_url( 'login' ) );
    exit;
}
if ( ! current_user_can( 'edit_others_posts' ) ) {
    wp_redirect( Edito_Core::page_url( 'dashboard' ) );
    exit;
}

/* ── Paramètres GET ──────────────────────────────────────────────────── */
$filter_cat    = (int)    ( $_GET['cat_id'] ?? 0 );
$filter_actif  = isset( $_GET['actif'] ) ? (int) $_GET['actif'] : 1;  // 1=actifs, 0=archivés, -1=tous
$filter_search = sanitize_text_field( $_GET['search'] ?? '' );
$paged         = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
$view          = in_array( $_GET['view'] ?? 'grid', [ 'grid', 'list' ], true )
                    ? $_GET['view'] : 'grid';

/* ── Données ─────────────────────────────────────────────────────────── */
$user           = wp_get_current_user();
$site_name      = get_bloginfo( 'name' );
$dashboard_url  = Edito_Core::page_url( 'dashboard' );
$editor_url     = Edito_Core::page_url( 'editor' );
$contacts_url   = Edito_Core::page_url( 'contacts' );
$categories_url = Edito_Core::page_url( 'categories' );
$logout_url     = wp_logout_url( Edito_Core::page_url( 'login' ) );

$counts         = Edito_DB::get_counts();
$cat_select     = Edito_Categories::get_for_select();     // [cat_id => label]
$cat_tree_json  = wp_json_encode( Edito_Categories::get_tree() );  // pour JS

$result = Edito_DB::get_contacts_paged( [
    'cat_id'   => $filter_cat,
    'actif'    => $filter_actif,
    'search'   => $filter_search,
    'per_page' => 15,
    'page'     => $paged,
] );

$contacts   = $result['contacts'];
$total      = $result['total'];
$total_pages= $result['pages'];

/* Catégories par défaut disponibles (level-1 uniquement) pour import CSV */
$cat_parents = array_filter(
    Edito_Categories::get_all(),
    fn( $c ) => 0 === (int) $c['cat_parent']
);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partenaires — <?php echo esc_html( $site_name ); ?></title>
    <?php wp_head(); ?>
</head>
<body class="edito-app-body">

<!-- =====================================================================
     SIDEBAR
     ===================================================================== -->
<aside class="edito-sidebar">
    <div class="edito-sidebar__brand">
        <div class="edito-sidebar__logo">
            <svg width="22" height="22" viewBox="0 0 28 28" fill="none" aria-hidden="true">
                <rect width="28" height="28" rx="7" fill="#c9a96e"/>
                <path d="M8 9h12M8 14h8M8 19h10" stroke="#1a1a2e" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </div>
        <span class="edito-sidebar__site"><?php echo esc_html( $site_name ); ?></span>
    </div>

    <nav class="edito-sidebar__nav" aria-label="Navigation principale">
        <a class="edito-nav-item" href="<?php echo esc_url( $dashboard_url ); ?>">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                <rect x="1" y="1" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
                <rect x="1" y="11" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
                <rect x="11" y="1" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
                <rect x="11" y="11" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
            </svg>
            Tableau de bord
        </a>
        <a class="edito-nav-item" href="<?php echo esc_url( $editor_url ); ?>">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                <path d="M3 13.5 12.5 4l2 2L5 15.5H3v-2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                <path d="M10.5 6 12.5 8" stroke="currentColor" stroke-width="1.5"/>
            </svg>
            Nouvel article
        </a>
        <a class="edito-nav-item edito-nav-item--active" href="<?php echo esc_url( $contacts_url ); ?>">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                <circle cx="9" cy="6" r="3" stroke="currentColor" stroke-width="1.5"/>
                <path d="M2 16c0-3.314 3.134-6 7-6s7 2.686 7 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            Partenaires
            <?php if ( $counts['actifs'] ) : ?>
            <span class="edito-nav-badge"><?php echo esc_html( $counts['actifs'] ); ?></span>
            <?php endif; ?>
        </a>
        <a class="edito-nav-item" href="<?php echo esc_url( $categories_url ); ?>">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                <path d="M2 4h6M2 9h10M2 14h8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                <circle cx="14" cy="4" r="2" stroke="currentColor" stroke-width="1.5"/>
            </svg>
            Catégories
        </a>
    </nav>

    <div class="edito-sidebar__footer">
        <div class="edito-sidebar__user">
            <div class="edito-sidebar__avatar"><?php echo esc_html( mb_substr( $user->display_name, 0, 1 ) ); ?></div>
            <div>
                <p class="edito-sidebar__user-name"><?php echo esc_html( $user->display_name ); ?></p>
                <p class="edito-sidebar__user-role">Éditeur</p>
            </div>
        </div>
        <a class="edito-sidebar__logout" href="<?php echo esc_url( $logout_url ); ?>" title="Déconnexion">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M6 2H3a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3M11 11l3-3-3-3M14 8H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
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
            <div class="edito-breadcrumb"><span>Partenaires</span></div>
            <h1 class="edito-page-title">Partenaires</h1>
        </div>
        <div class="edito-actions-stack" style="">
            <button type="button" class="edito-btn edito-btn--outline" id="btn-import-csv">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M2 11v2a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1v-2M8 2v8M5 7l3 3 3-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Importer CSV
            </button>
            <button type="button" class="edito-btn edito-btn--primary" id="btn-new-contact">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                Nouveau partenaire
            </button>
        </div>
    </header>

    <!-- Statistiques -->
    <div class="edito-stats-row">
        <?php
        $stats = [
            [ 'key' => 'actifs',   'label' => 'Actifs',      'icon' => '👥', 'count' => $counts['actifs'],   'actif' => 1  ],
            [ 'key' => 'archives', 'label' => 'Archivés',    'icon' => '📦', 'count' => $counts['archives'], 'actif' => 0  ],
            [ 'key' => 'carousel', 'label' => 'Carrousel',   'icon' => '⭐', 'count' => $counts['carousel'], 'actif' => 1  ],
            [ 'key' => 'total',    'label' => 'Total',        'icon' => '📊', 'count' => $counts['total'],    'actif' => -1 ],
        ];
        foreach ( $stats as $s ) :
            $is_active = (int) $filter_actif === $s['actif'] && 0 === $filter_cat && '' === $filter_search;
            $href = add_query_arg( [ 'actif' => $s['actif'], 'cat_id' => 0, 'search' => '' ], $contacts_url );
        ?>
        <a class="edito-stat-card <?php echo $is_active ? 'ce-stat-card--active' : ''; ?>"
           href="<?php echo esc_url( $href ); ?>">
            <div class="edito-stat-card__icon edito-stat-card__icon--<?php echo esc_attr( $s['key'] ); ?>"><?php echo $s['icon']; ?></div>
            <div class="edito-stat-card__count"><?php echo esc_html( $s['count'] ); ?></div>
            <div class="edito-stat-card__label"><?php echo esc_html( $s['label'] ); ?></div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Filtres -->
    <form method="get" action="<?php echo esc_url( $contacts_url ); ?>">
        <input type="hidden" name="view"  value="<?php echo esc_attr( $view ); ?>">
        <input type="hidden" name="actif" value="<?php echo esc_attr( $filter_actif ); ?>">
        <div class="edito-contacts-bar">
            <div class="edito-contacts-bar__search">
                <span class="edito-contacts-bar__search-icon" aria-hidden="true">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                        <circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M9.5 9.5 12 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </span>
                <input
                    type="search"
                    name="search"
                    class="edito-input"
                    placeholder="Rechercher un partenaire…"
                    value="<?php echo esc_attr( $filter_search ); ?>"
                    autocomplete="off"
                >
            </div>

            <div class="edito-select-wrap edito-select-wrap--sm">
                <select name="cat_id" class="edito-select" onchange="this.form.submit()">
                    <option value="0">Toutes les catégories</option>
                    <?php foreach ( $cat_select as $cid => $label ) : ?>
                    <option value="<?php echo esc_attr( $cid ); ?>" <?php selected( $cid, $filter_cat ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <svg class="edito-select-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                    <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>

            <button type="submit" class="edito-btn edito-btn--ghost edito-btn--sm">Filtrer</button>

            <div class="edito-view-toggle" role="group" aria-label="Mode d'affichage">
                <a class="edito-view-toggle__btn <?php echo 'grid' === $view ? 'edito-view-toggle__btn--active' : ''; ?>"
                   href="<?php echo esc_url( add_query_arg( 'view', 'grid', $contacts_url ) ); ?>"
                   aria-label="Vue grille" title="Grille">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <rect x="1" y="1" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.5"/>
                        <rect x="1" y="10" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.5"/>
                        <rect x="10" y="1" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.5"/>
                        <rect x="10" y="10" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.5"/>
                    </svg>
                </a>
                <a class="edito-view-toggle__btn <?php echo 'list' === $view ? 'edito-view-toggle__btn--active' : ''; ?>"
                   href="<?php echo esc_url( add_query_arg( 'view', 'list', $contacts_url ) ); ?>"
                   aria-label="Vue liste" title="Liste">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M5 4h9M5 8h9M5 12h9M2 4h.5M2 8h.5M2 12h.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </a>
            </div>
        </div>
    </form>

    <!-- ── Liste des partenaires ───────────────────────────────────────── -->
    <?php if ( ! empty( $contacts ) ) : ?>

    <?php if ( 'grid' === $view ) : ?>
    <div class="edito-contacts-grid" id="contacts-wrap">
    <?php else : ?>
    <div class="edito-contacts-list" id="contacts-wrap">
    <?php endif; ?>

        <?php foreach ( $contacts as $c ) :
            $cat_label = $cat_select[ (int) $c['contact_cat_id'] ] ?? '';
            $initials  = mb_strtoupper( mb_substr( $c['contact_nom'], 0, 2 ) );
            $archived  = ! (int) $c['contact_actif'];
        ?>

        <?php if ( 'grid' === $view ) : ?>
        <!-- ── Carte ── -->
        <article
            class="edito-contact-card <?php echo $archived ? 'edito-contact-card--archived' : ''; ?>"
            data-id="<?php echo esc_attr( $c['contact_id'] ); ?>">

            <div class="edito-contact-card__body">
                <div class="edito-contact-card__head">
                    <div class="edito-contact-avatar edito-contact-avatar--<?php echo $archived ? 'default' : 'partenaire'; ?>">
                        <?php if ( ! empty( $c['contact_icone'] ) ) : ?>
                        <img src="<?php echo esc_url( $c['contact_icone'] ); ?>"
                             alt="<?php echo esc_attr( $c['contact_nom'] ); ?>">
                        <?php else : ?>
                        <?php echo esc_html( $initials ); ?>
                        <?php endif; ?>
                    </div>
                    <div class="edito-contact-card__info">
                        <h2 class="edito-contact-card__name"><?php echo esc_html( $c['contact_nom'] ); ?></h2>
                        <div class="edito-contact-card__tags">
                            <?php if ( ! empty( $c['contact_type'] ) ) : ?>
                            <span class="edito-badge edito-badge--partenaire"><?php echo esc_html( $c['contact_type'] ); ?></span>
                            <?php endif; ?>
                            <?php if ( $cat_label ) : ?>
                            <span class="edito-badge edito-badge--draft"><?php echo esc_html( ltrim( $cat_label, '— ' ) ); ?></span>
                            <?php endif; ?>
                            <?php if ( $archived ) : ?>
                            <span class="edito-badge edito-badge--archived">Archivé</span>
                            <?php endif; ?>
                            <?php if ( (int) $c['contact_carousel'] ) : ?>
                            <span class="edito-badge edito-badge--carousel">Carrousel</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="edito-contact-details">
                    <?php if ( ! empty( $c['contact_email'] ) ) : ?>
                    <div class="edito-contact-detail">
                        <span class="edito-contact-detail__icon" aria-hidden="true">
                            <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><rect x="1" y="3" width="11" height="8" rx="1.5" stroke="currentColor" stroke-width="1.3"/><path d="M1 4l5.5 3.5L12 4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                        </span>
                        <span class="edito-contact-detail__value">
                            <a href="mailto:<?php echo esc_attr( $c['contact_email'] ); ?>"><?php echo esc_html( $c['contact_email'] ); ?></a>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if ( ! empty( $c['contact_tel'] ) ) : ?>
                    <div class="edito-contact-detail">
                        <span class="edito-contact-detail__icon" aria-hidden="true">
                            <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M3 2h2l1 2.5-1.5 1a7 7 0 0 0 3 3l1-1.5L11 8v2a1 1 0 0 1-1 1A9 9 0 0 1 2 3a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg>
                        </span>
                        <span class="edito-contact-detail__value"><?php echo esc_html( $c['contact_tel'] ); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ( ! empty( $c['contact_ville'] ) ) : ?>
                    <div class="edito-contact-detail">
                        <span class="edito-contact-detail__icon" aria-hidden="true">
                            <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M6.5 2A3.5 3.5 0 0 1 10 5.5C10 8 6.5 11 6.5 11S3 8 3 5.5A3.5 3.5 0 0 1 6.5 2Z" stroke="currentColor" stroke-width="1.3"/><circle cx="6.5" cy="5.5" r="1" stroke="currentColor" stroke-width="1.3"/></svg>
                        </span>
                        <span class="edito-contact-detail__value">
                            <?php echo esc_html( trim( $c['contact_cp'] . ' ' . $c['contact_ville'] ) ); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if ( ! empty( $c['contact_web'] ) ) : ?>
                    <div class="edito-contact-detail">
                        <span class="edito-contact-detail__icon" aria-hidden="true">
                            <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><circle cx="6.5" cy="6.5" r="5" stroke="currentColor" stroke-width="1.3"/><path d="M6.5 1.5C5.5 3 5 4.7 5 6.5s.5 3.5 1.5 5M6.5 1.5C7.5 3 8 4.7 8 6.5s-.5 3.5-1.5 5M1.5 6.5h10" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                        </span>
                        <span class="edito-contact-detail__value">
                            <a href="<?php echo esc_url( $c['contact_web'] ); ?>" target="_blank" rel="noopener">
                                <?php echo esc_html( preg_replace( '#^https?://#', '', rtrim( $c['contact_web'], '/' ) ) ); ?>
                            </a>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="edito-contact-card__footer">
                <span class="edito-contact-card__date">
                    <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $c['updated_at'] ) ) ); ?>
                </span>
                <div class="edito-contact-actions">
                    <button type="button"
                        class="edito-action-btn edito-action-btn--edit"
                        title="Modifier"
                        data-action="edit"
                        data-contact="<?php echo esc_attr( wp_json_encode( $c ) ); ?>">
                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M2 10.5 9.5 3l2 2L4 12.5H2v-2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M8 5 10 7" stroke="currentColor" stroke-width="1.5"/></svg>
                    </button>
                    <button type="button"
                        class="edito-action-btn edito-action-btn--delete"
                        title="Supprimer"
                        data-action="delete"
                        data-id="<?php echo esc_attr( $c['contact_id'] ); ?>"
                        data-name="<?php echo esc_attr( $c['contact_nom'] ); ?>">
                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M2.5 4h9l-.8 7H3.3L2.5 4ZM5 6.5v3M9 6.5v3M1 4h12M5.5 4l.5-1.5h2L8.5 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </div>
            </div>
        </article>

        <?php else : ?>
        <!-- ── Ligne liste ── -->
        <div class="edito-contact-row <?php echo $archived ? 'edito-contact-row--archived' : ''; ?>"
             data-id="<?php echo esc_attr( $c['contact_id'] ); ?>">

            <div class="edito-contact-row__avatar edito-contact-avatar--<?php echo $archived ? 'default' : 'partenaire'; ?>">
                <?php if ( ! empty( $c['contact_icone'] ) ) : ?>
                <img src="<?php echo esc_url( $c['contact_icone'] ); ?>" alt="">
                <?php else : ?>
                <?php echo esc_html( $initials ); ?>
                <?php endif; ?>
            </div>

            <div class="edito-contact-row__name">
                <strong><?php echo esc_html( $c['contact_nom'] ); ?></strong>
                <span><?php echo esc_html( trim( $c['contact_cp'] . ' ' . $c['contact_ville'] ) ); ?></span>
            </div>

            <div class="edito-contact-row__tags">
                <?php if ( ! empty( $c['contact_type'] ) ) : ?>
                <span class="edito-badge edito-badge--partenaire"><?php echo esc_html( $c['contact_type'] ); ?></span>
                <?php endif; ?>
                <?php if ( $cat_label ) : ?>
                <span class="edito-badge edito-badge--draft"><?php echo esc_html( ltrim( $cat_label, '— ' ) ); ?></span>
                <?php endif; ?>
                <?php if ( $archived ) : ?>
                <span class="edito-badge edito-badge--archived">Archivé</span>
                <?php endif; ?>
            </div>

            <div class="edito-contact-row__detail"><?php echo esc_html( $c['contact_email'] ); ?></div>

            <div class="edito-contact-row__actions">
                <button type="button"
                    class="edito-action-btn edito-action-btn--edit"
                    title="Modifier"
                    data-action="edit"
                    data-contact="<?php echo esc_attr( wp_json_encode( $c ) ); ?>">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M2 10.5 9.5 3l2 2L4 12.5H2v-2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M8 5 10 7" stroke="currentColor" stroke-width="1.5"/></svg>
                </button>
                <button type="button"
                    class="edito-action-btn edito-action-btn--delete"
                    title="Supprimer"
                    data-action="delete"
                    data-id="<?php echo esc_attr( $c['contact_id'] ); ?>"
                    data-name="<?php echo esc_attr( $c['contact_nom'] ); ?>">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M2.5 4h9l-.8 7H3.3L2.5 4ZM5 6.5v3M9 6.5v3M1 4h12M5.5 4l.5-1.5h2L8.5 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <?php endforeach; ?>
    </div><!-- /contacts-wrap -->

    <!-- Pagination -->
    <?php if ( $total_pages > 1 ) : ?>
    <div class="edito-pagination">
        <?php
        echo paginate_links( [
            'total'     => $total_pages,
            'current'   => $paged,
            'base'      => add_query_arg( 'paged', '%#%', $contacts_url ),
            'format'    => '',
            'prev_text' => '← Précédent',
            'next_text' => 'Suivant →',
        ] );
        ?>
    </div>
    <?php endif; ?>

    <?php else : ?>
    <div class="edito-empty-state">
        <div class="edito-empty-state__icon">🤝</div>
        <h2>Aucun partenaire</h2>
        <p>
            <?php echo ( $filter_search || $filter_cat )
                ? 'Aucun partenaire ne correspond à ces filtres.'
                : 'Ajoutez votre premier partenaire ou importez une liste CSV.'; ?>
        </p>
        <button type="button" class="edito-btn edito-btn--primary" id="btn-new-contact-empty">
            Nouveau partenaire
        </button>
    </div>
    <?php endif; ?>

</main>

<!-- =====================================================================
     DRAWER — Créer / Modifier un partenaire
     ===================================================================== -->
<div class="edito-drawer-overlay" id="contact-drawer-overlay"
     role="dialog" aria-modal="true" aria-labelledby="contact-drawer-title">
    <div class="edito-drawer">
        <div class="edito-drawer__header">
            <h2 class="edito-drawer__title" id="contact-drawer-title">Nouveau partenaire</h2>
            <button type="button" class="edito-drawer__close" id="contact-drawer-close" aria-label="Fermer">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M4 4l10 10M14 4L4 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            </button>
        </div>

        <div class="edito-drawer__body">
            <input type="hidden" id="c-id">

            <!-- Identité -->
            <div class="edito-form-section">
                <p class="edito-form-section__title">Identité</p>
                <div class="edito-form-group">
                    <label class="edito-label" for="c-nom">Nom <span class="edito-required" aria-hidden="true">*</span></label>
                    <input type="text" id="c-nom" class="edito-input" maxlength="100" required autocomplete="off">
                </div>
                <div class="edito-form-row">
                    <div class="edito-form-group">
                        <label class="edito-label" for="c-type">Type</label>
                        <input type="text" id="c-type" class="edito-input" placeholder="partenaire" maxlength="50" autocomplete="off">
                    </div>
                    <div class="edito-form-group">
                        <label class="edito-label" for="c-cat">Catégorie</label>
                        <div class="edito-select-wrap">
                            <select id="c-cat" class="edito-select">
                                <option value="0">— Aucune —</option>
                                <?php foreach ( $cat_select as $cid => $label ) : ?>
                                <option value="<?php echo esc_attr( $cid ); ?>"><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <svg class="edito-select-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                    </div>
                </div>
                <div class="edito-form-group">
                    <label class="edito-label" for="c-icone">
                        Icône / Logo
                        <span class="edito-label-hint">(URL médiathèque)</span>
                    </label>
                    <input type="url" id="c-icone" class="edito-input" placeholder="https://…" autocomplete="off">
                </div>
            </div>

            <!-- Coordonnées -->
            <div class="edito-form-section">
                <p class="edito-form-section__title">Coordonnées</p>
                <div class="edito-form-group">
                    <label class="edito-label" for="c-adr1">Adresse</label>
                    <input type="text" id="c-adr1" class="edito-input" maxlength="100" autocomplete="off">
                </div>
                <div class="edito-form-row">
                    <div class="edito-form-group">
                        <label class="edito-label" for="c-cp">Code postal</label>
                        <input type="text" id="c-cp" class="edito-input" maxlength="10" autocomplete="off">
                    </div>
                    <div class="edito-form-group">
                        <label class="edito-label" for="c-ville">Ville</label>
                        <input type="text" id="c-ville" class="edito-input" maxlength="80" autocomplete="off">
                    </div>
                </div>
                <div class="edito-form-row">
                    <div class="edito-form-group">
                        <label class="edito-label" for="c-tel">Téléphone</label>
                        <input type="tel" id="c-tel" class="edito-input" maxlength="20" autocomplete="off">
                    </div>
                    <div class="edito-form-group">
                        <label class="edito-label" for="c-email">E-mail</label>
                        <input type="email" id="c-email" class="edito-input" maxlength="100" autocomplete="off">
                    </div>
                </div>
                <div class="edito-form-group">
                    <label class="edito-label" for="c-web">Site web</label>
                    <input type="url" id="c-web" class="edito-input" placeholder="https://…" autocomplete="off">
                </div>
            </div>

            <!-- Notes -->
            <div class="edito-form-section">
                <p class="edito-form-section__title">Notes internes</p>
                <div class="edito-form-group">
                    <textarea id="c-notes" class="edito-textarea" rows="3" placeholder="Informations complémentaires…"></textarea>
                </div>
            </div>

            <!-- Visibilité -->
            <div class="edito-form-section">
                <p class="edito-form-section__title">Visibilité</p>
                <div class="edito-form-group">
                    <label class="edito-toggle-label">
                        <input type="checkbox" id="c-actif" checked>
                        Partenaire actif
                    </label>
                </div>
                <div class="edito-form-group">
                    <label class="edito-toggle-label">
                        <input type="checkbox" id="c-carousel">
                        Afficher dans le carrousel
                    </label>
                </div>
            </div>
        </div>

        <div class="edito-drawer__footer">
            <button type="button" class="edito-btn edito-btn--ghost" id="contact-drawer-cancel">Annuler</button>
            <button type="button" class="edito-btn edito-btn--primary" id="contact-drawer-save">Enregistrer</button>
        </div>
    </div>
</div>

<!-- =====================================================================
     DRAWER — Import CSV (3 étapes)
     ===================================================================== -->
<div class="edito-drawer-overlay" id="import-drawer-overlay"
     role="dialog" aria-modal="true" aria-labelledby="import-drawer-title">
    <div class="edito-drawer edito-drawer--wide">
        <div class="edito-drawer__header">
            <div>
                <h2 class="edito-drawer__title" id="import-drawer-title">Importer des partenaires</h2>
                <div class="edito-import-steps-indicator" aria-hidden="true">
                    <span class="edito-import-step-dot edito-import-step-dot--active" id="dot-1"></span>
                    <span class="edito-import-step-dot" id="dot-2"></span>
                    <span class="edito-import-step-dot" id="dot-3"></span>
                    <span class="edito-import-step-label" id="import-step-label">Étape 1 / 3 — Fichier</span>
                </div>
            </div>
            <button type="button" class="edito-drawer__close" id="import-drawer-close" aria-label="Fermer">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M4 4l10 10M14 4L4 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            </button>
        </div>

        <div class="edito-drawer__body">

            <!-- Étape 1 : Upload -->
            <div class="edito-import-step edito-import-step--active" id="import-step-1">
                <div class="edito-upload-zone" id="import-drop-zone">
                    <input type="file" class="edito-file-input" id="import-file-input" accept=".csv,text/csv,text/plain">
                    <div class="edito-upload-zone__inner">
                        <div class="edito-upload-zone__icon" aria-hidden="true">📂</div>
                        <p class="edito-upload-zone__text">
                            <span class="edito-upload-zone__link">Choisissez un fichier CSV</span> ou déposez-le ici
                        </p>
                        <p class="edito-upload-zone__sub">Séparateur virgule, point-virgule ou tabulation — UTF-8</p>
                    </div>
                </div>
                <div class="edito-upload-progress" id="import-loading" style="display:none;">
                    <div class="edito-progress">
                        <div class="edito-progress__bar" id="import-progress-bar"></div>
                    </div>
                    <span class="edito-progress-label">Analyse du fichier…</span>
                </div>
            </div>

            <!-- Étape 2 : Mapping -->
            <div class="edito-import-step" id="import-step-2">
                <div class="edito-form-group">
                    <label class="edito-label" for="import-default-cat">Catégorie par défaut</label>
                    <div class="edito-select-wrap">
                        <select id="import-default-cat" class="edito-select">
                            <option value="0">— Aucune —</option>
                            <?php foreach ( $cat_parents as $p ) : ?>
                            <option value="<?php echo esc_attr( $p['cat_id'] ); ?>"><?php echo esc_html( $p['cat_name'] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <svg class="edito-select-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                </div>

                <table class="edito-mapping-table" id="import-mapping-table">
                    <thead>
                        <tr>
                            <th>Champ Edito</th>
                            <th>Colonne CSV</th>
                            <th>Aperçu</th>
                        </tr>
                    </thead>
                    <tbody id="import-mapping-body">
                        <!-- Rempli par JS -->
                    </tbody>
                </table>
            </div>

            <!-- Étape 3 : Rapport -->
            <div class="edito-import-step" id="import-step-3">
                <div class="edito-import-report" id="import-report">
                    <!-- Rempli par JS -->
                </div>
            </div>

        </div><!-- /.edito-drawer__body -->

        <div class="edito-drawer__footer">
            <button type="button" class="edito-btn edito-btn--ghost" id="import-drawer-cancel">Fermer</button>
            <button type="button" class="edito-btn edito-btn--ghost" id="import-btn-back" style="display:none;">← Retour</button>
            <button type="button" class="edito-btn edito-btn--primary" id="import-btn-next" style="display:none;">
                Confirmer le mapping →
            </button>
        </div>
    </div>
</div>

<!-- =====================================================================
     MODAL — Confirmation suppression
     ===================================================================== -->
<div class="edito-modal-overlay" id="delete-overlay" style="display:none;"
     role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
    <div class="edito-modal">
        <h3 class="edito-modal__title" id="delete-modal-title">Supprimer le partenaire</h3>
        <p class="edito-modal__text" id="delete-modal-text"></p>
        <div class="edito-modal__actions">
            <button type="button" class="edito-btn edito-btn--outline" id="delete-cancel">Annuler</button>
            <button type="button" class="edito-btn edito-btn--danger" id="delete-confirm">Supprimer</button>
        </div>
    </div>
</div>

<?php wp_footer(); ?>

<script>
/* =========================================================================
   EditoContacts — CRUD partenaires + import CSV
   ========================================================================= */
const EditoContacts = (() => {

    const AJAX  = typeof edito !== 'undefined' ? edito.ajax_url : '/wp-admin/admin-ajax.php';
    const NONCE = typeof edito !== 'undefined' ? edito.nonce   : '';

    /* Catégories (arbre) injecté depuis PHP */
    const CAT_TREE   = <?php echo $cat_tree_json; ?>;
    const CAT_SELECT = (() => {
        const map = {};
        function walk(nodes) {
            nodes.forEach(n => {
                map[n.cat.cat_id] = n.cat.cat_name;
                n.children.forEach(c => { map[c.cat_id] = '— ' + c.cat_name; });
            });
        }
        walk(CAT_TREE);
        return map;
    })();

    /* Champs du mapping CSV → DB */
    const MAPPING_FIELDS = [
        { db: 'contact_nom',   label: 'Nom',        required: true  },
        { db: 'contact_email', label: 'E-mail',      required: false },
        { db: 'contact_type',  label: 'Type',        required: false },
        { db: 'contact_icone', label: 'Icône (URL)', required: false },
        { db: 'contact_adr1',  label: 'Adresse',     required: false },
        { db: 'contact_cp',    label: 'Code postal', required: false },
        { db: 'contact_ville', label: 'Ville',       required: false },
        { db: 'contact_tel',   label: 'Téléphone',   required: false },
        { db: 'contact_web',   label: 'Site web',    required: false },
        { db: 'contact_notes', label: 'Notes',       required: false },
    ];

    /* ── État import ────────────────────────────────────────────────────── */
    let importFile      = null;
    let importHeaders   = [];
    let importRows      = [];
    let importStep      = 1;
    let pendingDeleteId = 0;
    let toastTimer      = null;

    /* ── DOM ────────────────────────────────────────────────────────────── */
    const el = id => document.getElementById(id);

    /* ── Toast ─────────────────────────────────────────────────────────── */
    function showToast(msg, type = '') {
        const t = el('ce-toast');
        if (!t) return;
        t.textContent   = msg;
        t.className     = 'edito-toast' + (type ? ' edito-toast--' + type : '');
        t.style.display = 'block';
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { t.style.display = 'none'; }, 3600);
    }

    /* ── Drawer contact ─────────────────────────────────────────────────── */
    function openContactDrawer(data = {}) {
        el('contact-drawer-title').textContent =
            data.contact_id ? 'Modifier le partenaire' : 'Nouveau partenaire';
        el('c-id').value        = data.contact_id      || '';
        el('c-nom').value       = data.contact_nom     || '';
        el('c-type').value      = data.contact_type    || '';
        el('c-cat').value       = data.contact_cat_id  || 0;
        el('c-icone').value     = data.contact_icone   || '';
        el('c-adr1').value      = data.contact_adr1    || '';
        el('c-cp').value        = data.contact_cp      || '';
        el('c-ville').value     = data.contact_ville   || '';
        el('c-tel').value       = data.contact_tel     || '';
        el('c-email').value     = data.contact_email   || '';
        el('c-web').value       = data.contact_web     || '';
        el('c-notes').value     = data.contact_notes   || '';
        el('c-actif').checked   = data.contact_actif === undefined ? true : !!parseInt(data.contact_actif);
        el('c-carousel').checked= !!parseInt(data.contact_carousel || 0);
        el('contact-drawer-overlay').classList.add('open');
        el('c-nom').focus();
    }

    function closeContactDrawer() {
        el('contact-drawer-overlay').classList.remove('open');
    }

    function saveContact() {
        const nom = el('c-nom').value.trim();
        if (!nom) { el('c-nom').focus(); showToast('Le nom est obligatoire.', 'error'); return; }

        const btn = el('contact-drawer-save');
        btn.disabled = true;

        const fd = new FormData();
        fd.append('action',            'edito_save_contact');
        fd.append('nonce',             NONCE);
        fd.append('contact_id',        el('c-id').value);
        fd.append('contact_nom',       nom);
        fd.append('contact_type',      el('c-type').value.trim() || 'partenaire');
        fd.append('contact_cat_id',    el('c-cat').value);
        fd.append('contact_icone',     el('c-icone').value.trim());
        fd.append('contact_adr1',      el('c-adr1').value.trim());
        fd.append('contact_cp',        el('c-cp').value.trim());
        fd.append('contact_ville',     el('c-ville').value.trim());
        fd.append('contact_tel',       el('c-tel').value.trim());
        fd.append('contact_email',     el('c-email').value.trim());
        fd.append('contact_web',       el('c-web').value.trim());
        fd.append('contact_notes',     el('c-notes').value.trim());
        fd.append('contact_actif',     el('c-actif').checked ? 1 : 0);
        fd.append('contact_carousel',  el('c-carousel').checked ? 1 : 0);

        fetch(AJAX, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    closeContactDrawer();
                    showToast(el('c-id').value ? 'Partenaire modifié.' : 'Partenaire créé.', 'success');
                    setTimeout(() => location.reload(), 800);
                } else {
                    showToast(res.data?.message || 'Erreur.', 'error');
                }
            })
            .catch(() => showToast('Erreur réseau.', 'error'))
            .finally(() => { btn.disabled = false; });
    }

    /* ── Suppression ────────────────────────────────────────────────────── */
    function openDeleteModal(id, name) {
        pendingDeleteId = id;
        el('delete-modal-text').textContent = `Supprimer « ${name} » et toutes ses liaisons ? Cette action est irréversible.`;
        el('delete-overlay').style.display = 'flex';
    }

    function closeDeleteModal() {
        el('delete-overlay').style.display = 'none';
        pendingDeleteId = 0;
    }

    function confirmDelete() {
        if (!pendingDeleteId) return;
        const btn = el('delete-confirm');
        btn.disabled = true;
        const fd = new FormData();
        fd.append('action',     'edito_delete_contact');
        fd.append('nonce',      NONCE);
        fd.append('contact_id', pendingDeleteId);
        fetch(AJAX, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    closeDeleteModal();
                    document.querySelector(`[data-id="${pendingDeleteId}"]`)?.remove();
                    showToast('Partenaire supprimé.', 'success');
                } else {
                    showToast(res.data?.message || 'Erreur.', 'error');
                }
            })
            .catch(() => showToast('Erreur réseau.', 'error'))
            .finally(() => { btn.disabled = false; });
    }

    /* ── Import CSV ─────────────────────────────────────────────────────── */
    function openImportDrawer() {
        resetImport();
        el('import-drawer-overlay').classList.add('open');
    }

    function closeImportDrawer() {
        el('import-drawer-overlay').classList.remove('open');
    }

    function resetImport() {
        importFile    = null;
        importHeaders = [];
        importRows    = [];
        gotoStep(1);
        el('import-file-input').value = '';
        el('import-loading').style.display = 'none';
    }

    function gotoStep(n) {
        importStep = n;
        [1, 2, 3].forEach(i => {
            el(`import-step-${i}`)?.classList.toggle('edito-import-step--active', i === n);
            const dot = el(`dot-${i}`);
            if (!dot) return;
            dot.classList.remove('edito-import-step-dot--active', 'edito-import-step-dot--done');
            if (i < n) dot.classList.add('edito-import-step-dot--done');
            if (i === n) dot.classList.add('edito-import-step-dot--active');
        });
        const labels = ['Étape 1 / 3 — Fichier', 'Étape 2 / 3 — Mapping', 'Étape 3 / 3 — Résultat'];
        el('import-step-label').textContent = labels[n - 1];
        el('import-btn-back').style.display = n === 2 ? 'inline-flex' : 'none';
        el('import-btn-next').style.display = n === 2 ? 'inline-flex' : 'none';
        el('import-drawer-cancel').textContent = n === 3 ? 'Fermer' : 'Annuler';
    }

    function handleCsvFile(file) {
        if (!file) return;
        importFile = file;
        el('import-loading').style.display = 'block';
        animateProgress();

        const fd = new FormData();
        fd.append('action',   'edito_csv_preview');
        fd.append('nonce',    NONCE);
        fd.append('csv_file', file);

        fetch(AJAX, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                el('import-loading').style.display = 'none';
                if (res.success) {
                    importHeaders = res.data.headers;
                    importRows    = res.data.preview;
                    renderMapping();
                    gotoStep(2);
                } else {
                    showToast(res.data?.message || 'Erreur de lecture du fichier.', 'error');
                }
            })
            .catch(() => {
                el('import-loading').style.display = 'none';
                showToast('Erreur réseau.', 'error');
            });
    }

    function animateProgress() {
        const bar = el('import-progress-bar');
        if (!bar) return;
        let w = 0;
        const iv = setInterval(() => {
            w = Math.min(w + 8, 90);
            bar.style.width = w + '%';
            if (w >= 90) clearInterval(iv);
        }, 80);
    }

    function renderMapping() {
        const tbody = el('import-mapping-body');
        if (!tbody) return;
        tbody.innerHTML = '';

        const optionsHtml = ['<option value="">— Ne pas importer —</option>',
            ...importHeaders.map(h => `<option value="${escAttr(h)}">${escHtml(h)}</option>`)
        ].join('');

        MAPPING_FIELDS.forEach(field => {
            /* Auto-détection : on cherche un en-tête CSV dont le nom ressemble */
            const guessed = importHeaders.find(h =>
                h.toLowerCase().replace(/[^a-z]/g,'').includes(
                    field.db.replace('contact_','').replace(/_/g,'')
                )
            ) || '';

            /* Prévisualisation : premières valeurs pour la colonne devinée */
            const preview = guessed
                ? importRows.slice(0,3).map(r => r[guessed]).filter(Boolean).join(', ')
                : '';

            tbody.innerHTML += `
            <tr>
                <td>
                    ${escHtml(field.label)}
                    ${field.required ? '<span class="edito-mapping-required">*</span>' : ''}
                </td>
                <td>
                    <div class="edito-select-wrap edito-select-wrap--sm">
                        <select class="edito-select" id="map-${field.db}" data-db="${field.db}">
                            ${optionsHtml}
                        </select>
                        <svg class="edito-select-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                </td>
                <td>
                    <div class="edito-mapping-preview-values" id="preview-${field.db}">${escHtml(preview)}</div>
                </td>
            </tr>`;
        });

        /* Pré-sélectionner les colonnes devinées */
        MAPPING_FIELDS.forEach(field => {
            const guessed = importHeaders.find(h =>
                h.toLowerCase().replace(/[^a-z]/g,'').includes(
                    field.db.replace('contact_','').replace(/_/g,'')
                )
            ) || '';
            const sel = el(`map-${field.db}`);
            if (sel && guessed) sel.value = guessed;
        });

        /* Mise à jour aperçu au changement de colonne */
        tbody.querySelectorAll('select').forEach(sel => {
            sel.addEventListener('change', () => {
                const db    = sel.dataset.db;
                const col   = sel.value;
                const vals  = col ? importRows.slice(0,3).map(r => r[col]).filter(Boolean).join(', ') : '';
                const prev  = el(`preview-${db}`);
                if (prev) prev.textContent = vals;
            });
        });
    }

    function runImport() {
        const mapSel = document.querySelectorAll('#import-mapping-body select');
        const colMap = {};
        mapSel.forEach(s => { if (s.value) colMap[s.dataset.db] = s.value; });

        if (!colMap['contact_nom']) {
            showToast('La colonne "Nom" est obligatoire.', 'error');
            return;
        }

        const btn = el('import-btn-next');
        btn.disabled    = true;
        btn.textContent = 'Import en cours…';

        const fd = new FormData();
        fd.append('action',          'edito_csv_import');
        fd.append('nonce',           NONCE);
        fd.append('csv_file',        importFile);
        fd.append('col_map',         JSON.stringify(colMap));
        fd.append('default_cat_id',  el('import-default-cat')?.value || 0);

        fetch(AJAX, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    renderReport(res.data);
                    gotoStep(3);
                    showToast(`Import terminé : ${res.data.inserted} ajoutés, ${res.data.updated} mis à jour.`, 'success');
                } else {
                    showToast(res.data?.message || 'Erreur lors de l\'import.', 'error');
                }
            })
            .catch(() => showToast('Erreur réseau.', 'error'))
            .finally(() => { btn.disabled = false; btn.textContent = 'Confirmer le mapping →'; });
    }

    function renderReport(data) {
        const report = el('import-report');
        if (!report) return;

        const errorsHtml = data.errors?.length
            ? `<div class="edito-import-report__errors">
                <p class="edito-import-report__errors-title">${data.errors.length} erreur(s)</p>
                ${data.errors.map(e => `<div class="edito-import-report__error-item">Ligne ${e.row} : ${escHtml(e.message)}</div>`).join('')}
               </div>`
            : '';

        report.innerHTML = `
        <div class="edito-import-report__stats">
            <div class="edito-import-report__stat edito-import-report__stat--inserted">
                <div class="edito-import-report__stat-count">${data.inserted}</div>
                <div class="edito-import-report__stat-label">Ajoutés</div>
            </div>
            <div class="edito-import-report__stat edito-import-report__stat--updated">
                <div class="edito-import-report__stat-count">${data.updated}</div>
                <div class="edito-import-report__stat-label">Mis à jour</div>
            </div>
            <div class="edito-import-report__stat edito-import-report__stat--skipped">
                <div class="edito-import-report__stat-count">${data.skipped}</div>
                <div class="edito-import-report__stat-label">Ignorés</div>
            </div>
        </div>
        ${errorsHtml}`;
    }

    /* ── Sécurité DOM ───────────────────────────────────────────────────── */
    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function escAttr(s) { return escHtml(s); }

    /* ── Initialisation ─────────────────────────────────────────────────── */
    function init() {
        /* Boutons header */
        el('btn-new-contact')?.addEventListener('click', () => openContactDrawer());
        el('btn-new-contact-empty')?.addEventListener('click', () => openContactDrawer());
        el('btn-import-csv')?.addEventListener('click', openImportDrawer);

        /* Drawer contact */
        el('contact-drawer-close')?.addEventListener('click', closeContactDrawer);
        el('contact-drawer-cancel')?.addEventListener('click', closeContactDrawer);
        el('contact-drawer-save')?.addEventListener('click', saveContact);
        el('contact-drawer-overlay')?.addEventListener('click', e => {
            if (e.target === el('contact-drawer-overlay')) closeContactDrawer();
        });

        /* Drawer import */
        el('import-drawer-close')?.addEventListener('click', closeImportDrawer);
        el('import-drawer-cancel')?.addEventListener('click', () => {
            if (importStep === 3) location.reload();
            else closeImportDrawer();
        });
        el('import-btn-back')?.addEventListener('click', () => gotoStep(1));
        el('import-btn-next')?.addEventListener('click', runImport);
        el('import-drawer-overlay')?.addEventListener('click', e => {
            if (e.target === el('import-drawer-overlay')) closeImportDrawer();
        });

        /* Drop zone */
        const dropZone  = el('import-drop-zone');
        const fileInput = el('import-file-input');

        fileInput?.addEventListener('change', e => {
            if (e.target.files[0]) handleCsvFile(e.target.files[0]);
        });

        if (dropZone) {
            dropZone.addEventListener('dragover', e => {
                e.preventDefault();
                dropZone.classList.add('drag-over');
            });
            dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
            dropZone.addEventListener('drop', e => {
                e.preventDefault();
                dropZone.classList.remove('drag-over');
                if (e.dataTransfer.files[0]) handleCsvFile(e.dataTransfer.files[0]);
            });
        }

        /* Délégation sur grille/liste */
        document.getElementById('contacts-wrap')?.addEventListener('click', e => {
            const btn = e.target.closest('button[data-action]');
            if (!btn) return;
            if (btn.dataset.action === 'edit') {
                try { openContactDrawer(JSON.parse(btn.dataset.contact)); }
                catch { showToast('Erreur de lecture des données.', 'error'); }
            } else if (btn.dataset.action === 'delete') {
                openDeleteModal(parseInt(btn.dataset.id), btn.dataset.name);
            }
        });

        /* Modal suppression */
        el('delete-cancel')?.addEventListener('click', closeDeleteModal);
        el('delete-confirm')?.addEventListener('click', confirmDelete);
        el('delete-overlay')?.addEventListener('click', e => {
            if (e.target === el('delete-overlay')) closeDeleteModal();
        });

        /* Échap */
        document.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;
            if (el('delete-overlay')?.style.display === 'flex') closeDeleteModal();
            else if (el('contact-drawer-overlay')?.classList.contains('open')) closeContactDrawer();
            else if (el('import-drawer-overlay')?.classList.contains('open')) closeImportDrawer();
        });
    }

    return { init };
})();

document.addEventListener('DOMContentLoaded', EditoContacts.init);
</script>

</body>
</html>
