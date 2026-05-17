<?php
/**
 * Edito_Notifications – Notifications e-mail sur changement de statut
 *
 * L'auteur est notifié à chaque changement de statut de son article.
 * Les éditeurs sont notifiés quand un article est soumis pour validation.
 */
class Edito_Notifications {

    private static ?Edito_Notifications $instance = null;

    /**
     * Garde de ré-entrance : empêche la cascade causée par les plugins de mail
     * logging qui créent un post WP lors de chaque appel à wp_mail(), ce qui
     * refirerait transition_post_status → on_status_change → send_email à l'infini.
     */
    private static bool $_sending = false;

    public static function get_instance(): Edito_Notifications {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'transition_post_status', [ $this, 'on_status_change' ], 10, 3 );
    }

    /* -----------------------------------------------------------------------
     * Déclencheur principal
     * --------------------------------------------------------------------- */
    public function on_status_change( string $new_status, string $old_status, WP_Post $post ): void {
        // Garde de ré-entrance : si on est déjà en train d'envoyer un email,
        // ignorer toute transition déclenchée par un logger de mail.
        if ( self::$_sending ) return;

        // Ignorer les auto-drafts, les révisions et les statuts identiques
        if ( $new_status === $old_status ) return;
        if ( 'post' !== $post->post_type ) return;
        if ( 'auto-draft' === $new_status || 'inherit' === $new_status ) return;

        // Ignorer les posts dont le titre ressemble à un email de notification
        // (défense en profondeur : arrête la cascade même si un logger crée en post_type='post')
        $site_name = get_bloginfo( 'name' );
        if ( str_starts_with( $post->post_title, "[{$site_name}]" ) ) return;

        $author = get_userdata( $post->post_author );
        if ( ! $author ) return;

        // Notifier l'auteur
        $this->notify_author( $author, $post, $old_status, $new_status );

        // Notifier les éditeurs si l'article vient d'être soumis
        if ( 'pending' === $new_status ) {
            $this->notify_editors( $post );
        }
    }

    /* -----------------------------------------------------------------------
     * Notification à l'auteur
     * --------------------------------------------------------------------- */
    private function notify_author( WP_User $author, WP_Post $post, string $old_status, string $new_status ): void {
        $site_name  = get_bloginfo( 'name' );
        $title      = esc_html( $post->post_title );
        $old_label  = Edito_Core::status_label( $old_status );
        $new_label  = Edito_Core::status_label( $new_status );
        $editor_url = Edito_Core::page_url( 'editor' );
        $edit_link  = add_query_arg( 'post_id', $post->ID, $editor_url );

        $subject = sprintf( '[%s] Votre article "%s" : %s', $site_name, $title, $new_label );

        // Corps du message selon le nouveau statut
        $body_intro = match ( $new_status ) {
            'publish' => "Bonne nouvelle ! Votre article a été <strong>validé et publié</strong> par l'équipe éditoriale.",
            'draft'   => "Votre article a été <strong>renvoyé en brouillon</strong>. Vous pouvez le modifier et le soumettre à nouveau.",
            'pending' => "Votre article est maintenant <strong>en attente de validation</strong>. L'équipe éditoriale en prendra connaissance prochainement.",
            'trash'   => "Votre article a été <strong>supprimé</strong> par l'équipe éditoriale.",
            default   => "Le statut de votre article a changé.",
        };
        $message = $this->email_template( $site_name, $title, $body_intro, $new_label, $old_label, $edit_link, $new_status );

        $this->send_email( $author->user_email, $subject, $message );
    }

    /* -----------------------------------------------------------------------
     * Notification aux éditeurs lors d'une soumission
     * --------------------------------------------------------------------- */
    private function notify_editors( WP_Post $post ): void {
        $editors = get_users( [ 'role' => 'editor' ] );
        if ( empty( $editors ) ) return;

        $site_name     = get_bloginfo( 'name' );
        $title         = esc_html( $post->post_title );
        $author        = get_userdata( $post->post_author );
        $author_name   = $author ? esc_html( $author->display_name ) : 'Un auteur';
        $dashboard_url = Edito_Core::page_url( 'dashboard' );

        $subject = sprintf( '[%s] Nouvel article à valider : "%s"', $site_name, $title );

        $body_intro = sprintf(
            '<strong>%s</strong> a soumis un nouvel article en attente de votre validation.',
            $author_name
        );

        foreach ( $editors as $editor ) {
            $message = $this->email_template(
                $site_name,
                $title,
                $body_intro,
                'En attente de validation',
                '',
                $dashboard_url,
                'pending',
                true
            );
            $this->send_email( $editor->user_email, $subject, $message );
        }
    }

    /* -----------------------------------------------------------------------
     * Template HTML de l'e-mail
     * --------------------------------------------------------------------- */
    private function email_template(
        string $site_name,
        string $article_title,
        string $body_intro,
        string $new_label,
        string $old_label,
        string $cta_url,
        string $status,
        bool   $is_editor = false
    ): string {
        $accent_color = match ( $status ) {
            'publish' => '#22c55e',
            'draft'   => '#f59e0b',
            'trash'   => '#ef4444',
            'pending' => '#3b82f6',
            default   => '#6b7280',
        };

        $cta_label = $is_editor ? 'Voir le tableau de bord' : 'Voir mon article';

        $old_row = $old_label
            ? "<tr><td style='padding:4px 0;color:#6b7280;font-size:13px;'>Statut précédent</td><td style='padding:4px 0;font-size:13px;'>{$old_label}</td></tr>"
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:'Helvetica Neue',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:40px 20px;">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
        <!-- Header -->
        <tr>
          <td style="background:#1a1a2e;padding:32px 40px;text-align:center;">
            <p style="margin:0;color:#c9a96e;font-size:12px;letter-spacing:3px;text-transform:uppercase;font-weight:600;">Plateforme éditoriale</p>
            <h1 style="margin:8px 0 0;color:#ffffff;font-size:22px;font-weight:700;">{$site_name}</h1>
          </td>
        </tr>
        <!-- Bandeau statut -->
        <tr>
          <td style="background:{$accent_color};padding:10px 40px;text-align:center;">
            <span style="color:#fff;font-size:13px;font-weight:600;letter-spacing:1px;text-transform:uppercase;">{$new_label}</span>
          </td>
        </tr>
        <!-- Corps -->
        <tr>
          <td style="padding:40px 40px 32px;">
            <p style="margin:0 0 20px;font-size:15px;color:#374151;line-height:1.6;">{$body_intro}</p>
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:8px;padding:16px 20px;margin-bottom:28px;">
              <tr>
                <td style="padding:4px 0;color:#6b7280;font-size:13px;">Article</td>
                <td style="padding:4px 0;font-size:13px;font-weight:600;color:#1a1a2e;">«&nbsp;{$article_title}&nbsp;»</td>
              </tr>
              {$old_row}
              <tr>
                <td style="padding:4px 0;color:#6b7280;font-size:13px;">Nouveau statut</td>
                <td style="padding:4px 0;font-size:13px;font-weight:600;color:{$accent_color};">{$new_label}</td>
              </tr>
            </table>
            <div style="text-align:center;">
              <a href="{$cta_url}" style="display:inline-block;padding:14px 32px;background:#1a1a2e;color:#c9a96e;text-decoration:none;border-radius:8px;font-size:14px;font-weight:600;letter-spacing:.5px;">{$cta_label}</a>
            </div>
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style="padding:20px 40px 32px;border-top:1px solid #e5e7eb;text-align:center;">
            <p style="margin:0;font-size:12px;color:#9ca3af;">Ce message a été envoyé automatiquement par la plateforme éditoriale de {$site_name}.</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }

    /* -----------------------------------------------------------------------
     * Envoi effectif
     * --------------------------------------------------------------------- */
    private function send_email( string $to, string $subject, string $html_message ): void {
        self::$_sending = true;

        // On stocke la closure dans une variable pour pouvoir la retirer proprement.
        // remove_filter( ..., static fn() => ... ) ne fonctionne pas car chaque appel
        // à static fn() crée une instance distincte : la callback ajoutée et celle
        // passée à remove_filter ne sont jamais le même objet.
        $content_type_cb = static fn() => 'text/html';
        add_filter( 'wp_mail_content_type', $content_type_cb );

        wp_mail( $to, $subject, $html_message, [ 'Content-Type: text/html; charset=UTF-8' ] );

        remove_filter( 'wp_mail_content_type', $content_type_cb );

        self::$_sending = false;
    }
}
