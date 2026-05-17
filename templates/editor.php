<?php
/**
 * Template : Éditeur d'article (auteurs & éditeurs)
 */
defined( 'ABSPATH' ) || exit;

$user     = wp_get_current_user();
$post_id  = (int) ( $_GET['post_id'] ?? 0 );
$is_edit  = $post_id > 0;
$post     = $is_edit ? get_post( $post_id ) : null;

// Vérifier droits si édition
if ( $is_edit && $post ) {
    if (
        (int) $post->post_author !== $user->ID &&
        ! current_user_can( 'edit_others_posts' )
    ) {
        wp_redirect( Edito_Core::page_url( 'editor' ) );
        exit;
    }
}

// Données de l'article existant
$edit_title    = $is_edit && $post ? esc_html( $post->post_title ) : '';
$edit_content  = $is_edit && $post ? $post->post_content : '';
$edit_status   = $is_edit && $post ? $post->post_status : 'draft';

/*
 * MULTI-CATÉGORIES
 * On récupère tous les IDs de catégories affectés à l'article (tableau d'entiers).
 * Utilisé pour pré-cocher les checkboxes au chargement.
 */
$checked_cat_ids = $is_edit
    ? wp_get_post_categories( $post_id, [ 'fields' => 'ids' ] )
    : [];

/*
 * DATE DE CRÉATION
 * Convertit la date GMT stockée en BDD vers le fuseau local du site
 * au format attendu par <input type="datetime-local"> : 'Y-m-d\TH:i'.
 */
$post_date_local = ( $is_edit && $post )
    ? get_date_from_gmt( $post->post_date_gmt, 'Y-m-d\TH:i' )
    : current_time( 'Y-m-d\TH:i' );

$edit_gallery  = $is_edit ? Edito_Editor::get_gallery( $post_id ) : [];

// ── Contact lié ──────────────────────────────────────────────────────────────
// Récupère l'ID du premier contact lié (liaison 1:1 côté UI, n:n côté DB)
$edit_contact_id = 0;
$edit_contact    = null;
if ( $is_edit && class_exists( 'Edito_DB' ) ) {
    $linked_ids      = Edito_DB::get_linked_contact_ids( $post_id );
    $edit_contact_id = ! empty( $linked_ids ) ? (int) $linked_ids[0] : 0;
    if ( $edit_contact_id ) {
        $edit_contact = Edito_DB::get_contact( $edit_contact_id );
    }
}

// Liste des contacts actifs pour le select
$contacts_list = class_exists( 'Edito_DB' ) ? Edito_DB::get_contacts_list() : [];
// ─────────────────────────────────────────────────────────────────────────────

$categories    = get_terms( [ 'taxonomy' => 'category', 'hide_empty' => false ] );
$is_editor     = current_user_can( 'edit_others_posts' );
$site_name     = get_bloginfo( 'name' );

$dashboard_url = Edito_Core::page_url( 'dashboard' );
$editor_url    = Edito_Core::page_url( 'editor' );
$contacts_url  = Edito_Core::page_url( 'contacts' );
$logout_url    = wp_logout_url( Edito_Core::page_url( 'login' ) );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Modifier' : 'Nouvel article'; ?> — <?php echo esc_html( $site_name ); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url( EDITO_URL . 'assets/css/edito-style.css' ); ?>?v=<?php echo EDITO_VERSION; ?>" data-no-optimize="1">
    <?php wp_head(); ?>
    <style>
    /* ── Checkboxes catégories ──────────────────────────────────────────────── */
    .edito-cats-checkboxes {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    .edito-cat-checkbox {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        border-radius: var(--edito-radius);
        border: 1px solid var(--edito-border-light);
        cursor: pointer;
        transition: border-color .15s, background .15s;
        font-size: .875rem;
        color: var(--edito-text);
        user-select: none;
        position: relative;
    }
    .edito-cat-checkbox:hover {
        border-color: var(--edito-primary);
        background: rgba(37,99,235,.03);
    }
    .edito-cat-checkbox--checked {
        border-color: var(--edito-primary);
        background: #eff6ff;
    }
    .edito-cat-checkbox--disabled {
        opacity: .4;
        cursor: not-allowed;
        pointer-events: none;
    }
    /* Masque la case native */
    .edito-cat-checkbox__input {
        position: absolute;
        opacity: 0;
        width: 0; height: 0;
    }
    .edito-cat-checkbox__icon {
        width: 16px; height: 16px;
        object-fit: contain;
        flex-shrink: 0;
    }
    .edito-cat-checkbox__dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: var(--edito-border);
        flex-shrink: 0;
        transition: background .15s;
    }
    .edito-cat-checkbox--checked .edito-cat-checkbox__dot {
        background: var(--edito-primary);
    }
    .edito-cat-checkbox__name { flex: 1; line-height: 1.3; }
    /* Coche visuelle */
    .edito-cat-checkbox--checked::after {
        content: '';
        width: 16px; height: 16px;
        background: var(--edito-primary) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' fill='none'%3E%3Cpath d='M2 6l3 3 5-5' stroke='%23fff' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") center / 10px no-repeat;
        border-radius: 4px;
        flex-shrink: 0;
    }
    .edito-cats-hint {
        margin: 8px 0 0;
        font-size: .78rem;
        color: #dc2626;
        min-height: 1.1em;
    }
    </style>
