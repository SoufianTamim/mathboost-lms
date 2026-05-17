<?php
defined( 'ABSPATH' ) || exit;

class MB_Admin {

    public static function init() {
        add_action( 'admin_menu',                     [ __CLASS__, 'register_menus' ] );
        add_action( 'admin_init',                     [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts',          [ __CLASS__, 'enqueue_admin' ] );
        add_action( 'add_meta_boxes',                 [ __CLASS__, 'add_meta_boxes' ] );
        add_action( 'save_post_mb_qcm',               [ __CLASS__, 'save_qcm_meta' ], 10, 2 );
        // Use classic editor for mb_qcm so metabox JS works reliably
        add_filter( 'use_block_editor_for_post_type', [ __CLASS__, 'disable_gutenberg' ], 10, 2 );
        // Lock column on QCM list
        add_filter( 'manage_mb_qcm_posts_columns',       [ __CLASS__, 'add_lock_column' ] );
        add_action( 'manage_mb_qcm_posts_custom_column', [ __CLASS__, 'render_lock_column' ], 10, 2 );
        add_action( 'wp_ajax_mb_admin_toggle_lock',      [ __CLASS__, 'handle_toggle_lock' ] );
    }

    // ── Disable Gutenberg for mb_qcm ─────────────────────────────────────────
    public static function disable_gutenberg( bool $use, string $post_type ): bool {
        return $post_type === 'mb_qcm' ? false : $use;
    }

    // ── Metaboxes ─────────────────────────────────────────────────────────────
    public static function add_meta_boxes() {
        add_meta_box(
            'mb-qcm-questions',
            __( 'Questions & Contenu du QCM', MB_TEXT_DOMAIN ),
            [ __CLASS__, 'render_qcm_meta_box' ],
            'mb_qcm',
            'normal',
            'high'
        );
    }

    public static function render_qcm_meta_box( WP_Post $post ): void {
        $subtitle  = get_post_meta( $post->ID, '_mb_subtitle',  true );
        $intro     = get_post_meta( $post->ID, '_mb_intro',     true );
        $questions = get_post_meta( $post->ID, '_mb_questions', true ) ?: '[]';
        $locked    = get_post_meta( $post->ID, '_mb_locked',    true );

        wp_nonce_field( 'mb_save_qcm_meta', 'mb_qcm_nonce' );
        ?>
        <div id="mb-qcm-builder">

            <table class="form-table" style="margin-bottom:0">
                <tr>
                    <th style="width:130px;padding-top:10px">
                        <label for="mb_subtitle"><?php esc_html_e( 'Sous-titre', MB_TEXT_DOMAIN ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="mb_subtitle" name="_mb_subtitle"
                               class="widefat" value="<?php echo esc_attr( $subtitle ); ?>"
                               placeholder="<?php esc_attr_e( 'Ex : 10 questions · Niveau 3ème', MB_TEXT_DOMAIN ); ?>">
                    </td>
                </tr>
                <tr>
                    <th style="padding-top:10px">
                        <label for="mb_intro"><?php esc_html_e( 'Introduction', MB_TEXT_DOMAIN ); ?></label>
                    </th>
                    <td>
                        <textarea id="mb_intro" name="_mb_intro" class="widefat" rows="3"
                                  placeholder="<?php esc_attr_e( 'Texte d\'introduction affiché avant les questions. LaTeX supporté : \\(...\\)', MB_TEXT_DOMAIN ); ?>"><?php echo esc_textarea( $intro ); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th style="padding-top:10px">
                        <?php esc_html_e( 'Accès', MB_TEXT_DOMAIN ); ?>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="_mb_locked" value="1" <?php checked( $locked, '1' ); ?>>
                            🔒 <?php esc_html_e( 'Verrouiller ce QCM (réservé aux abonnés premium)', MB_TEXT_DOMAIN ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Si coché, seuls les utilisateurs premium peuvent accéder à ce QCM.', MB_TEXT_DOMAIN ); ?></p>
                    </td>
                </tr>
            </table>

            <hr style="margin:16px 0 0">

            <!-- ── Import Panel ─────────────────────────────────────────── -->
            <div class="mb-import-section">
                <div class="mb-import-header">
                    <span class="mb-import-icon">📋</span>
                    <strong><?php esc_html_e( 'Importer depuis vos fichiers HTML', MB_TEXT_DOMAIN ); ?></strong>
                    <button type="button" id="mb-toggle-import" class="button button-small mb-import-toggle-btn">
                        <?php esc_html_e( '▲ Réduire', MB_TEXT_DOMAIN ); ?>
                    </button>
                </div>
                <div id="mb-import-panel">
                    <p class="description mb-import-desc">
                        <?php esc_html_e( 'Copiez le contenu de votre fichier HTML (la partie', MB_TEXT_DOMAIN ); ?>
                        <code>const Q = [{...}]</code>)
                        <?php esc_html_e( 'et collez-le ci-dessous. Les questions sont ajoutées à la suite.', MB_TEXT_DOMAIN ); ?>
                    </p>
                    <textarea id="mb-import-code" rows="10" class="widefat mb-import-textarea"
                              placeholder="<?php esc_attr_e( 'Collez ici votre code JS (const Q = [...]) ou JSON pur…', MB_TEXT_DOMAIN ); ?>"></textarea>
                    <div class="mb-import-actions">
                        <button type="button" id="mb-do-import" class="button button-primary">
                            📥 <?php esc_html_e( 'Charger les questions', MB_TEXT_DOMAIN ); ?>
                        </button>
                        <button type="button" id="mb-clear-import" class="button">
                            🗑 <?php esc_html_e( 'Vider', MB_TEXT_DOMAIN ); ?>
                        </button>
                        <button type="button" id="mb-insert-template" class="button">
                            📄 <?php esc_html_e( 'Voir un exemple', MB_TEXT_DOMAIN ); ?>
                        </button>
                        <span id="mb-import-status" class="mb-import-status"></span>
                    </div>
                </div>
            </div>

            <div class="mb-qbuilder-toolbar">
                <div class="mb-qbuilder-toolbar-left">
                    <span class="mb-qbuilder-title"><?php esc_html_e( 'Questions', MB_TEXT_DOMAIN ); ?></span>
                    <span class="mb-q-count-badge" id="mb-q-count">0 <?php esc_html_e( 'question', MB_TEXT_DOMAIN ); ?></span>
                </div>
                <div class="mb-qbuilder-toolbar-right">
                    <span id="mb-save-status" class="mb-save-status" aria-live="polite"></span>
                    <button type="button" id="mb-add-q" class="button mb-btn-add-q">
                        + <?php esc_html_e( 'Ajouter une question', MB_TEXT_DOMAIN ); ?>
                    </button>
                    <button type="button" id="mb-save-questions" class="button button-primary mb-btn-save-q">
                        💾 <?php esc_html_e( 'Sauvegarder les questions', MB_TEXT_DOMAIN ); ?>
                    </button>
                </div>
            </div>

            <div id="mb-questions-list"></div>

            <div class="mb-qbuilder-footer">
                <button type="button" id="mb-add-q-bottom" class="button mb-btn-add-q">
                    + <?php esc_html_e( 'Ajouter une question', MB_TEXT_DOMAIN ); ?>
                </button>
                <button type="button" id="mb-save-questions-bottom" class="button button-primary mb-btn-save-q">
                    💾 <?php esc_html_e( 'Sauvegarder les questions', MB_TEXT_DOMAIN ); ?>
                </button>
            </div>

            <input type="hidden" id="mb-questions-json" name="_mb_questions"
                   value="<?php echo esc_attr( $questions ); ?>">
        </div>
        <?php
    }

    public static function save_qcm_meta( int $post_id, WP_Post $post ): void {
        if ( ! isset( $_POST['mb_qcm_nonce'] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mb_qcm_nonce'] ) ), 'mb_save_qcm_meta' )
        ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Subtitle
        if ( isset( $_POST['_mb_subtitle'] ) ) {
            update_post_meta(
                $post_id,
                '_mb_subtitle',
                sanitize_text_field( wp_unslash( $_POST['_mb_subtitle'] ) )
            );
        }

        // Intro (allows basic HTML + LaTeX)
        if ( isset( $_POST['_mb_intro'] ) ) {
            update_post_meta(
                $post_id,
                '_mb_intro',
                wp_kses_post( wp_unslash( $_POST['_mb_intro'] ) )
            );
        }

        // Questions JSON
        if ( isset( $_POST['_mb_questions'] ) ) {
            $raw       = wp_unslash( $_POST['_mb_questions'] );
            $questions = json_decode( $raw, true );

            if ( is_array( $questions ) ) {
                $clean = [];
                foreach ( $questions as $q ) {
                    if ( ! is_array( $q ) ) {
                        continue;
                    }

                    $ans_raw = isset( $q['ans'] ) && is_array( $q['ans'] ) ? $q['ans'] : [];
                    $clean[] = [
                        'text'    => wp_kses_post( (string) ( $q['text']    ?? '' ) ),
                        'layout'  => in_array( $q['layout'] ?? '', [ 'grid', 'stack' ], true ) ? $q['layout'] : 'grid',
                        'ans'     => [
                            'a' => wp_kses_post( (string) ( $ans_raw['a'] ?? '' ) ),
                            'b' => wp_kses_post( (string) ( $ans_raw['b'] ?? '' ) ),
                            'c' => wp_kses_post( (string) ( $ans_raw['c'] ?? '' ) ),
                            'd' => wp_kses_post( (string) ( $ans_raw['d'] ?? '' ) ),
                        ],
                        'correct' => in_array( $q['correct'] ?? '', [ 'a', 'b', 'c', 'd' ], true ) ? $q['correct'] : 'a',
                        'corr'    => wp_kses_post( (string) ( $q['corr']   ?? '' ) ),
                    ];
                }

                update_post_meta( $post_id, '_mb_questions', wp_json_encode( $clean ) );
            }
        }

        // Lock flag
        $locked = isset( $_POST['_mb_locked'] ) && $_POST['_mb_locked'] === '1' ? '1' : '0';
        update_post_meta( $post_id, '_mb_locked', $locked );
    }

    // ── Lock column ───────────────────────────────────────────────────────────
    public static function add_lock_column( array $columns ): array {
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'title' ) {
                $new['mb_lock'] = __( 'Accès', MB_TEXT_DOMAIN );
            }
        }
        return $new;
    }

    public static function render_lock_column( string $column, int $post_id ): void {
        if ( $column !== 'mb_lock' ) {
            return;
        }
        $locked = get_post_meta( $post_id, '_mb_locked', true );
        $is_locked = $locked === '1';
        $label = $is_locked
            ? '<span class="mb-badge mb-badge-locked">🔒 ' . esc_html__( 'Premium', MB_TEXT_DOMAIN ) . '</span>'
            : '<span class="mb-badge mb-badge-free">🔓 ' . esc_html__( 'Libre', MB_TEXT_DOMAIN ) . '</span>';
        $title = $is_locked
            ? esc_html__( 'Cliquer pour déverrouiller', MB_TEXT_DOMAIN )
            : esc_html__( 'Cliquer pour verrouiller', MB_TEXT_DOMAIN );

        printf(
            '<button class="mb-toggle-lock-btn" data-id="%d" data-locked="%s" title="%s">%s</button>',
            $post_id,
            $is_locked ? '1' : '0',
            $title,
            $label
        );
    }

    public static function handle_toggle_lock(): void {
        check_ajax_referer( 'mb_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Accès refusé.' ] );
        }

        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => 'ID manquant.' ] );
        }

        $current = get_post_meta( $post_id, '_mb_locked', true );
        $new     = $current === '1' ? '0' : '1';
        update_post_meta( $post_id, '_mb_locked', $new );

        wp_send_json_success( [ 'locked' => $new ] );
    }

    // ── Menus ─────────────────────────────────────────────────────────────────
    public static function register_menus() {
        add_submenu_page(
            'edit.php?post_type=mb_qcm',
            __( 'Codes d\'activation', MB_TEXT_DOMAIN ),
            __( 'Codes', MB_TEXT_DOMAIN ),
            'manage_options',
            'mb-codes',
            [ __CLASS__, 'page_codes' ]
        );

        add_submenu_page(
            'edit.php?post_type=mb_qcm',
            __( 'Signalements', MB_TEXT_DOMAIN ),
            __( 'Signalements', MB_TEXT_DOMAIN ),
            'manage_options',
            'mb-reports',
            [ __CLASS__, 'page_reports' ]
        );

        add_submenu_page(
            'edit.php?post_type=mb_qcm',
            __( 'Réglages MathBoost', MB_TEXT_DOMAIN ),
            __( 'Réglages', MB_TEXT_DOMAIN ),
            'manage_options',
            'mb-settings',
            [ __CLASS__, 'page_settings' ]
        );
    }

    // ── Enqueue ───────────────────────────────────────────────────────────────
    public static function enqueue_admin( string $hook ): void {
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }

        $is_qcm_screen = ( $screen->post_type === 'mb_qcm' );
        $is_mb_page    = ( strpos( $hook, 'mb-' ) !== false );

        if ( ! $is_qcm_screen && ! $is_mb_page ) {
            return;
        }

        wp_enqueue_style( 'mb-admin', MB_PLUGIN_URL . 'assets/css/mb-admin.css', [], MB_VERSION );

        // Question builder JS only on the post edit screen
        if ( $is_qcm_screen && $screen->base === 'post' ) {
            wp_enqueue_script(
                'mb-admin',
                MB_PLUGIN_URL . 'assets/js/mb-admin.js',
                [ 'jquery' ],
                MB_VERSION,
                true
            );
        }

        wp_localize_script( 'jquery', 'mbAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'mb_admin_nonce' ),
        ] );

        // Lock toggle inline JS on QCM list screen
        if ( $is_qcm_screen && $screen->base === 'edit' ) {
            wp_add_inline_script( 'jquery', '
jQuery(function($){
  $(document).on("click", ".mb-toggle-lock-btn", function(){
    var btn = $(this);
    var id  = btn.data("id");
    $.post(mbAdmin.ajaxUrl, {
      action: "mb_admin_toggle_lock",
      nonce:  mbAdmin.nonce,
      post_id: id
    }, function(r){
      if (r.success) location.reload();
    });
  });
});' );
        }
    }

    // ── Settings ──────────────────────────────────────────────────────────────
    public static function register_settings() {
        register_setting( 'mb_settings_group', 'mb_paypal_client_id' );
        register_setting( 'mb_settings_group', 'mb_paypal_secret' );
        register_setting( 'mb_settings_group', 'mb_price' );
        register_setting( 'mb_settings_group', 'mb_currency' );
        register_setting( 'mb_settings_group', 'mb_max_sessions' );
        register_setting( 'mb_settings_group', 'mb_free_locked_count' );
        register_setting( 'mb_settings_group', 'mb_premium_duration' );
        register_setting( 'mb_settings_group', 'mb_email_contact' );
        register_setting( 'mb_settings_group', 'mb_payment_page_url' );
    }

    // ── Codes Page ───────────────────────────────────────────────────────────
    public static function page_codes() {
        $data = MB_Activation_Codes::get_all( 50, max( 1, (int) ( $_GET['paged'] ?? 1 ) ) );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Codes d\'activation', MB_TEXT_DOMAIN ); ?></h1>

            <div class="mb-admin-card">
                <h2><?php esc_html_e( 'Générer des codes', MB_TEXT_DOMAIN ); ?></h2>
                <form id="mb-generate-form" class="mb-gen-form">
                    <label><?php esc_html_e( 'Nombre', MB_TEXT_DOMAIN ); ?>
                        <input type="number" name="count" value="1" min="1" max="100">
                    </label>
                    <label><?php esc_html_e( 'Expiration', MB_TEXT_DOMAIN ); ?>
                        <input type="date" name="expires_at">
                    </label>
                    <label><?php esc_html_e( 'Notes', MB_TEXT_DOMAIN ); ?>
                        <input type="text" name="notes" placeholder="<?php esc_attr_e( 'Usage interne', MB_TEXT_DOMAIN ); ?>">
                    </label>
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Générer', MB_TEXT_DOMAIN ); ?></button>
                </form>
                <div id="mb-generated-codes" style="display:none;">
                    <h3><?php esc_html_e( 'Codes générés :', MB_TEXT_DOMAIN ); ?></h3>
                    <pre id="mb-codes-output"></pre>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Code', MB_TEXT_DOMAIN ); ?></th>
                        <th><?php esc_html_e( 'Statut', MB_TEXT_DOMAIN ); ?></th>
                        <th><?php esc_html_e( 'Utilisateur', MB_TEXT_DOMAIN ); ?></th>
                        <th><?php esc_html_e( 'Utilisé le', MB_TEXT_DOMAIN ); ?></th>
                        <th><?php esc_html_e( 'Expire le', MB_TEXT_DOMAIN ); ?></th>
                        <th><?php esc_html_e( 'Notes', MB_TEXT_DOMAIN ); ?></th>
                        <th><?php esc_html_e( 'Créé le', MB_TEXT_DOMAIN ); ?></th>
                        <th><?php esc_html_e( 'Actions', MB_TEXT_DOMAIN ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( ! empty( $data['rows'] ) ) : ?>
                    <?php foreach ( $data['rows'] as $row ) : ?>
                    <tr>
                        <td><code><?php echo esc_html( $row->code ); ?></code></td>
                        <td>
                            <?php if ( $row->used_at ) : ?>
                                <span class="mb-badge mb-badge-used"><?php esc_html_e( 'Utilisé', MB_TEXT_DOMAIN ); ?></span>
                            <?php elseif ( $row->expires_at && strtotime( $row->expires_at ) < time() ) : ?>
                                <span class="mb-badge mb-badge-expired"><?php esc_html_e( 'Expiré', MB_TEXT_DOMAIN ); ?></span>
                            <?php else : ?>
                                <span class="mb-badge mb-badge-active"><?php esc_html_e( 'Actif', MB_TEXT_DOMAIN ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $row->user_login ?? '—' ); ?></td>
                        <td><?php echo $row->used_at ? esc_html( $row->used_at ) : '—'; ?></td>
                        <td><?php echo $row->expires_at ? esc_html( $row->expires_at ) : '—'; ?></td>
                        <td><?php echo esc_html( $row->notes ?: '—' ); ?></td>
                        <td><?php echo esc_html( $row->created_at ); ?></td>
                        <td>
                            <button class="button button-small mb-delete-code" data-id="<?php echo (int) $row->id; ?>">
                                <?php esc_html_e( 'Supprimer', MB_TEXT_DOMAIN ); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="8"><?php esc_html_e( 'Aucun code.', MB_TEXT_DOMAIN ); ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <script>
        jQuery(function($) {
            $('#mb-generate-form').on('submit', function(e) {
                e.preventDefault();
                var fd = new FormData(this);
                fd.append('action', 'mb_admin_generate_codes');
                fd.append('nonce', mbAdmin.nonce);
                $.ajax({
                    url: mbAdmin.ajaxUrl, method: 'POST',
                    data: Object.fromEntries(fd),
                    success: function(r) {
                        if (r.success) {
                            $('#mb-codes-output').text(r.data.codes.join('\n'));
                            $('#mb-generated-codes').show();
                            setTimeout(function(){ location.reload(); }, 2000);
                        }
                    }
                });
            });
            $('.mb-delete-code').on('click', function() {
                if (!confirm('Supprimer ?')) return;
                var btn = $(this);
                $.post(mbAdmin.ajaxUrl, {
                    action: 'mb_admin_delete_code',
                    nonce: mbAdmin.nonce,
                    id: btn.data('id')
                }, function(r) { if (r.success) btn.closest('tr').fadeOut(); });
            });
        });
        </script>
        <?php
    }

    // ── Reports Page ─────────────────────────────────────────────────────────
    public static function page_reports() {
        global $wpdb;
        $table   = $wpdb->prefix . 'mb_error_reports';
        $reports = $wpdb->get_results(
            "SELECT r.*, p.post_title, u.user_login
             FROM {$table} r
             LEFT JOIN {$wpdb->posts} p ON p.ID = r.qcm_id
             LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id
             ORDER BY r.created_at DESC LIMIT 100"
        );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Signalements d\'erreurs', MB_TEXT_DOMAIN ); ?></h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'QCM', MB_TEXT_DOMAIN ); ?></th>
                        <th><?php esc_html_e( 'Question #', MB_TEXT_DOMAIN ); ?></th>
                        <th><?php esc_html_e( 'Message', MB_TEXT_DOMAIN ); ?></th>
                        <th><?php esc_html_e( 'Utilisateur', MB_TEXT_DOMAIN ); ?></th>
                        <th><?php esc_html_e( 'Statut', MB_TEXT_DOMAIN ); ?></th>
                        <th><?php esc_html_e( 'Date', MB_TEXT_DOMAIN ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( $reports ) : foreach ( $reports as $r ) : ?>
                    <tr>
                        <td><?php echo esc_html( $r->post_title ?? '#' . $r->qcm_id ); ?></td>
                        <td><?php echo (int) $r->question_num; ?></td>
                        <td><?php echo esc_html( $r->message ); ?></td>
                        <td><?php echo esc_html( $r->user_login ?? '—' ); ?></td>
                        <td><?php echo esc_html( $r->status ); ?></td>
                        <td><?php echo esc_html( $r->created_at ); ?></td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr><td colspan="6"><?php esc_html_e( 'Aucun signalement.', MB_TEXT_DOMAIN ); ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // ── Settings Page ────────────────────────────────────────────────────────
    public static function page_settings() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Réglages MathBoost', MB_TEXT_DOMAIN ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'mb_settings_group' ); ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'PayPal Client ID', MB_TEXT_DOMAIN ); ?></th>
                        <td><input type="text" name="mb_paypal_client_id" value="<?php echo esc_attr( get_option( 'mb_paypal_client_id' ) ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'PayPal Secret', MB_TEXT_DOMAIN ); ?></th>
                        <td><input type="password" name="mb_paypal_secret" value="<?php echo esc_attr( get_option( 'mb_paypal_secret' ) ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Prix (€)', MB_TEXT_DOMAIN ); ?></th>
                        <td><input type="number" name="mb_price" value="<?php echo esc_attr( get_option( 'mb_price', '15' ) ); ?>" step="0.01"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Devise', MB_TEXT_DOMAIN ); ?></th>
                        <td>
                            <select name="mb_currency">
                                <option value="EUR" <?php selected( get_option( 'mb_currency' ), 'EUR' ); ?>>EUR</option>
                                <option value="USD" <?php selected( get_option( 'mb_currency' ), 'USD' ); ?>>USD</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Sessions max par utilisateur', MB_TEXT_DOMAIN ); ?></th>
                        <td><input type="number" name="mb_max_sessions" value="<?php echo esc_attr( get_option( 'mb_max_sessions', '2' ) ); ?>" min="1" max="10"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'QCMs verrouillés (derniers N)', MB_TEXT_DOMAIN ); ?></th>
                        <td><input type="number" name="mb_free_locked_count" value="<?php echo esc_attr( get_option( 'mb_free_locked_count', '3' ) ); ?>" min="0"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Durée premium (jours, 0=illimité)', MB_TEXT_DOMAIN ); ?></th>
                        <td><input type="number" name="mb_premium_duration" value="<?php echo esc_attr( get_option( 'mb_premium_duration', '365' ) ); ?>" min="0"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Email contact', MB_TEXT_DOMAIN ); ?></th>
                        <td><input type="email" name="mb_email_contact" value="<?php echo esc_attr( get_option( 'mb_email_contact' ) ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'URL page paiement', MB_TEXT_DOMAIN ); ?></th>
                        <td>
                            <input type="url" name="mb_payment_page_url" value="<?php echo esc_attr( get_option( 'mb_payment_page_url' ) ); ?>" class="regular-text"
                                   placeholder="https://mathboost.net/abonnement/">
                            <p class="description"><?php esc_html_e( 'URL vers la page PayPal affichée quand un QCM est verrouillé.', MB_TEXT_DOMAIN ); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
