<?php
/**
 * Template : Gestion des catégories partenaires
 * Accessible aux éditeurs uniquement.
 * CSS : edito-style.css + categories-style.css (aucun style inline dans ce fichier)
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

/* ── Données ──────────────────────────────────────────────────────────── */
$user           = wp_get_current_user();
$site_name      = get_bloginfo( 'name' );
$dashboard_url  = Edito_Core::page_url( 'dashboard' );
$editor_url     = Edito_Core::page_url( 'editor' );
$contacts_url   = Edito_Core::page_url( 'contacts' );
$categories_url = Edito_Core::page_url( 'categories' );
$logout_url     = wp_logout_url( Edito_Core::page_url( 'login' ) );

$tree           = Edito_Categories::get_tree();

/* Aplatissement des parents pour alimenter le select "parent" du drawer */
$parents = array_map( fn( $node ) => $node['cat'], $tree );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catégories partenaires — <?php echo esc_html( $site_name ); ?></title>
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
        <a class="edito-nav-item" href="<?php echo esc_url( $contacts_url ); ?>">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                <circle cx="9" cy="6" r="3" stroke="currentColor" stroke-width="1.5"/>
                <path d="M2 16c0-3.314 3.134-6 7-6s7 2.686 7 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            Partenaires
        </a>
        <a class="edito-nav-item edito-nav-item--active" href="<?php echo esc_url( $categories_url ); ?>">
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
            <div class="edito-breadcrumb">
                <a href="<?php echo esc_url( $dashboard_url ); ?>">Tableau de bord</a>
                <span class="edito-breadcrumb__sep">›</span>
                <span>Catégories partenaires</span>
            </div>
            <h1 class="edito-page-title">Catégories partenaires</h1>
        </div>
        <button
            type="button"
            class="edito-btn edito-btn--primary"
            id="btn-new-cat"
            aria-haspopup="dialog"
        >
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            Nouvelle catégorie
        </button>
    </header>

    <!-- ── Arborescence ─────────────────────────────────────────────── -->
    <div id="cat-tree-wrap">
        <?php if ( ! empty( $tree ) ) : ?>
        <div class="edito-cat-tree" id="cat-tree">
            <?php foreach ( $tree as $node ) :
                $parent   = $node['cat'];
                $children = $node['children'];
                $nb       = count( $children );
            ?>
            <div class="edito-cat-tree__group"
                 data-id="<?php echo esc_attr( $parent['cat_id'] ); ?>">

                <!-- Ligne niveau 1 -->
                <div class="edito-cat-tree__row edito-cat-tree__parent-row <?php echo $nb > 0 ? 'edito-cat-tree__parent-row--has-children' : ''; ?>"
                     data-id="<?php echo esc_attr( $parent['cat_id'] ); ?>">

                    <?php if ( $nb > 0 ) : ?>
                    <button
                        type="button"
                        class="edito-cat-tree__toggle edito-cat-tree__toggle--open"
                        aria-expanded="true"
                        aria-controls="children-<?php echo esc_attr( $parent['cat_id'] ); ?>"
                        aria-label="Replier les sous-catégories"
                    >
                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                            <path d="M5 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <?php else : ?>
                    <span class="edito-cat-tree__toggle-placeholder" aria-hidden="true"></span>
                    <?php endif; ?>

                    <span class="edito-cat-tree__icon" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <path d="M2 4a1 1 0 0 1 1-1h4l1.5 2H13a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V4Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                        </svg>
                    </span>

                    <span class="edito-cat-tree__name"><?php echo esc_html( $parent['cat_name'] ); ?></span>

                    <span class="edito-cat-tree__slug"><?php echo esc_html( $parent['cat_slug'] ); ?></span>

                    <?php if ( $nb > 0 ) : ?>
                    <span class="edito-cat-tree__count">
                        <?php echo esc_html( $nb . ' sous-cat.' ); ?>
                    </span>
                    <?php endif; ?>

                    <div class="edito-cat-tree__actions" role="group" aria-label="Actions">
                        <button
                            type="button"
                            class="edito-action-btn edito-action-btn--edit"
                            title="Modifier"
                            data-action="edit"
                            data-id="<?php echo esc_attr( $parent['cat_id'] ); ?>"
                            data-name="<?php echo esc_attr( $parent['cat_name'] ); ?>"
                            data-slug="<?php echo esc_attr( $parent['cat_slug'] ); ?>"
                            data-parent="0"
                            data-order="<?php echo esc_attr( $parent['cat_order'] ); ?>"
                        >
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                                <path d="M2 10.5 9.5 3l2 2L4 12.5H2v-2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                <path d="M8 5 10 7" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                        </button>
                        <button
                            type="button"
                            class="edito-action-btn edito-action-btn--delete"
                            title="Supprimer"
                            data-action="delete"
                            data-id="<?php echo esc_attr( $parent['cat_id'] ); ?>"
                            data-name="<?php echo esc_attr( $parent['cat_name'] ); ?>"
                        >
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                                <path d="M2.5 4h9l-.8 7H3.3L2.5 4ZM5 6.5v3M9 6.5v3M1 4h12M5.5 4l.5-1.5h2L8.5 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>
                </div><!-- /parent-row -->

                <!-- Lignes niveau 2 -->
                <?php if ( ! empty( $children ) ) : ?>
                <div class="edito-cat-tree__children"
                     id="children-<?php echo esc_attr( $parent['cat_id'] ); ?>">
                    <?php foreach ( $children as $child ) : ?>
                    <div class="edito-cat-tree__row edito-cat-tree__child-row"
                         data-id="<?php echo esc_attr( $child['cat_id'] ); ?>">

                        <span class="edito-cat-tree__connector" aria-hidden="true">
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                <path d="M2 2v6h8" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>

                        <span class="edito-cat-tree__name"><?php echo esc_html( $child['cat_name'] ); ?></span>

                        <span class="edito-cat-tree__slug"><?php echo esc_html( $child['cat_slug'] ); ?></span>

                        <div class="edito-cat-tree__actions" role="group" aria-label="Actions">
                            <button
                                type="button"
                                class="edito-action-btn edito-action-btn--edit"
                                title="Modifier"
                                data-action="edit"
                                data-id="<?php echo esc_attr( $child['cat_id'] ); ?>"
                                data-name="<?php echo esc_attr( $child['cat_name'] ); ?>"
                                data-slug="<?php echo esc_attr( $child['cat_slug'] ); ?>"
                                data-parent="<?php echo esc_attr( $child['cat_parent'] ); ?>"
                                data-order="<?php echo esc_attr( $child['cat_order'] ); ?>"
                            >
                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                                    <path d="M2 10.5 9.5 3l2 2L4 12.5H2v-2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                    <path d="M8 5 10 7" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                            </button>
                            <button
                                type="button"
                                class="edito-action-btn edito-action-btn--delete"
                                title="Supprimer"
                                data-action="delete"
                                data-id="<?php echo esc_attr( $child['cat_id'] ); ?>"
                                data-name="<?php echo esc_attr( $child['cat_name'] ); ?>"
                            >
                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                                    <path d="M2.5 4h9l-.8 7H3.3L2.5 4ZM5 6.5v3M9 6.5v3M1 4h12M5.5 4l.5-1.5h2L8.5 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            </div><!-- /group -->
            <?php endforeach; ?>
        </div><!-- /cat-tree -->

        <?php else : ?>
        <div class="edito-empty-state" id="cat-empty">
            <div class="edito-empty-state__icon">🗂️</div>
            <h2>Aucune catégorie</h2>
            <p>Créez votre première catégorie pour organiser vos partenaires.</p>
            <button type="button" class="edito-btn edito-btn--primary" id="btn-new-cat-empty">
                Créer une catégorie
            </button>
        </div>
        <?php endif; ?>
    </div><!-- /cat-tree-wrap -->