</head>
<body class="edito-app-body">
<?php wp_body_open(); ?>

<div class="edito-app">

    <!-- =================================================================
         SIDEBAR / NAV
         ================================================================= -->
    <aside class="edito-sidebar">
        <div class="edito-sidebar__brand">
            <div class="edito-sidebar__logo">
                <svg width="22" height="22" viewBox="0 0 28 28" fill="none"><rect width="28" height="28" rx="7" fill="#c9a96e"/><path d="M8 9h12M8 14h8M8 19h10" stroke="#1a1a2e" stroke-width="2" stroke-linecap="round"/></svg>
            </div>
            <span class="edito-sidebar__site"><?php echo esc_html( $site_name ); ?></span>
        </div>

        <nav class="edito-sidebar__nav">
            <?php if ( $is_editor ) : ?>
            <a class="edito-nav-item" href="<?php echo esc_url( $dashboard_url ); ?>">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="1" y="1" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/><rect x="1" y="11" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/><rect x="11" y="1" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/><rect x="11" y="11" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/></svg>
                Gestion articles
            </a>
            <?php endif; ?>
            <a class="edito-nav-item edito-nav-item--active" href="<?php echo esc_url( $editor_url ); ?>">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M3 13.5 12.5 4l2 2L5 15.5H3v-2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M10.5 6 12.5 8" stroke="currentColor" stroke-width="1.5"/></svg>
                <?php echo $is_edit ? 'Modifier l\'article' : 'Nouvel article'; ?>
            </a>
            <?php if ( ! $is_edit ) : ?>
            <a class="edito-nav-item" href="<?php echo esc_url( add_query_arg( 'filter_author', $user->ID, $dashboard_url ) ); ?>">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M2 14.5V13a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v1.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="9" cy="5.5" r="2.5" stroke="currentColor" stroke-width="1.5"/></svg>
                Mes articles
            </a>
            <?php endif; ?>
            <div style="margin:10px 16px; border-top:1px solid rgba(255,255,255,.08);"></div>
            <a class="edito-nav-item" href="<?php echo esc_url( $contacts_url ); ?>">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="6" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M2 15c0-3.314 3.134-6 7-6s7 2.686 7 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                Gestion contacts
            </a>
        </nav>

        <div class="edito-sidebar__footer">
            <div class="edito-sidebar__user">
                <div class="edito-sidebar__avatar"><?php echo esc_html( mb_substr( $user->display_name, 0, 1 ) ); ?></div>
                <div>
                    <p class="edito-sidebar__user-name"><?php echo esc_html( $user->display_name ); ?></p>
                    <p class="edito-sidebar__user-role"><?php echo esc_html( ucfirst( implode( ', ', $user->roles ) ) ); ?></p>
                </div>
            </div>
            <a class="edito-sidebar__logout" href="<?php echo esc_url( $logout_url ); ?>" title="Déconnexion">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M6 2H3a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3M11 11l3-3-3-3M14 8H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
        </div>
    </aside>

    <!-- =================================================================
         CONTENU PRINCIPAL
         ================================================================= -->
    <main class="edito-main">

        <div class="edito-toast" id="ce-toast" role="alert" aria-live="polite" style="display:none;"></div>

        <header class="edito-page-header">
            <div>
                <div class="edito-breadcrumb">
                    <span>Rédaction</span>
                    <span class="edito-breadcrumb__sep">›</span>
                    <span><?php echo $is_edit ? 'Modifier' : 'Nouvel article'; ?></span>
                </div>
                <h1 class="edito-page-title"><?php echo $is_edit ? 'Modifier l\'article' : 'Rédiger un article'; ?></h1>
            </div>
            <?php if ( $is_edit && $post ) : ?>
            <div class="edito-badge <?php echo esc_attr( Edito_Core::status_badge_class( $edit_status ) ); ?>">
                <?php echo esc_html( Edito_Core::status_label( $edit_status ) ); ?>
            </div>
            <?php endif; ?>
        </header>

        <div class="edito-editor-layout">

            <!-- Zone principale -->
            <div class="edito-editor-main">

                <!-- Titre -->
                <div class="edito-card">
                    <label class="edito-label" for="ce-title">
                        Titre de l'article <span class="edito-required">*</span>
                    </label>
                    <input
                        class="edito-input edito-input--title"
                        type="text"
                        id="ce-title"
                        name="ce_title"
                        placeholder="Saisissez le titre de l'article…"
                        value="<?php echo esc_attr( $edit_title ); ?>"
                        required
                    >
                </div>

                <!-- Contenu -->
                <div class="edito-card">
                    <label class="edito-label" for="ce-content">Contenu de l'article</label>
                    <div class="edito-toolbar" role="toolbar" aria-label="Mise en forme du texte">
                        <button type="button" class="edito-tb-btn" data-cmd="bold"                title="Gras"><strong>B</strong></button>
                        <button type="button" class="edito-tb-btn" data-cmd="italic"              title="Italique"><em>I</em></button>
                        <button type="button" class="edito-tb-btn" data-cmd="underline"           title="Souligné"><u>S</u></button>
                        <div class="edito-toolbar__sep"></div>
                        <button type="button" class="edito-tb-btn" data-cmd="insertUnorderedList" title="Liste à puces">≡</button>
                        <button type="button" class="edito-tb-btn" data-cmd="insertOrderedList"   title="Liste numérotée">1.</button>
                        <div class="edito-toolbar__sep"></div>
                        <select class="edito-tb-select" data-cmd="formatBlock" title="Style de paragraphe">
                            <option value="p">Paragraphe</option>
                            <option value="h2">Titre 2</option>
                            <option value="h3">Titre 3</option>
                            <option value="h4">Titre 4</option>
                            <option value="blockquote">Citation</option>
                        </select>
                        <div class="edito-toolbar__sep"></div>
                        <button type="button" class="edito-tb-btn" data-cmd="justifyLeft"   title="Aligner à gauche">⬱</button>
                        <button type="button" class="edito-tb-btn" data-cmd="justifyCenter" title="Centrer">≡</button>
                    </div>
                    <div
                        class="edito-content-editor"
                        id="ce-content"
                        contenteditable="true"
                        role="textbox"
                        aria-multiline="true"
                        aria-label="Corps de l'article"
                        data-placeholder="Commencez à rédiger votre article ici…"
                    ><?php echo wp_kses_post( $edit_content ); ?></div>
                    <input type="hidden" id="ce-content-hidden" name="ce_content">
                </div>

                <!-- Photos -->
                <div class="edito-card" id="ce-photos-card">
                    <div class="edito-card__header">
                        <label class="edito-label">
                            Photos de l'article
                            <span class="edito-label-hint">(4 à 5 photos, formats JPG / PNG / WebP, max 8 Mo)</span>
                        </label>
                        <span class="edito-photo-counter"><span id="ce-photo-count"><?php echo count( $edit_gallery ); ?></span>/5</span>
                    </div>

                    <div class="edito-upload-zone" id="ce-upload-zone" tabindex="0" role="button" aria-label="Zone d'upload de photos">
                        <div class="edito-upload-zone__inner">
                            <div class="edito-upload-zone__icon">
                                <svg width="40" height="40" viewBox="0 0 40 40" fill="none"><rect x="1" y="8" width="38" height="28" rx="4" stroke="currentColor" stroke-width="1.5"/><circle cx="13" cy="18" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M1 29l10-8 8 6 5-4 15 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M20 8V1M17 4l3-3 3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </div>
                            <p class="edito-upload-zone__text">Glissez vos photos ici ou <span class="edito-upload-zone__link">parcourez</span></p>
                            <p class="edito-upload-zone__sub">JPG, PNG, GIF, WebP — Max 8 Mo par photo</p>
                        </div>
                        <input type="file" class="edito-file-input" id="ce-file-input" accept="image/*" multiple>
                    </div>

                    <div class="edito-gallery" id="ce-gallery">
                        <?php foreach ( $edit_gallery as $img ) : ?>
                        <div class="edito-gallery__item" data-id="<?php echo esc_attr( $img['id'] ); ?>">
                            <img src="<?php echo esc_url( $img['thumb'] ); ?>" alt="<?php echo esc_attr( $img['title'] ); ?>" loading="lazy">
                            <div class="edito-gallery__overlay">
                                <button type="button" class="edito-gallery__remove" title="Supprimer cette photo" data-id="<?php echo esc_attr( $img['id'] ); ?>">
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 2l10 10M12 2L2 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                </button>
                            </div>
                            <input type="hidden" name="ce_photo_ids[]" value="<?php echo esc_attr( $img['id'] ); ?>">
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="edito-upload-progress" id="ce-upload-progress" style="display:none;">
                        <div class="edito-progress-bar">
                            <div class="edito-progress-bar__fill" id="ce-progress-fill"></div>
                        </div>
                        <span class="edito-progress-label" id="ce-progress-label">Upload en cours…</span>
                    </div>
                </div>

            </div><!-- .edito-editor-main -->

            <!-- ============================================================
                 PANNEAU LATÉRAL
                 ============================================================ -->
            <aside class="edito-editor-sidebar">

                <!-- ── CATÉGORIES (multi-sélection, 1 à 3) ────────────────── -->
                <div class="edito-card">
                    <p class="edito-card__title">
                        Catégories <span class="edito-required">*</span>
                        <span class="edito-label-hint" style="font-size:.74rem;"> (1 à 3)</span>
                    </p>

                    <!--
                        Champs cachés lus par edito-auteur.js lors de la sauvegarde :
                        • ce_category     → premier ID (compatibilité avec l'ancien code)
                        • ce_cat_ids      → tous les IDs, séparés par des virgules (nouveau)
                        Le JS ci-dessous les maintient synchronisés avec les checkboxes.
                    -->
                    <input type="hidden" id="ce-category"  name="ce_category"  value="<?php echo esc_attr( $checked_cat_ids[0] ?? '' ); ?>">
                    <input type="hidden" id="ce-cat-ids"   name="ce_cat_ids"   value="<?php echo esc_attr( implode( ',', $checked_cat_ids ) ); ?>">

                    <div
                        class="edito-cats-checkboxes"
                        id="edito-cats-checkboxes"
                        role="group"
                        aria-label="Catégories de l'article"
                    >
                        <?php foreach ( $categories as $cat ) :
                            $is_checked = in_array( (int) $cat->term_id, array_map( 'intval', $checked_cat_ids ), true );
                            $cat_icon   = Edito_Admin::get_category_icon( $cat->slug );
                        ?>
                        <label
                            class="edito-cat-checkbox <?php echo $is_checked ? 'edito-cat-checkbox--checked' : ''; ?>"
                            for="edito-cat-<?php echo esc_attr( $cat->term_id ); ?>"
                        >
                            <input
                                type="checkbox"
                                class="edito-cat-checkbox__input"
                                id="edito-cat-<?php echo esc_attr( $cat->term_id ); ?>"
                                value="<?php echo esc_attr( $cat->term_id ); ?>"
                                <?php checked( $is_checked ); ?>
                            >
                            <?php if ( $cat_icon ) : ?>
                            <img
                                src="<?php echo esc_url( $cat_icon ); ?>"
                                width="16" height="16"
                                alt=""
                                class="edito-cat-checkbox__icon"
                            >
                            <?php else : ?>
                            <span class="edito-cat-checkbox__dot"></span>
                            <?php endif; ?>
                            <span class="edito-cat-checkbox__name"><?php echo esc_html( $cat->name ); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <p class="edito-cats-hint" id="edito-cats-hint" aria-live="polite"></p>
                </div>
                <!-- ── Fin Catégories ──────────────────────────────────────── -->

                <!-- ── Client / Partenaire ─────────────────────────────── -->
                <?php if ( ! empty( $contacts_list ) ) : ?>
                <div class="edito-card">
                    <label class="edito-label" for="ce-contact">
                        Client / Partenaire
                        <span class="edito-label-hint">(optionnel)</span>
                    </label>
                    <div class="edito-select-wrap">
                        <select class="edito-select" id="ce-contact" name="ce_contact">
                            <option value="">— Aucun contact —</option>
                            <?php foreach ( $contacts_list as $c ) : ?>
                            <option
                                value="<?php echo esc_attr( $c['contact_id'] ); ?>"
                                data-type="<?php echo esc_attr( $c['contact_type'] ); ?>"
                                data-categorie="<?php echo esc_attr( $c['contact_categorie'] ); ?>"
                                <?php selected( (int) $c['contact_id'], $edit_contact_id ); ?>
                            >
                                <?php echo esc_html( $c['contact_nom'] ); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <svg class="edito-select-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>

                    <!-- Aperçu du contact sélectionné -->
                    <div class="edito-cat-preview" id="ce-contact-preview"
                         style="<?php echo $edit_contact_id ? '' : 'display:none;'; ?> align-items:center; gap:8px; margin-top:10px;">
                        <?php if ( $edit_contact ) :
                            $type_label = 'client' === $edit_contact['contact_type'] ? 'Client' : 'Partenaire';
                            $cat_label  = 'agrement' === $edit_contact['contact_categorie'] ? 'Agrément' : 'NCO';
                        ?>
                        <?php if ( $edit_contact['contact_icone'] ) : ?>
                        <img src="<?php echo esc_url( $edit_contact['contact_icone'] ); ?>"
                             alt="<?php echo esc_attr( $edit_contact['contact_nom'] ); ?>"
                             id="ce-contact-preview-img"
                             style="width:32px;height:32px;object-fit:contain;border-radius:6px;border:1px solid var(--edito-border-light);">
                        <?php else : ?>
                        <div id="ce-contact-preview-img"
                             style="width:32px;height:32px;border-radius:6px;background:#eff6ff;color:#2563eb;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.75rem;flex-shrink:0;border:1px solid #bfdbfe;">
                            <?php echo esc_html( strtoupper( mb_substr( $edit_contact['contact_nom'], 0, 2 ) ) ); ?>
                        </div>
                        <?php endif; ?>
                        <div style="min-width:0;">
                            <div style="font-size:.82rem;font-weight:600;color:var(--edito-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                                 id="ce-contact-preview-nom"><?php echo esc_html( $edit_contact['contact_nom'] ); ?></div>
                            <div style="display:flex;gap:4px;margin-top:2px;">
                                <span class="edito-badge edito-badge--<?php echo esc_attr( $edit_contact['contact_type'] ); ?>"
                                      id="ce-contact-preview-type" style="font-size:.65rem;padding:1px 6px;"><?php echo esc_html( $type_label ); ?></span>
                                <span class="edito-badge edito-badge--<?php echo esc_attr( $edit_contact['contact_categorie'] ); ?>"
                                      id="ce-contact-preview-cat" style="font-size:.65rem;padding:1px 6px;"><?php echo esc_html( $cat_label ); ?></span>
                            </div>
                        </div>
                        <?php else : ?>
                        <!-- Placeholders vides pour remplissage JS si nouvel article -->
                        <div id="ce-contact-preview-img" style="display:none;width:32px;height:32px;border-radius:6px;border:1px solid var(--edito-border-light);flex-shrink:0;"></div>
                        <div style="min-width:0;">
                            <div style="font-size:.82rem;font-weight:600;color:var(--edito-text);" id="ce-contact-preview-nom"></div>
                            <div style="display:flex;gap:4px;margin-top:2px;">
                                <span class="edito-badge" id="ce-contact-preview-type" style="font-size:.65rem;padding:1px 6px;"></span>
                                <span class="edito-badge" id="ce-contact-preview-cat"  style="font-size:.65rem;padding:1px 6px;"></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <!-- ── Fin Client / Partenaire ─────────────────────────── -->

                <!-- ── DATE DE CRÉATION ───────────────────────────────────
                     Permet de modifier la date de l'article.
                     Champ lu par edito-auteur.js via id="ce-post-date" et
                     envoyé comme ce_post_date dans le POST AJAX.
                ─────────────────────────────────────────────────────────── -->
                <div class="edito-card">
                    <h3 class="edito-card__title">Date de publication</h3>
                    <div class="edito-form-group" style="margin-bottom:0;">
                        <label class="edito-label" for="ce-post-date">
                            Date et heure
                            <span class="edito-label-hint">(<?php echo esc_html( wp_timezone_string() ); ?>)</span>
                        </label>
                        <input
                            type="datetime-local"
                            id="ce-post-date"
                            name="ce_post_date"
                            class="edito-input"
                            value="<?php echo esc_attr( $post_date_local ); ?>"
                            step="60"
                        >
                    </div>
                </div>
                <!-- ── Fin Date de création ───────────────────────────── -->

                <!-- Informations article (mode édition uniquement) -->
                <?php if ( $is_edit && $post ) : ?>
                <div class="edito-card edito-card--info">
                    <h3 class="edito-card__title">Informations</h3>
                    <dl class="edito-info-list">
                        <dt>Créé le</dt>
                        <dd><?php echo esc_html( get_the_date( 'd/m/Y H:i', $post ) ); ?></dd>
                        <dt>Modifié le</dt>
                        <dd><?php echo esc_html( get_the_modified_date( 'd/m/Y H:i', $post ) ); ?></dd>
                        <dt>Auteur</dt>
                        <dd><?php echo esc_html( get_the_author_meta( 'display_name', $post->post_author ) ); ?></dd>
                    </dl>
                </div>
                <?php endif; ?>

                <!-- Actions de publication -->
                <div class="edito-card edito-actions-card">
                    <h3 class="edito-card__title">Publication</h3>
                    <input type="hidden" id="ce-post-id"       value="<?php echo esc_attr( $post_id ); ?>">
                    <input type="hidden" id="ce-current-status" value="<?php echo esc_attr( $edit_status ); ?>">
                    <div class="edito-actions-stack">

                        <?php if ( 'publish' === $edit_status ) : ?>
                        <!-- ── Article publié : Mettre à jour ── -->
                        <button type="button" class="edito-btn edito-btn--primary edito-btn--full" id="btn-update">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 4h10v9H3z" stroke="currentColor" stroke-width="1.5"/><path d="M5.5 9.5l2 2 3-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Mettre à jour
                        </button>
                        <?php if ( $is_editor ) : ?>
                        <button type="button" class="edito-btn edito-btn--outline edito-btn--full" id="btn-save-draft">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M7 2v5l3 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M12.7 5.3A6 6 0 1 0 12 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                            Repasser en brouillon
                        </button>
                        <?php endif; ?>

                        <?php else : ?>
                        <!-- ── Article non publié : workflow standard ── -->
                        <button type="button" class="edito-btn edito-btn--outline edito-btn--full" id="btn-save-draft">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 4h10v9H3z" stroke="currentColor" stroke-width="1.5"/><path d="M6 4V2h4v2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M5.5 9.5l2 2 3-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Enregistrer le brouillon
                        </button>
                        <button type="button" class="edito-btn edito-btn--primary edito-btn--full" id="btn-submit">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M14 2L2 6.5l5 2L14 2ZM2 6.5l3 7.5L9.5 8.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Soumettre pour validation
                        </button>
                        <?php endif; ?>

                        <?php if ( $is_edit ) : ?>
                        <button type="button" class="edito-btn edito-btn--danger edito-btn--full" id="btn-delete" data-id="<?php echo esc_attr( $post_id ); ?>">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 5h10l-1 8H4L3 5ZM6 8v3M10 8v3M1 5h14M6 5l1-2h2l1 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Supprimer l'article
                        </button>
                        <?php endif; ?>

                    </div>
                </div>

            </aside><!-- .edito-editor-sidebar -->

        </div><!-- .edito-editor-layout -->

    </main>

</div><!-- .edito-app -->

<!-- Modal confirmation suppression -->
<div class="edito-modal-overlay" id="ce-confirm-overlay" style="display:none;">
    <div class="edito-modal" role="dialog" aria-modal="true" aria-labelledby="ce-confirm-title">
        <h3 class="edito-modal__title" id="ce-confirm-title">Confirmer la suppression</h3>
        <p class="edito-modal__text" id="ce-confirm-text">Êtes-vous sûr de vouloir supprimer cet article ? Cette action est irréversible.</p>
        <div class="edito-modal__actions">
            <button type="button" class="edito-btn edito-btn--outline" id="ce-confirm-cancel">Annuler</button>
            <button type="button" class="edito-btn edito-btn--danger"  id="ce-confirm-ok">Supprimer</button>
        </div>
    </div>
</div>

<?php wp_footer(); ?>

<!-- =====================================================================
     JS — Liaison contact ↔ article
     Séparé de edito-auteur.js car fonctionnalité ajoutée a posteriori.
     Dépend de : Edito_Auteur.ajax_url + Edito_Auteur.edito_nonce (voir note)
     ===================================================================== -->
<script>
(function () {
    'use strict';

    const AJAX  = typeof Edito_Auteur !== 'undefined' ? Edito_Auteur.ajax_url   : '/wp-admin/admin-ajax.php';
    const NONCE = typeof Edito_Auteur !== 'undefined' ? Edito_Auteur.edito_nonce : '';

    const sel     = document.getElementById('ce-contact');
    const preview = document.getElementById('ce-contact-preview');
    const postId  = parseInt(document.getElementById('ce-post-id')?.value || '0', 10);

    if (!sel) return; // pas de contacts en base → bloc masqué côté PHP

    /* ── Données des contacts sérialisées depuis PHP ─────────────────── */
    const contactsData = <?php
        // Indexé par contact_id pour accès O(1) côté JS
        $js_map = [];
        foreach ( $contacts_list as $c ) {
            $js_map[ (int)$c['contact_id'] ] = [
                'nom'       => $c['contact_nom'],
                'type'      => $c['contact_type'],
                'categorie' => $c['contact_categorie'],
            ];
        }
        echo wp_json_encode( $js_map );
    ?>;

    /* ── Labels lisibles ─────────────────────────────────────────────── */
    function typeLabel(t)  { return t === 'partenaire' ? 'Partenaire' : 'Client'; }
    function catLabel(c)   { return c === 'nco' ? 'NCO' : 'Agrément'; }
    function badgeClass(v) { return 'edito-badge edito-badge--' + v; }

    /* ── Mise à jour du preview ──────────────────────────────────────── */
    function updatePreview(contactId) {
        const nomEl  = document.getElementById('ce-contact-preview-nom');
        const typeEl = document.getElementById('ce-contact-preview-type');
        const catEl  = document.getElementById('ce-contact-preview-cat');
        const imgEl  = document.getElementById('ce-contact-preview-img');

        if (!contactId || !contactsData[contactId]) {
            if (preview) preview.style.display = 'none';
            return;
        }

        const c = contactsData[contactId];
        if (nomEl)  nomEl.textContent  = c.nom;
        if (typeEl) { typeEl.textContent = typeLabel(c.type); typeEl.className = badgeClass(c.type); }
        if (catEl)  { catEl.textContent  = catLabel(c.categorie); catEl.className = badgeClass(c.categorie); }

        // Pas d'icone disponible dans la liste allégée → initiales
        if (imgEl) {
            const initials = c.nom.split(' ').map(w => w[0]).join('').substring(0,2).toUpperCase();
            const bg = c.type === 'partenaire' ? '#fdf8f0' : '#eff6ff';
            const color = c.type === 'partenaire' ? '#92660a' : '#2563eb';
            imgEl.style.cssText = `display:flex;width:32px;height:32px;border-radius:6px;background:${bg};color:${color};align-items:center;justify-content:center;font-weight:700;font-size:.75rem;flex-shrink:0;border:1px solid var(--edito-border-light);`;
            imgEl.textContent = initials;
        }

        if (preview) preview.style.display = 'flex';
    }

    /* ── Synchronisation AJAX ────────────────────────────────────────── */
    function syncContact(contactId) {
        if (!postId) return;

        const fd = new FormData();
        fd.append('action',      'edito_sync_contact_post');
        fd.append('nonce',       NONCE);
        fd.append('post_id',     postId);
        fd.append('contact_id',  contactId || 0);

        fetch(AJAX, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (!res.success) console.warn('Edito contact sync:', res.data?.message);
            })
            .catch(err => console.error('Edito contact sync error:', err));
    }

    /* ── Événement change ────────────────────────────────────────────── */
    sel.addEventListener('change', function () {
        const id = parseInt(this.value, 10) || 0;
        updatePreview(id);
        syncContact(id);
        sel.dataset.pendingContactId = id;
    });

    /* Init au chargement */
    const initialId = parseInt(sel.value, 10) || 0;
    updatePreview(initialId);
    sel.dataset.pendingContactId = initialId;

})();
</script>

