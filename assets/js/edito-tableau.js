/**
 * Edito — edito-tableau.js
 * Gestion du tableau de bord : changements de statut, lightbox, modal confirmation
 */
(function ($) {
  'use strict';

  const Dashboard = {

    pendingAction: null,

    /* ------------------------------------------------------------------
     * Init
     * ------------------------------------------------------------------ */
    init() {
      this.bindStatusActions();
      this.bindLightbox();
      this.bindModal();
    },

    /* ------------------------------------------------------------------
     * Actions de changement de statut sur les cartes
     * ------------------------------------------------------------------ */
    bindStatusActions() {
      // Publier
      $(document).on('click', '[data-action="publish"]', (e) => {
        const id = $(e.currentTarget).data('id');
        this.confirmAction(
          'Publier l\'article',
          'Cet article sera rendu public sur le site. Souhaitez-vous continuer ?',
          () => this.changeStatus(id, 'publish', $(e.currentTarget).closest('.edito-article-card'))
        );
      });

      // Remettre en brouillon
      $(document).on('click', '[data-action="draft"]', (e) => {
        const id = $(e.currentTarget).data('id');
        this.confirmAction(
          'Remettre en brouillon',
          'L\'article sera renvoyé à l\'auteur pour modifications. Continuer ?',
          () => this.changeStatus(id, 'draft', $(e.currentTarget).closest('.edito-article-card'))
        );
      });

      // Supprimer (mettre à la corbeille)
      $(document).on('click', '[data-action="trash"]', (e) => {
        const id = $(e.currentTarget).data('id');
        this.confirmAction(
          'Supprimer l\'article',
          'Cet article sera déplacé dans la corbeille. L\'auteur en sera notifié.',
          () => this.changeStatus(id, 'trash', $(e.currentTarget).closest('.edito-article-card')),
          true // danger
        );
      });
    },

    changeStatus(postId, newStatus, $card) {
      const $actionsArea = $card.find('.edito-article-actions');
      $actionsArea.css('opacity', '.4').css('pointer-events', 'none');

      // Lire le statut actuel depuis le badge AVANT la requête
      const $badge    = $card.find('.edito-badge');
      const badgeCls  = $badge.attr('class') || '';
      let oldStatus   = 'pending';
      if (badgeCls.includes('edito-badge--draft'))   oldStatus = 'draft';
      else if (badgeCls.includes('edito-badge--publish')) oldStatus = 'publish';

      $.post(Edito_Tableau.ajax_url, {
        action:     'edito_change_status',
        nonce:      Edito_Tableau.nonce,
        post_id:    postId,
        new_status: newStatus,
      }, (res) => {
        $actionsArea.css('opacity', '').css('pointer-events', '');

        if (res.success) {
          this.showToast(res.data.message, 'success');
          this.updateStatCounts(oldStatus, newStatus);

          if ('trash' === newStatus) {
            $card.animate({ opacity: 0, height: 0, marginBottom: 0 }, 400, () => {
              $card.remove();
              this.checkEmpty();
            });
          } else {
            const badgeClass = {
              'draft':        'edito-badge--draft',
              'pending':      'edito-badge--pending',
              'publish':      'edito-badge--publish',
              'ce_validated': 'edito-badge--validated',
            }[newStatus] || 'edito-badge--draft';

            $badge.attr('class', `edito-badge ${badgeClass}`).text(res.data.new_label);

            const $publishBtn = $card.find('[data-action="publish"]');
            const $draftBtn   = $card.find('[data-action="draft"]');

            if ('publish' === newStatus) {
              $publishBtn.hide();
              $draftBtn.show();
            } else if ('draft' === newStatus) {
              $publishBtn.show();
              $draftBtn.hide();
            }
          }
        } else {
          this.showToast(res.data.message || 'Erreur.', 'error');
        }
      }).fail(() => {
        $actionsArea.css('opacity', '').css('pointer-events', '');
        this.showToast('Erreur réseau.', 'error');
      });
    },

    /* ------------------------------------------------------------------
     * Met à jour les compteurs des stat-cards sans recharger la page
     * ------------------------------------------------------------------ */
    updateStatCounts(oldStatus, newStatus) {
      const findCard = (status) => {
        let $found = $();
        $('.edito-stat-card').each(function () {
          try {
            const params = new URL($(this).attr('href'), window.location.origin).searchParams;
            if (params.get('status') === status) $found = $(this);
          } catch (e) {}
        });
        return $found;
      };

      const adjust = ($el, delta) => {
        if (!$el.length) return;
        const $n = $el.find('.edito-stat-card__count');
        $n.text(Math.max(0, (parseInt($n.text(), 10) || 0) + delta));
      };

      adjust(findCard(oldStatus), -1);   // le statut précédent perd 1

      if ('trash' === newStatus) {
        adjust(findCard('all'), -1);     // la corbeille réduit le total
      } else {
        adjust(findCard(newStatus), +1); // le nouveau statut gagne 1
      }
    },

    checkEmpty() {
      if ($('.edito-article-card').length === 0) {
        const $grid = $('.edito-articles-grid');
        $grid.replaceWith(`
          <div class="edito-empty-state">
            <div class="edito-empty-state__icon">📭</div>
            <h2>Aucun article ici</h2>
            <p>Tous les articles ont été traités.</p>
            <a href="${Edito_Tableau.editor_url}" class="edito-btn edito-btn--primary">Rédiger un article</a>
          </div>
        `);
      }
    },

    /* ------------------------------------------------------------------
     * Lightbox
     * ------------------------------------------------------------------ */
    bindLightbox() {
      // Exposer la fonction globalement pour les onclick inline dans le template
      window.Edito_Tableau = window.Edito_Tableau || {};
      window.Edito_Tableau.openLightbox = this.openLightbox.bind(this);

      $('#ce-lightbox-close').on('click', () => this.closeLightbox());
      $('#ce-lightbox').on('click', (e) => {
        if ($(e.target).is('#ce-lightbox')) this.closeLightbox();
      });
      $(document).on('keydown', (e) => {
        if (e.key === 'Escape') this.closeLightbox();
      });
    },

    openLightbox(url, title) {
      const $lb = $('#ce-lightbox');
      $('#ce-lightbox-img').attr('src', url).attr('alt', title);
      $('#ce-lightbox-caption').text(title);
      $lb.fadeIn(150);
      document.body.style.overflow = 'hidden';
    },

    closeLightbox() {
      $('#ce-lightbox').fadeOut(150);
      document.body.style.overflow = '';
    },

    /* ------------------------------------------------------------------
     * Modal de confirmation
     * ------------------------------------------------------------------ */
    bindModal() {
      $('#ce-confirm-cancel').on('click', () => this.closeModal());
      $('#ce-confirm-ok').on('click', () => {
        if (typeof this.pendingAction === 'function') {
          this.pendingAction();
          this.pendingAction = null;
        }
        this.closeModal();
      });
      $('#ce-confirm-overlay').on('click', (e) => {
        if ($(e.target).is('#ce-confirm-overlay')) this.closeModal();
      });
    },

    confirmAction(title, text, callback, isDanger = false) {
      $('#ce-confirm-title').text(title);
      $('#ce-confirm-text').text(text);
      const $ok = $('#ce-confirm-ok');
      $ok.removeClass('edito-btn--danger edito-btn--primary')
         .addClass(isDanger ? 'edito-btn--danger' : 'edito-btn--primary');
      this.pendingAction = callback;
      $('#ce-confirm-overlay').fadeIn(150);
    },

    closeModal() {
      $('#ce-confirm-overlay').fadeOut(150);
      this.pendingAction = null;
    },

    /* ------------------------------------------------------------------
     * Toast notification
     * ------------------------------------------------------------------ */
    showToast(msg, type = 'info') {
      const $t = $('#ce-toast');
      $t.removeClass('edito-toast--success edito-toast--error edito-toast--info')
        .addClass(`edito-toast--${type}`)
        .text(msg)
        .addClass('show');
      clearTimeout(this._toastTimer);
      this._toastTimer = setTimeout(() => $t.removeClass('show'), 3500);
    },
  };

  $(document).ready(() => Dashboard.init());

  // Exposer l'objet pour les appels depuis le template PHP (onclick)
  window.Edito_Tableau = window.Edito_Tableau || {};
  window.Edito_Tableau.openLightbox = function (url, title) {
    Dashboard.openLightbox(url, title);
  };

})(jQuery);