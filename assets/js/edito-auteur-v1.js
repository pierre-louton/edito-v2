/**
 * Edito — edito-auteur.js
 * Gestion du formulaire d'édition d'article, upload de photos, rich text
 */
(function ($) {
  'use strict';

  const Editor = {
    postId:    0,
    photoIds:  [],
    maxPhotos: parseInt(Edito_Auteur.max_photos) || 5,
    uploading: false,

    /* ------------------------------------------------------------------
     * Init
     * ------------------------------------------------------------------ */
    init() {
      this.postId = parseInt($('#ce-post-id').val()) || 0;

      // Récupérer les IDs de photos déjà présentes
      $('.edito-gallery__item').each((_, el) => {
        const id = parseInt($(el).data('id'));
        if (id && !this.photoIds.includes(id)) this.photoIds.push(id);
      });
      this.updatePhotoCount();

      this.bindRichText();
      this.bindUpload();
      this.bindActions();
      this.bindCategoryPreview();
    },

    /* ------------------------------------------------------------------
     * Rich Text Editor
     * ------------------------------------------------------------------ */
    bindRichText() {
      const $editor  = $('#ce-content');
      const $hidden  = $('#ce-content-hidden');

      const sync = () => { $hidden.val($editor.html()); };

      $('.edito-tb-btn').on('click', function () {
        const cmd = $(this).data('cmd');
        document.execCommand(cmd, false, null);
        $editor.focus();
        sync();
      });

      $('.edito-tb-select').on('change', function () {
        const val = $(this).val();
        document.execCommand('formatBlock', false, val);
        $editor.focus();
        sync();
      });

      $editor.on('input keyup paste', sync);

      $editor.on('keyup mouseup', () => {
        $('.edito-tb-btn').each(function () {
          const cmd = $(this).data('cmd');
          $(this).toggleClass('active', document.queryCommandState(cmd));
        });
      });
    },

    /* ------------------------------------------------------------------
     * Upload de photos
     * ------------------------------------------------------------------ */
    bindUpload() {
      const $zone    = $('#ce-upload-zone');
      const $input   = $('#ce-file-input');
      const $gallery = $('#ce-gallery');

      $zone.on('click', (e) => {
        if ($(e.target).is('.edito-file-input')) return;
        $input.trigger('click');
      });

      $zone.on('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') $input.trigger('click');
      });

      $zone.on('dragover dragenter', (e) => {
        e.preventDefault();
        $zone.addClass('drag-over');
      });
      $zone.on('dragleave dragend drop', () => {
        $zone.removeClass('drag-over');
      });
      $zone.on('drop', (e) => {
        e.preventDefault();
        const files = e.originalEvent.dataTransfer.files;
        this.handleFiles(files);
      });

      $input.on('change', (e) => {
        this.handleFiles(e.target.files);
        $input.val('');
      });

      $gallery.on('click', '.edito-gallery__remove', (e) => {
        e.stopPropagation();
        const $item = $(e.currentTarget).closest('.edito-gallery__item');
        const id    = parseInt($item.data('id'));
        this.photoIds = this.photoIds.filter(i => i !== id);
        $item.remove();
        this.updatePhotoCount();
        this.showToast('Photo retirée.', 'info');
      });
    },

    handleFiles(files) {
      const available = this.maxPhotos - this.photoIds.length;
      if (available <= 0) {
        this.showToast(`Maximum ${this.maxPhotos} photos atteint.`, 'error');
        return;
      }
      const toUpload = Array.from(files).slice(0, available);
      toUpload.forEach(file => this.uploadFile(file));
    },

    uploadFile(file) {
      if (this.uploading) return;

      const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
      if (!allowed.includes(file.type)) {
        this.showToast('Type de fichier non supporté.', 'error');
        return;
      }
      if (file.size > 8 * 1024 * 1024) {
        this.showToast('Fichier trop volumineux (max 8 Mo).', 'error');
        return;
      }

      this.uploading = true;
      const $progress = $('#ce-upload-progress').show();
      const $fill     = $('#ce-progress-fill');
      const $label    = $('#ce-progress-label');

      const reader = new FileReader();
      reader.onload = (e) => {
        const $placeholder = this.addGalleryPlaceholder(e.target.result);

        const formData = new FormData();
        formData.append('action', 'edito_upload_photo');
        formData.append('nonce',  Edito_Auteur.nonce);
        formData.append('photo',  file, file.name);

        $.ajax({
          url:         Edito_Auteur.ajax_url,
          type:        'POST',
          data:        formData,
          processData: false,
          contentType: false,
          xhr: () => {
            const xhr = new XMLHttpRequest();
            xhr.upload.addEventListener('progress', (ev) => {
              if (ev.lengthComputable) {
                const pct = Math.round((ev.loaded / ev.total) * 100);
                $fill.css('width', pct + '%');
                $label.text(`Upload en cours… ${pct}%`);
              }
            });
            return xhr;
          },
          success: (res) => {
            if (res.success) {
              const d = res.data;
              this.photoIds.push(d.attachment_id);
              $placeholder.replaceWith(this.buildGalleryItem(d.attachment_id, d.thumb_url));
              this.updatePhotoCount();
              this.showToast('Photo ajoutée avec succès.', 'success');
            } else {
              $placeholder.remove();
              this.showToast(res.data.message || 'Erreur lors de l\'upload.', 'error');
            }
          },
          error: () => {
            $placeholder.remove();
            this.showToast('Erreur réseau lors de l\'upload.', 'error');
          },
          complete: () => {
            this.uploading = false;
            setTimeout(() => {
              $progress.hide();
              $fill.css('width', '0');
              $label.text('Upload en cours…');
            }, 800);
          },
        });
      };
      reader.readAsDataURL(file);
    },

    addGalleryPlaceholder(src) {
      const $item = $(`
        <div class="edito-gallery__item edito-gallery__item--uploading">
          <img src="${src}" alt="" style="opacity:.5;">
        </div>
      `);
      $('#ce-gallery').append($item);
      return $item;
    },

    buildGalleryItem(id, thumbUrl) {
      return $(`
        <div class="edito-gallery__item" data-id="${id}">
          <img src="${thumbUrl}" alt="" loading="lazy">
          <div class="edito-gallery__overlay">
            <button type="button" class="edito-gallery__remove" title="Supprimer" data-id="${id}">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 2l10 10M12 2L2 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
          </div>
          <input type="hidden" name="ce_photo_ids[]" value="${id}">
        </div>
      `);
    },

    updatePhotoCount() {
      $('#ce-photo-count').text(this.photoIds.length);
      const $zone = $('#ce-upload-zone');
      if (this.photoIds.length >= this.maxPhotos) {
        $zone.css('opacity', '.5').attr('tabindex', '-1');
        $('#ce-file-input').prop('disabled', true);
      } else {
        $zone.css('opacity', '').attr('tabindex', '0');
        $('#ce-file-input').prop('disabled', false);
      }
    },

    /* ------------------------------------------------------------------
     * Boutons d'action (sauvegarder / soumettre / supprimer)
     * ------------------------------------------------------------------ */
    bindActions() {
      $('#btn-save-draft').on('click', () => this.save('draft'));
      $('#btn-submit').on('click',     () => this.save('pending'));
      $('#btn-delete').on('click',     () => this.deleteArticle());
    },

    save(status) {
      const title   = $('#ce-title').val().trim();
      const catId   = $('#ce-category').val();
      const content = $('#ce-content').html();

      if (!title) {
        this.showToast('Le titre est obligatoire.', 'error');
        $('#ce-title').focus();
        return;
      }
      if (!catId) {
        this.showToast('Veuillez choisir une catégorie.', 'error');
        return;
      }

      $('#ce-content-hidden').val(content);

      const $btn = status === 'draft' ? $('#btn-save-draft') : $('#btn-submit');
      $btn.prop('disabled', true).text('Enregistrement…');

      const data = {
        action:    'edito_save_article',
        nonce:     Edito_Auteur.nonce,
        post_id:   this.postId,
        title,
        content,
        category:  catId,
        status,
        photo_ids: this.photoIds,
      };

      $.post(Edito_Auteur.ajax_url, data, (res) => {
        $btn.prop('disabled', false);

        if (res.success) {
          const wasNew   = this.postId === 0;
          this.postId    = res.data.post_id;

          // Mettre à jour l'URL et l'input caché avec le vrai post_id
          const newUrl = Edito_Auteur.editor_url + '?post_id=' + this.postId;
          window.history.replaceState({}, '', newUrl);
          $('#ce-post-id').val(this.postId);

          // ── Liaison contact ───────────────────────────────────────────
          // Déclenché ici pour les nouveaux articles (postId était 0).
          // Pour les articles existants, la liaison est déjà synchronisée
          // en temps réel par le change handler dans editor.php.
          if (wasNew) {
            this.syncContact(this.postId);
          }
          // ─────────────────────────────────────────────────────────────

          const msg = 'pending' === status
            ? 'Article soumis pour validation ✓'
            : 'Brouillon enregistré ✓';
          this.showToast(msg, 'success');

          if ('pending' === status) {
            setTimeout(() => { window.location.href = Edito_Auteur.dashboard_url; }, 1500);
          } else {
            $btn.text(status === 'draft' ? 'Enregistrer le brouillon' : 'Soumettre pour validation');
          }
        } else {
          this.showToast(res.data.message || 'Erreur.', 'error');
          $btn.text(status === 'draft' ? 'Enregistrer le brouillon' : 'Soumettre pour validation');
        }
      }).fail(() => {
        $btn.prop('disabled', false).text('Réessayer');
        this.showToast('Erreur réseau.', 'error');
      });
    },

    /* ------------------------------------------------------------------
     * Synchronisation liaison contact ↔ article
     * Appelé après la première sauvegarde d'un nouvel article.
     * Pour les articles existants, le change handler inline s'en charge.
     * ------------------------------------------------------------------ */
    syncContact(postId) {
      const sel = document.getElementById('ce-contact');
      if (!sel) return; // bloc contact absent (aucun contact en base)

      const contactId = parseInt(sel.dataset.pendingContactId || sel.value) || 0;
      if (!contactId) return; // aucun contact sélectionné → rien à lier

      // Nonce : Edito_Auteur.edito_nonce ajouté dans enqueue_assets()
      // Fallback vide si la constante n'a pas encore été ajoutée
      const nonce = Edito_Auteur.edito_nonce || '';

      const fd = new FormData();
      fd.append('action',     'edito_sync_contact_post');
      fd.append('nonce',      nonce);
      fd.append('post_id',    postId);
      fd.append('contact_id', contactId);

      fetch(Edito_Auteur.ajax_url, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
          if (!res.success) {
            console.warn('Edito — sync contact:', res.data?.message);
          }
        })
        .catch(err => console.error('Edito — sync contact error:', err));
    },

    deleteArticle() {
      if (!this.postId) { window.location.href = Edito_Auteur.dashboard_url; return; }
      if (!confirm('Supprimer cet article ? Cette action est irréversible.')) return;

      $.post(Edito_Auteur.ajax_url, {
        action:   'edito_delete_article',
        nonce:    Edito_Auteur.nonce,
        post_id:  this.postId,
      }, (res) => {
        if (res.success) {
          this.showToast('Article supprimé.', 'info');
          setTimeout(() => { window.location.href = Edito_Auteur.dashboard_url; }, 1000);
        } else {
          this.showToast(res.data.message || 'Erreur.', 'error');
        }
      });
    },

    /* ------------------------------------------------------------------
     * Aperçu icône de catégorie
     * ------------------------------------------------------------------ */
    bindCategoryPreview() {
      $('#ce-category').on('change', function () {
        const icon     = $('option:selected', this).data('icon');
        const $preview = $('#ce-cat-preview');
        if (icon) {
          $preview.html(`<img src="${icon}" alt="" class="edito-cat-preview__icon">`).show();
        } else {
          $preview.hide();
        }
      });
    },

    /* ------------------------------------------------------------------
     * Toast notification
     * ------------------------------------------------------------------ */
    showToast(msg, type = 'info') {
      const $t = $('#ce-toast');
      $t.removeClass('edito-toast--success edito-toast--error edito-toast--info')
        .addClass(`edito-toast--${type}`)
        .text(msg)
        .show();
      clearTimeout(this._toastTimer);
      this._toastTimer = setTimeout(() => $t.hide(), 3500);
    },
  };

  $(document).ready(() => Editor.init());

})(jQuery);