<!-- =====================================================================
     JS — Checkboxes multi-catégories
     Synchronise les checkboxes avec les deux champs cachés lus par
     edito-auteur.js :
       #ce-category  → premier ID sélectionné  (compatibilité legacy)
       #ce-cat-ids   → tous les IDs, séparés par des virgules (nouveau)
     ===================================================================== -->
<script>
(function () {
    'use strict';

    const MAX_CATS  = 3;
    const container = document.getElementById('edito-cats-checkboxes');
    const hint      = document.getElementById('edito-cats-hint');
    const hiddenOne = document.getElementById('ce-category');   // premier ID (legacy)
    const hiddenAll = document.getElementById('ce-cat-ids');    // tous les IDs

    if (!container) return;

    function getCheckedInputs() {
        return Array.from(container.querySelectorAll('.edito-cat-checkbox__input:checked'));
    }

    function syncHiddenFields() {
        const checked = getCheckedInputs();
        const ids     = checked.map(function (i) { return i.value; });

        if (hiddenOne) hiddenOne.value = ids[0] || '';
        if (hiddenAll) hiddenAll.value = ids.join(',');
    }

    function updateUI() {
        const checked = getCheckedInputs();
        const count   = checked.length;

        // Message d'aide
        if (count === 0) {
            hint.textContent = 'Sélectionnez au moins une catégorie.';
        } else if (count >= MAX_CATS) {
            hint.textContent = 'Maximum ' + MAX_CATS + ' catégories atteint.';
        } else {
            hint.textContent = '';
        }

        // Styles visuels + désactivation si max atteint
        container.querySelectorAll('.edito-cat-checkbox').forEach(function (label) {
            const input = label.querySelector('.edito-cat-checkbox__input');
            label.classList.toggle('edito-cat-checkbox--checked', input.checked);
            label.classList.toggle(
                'edito-cat-checkbox--disabled',
                count >= MAX_CATS && !input.checked
            );
        });

        syncHiddenFields();
    }

    // Délégation d'événements sur le container
    container.addEventListener('change', function (e) {
        if (!e.target.matches('.edito-cat-checkbox__input')) return;

        // Empêcher de dépasser MAX_CATS
        const checked = getCheckedInputs();
        if (checked.length > MAX_CATS) {
            e.target.checked = false;
        }

        updateUI();
    });

    // Initialisation au chargement (article existant pré-coché)
    updateUI();
})();
</script>

</body>
</html>