</main><!-- /edito-main -->

<!-- =====================================================================
     DRAWER — Créer / Modifier une catégorie
     ===================================================================== -->
<div class="edito-drawer-overlay" id="cat-drawer-overlay" role="dialog"
     aria-modal="true" aria-labelledby="drawer-title">
    <div class="edito-drawer">
        <div class="edito-drawer__header">
            <h2 class="edito-drawer__title" id="drawer-title">Nouvelle catégorie</h2>
            <button type="button" class="edito-drawer__close" id="drawer-close" aria-label="Fermer">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                    <path d="M4 4l10 10M14 4L4 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <div class="edito-drawer__body">
            <input type="hidden" id="cat-id" value="0">

            <!-- Nom -->
            <div class="edito-form-group">
                <label class="edito-label" for="cat-name">
                    Nom <span class="edito-required" aria-hidden="true">*</span>
                </label>
                <input
                    type="text"
                    id="cat-name"
                    class="edito-input"
                    placeholder="Ex : Hébergement"
                    maxlength="100"
                    required
                    autocomplete="off"
                >
            </div>

            <!-- Catégorie parente -->
            <div class="edito-form-group">
                <label class="edito-label" for="cat-parent">Catégorie parente</label>
                <div class="edito-select-wrap">
                    <select id="cat-parent" class="edito-select">
                        <option value="0">— Catégorie de niveau 1 (racine) —</option>
                        <?php foreach ( $parents as $p ) : ?>
                        <option value="<?php echo esc_attr( $p['cat_id'] ); ?>">
                            <?php echo esc_html( $p['cat_name'] ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="edito-select-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                        <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="edito-notice edito-notice--info" id="notice-depth" style="">
                    <span class="edito-notice__icon" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                            <circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M7 6v4M7 4.5v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <span>Maximum 2 niveaux. Une sous-catégorie ne peut pas être parente d'une autre sous-catégorie.</span>
                </div>
            </div>

            <!-- Ordre -->
            <div class="edito-form-group">
                <label class="edito-label" for="cat-order">
                    Ordre d'affichage
                    <span class="edito-label-hint">(entier, plus petit = plus haut)</span>
                </label>
                <input
                    type="number"
                    id="cat-order"
                    class="edito-input"
                    value="0"
                    min="0"
                    max="9999"
                >
            </div>

            <!-- Aperçu du slug (lecture seule, mis à jour par JS) -->
            <div class="edito-form-group">
                <label class="edito-label" for="cat-slug">
                    Identifiant (slug)
                    <span class="edito-label-hint">(généré automatiquement)</span>
                </label>
                <input
                    type="text"
                    id="cat-slug"
                    class="edito-input"
                    placeholder="genere-auto"
                    autocomplete="off"
                    spellcheck="false"
                >
            </div>
        </div>

        <div class="edito-drawer__footer">
            <button type="button" class="edito-btn edito-btn--ghost" id="drawer-cancel">
                Annuler
            </button>
            <button type="button" class="edito-btn edito-btn--primary" id="drawer-save">
                Enregistrer
            </button>
        </div>
    </div>
</div>

<!-- =====================================================================
     MODAL — Confirmation de suppression
     ===================================================================== -->
<div class="edito-modal-overlay" id="delete-overlay" style="display:none;"
     role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
    <div class="edito-modal">
        <h3 class="edito-modal__title" id="delete-modal-title">Supprimer la catégorie</h3>
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
   EditoCats — gestion AJAX des catégories partenaires
   ========================================================================= */
const EditoCats = (() => {

    const AJAX  = typeof edito !== 'undefined' ? edito.ajax_url : '/wp-admin/admin-ajax.php';
    const NONCE = typeof edito !== 'undefined' ? edito.nonce   : '';

    /* ── État ──────────────────────────────────────────────────────────── */
    let pendingDeleteId   = 0;
    let toastTimer        = null;

    /* ── Raccourcis DOM ─────────────────────────────────────────────────── */
    const el = id => document.getElementById( id );

    /* ── Toast ─────────────────────────────────────────────────────────── */
    function showToast( msg, type = '' ) {
        const t = el( 'ce-toast' );
        if ( ! t ) return;
        t.textContent       = msg;
        t.className         = 'edito-toast' + ( type ? ' edito-toast--' + type : '' );
        t.style.display     = 'block';
        clearTimeout( toastTimer );
        toastTimer = setTimeout( () => { t.style.display = 'none'; }, 3600 );
    }

    /* ── Drawer ─────────────────────────────────────────────────────────── */
    function openDrawer( data = {} ) {
        const overlay = el( 'cat-drawer-overlay' );

        el( 'drawer-title'  ).textContent = data.cat_id ? 'Modifier la catégorie' : 'Nouvelle catégorie';
        el( 'cat-id'        ).value  = data.cat_id   || 0;
        el( 'cat-name'      ).value  = data.cat_name || '';
        el( 'cat-slug'      ).value  = data.cat_slug || '';
        el( 'cat-order'     ).value  = data.cat_order !== undefined ? data.cat_order : 0;
        el( 'cat-parent'    ).value  = data.cat_parent || 0;

        overlay.classList.add( 'open' );
        el( 'cat-name' ).focus();
    }

    function closeDrawer() {
        el( 'cat-drawer-overlay' ).classList.remove( 'open' );
    }

    /* ── Arbre — rendu JS (après AJAX) ───────────────────────────────────── */
    function buildRowActions( cat, isChild ) {
        return `<div class="edito-cat-tree__actions" role="group" aria-label="Actions">
            <button type="button" class="edito-action-btn edito-action-btn--edit" title="Modifier"
                data-action="edit"
                data-id="${ cat.cat_id }"
                data-name="${ escAttr( cat.cat_name ) }"
                data-slug="${ escAttr( cat.cat_slug ) }"
                data-parent="${ cat.cat_parent }"
                data-order="${ cat.cat_order }">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                    <path d="M2 10.5 9.5 3l2 2L4 12.5H2v-2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                    <path d="M8 5 10 7" stroke="currentColor" stroke-width="1.5"/>
                </svg>
            </button>
            <button type="button" class="edito-action-btn edito-action-btn--delete" title="Supprimer"
                data-action="delete"
                data-id="${ cat.cat_id }"
                data-name="${ escAttr( cat.cat_name ) }">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                    <path d="M2.5 4h9l-.8 7H3.3L2.5 4ZM5 6.5v3M9 6.5v3M1 4h12M5.5 4l.5-1.5h2L8.5 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>`;
    }

    function buildTree( tree ) {
        if ( ! tree.length ) {
            return `<div class="edito-empty-state" id="cat-empty">
                <div class="edito-empty-state__icon">🗂️</div>
                <h2>Aucune catégorie</h2>
                <p>Créez votre première catégorie pour organiser vos partenaires.</p>
                <button type="button" class="edito-btn edito-btn--primary" id="btn-new-cat-empty">
                    Créer une catégorie
                </button>
            </div>`;
        }

        const groups = tree.map( node => {
            const p   = node.cat;
            const ch  = node.children;
            const nb  = ch.length;

            const toggleBtn = nb > 0
                ? `<button type="button"
                       class="edito-cat-tree__toggle edito-cat-tree__toggle--open"
                       aria-expanded="true"
                       aria-controls="children-${p.cat_id}"
                       aria-label="Replier les sous-catégories">
                       <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                           <path d="M5 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                       </svg>
                   </button>`
                : `<span class="edito-cat-tree__toggle-placeholder" aria-hidden="true"></span>`;

            const countBadge = nb > 0
                ? `<span class="edito-cat-tree__count">${nb} sous-cat.</span>` : '';

            const childRows = ch.map( child => `
                <div class="edito-cat-tree__row edito-cat-tree__child-row" data-id="${child.cat_id}">
                    <span class="edito-cat-tree__connector" aria-hidden="true">
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                            <path d="M2 2v6h8" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span class="edito-cat-tree__name">${ escHtml( child.cat_name ) }</span>
                    <span class="edito-cat-tree__slug">${ escHtml( child.cat_slug ) }</span>
                    ${ buildRowActions( child, true ) }
                </div>` ).join('');

            const childBlock = nb > 0
                ? `<div class="edito-cat-tree__children" id="children-${p.cat_id}">${childRows}</div>`
                : '';

            const hasChildrenClass = nb > 0 ? 'edito-cat-tree__parent-row--has-children' : '';

            return `
            <div class="edito-cat-tree__group" data-id="${p.cat_id}">
                <div class="edito-cat-tree__row edito-cat-tree__parent-row ${hasChildrenClass}" data-id="${p.cat_id}">
                    ${toggleBtn}
                    <span class="edito-cat-tree__icon" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <path d="M2 4a1 1 0 0 1 1-1h4l1.5 2H13a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V4Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span class="edito-cat-tree__name">${ escHtml( p.cat_name ) }</span>
                    <span class="edito-cat-tree__slug">${ escHtml( p.cat_slug ) }</span>
                    ${countBadge}
                    ${ buildRowActions( p, false ) }
                </div>
                ${childBlock}
            </div>`;
        });

        return `<div class="edito-cat-tree" id="cat-tree">${ groups.join('') }</div>`;
    }

    function renderTree( tree ) {
        el( 'cat-tree-wrap' ).innerHTML = buildTree( tree );
        updateParentSelect( tree );
        bindTreeEvents();
    }

    /* Met à jour le select "parent" du drawer avec les catégories de niveau 1 */
    function updateParentSelect( tree ) {
        const sel = el( 'cat-parent' );
        const current = sel.value;
        // Vider sauf l'option racine
        while ( sel.options.length > 1 ) sel.remove( 1 );
        tree.forEach( node => {
            const p   = node.cat;
            const opt = document.createElement( 'option' );
            opt.value       = p.cat_id;
            opt.textContent = p.cat_name;
            sel.appendChild( opt );
        });
        sel.value = current;
    }

    /* ── Sauvegarde (AJAX) ───────────────────────────────────────────────── */
    function saveCategory() {
        const name  = el( 'cat-name'   ).value.trim();
        const id    = parseInt( el( 'cat-id' ).value, 10 );
        const parent= parseInt( el( 'cat-parent' ).value, 10 );
        const order = parseInt( el( 'cat-order' ).value, 10 ) || 0;
        const slug  = el( 'cat-slug'  ).value.trim();

        if ( ! name ) {
            el( 'cat-name' ).focus();
            showToast( 'Le nom est obligatoire.', 'error' );
            return;
        }

        const btn = el( 'drawer-save' );
        btn.disabled = true;

        const fd = new FormData();
        fd.append( 'action',     'edito_save_contact_cat' );
        fd.append( 'nonce',      NONCE );
        fd.append( 'cat_id',     id );
        fd.append( 'cat_name',   name );
        fd.append( 'cat_parent', parent );
        fd.append( 'cat_order',  order );
        fd.append( 'cat_slug',   slug );

        fetch( AJAX, { method: 'POST', body: fd } )
            .then( r => r.json() )
            .then( res => {
                if ( res.success ) {
                    closeDrawer();
                    renderTree( res.data.tree );
                    showToast( id ? 'Catégorie modifiée.' : 'Catégorie créée.', 'success' );
                } else {
                    showToast( res.data?.message || 'Erreur.', 'error' );
                }
            })
            .catch( () => showToast( 'Erreur réseau.', 'error' ) )
            .finally( () => { btn.disabled = false; });
    }

    /* ── Suppression (AJAX) ─────────────────────────────────────────────── */
    function openDeleteModal( id, name ) {
        pendingDeleteId = id;
        el( 'delete-modal-text' ).textContent =
            `Supprimer « ${ name } » ? Cette action est irréversible.`;
        el( 'delete-overlay' ).style.display = 'flex';
    }

    function closeDeleteModal() {
        el( 'delete-overlay' ).style.display = 'none';
        pendingDeleteId = 0;
    }

    function confirmDelete() {
        if ( ! pendingDeleteId ) return;

        const btn = el( 'delete-confirm' );
        btn.disabled = true;

        const fd = new FormData();
        fd.append( 'action', 'edito_delete_contact_cat' );
        fd.append( 'nonce',  NONCE );
        fd.append( 'cat_id', pendingDeleteId );

        fetch( AJAX, { method: 'POST', body: fd } )
            .then( r => r.json() )
            .then( res => {
                if ( res.success ) {
                    closeDeleteModal();
                    renderTree( res.data.tree );
                    showToast( 'Catégorie supprimée.', 'success' );
                } else {
                    showToast( res.data?.message || 'Erreur.', 'error' );
                }
            })
            .catch( () => showToast( 'Erreur réseau.', 'error' ) )
            .finally( () => { btn.disabled = false; });
    }

    /* ── Génération de slug côté client ─────────────────────────────────── */
    function slugify( str ) {
        return str
            .toLowerCase()
            .normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' )
            .replace( /[^a-z0-9\s-]/g, '' )
            .trim()
            .replace( /[\s]+/g, '-' );
    }

    /* ── Échappe HTML/attributs pour le rendu JS ─────────────────────────── */
    function escHtml( s ) {
        return String( s )
            .replace( /&/g, '&amp;' ).replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
    }
    function escAttr( s ) { return escHtml( s ); }

    /* ── Délégation d'événements sur l'arbre ─────────────────────────────── */
    function bindTreeEvents() {
        const wrap = el( 'cat-tree-wrap' );
        if ( ! wrap ) return;

        wrap.addEventListener( 'click', e => {
            const btn = e.target.closest( 'button[data-action]' );
            if ( ! btn ) {
                // Toggle chevron
                const toggle = e.target.closest( '.edito-cat-tree__toggle' );
                if ( toggle ) {
                    const id       = toggle.closest( '[data-id]' ).dataset.id;
                    const children = document.getElementById( 'children-' + id );
                    if ( children ) {
                        const isOpen = toggle.classList.toggle( 'edito-cat-tree__toggle--open' );
                        toggle.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
                        toggle.setAttribute( 'aria-label', isOpen ? 'Replier les sous-catégories' : 'Déplier les sous-catégories' );
                        children.style.display = isOpen ? '' : 'none';
                    }
                }
                return;
            }

            const action = btn.dataset.action;
            const id     = parseInt( btn.dataset.id, 10 );
            const name   = btn.dataset.name || '';

            if ( action === 'edit' ) {
                openDrawer({
                    cat_id:     id,
                    cat_name:   name,
                    cat_slug:   btn.dataset.slug   || '',
                    cat_parent: parseInt( btn.dataset.parent || 0, 10 ),
                    cat_order:  parseInt( btn.dataset.order  || 0, 10 ),
                });
            } else if ( action === 'delete' ) {
                openDeleteModal( id, name );
            }

            // Bouton vide "Créer" re-généré dans l'état vide
            const emptyBtn = e.target.closest( '#btn-new-cat-empty' );
            if ( emptyBtn ) openDrawer();
        });
    }

    /* ── Initialisation ─────────────────────────────────────────────────── */
    function init() {
        /* Bouton principal "Nouvelle catégorie" */
        el( 'btn-new-cat' )?.addEventListener( 'click', () => openDrawer() );
        el( 'btn-new-cat-empty' )?.addEventListener( 'click', () => openDrawer() );

        /* Drawer */
        el( 'drawer-close'  )?.addEventListener( 'click', closeDrawer );
        el( 'drawer-cancel' )?.addEventListener( 'click', closeDrawer );
        el( 'drawer-save'   )?.addEventListener( 'click', saveCategory );

        /* Fermeture drawer en cliquant sur l'overlay */
        el( 'cat-drawer-overlay' )?.addEventListener( 'click', e => {
            if ( e.target === el( 'cat-drawer-overlay' ) ) closeDrawer();
        });

        /* Génération slug automatique depuis le nom */
        el( 'cat-name' )?.addEventListener( 'input', e => {
            const slugField = el( 'cat-slug' );
            if ( ! slugField || slugField.dataset.manual === 'true' ) return;
            slugField.value = slugify( e.target.value );
        });

        /* Si l'utilisateur modifie le slug manuellement, on arrête l'auto-génération */
        el( 'cat-slug' )?.addEventListener( 'input', e => {
            e.target.dataset.manual = 'true';
        });

        /* Touche Entrée dans le champ nom → sauvegarder */
        el( 'cat-name' )?.addEventListener( 'keydown', e => {
            if ( e.key === 'Enter' ) saveCategory();
        });

        /* Touche Échap → fermer drawer ou modal */
        document.addEventListener( 'keydown', e => {
            if ( e.key !== 'Escape' ) return;
            if ( el( 'delete-overlay' )?.style.display === 'flex' ) closeDeleteModal();
            else closeDrawer();
        });

        /* Modal suppression */
        el( 'delete-cancel' )?.addEventListener( 'click', closeDeleteModal );
        el( 'delete-confirm')?.addEventListener( 'click', confirmDelete );
        el( 'delete-overlay')?.addEventListener( 'click', e => {
            if ( e.target === el( 'delete-overlay' ) ) closeDeleteModal();
        });

        /* Délégation initiale sur l'arbre existant */
        bindTreeEvents();
    }

    return { init };

})();

document.addEventListener( 'DOMContentLoaded', EditoCats.init );
</script>

</body>
</html>
