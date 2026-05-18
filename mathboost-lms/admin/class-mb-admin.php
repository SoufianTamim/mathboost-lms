<?php
defined( 'ABSPATH' ) || exit;

class MB_Admin {

    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'register_menus' ] );
        add_action( 'admin_init',            [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin' ] );
        add_action( 'admin_notices',         [ __CLASS__, 'maybe_show_success' ] );
        add_filter( 'admin_footer_text',     [ __CLASS__, 'admin_footer_version' ] );

        // AJAX
        add_action( 'wp_ajax_mb_admin_toggle_lock', [ __CLASS__, 'handle_toggle_lock' ] );

        // admin-post handlers (always registered)
        add_action( 'admin_post_mb_save_qcm',      [ __CLASS__, 'handle_save_qcm' ] );
        add_action( 'admin_post_mb_delete_qcm',    [ __CLASS__, 'handle_delete_qcm' ] );
        add_action( 'admin_post_mb_save_level',    [ __CLASS__, 'handle_save_level' ] );
        add_action( 'admin_post_mb_delete_level',  [ __CLASS__, 'handle_delete_level' ] );
        add_action( 'admin_post_mb_save_cat',      [ __CLASS__, 'handle_save_category' ] );
        add_action( 'admin_post_mb_delete_cat',    [ __CLASS__, 'handle_delete_category' ] );

        // Legacy WP CPT support while migration is pending
        if ( ! MB_Migrator::is_done() ) {
            add_action( 'add_meta_boxes',                    [ __CLASS__, 'add_meta_boxes' ] );
            add_action( 'save_post_mb_qcm',                  [ __CLASS__, 'save_qcm_meta' ], 10, 2 );
            add_filter( 'use_block_editor_for_post_type',    [ __CLASS__, 'disable_gutenberg' ], 10, 2 );
            add_filter( 'manage_mb_qcm_posts_columns',       [ __CLASS__, 'add_lock_column' ] );
            add_action( 'manage_mb_qcm_posts_custom_column', [ __CLASS__, 'render_lock_column' ], 10, 2 );
        }
    }

    // ── Menus ─────────────────────────────────────────────────────────────────
    public static function register_menus() {
        add_menu_page(
            __( 'MathBoost LMS', MB_TEXT_DOMAIN ),
            'MathBoost',
            'edit_posts',
            'mb-qcms',
            [ __CLASS__, 'page_qcms' ],
            'dashicons-welcome-learn-more',
            5
        );
        add_submenu_page( 'mb-qcms', __( 'QCMs', MB_TEXT_DOMAIN ),        __( 'QCMs', MB_TEXT_DOMAIN ),        'edit_posts',     'mb-qcms',       [ __CLASS__, 'page_qcms' ] );
        add_submenu_page( 'mb-qcms', __( 'Niveaux', MB_TEXT_DOMAIN ),     __( 'Niveaux', MB_TEXT_DOMAIN ),     'manage_options', 'mb-levels',     [ __CLASS__, 'page_levels' ] );
        add_submenu_page( 'mb-qcms', __( 'Catégories', MB_TEXT_DOMAIN ),  __( 'Catégories', MB_TEXT_DOMAIN ),  'manage_options', 'mb-categories', [ __CLASS__, 'page_categories' ] );
        add_submenu_page( 'mb-qcms', __( 'Codes', MB_TEXT_DOMAIN ),       __( 'Codes', MB_TEXT_DOMAIN ),       'manage_options', 'mb-codes',      [ __CLASS__, 'page_codes' ] );
        add_submenu_page( 'mb-qcms', __( 'Signalements', MB_TEXT_DOMAIN ),__( 'Signalements', MB_TEXT_DOMAIN ),'manage_options', 'mb-reports',    [ __CLASS__, 'page_reports' ] );
        add_submenu_page( 'mb-qcms', __( 'Réglages', MB_TEXT_DOMAIN ),    __( 'Réglages', MB_TEXT_DOMAIN ),    'manage_options', 'mb-settings',   [ __CLASS__, 'page_settings' ] );
    }

    // ── Success notice from redirects ─────────────────────────────────────────
    public static function maybe_show_success() {
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'mb-' ) === false ) {
            return;
        }

        // Migration success
        if ( isset( $_GET['mb_migrated'] ) ) {
            printf(
                '<div class="notice notice-success is-dismissible"><p>✅ ' .
                esc_html__( 'Migration réussie : %d niveaux, %d catégories, %d QCMs, %d liaisons.', MB_TEXT_DOMAIN ) . '</p></div>',
                (int) ( $_GET['mb_levels']  ?? 0 ),
                (int) ( $_GET['mb_cats']    ?? 0 ),
                (int) ( $_GET['mb_qcms']    ?? 0 ),
                (int) ( $_GET['mb_pivots']  ?? 0 )
            );
        }

        if ( isset( $_GET['saved'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Enregistré.', MB_TEXT_DOMAIN ) . '</p></div>';
        }
        if ( isset( $_GET['deleted'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Supprimé.', MB_TEXT_DOMAIN ) . '</p></div>';
        }
    }

    // ── Admin footer version badge ────────────────────────────────────────────
    public static function admin_footer_version( string $text ): string {
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'mb-' ) === false ) {
            return $text;
        }
        return '<span style="color:#aaa">MathBoost LMS <strong>v' . MB_VERSION . '</strong></span>';
    }

    // ── Enqueue ───────────────────────────────────────────────────────────────
    public static function enqueue_admin( string $hook ): void {
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }

        $is_mb_page    = ( strpos( $hook, 'mb-' ) !== false );
        $is_qcm_screen = ( $screen->post_type === 'mb_qcm' );

        if ( ! $is_mb_page && ! $is_qcm_screen ) {
            return;
        }

        wp_enqueue_style( 'mb-admin', MB_PLUGIN_URL . 'assets/css/mb-admin.css', [], MB_VERSION );

        wp_localize_script( 'jquery', 'mbAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'mb_admin_nonce' ),
        ] );

        // Question builder on new custom edit page or legacy WP post edit
        $is_qcm_edit = (
            ( $hook === 'toplevel_page_mb-qcms' && in_array( $_GET['action'] ?? '', [ 'edit', 'new' ], true ) ) ||
            ( $is_qcm_screen && $screen->base === 'post' )
        );

        if ( $is_qcm_edit ) {
            wp_enqueue_script( 'mb-admin', MB_PLUGIN_URL . 'assets/js/mb-admin.js', [ 'jquery' ], MB_VERSION, true );
            // Also localize on mb-admin (not just jquery)
            wp_localize_script( 'mb-admin', 'mbAdmin', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'mb_admin_nonce' ),
            ] );
        }

        // Lock toggle on the custom QCM list page
        if ( $hook === 'toplevel_page_mb-qcms' && ! isset( $_GET['action'] ) ) {
            wp_add_inline_script( 'jquery', '
jQuery(function($){
  $(document).on("click",".mb-toggle-lock-btn",function(){
    var btn=$(this), id=btn.data("id");
    $.post(mbAdmin.ajaxUrl,{action:"mb_admin_toggle_lock",nonce:mbAdmin.nonce,post_id:id},function(r){
      if(r.success) location.reload();
    });
  });
});' );
        }

        // Legacy lock toggle on WP CPT list
        if ( $is_qcm_screen && $screen->base === 'edit' ) {
            wp_add_inline_script( 'jquery', '
jQuery(function($){
  $(document).on("click",".mb-toggle-lock-btn",function(){
    var btn=$(this),id=btn.data("id");
    $.post(mbAdmin.ajaxUrl,{action:"mb_admin_toggle_lock",nonce:mbAdmin.nonce,post_id:id},function(r){
      if(r.success) location.reload();
    });
  });
});' );
        }
    }

    // ── QCMs page (list + edit/new) ───────────────────────────────────────────
    public static function page_qcms() {
        $action = $_GET['action'] ?? '';

        if ( $action === 'edit' || $action === 'new' ) {
            self::render_qcm_edit_page();
            return;
        }

        // Default: list page
        if ( ! MB_Migrator::is_done() ) {
            echo '<div class="wrap"><h1>MathBoost — QCMs</h1>';
            echo '<div class="notice notice-warning inline"><p>' .
                 esc_html__( 'La migration doit être effectuée avant d\'utiliser cet espace.', MB_TEXT_DOMAIN ) .
                 '</p></div></div>';
            return;
        }

        $qcms    = MB_QCM_Repository::get_all_any_status();
        $cats    = MB_Category_Repository::get_all();
        $cat_map = [];
        foreach ( $cats as $c ) {
            $cat_map[ (int) $c->id ] = $c->name;
        }
        $new_url = admin_url( 'admin.php?page=mb-qcms&action=new' );
        ?>
        <div class="wrap">
          <h1 class="wp-heading-inline"><?php esc_html_e( 'QCMs', MB_TEXT_DOMAIN ); ?></h1>
          <a href="<?php echo esc_url( $new_url ); ?>" class="page-title-action">
            + <?php esc_html_e( 'Ajouter un QCM', MB_TEXT_DOMAIN ); ?>
          </a>
          <hr class="wp-header-end">

          <table class="wp-list-table widefat fixed striped">
            <thead>
              <tr>
                <th style="width:40px">ID</th>
                <th><?php esc_html_e( 'Titre', MB_TEXT_DOMAIN ); ?></th>
                <th><?php esc_html_e( 'Catégories', MB_TEXT_DOMAIN ); ?></th>
                <th style="width:80px"><?php esc_html_e( 'Questions', MB_TEXT_DOMAIN ); ?></th>
                <th style="width:90px"><?php esc_html_e( 'Accès', MB_TEXT_DOMAIN ); ?></th>
                <th style="width:80px"><?php esc_html_e( 'Statut', MB_TEXT_DOMAIN ); ?></th>
                <th style="width:150px"><?php esc_html_e( 'Actions', MB_TEXT_DOMAIN ); ?></th>
              </tr>
            </thead>
            <tbody>
            <?php if ( $qcms ) : foreach ( $qcms as $qcm ) :
              $q_count   = count( json_decode( $qcm->questions ?: '[]', true ) ?? [] );
              $cat_ids   = $qcm->cat_ids ? explode( ',', $qcm->cat_ids ) : [];
              $cat_names = array_filter( array_map( fn( $cid ) => $cat_map[ (int) $cid ] ?? null, $cat_ids ) );
              $edit_url  = admin_url( 'admin.php?page=mb-qcms&action=edit&id=' . $qcm->id );
              $del_url   = wp_nonce_url(
                  admin_url( 'admin-post.php?action=mb_delete_qcm&id=' . $qcm->id ),
                  'mb_delete_qcm_' . $qcm->id
              );
            ?>
              <tr>
                <td><?php echo (int) $qcm->id; ?></td>
                <td>
                  <strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $qcm->title ); ?></a></strong>
                  <?php if ( $qcm->subtitle ) echo '<br><small>' . esc_html( $qcm->subtitle ) . '</small>'; ?>
                </td>
                <td><?php echo esc_html( implode( ', ', $cat_names ) ?: '—' ); ?></td>
                <td><?php echo $q_count; ?></td>
                <td>
                  <button class="mb-toggle-lock-btn button button-small" data-id="<?php echo (int) $qcm->id; ?>">
                    <?php echo $qcm->is_locked
                        ? '<span class="mb-badge mb-badge-locked">🔒 ' . esc_html__( 'Premium', MB_TEXT_DOMAIN ) . '</span>'
                        : '<span class="mb-badge mb-badge-free">🔓 ' . esc_html__( 'Libre', MB_TEXT_DOMAIN ) . '</span>'; ?>
                  </button>
                </td>
                <td><?php echo esc_html( $qcm->status ); ?></td>
                <td>
                  <a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small"><?php esc_html_e( 'Modifier', MB_TEXT_DOMAIN ); ?></a>
                  <a href="<?php echo esc_url( $del_url ); ?>" class="button button-small"
                     onclick="return confirm('<?php esc_attr_e( 'Supprimer ce QCM et ses questions ?', MB_TEXT_DOMAIN ); ?>')">
                    <?php esc_html_e( 'Supprimer', MB_TEXT_DOMAIN ); ?>
                  </a>
                </td>
              </tr>
            <?php endforeach; else : ?>
              <tr><td colspan="7"><?php esc_html_e( 'Aucun QCM.', MB_TEXT_DOMAIN ); ?></td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php
    }

    private static function render_qcm_edit_page() {
        $qcm_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
        $qcm    = $qcm_id ? MB_QCM_Repository::get_by_id( $qcm_id ) : null;

        if ( $qcm_id && ! $qcm ) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' .
                 esc_html__( 'QCM introuvable.', MB_TEXT_DOMAIN ) . '</p></div></div>';
            return;
        }

        $current_cat_ids = $qcm_id ? MB_QCM_Repository::get_category_ids( $qcm_id ) : [];
        $all_levels      = MB_Level_Repository::get_all();
        $questions_json  = $qcm ? ( $qcm->questions ?: '[]' ) : '[]';
        $page_title      = $qcm ? __( 'Modifier le QCM', MB_TEXT_DOMAIN ) : __( 'Ajouter un QCM', MB_TEXT_DOMAIN );
        $back_url        = admin_url( 'admin.php?page=mb-qcms' );
        ?>
        <div class="wrap">
          <h1><?php echo esc_html( $page_title ); ?>
            <a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action">← <?php esc_html_e( 'Retour', MB_TEXT_DOMAIN ); ?></a>
          </h1>

          <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'mb_save_qcm', 'mb_qcm_nonce' ); ?>
            <input type="hidden" name="action" value="mb_save_qcm">
            <input type="hidden" name="mb_qcm_id" id="mb_qcm_id" value="<?php echo $qcm_id; ?>">

            <div id="poststuff">
              <div id="post-body" class="metabox-holder columns-2">

                <!-- ── Main column ────────────────────────────── -->
                <div id="post-body-content">

                  <div class="postbox">
                    <h2 class="hndle"><span><?php esc_html_e( 'Informations', MB_TEXT_DOMAIN ); ?></span></h2>
                    <div class="inside">
                      <table class="form-table">
                        <tr>
                          <th><label for="mb_title"><?php esc_html_e( 'Titre', MB_TEXT_DOMAIN ); ?></label></th>
                          <td><input type="text" id="mb_title" name="mb_title" class="widefat"
                                     value="<?php echo esc_attr( $qcm->title ?? '' ); ?>" required></td>
                        </tr>
                        <tr>
                          <th><label for="mb_subtitle"><?php esc_html_e( 'Sous-titre', MB_TEXT_DOMAIN ); ?></label></th>
                          <td><input type="text" id="mb_subtitle" name="mb_subtitle" class="widefat"
                                     value="<?php echo esc_attr( $qcm->subtitle ?? '' ); ?>"
                                     placeholder="<?php esc_attr_e( 'Ex : 10 questions · Niveau 3ème', MB_TEXT_DOMAIN ); ?>"></td>
                        </tr>
                        <tr>
                          <th><label for="mb_intro"><?php esc_html_e( 'Introduction', MB_TEXT_DOMAIN ); ?></label></th>
                          <td><textarea id="mb_intro" name="mb_intro" class="widefat" rows="4"><?php
                            echo esc_textarea( $qcm->intro ?? '' );
                          ?></textarea></td>
                        </tr>
                      </table>
                    </div>
                  </div>

                  <!-- ── Question builder ───────────────────────── -->
                  <div id="mb-qcm-builder" class="postbox">
                    <h2 class="hndle"><span><?php esc_html_e( 'Questions', MB_TEXT_DOMAIN ); ?></span></h2>
                    <div class="inside">

                      <!-- Import panel -->
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
                            <?php esc_html_e( 'Collez le contenu JS (const Q = [{...}]) ou JSON pur.', MB_TEXT_DOMAIN ); ?>
                          </p>
                          <textarea id="mb-import-code" rows="8" class="widefat mb-import-textarea"
                                    placeholder="<?php esc_attr_e( 'const Q = [ { text: "…", ans: {a:…,b:…,c:…,d:…}, correct: "a", corr: "…" }, … ]', MB_TEXT_DOMAIN ); ?>"></textarea>
                          <div class="mb-import-actions">
                            <button type="button" id="mb-do-import" class="button button-primary">
                              📥 <?php esc_html_e( 'Charger les questions', MB_TEXT_DOMAIN ); ?>
                            </button>
                            <button type="button" id="mb-clear-import" class="button">🗑 <?php esc_html_e( 'Vider', MB_TEXT_DOMAIN ); ?></button>
                            <button type="button" id="mb-insert-template" class="button">📄 <?php esc_html_e( 'Voir un exemple', MB_TEXT_DOMAIN ); ?></button>
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

                      <input type="hidden" id="mb-questions-json" value="<?php echo esc_attr( $questions_json ); ?>">
                    </div>
                  </div>

                </div><!-- #post-body-content -->

                <!-- ── Sidebar ────────────────────────────────── -->
                <div id="postbox-container-1" class="postbox-container">

                  <div class="postbox">
                    <h2 class="hndle"><span><?php esc_html_e( 'Publier', MB_TEXT_DOMAIN ); ?></span></h2>
                    <div class="inside">
                      <div class="misc-pub-section">
                        <label><?php esc_html_e( 'Statut', MB_TEXT_DOMAIN ); ?></label>
                        <select name="mb_status" style="width:100%;margin-top:4px">
                          <option value="publish" <?php selected( $qcm->status ?? 'publish', 'publish' ); ?>>
                            <?php esc_html_e( 'Publié', MB_TEXT_DOMAIN ); ?>
                          </option>
                          <option value="draft" <?php selected( $qcm->status ?? 'publish', 'draft' ); ?>>
                            <?php esc_html_e( 'Brouillon', MB_TEXT_DOMAIN ); ?>
                          </option>
                        </select>
                      </div>
                      <div class="misc-pub-section">
                        <label>
                          <input type="checkbox" name="mb_is_locked" value="1" <?php checked( $qcm->is_locked ?? 0, 1 ); ?>>
                          🔒 <?php esc_html_e( 'Réservé aux membres premium', MB_TEXT_DOMAIN ); ?>
                        </label>
                      </div>
                      <div style="margin-top:12px">
                        <button type="submit" class="button button-primary button-large" style="width:100%">
                          💾 <?php echo $qcm ? esc_html__( 'Mettre à jour', MB_TEXT_DOMAIN ) : esc_html__( 'Créer le QCM', MB_TEXT_DOMAIN ); ?>
                        </button>
                      </div>
                    </div>
                  </div>

                  <div class="postbox">
                    <h2 class="hndle"><span><?php esc_html_e( 'Catégories', MB_TEXT_DOMAIN ); ?></span></h2>
                    <div class="inside" style="max-height:300px;overflow-y:auto">
                      <?php foreach ( $all_levels as $lvl ) :
                        $lvl_cats = MB_Category_Repository::get_by_level( (int) $lvl->id );
                        if ( empty( $lvl_cats ) ) continue;
                      ?>
                        <p style="font-weight:600;margin:8px 0 4px"><?php echo esc_html( $lvl->name ); ?></p>
                        <?php foreach ( $lvl_cats as $cat ) : ?>
                          <label style="display:block;margin-left:12px">
                            <input type="checkbox" name="mb_categories[]" value="<?php echo (int) $cat->id; ?>"
                                   <?php checked( in_array( (int) $cat->id, $current_cat_ids, true ) ); ?>>
                            <?php echo esc_html( $cat->name ); ?>
                          </label>
                        <?php endforeach; ?>
                      <?php endforeach; ?>
                    </div>
                  </div>

                </div><!-- #postbox-container-1 -->

              </div><!-- #post-body -->
            </div><!-- #poststuff -->
          </form>
        </div>
        <?php
    }

    // ── admin-post handlers: QCM ──────────────────────────────────────────────
    public static function handle_save_qcm() {
        check_admin_referer( 'mb_save_qcm', 'mb_qcm_nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'Accès refusé.', MB_TEXT_DOMAIN ) );
        }

        $qcm_id    = isset( $_POST['mb_qcm_id'] ) ? (int) $_POST['mb_qcm_id'] : 0;
        $title     = sanitize_text_field( wp_unslash( $_POST['mb_title']    ?? '' ) );
        $subtitle  = sanitize_text_field( wp_unslash( $_POST['mb_subtitle'] ?? '' ) );
        $intro     = wp_kses_post( wp_unslash( $_POST['mb_intro']           ?? '' ) );
        $status    = in_array( $_POST['mb_status'] ?? 'publish', [ 'publish', 'draft' ], true )
                     ? $_POST['mb_status'] : 'publish';
        $is_locked = isset( $_POST['mb_is_locked'] ) ? 1 : 0;
        $cat_ids   = isset( $_POST['mb_categories'] ) ? array_map( 'intval', (array) $_POST['mb_categories'] ) : [];

        $data = [
            'title'     => $title,
            'subtitle'  => $subtitle,
            'intro'     => $intro,
            'status'    => $status,
            'is_locked' => $is_locked,
        ];
        if ( $qcm_id ) {
            $data['id'] = $qcm_id;
        }

        $new_id = MB_QCM_Repository::save( $data );
        MB_QCM_Repository::set_categories( $new_id, $cat_ids );

        wp_safe_redirect( admin_url( 'admin.php?page=mb-qcms&action=edit&id=' . $new_id . '&saved=1' ) );
        exit;
    }

    public static function handle_delete_qcm() {
        $id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
        check_admin_referer( 'mb_delete_qcm_' . $id );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', MB_TEXT_DOMAIN ) );
        }

        if ( $id ) {
            MB_QCM_Repository::delete( $id );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=mb-qcms&deleted=1' ) );
        exit;
    }

    // ── AJAX: toggle lock ─────────────────────────────────────────────────────
    public static function handle_toggle_lock(): void {
        check_ajax_referer( 'mb_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Accès refusé.' ] );
        }

        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => 'ID manquant.' ] );
        }

        if ( MB_Migrator::is_done() ) {
            $qcm       = MB_QCM_Repository::get_by_id( $post_id );
            $new_locked = $qcm ? ( $qcm->is_locked ? 0 : 1 ) : 0;
            MB_QCM_Repository::save( [ 'id' => $post_id, 'is_locked' => $new_locked ] );
            wp_send_json_success( [ 'locked' => (string) $new_locked ] );
        } else {
            $current = get_post_meta( $post_id, '_mb_locked', true );
            $new     = $current === '1' ? '0' : '1';
            update_post_meta( $post_id, '_mb_locked', $new );
            wp_send_json_success( [ 'locked' => $new ] );
        }
    }

    // ── Levels page ───────────────────────────────────────────────────────────
    public static function page_levels() {
        $action = $_GET['action'] ?? '';
        $edit   = $action === 'edit' && isset( $_GET['id'] ) ? MB_Level_Repository::get_by_id( (int) $_GET['id'] ) : null;
        $levels = MB_Level_Repository::get_all();
        ?>
        <div class="wrap">
          <h1><?php esc_html_e( 'Niveaux', MB_TEXT_DOMAIN ); ?></h1>

          <div style="display:flex;gap:24px;align-items:flex-start">

            <!-- List -->
            <div style="flex:2">
              <table class="wp-list-table widefat fixed striped">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th><?php esc_html_e( 'Nom', MB_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Slug', MB_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Parent', MB_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Couleur', MB_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Ordre', MB_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Actions', MB_TEXT_DOMAIN ); ?></th>
                  </tr>
                </thead>
                <tbody>
                <?php if ( $levels ) :
                  $level_map = array_column( $levels, 'name', 'id' );
                  foreach ( $levels as $lvl ) :
                    $edit_url = admin_url( 'admin.php?page=mb-levels&action=edit&id=' . $lvl->id );
                    $del_url  = wp_nonce_url( admin_url( 'admin-post.php?action=mb_delete_level&id=' . $lvl->id ), 'mb_delete_level_' . $lvl->id );
                ?>
                  <tr>
                    <td><?php echo (int) $lvl->id; ?></td>
                    <td><?php echo esc_html( $lvl->name ); ?></td>
                    <td><code><?php echo esc_html( $lvl->slug ); ?></code></td>
                    <td><?php echo esc_html( $lvl->parent_id ? ( $level_map[ $lvl->parent_id ] ?? '#' . $lvl->parent_id ) : '—' ); ?></td>
                    <td><?php echo esc_html( $lvl->color ?: 'teal' ); ?></td>
                    <td><?php echo (int) $lvl->menu_order; ?></td>
                    <td>
                      <a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small"><?php esc_html_e( 'Modifier', MB_TEXT_DOMAIN ); ?></a>
                      <a href="<?php echo esc_url( $del_url ); ?>" class="button button-small"
                         onclick="return confirm('<?php esc_attr_e( 'Supprimer ?', MB_TEXT_DOMAIN ); ?>')">
                        <?php esc_html_e( 'Supprimer', MB_TEXT_DOMAIN ); ?>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; else : ?>
                  <tr><td colspan="7"><?php esc_html_e( 'Aucun niveau.', MB_TEXT_DOMAIN ); ?></td></tr>
                <?php endif; ?>
                </tbody>
              </table>
            </div>

            <!-- Form -->
            <div style="flex:1;min-width:280px">
              <div class="postbox">
                <h2 class="hndle"><span><?php echo $edit ? esc_html__( 'Modifier le niveau', MB_TEXT_DOMAIN ) : esc_html__( 'Ajouter un niveau', MB_TEXT_DOMAIN ); ?></span></h2>
                <div class="inside">
                  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'mb_save_level', 'mb_level_nonce' ); ?>
                    <input type="hidden" name="action" value="mb_save_level">
                    <input type="hidden" name="mb_level_id" value="<?php echo $edit ? (int) $edit->id : 0; ?>">

                    <table class="form-table">
                      <tr>
                        <th><label><?php esc_html_e( 'Nom', MB_TEXT_DOMAIN ); ?></label></th>
                        <td><input type="text" name="mb_name" class="widefat" value="<?php echo esc_attr( $edit->name ?? '' ); ?>" required></td>
                      </tr>
                      <tr>
                        <th><label><?php esc_html_e( 'Slug', MB_TEXT_DOMAIN ); ?></label></th>
                        <td><input type="text" name="mb_slug" class="widefat" value="<?php echo esc_attr( $edit->slug ?? '' ); ?>"
                                   placeholder="<?php esc_attr_e( 'auto-généré si vide', MB_TEXT_DOMAIN ); ?>"></td>
                      </tr>
                      <tr>
                        <th><label><?php esc_html_e( 'Parent', MB_TEXT_DOMAIN ); ?></label></th>
                        <td>
                          <select name="mb_parent_id" class="widefat">
                            <option value=""><?php esc_html_e( '— Aucun (niveau racine) —', MB_TEXT_DOMAIN ); ?></option>
                            <?php foreach ( $levels as $lvl ) :
                              if ( $edit && $lvl->id == $edit->id ) continue;
                            ?>
                              <option value="<?php echo (int) $lvl->id; ?>" <?php selected( $edit->parent_id ?? null, $lvl->id ); ?>>
                                <?php echo esc_html( $lvl->name ); ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </td>
                      </tr>
                      <tr>
                        <th><label><?php esc_html_e( 'Couleur', MB_TEXT_DOMAIN ); ?></label></th>
                        <td><input type="text" name="mb_color" class="widefat" value="<?php echo esc_attr( $edit->color ?? 'teal' ); ?>"
                                   placeholder="teal, coral, blue…"></td>
                      </tr>
                      <tr>
                        <th><label><?php esc_html_e( 'Description', MB_TEXT_DOMAIN ); ?></label></th>
                        <td><textarea name="mb_description" class="widefat" rows="2"><?php echo esc_textarea( $edit->description ?? '' ); ?></textarea></td>
                      </tr>
                      <tr>
                        <th><label><?php esc_html_e( 'Ordre', MB_TEXT_DOMAIN ); ?></label></th>
                        <td><input type="number" name="mb_menu_order" value="<?php echo (int) ( $edit->menu_order ?? 0 ); ?>" style="width:80px"></td>
                      </tr>
                    </table>

                    <p>
                      <button type="submit" class="button button-primary">
                        <?php echo $edit ? esc_html__( 'Mettre à jour', MB_TEXT_DOMAIN ) : esc_html__( 'Ajouter', MB_TEXT_DOMAIN ); ?>
                      </button>
                      <?php if ( $edit ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=mb-levels' ) ); ?>" class="button">
                          <?php esc_html_e( 'Annuler', MB_TEXT_DOMAIN ); ?>
                        </a>
                      <?php endif; ?>
                    </p>
                  </form>
                </div>
              </div>
            </div>

          </div>
        </div>
        <?php
    }

    // ── admin-post handlers: levels ───────────────────────────────────────────
    public static function handle_save_level() {
        check_admin_referer( 'mb_save_level', 'mb_level_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', MB_TEXT_DOMAIN ) );
        }

        $id    = isset( $_POST['mb_level_id'] ) ? (int) $_POST['mb_level_id'] : 0;
        $name  = sanitize_text_field( wp_unslash( $_POST['mb_name']        ?? '' ) );
        $slug  = sanitize_title( wp_unslash( $_POST['mb_slug']             ?? '' ) ) ?: sanitize_title( $name );
        $desc  = sanitize_textarea_field( wp_unslash( $_POST['mb_description'] ?? '' ) );
        $color = sanitize_text_field( wp_unslash( $_POST['mb_color']       ?? 'teal' ) );
        $order = (int) ( $_POST['mb_menu_order'] ?? 0 );
        $parent = isset( $_POST['mb_parent_id'] ) && $_POST['mb_parent_id'] !== ''
                  ? (int) $_POST['mb_parent_id'] : null;

        $data = [
            'name'        => $name,
            'slug'        => $slug,
            'description' => $desc,
            'color'       => $color,
            'menu_order'  => $order,
            'parent_id'   => $parent,
        ];
        if ( $id ) {
            $data['id'] = $id;
        }

        MB_Level_Repository::save( $data );
        wp_safe_redirect( admin_url( 'admin.php?page=mb-levels&saved=1' ) );
        exit;
    }

    public static function handle_delete_level() {
        $id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
        check_admin_referer( 'mb_delete_level_' . $id );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', MB_TEXT_DOMAIN ) );
        }

        if ( $id ) {
            MB_Level_Repository::delete( $id );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=mb-levels&deleted=1' ) );
        exit;
    }

    // ── Categories page ───────────────────────────────────────────────────────
    public static function page_categories() {
        $action = $_GET['action'] ?? '';
        $edit   = $action === 'edit' && isset( $_GET['id'] ) ? MB_Category_Repository::get_by_id( (int) $_GET['id'] ) : null;
        $cats   = MB_Category_Repository::get_all();
        $levels = MB_Level_Repository::get_all();
        $level_map = array_column( $levels, 'name', 'id' );
        ?>
        <div class="wrap">
          <h1><?php esc_html_e( 'Catégories', MB_TEXT_DOMAIN ); ?></h1>

          <div style="display:flex;gap:24px;align-items:flex-start">

            <div style="flex:2">
              <table class="wp-list-table widefat fixed striped">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th><?php esc_html_e( 'Nom', MB_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Slug', MB_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Niveau', MB_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Ordre', MB_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Actions', MB_TEXT_DOMAIN ); ?></th>
                  </tr>
                </thead>
                <tbody>
                <?php if ( $cats ) : foreach ( $cats as $cat ) :
                  $edit_url = admin_url( 'admin.php?page=mb-categories&action=edit&id=' . $cat->id );
                  $del_url  = wp_nonce_url( admin_url( 'admin-post.php?action=mb_delete_cat&id=' . $cat->id ), 'mb_delete_cat_' . $cat->id );
                ?>
                  <tr>
                    <td><?php echo (int) $cat->id; ?></td>
                    <td><?php echo esc_html( $cat->name ); ?></td>
                    <td><code><?php echo esc_html( $cat->slug ); ?></code></td>
                    <td><?php echo esc_html( $cat->level_id ? ( $level_map[ $cat->level_id ] ?? '#' . $cat->level_id ) : '—' ); ?></td>
                    <td><?php echo (int) $cat->menu_order; ?></td>
                    <td>
                      <a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small"><?php esc_html_e( 'Modifier', MB_TEXT_DOMAIN ); ?></a>
                      <a href="<?php echo esc_url( $del_url ); ?>" class="button button-small"
                         onclick="return confirm('<?php esc_attr_e( 'Supprimer ?', MB_TEXT_DOMAIN ); ?>')">
                        <?php esc_html_e( 'Supprimer', MB_TEXT_DOMAIN ); ?>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; else : ?>
                  <tr><td colspan="6"><?php esc_html_e( 'Aucune catégorie.', MB_TEXT_DOMAIN ); ?></td></tr>
                <?php endif; ?>
                </tbody>
              </table>
            </div>

            <div style="flex:1;min-width:280px">
              <div class="postbox">
                <h2 class="hndle"><span><?php echo $edit ? esc_html__( 'Modifier', MB_TEXT_DOMAIN ) : esc_html__( 'Ajouter une catégorie', MB_TEXT_DOMAIN ); ?></span></h2>
                <div class="inside">
                  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'mb_save_cat', 'mb_cat_nonce' ); ?>
                    <input type="hidden" name="action" value="mb_save_cat">
                    <input type="hidden" name="mb_cat_id" value="<?php echo $edit ? (int) $edit->id : 0; ?>">

                    <table class="form-table">
                      <tr>
                        <th><label><?php esc_html_e( 'Nom', MB_TEXT_DOMAIN ); ?></label></th>
                        <td><input type="text" name="mb_name" class="widefat" value="<?php echo esc_attr( $edit->name ?? '' ); ?>" required></td>
                      </tr>
                      <tr>
                        <th><label><?php esc_html_e( 'Slug', MB_TEXT_DOMAIN ); ?></label></th>
                        <td><input type="text" name="mb_slug" class="widefat" value="<?php echo esc_attr( $edit->slug ?? '' ); ?>"
                                   placeholder="<?php esc_attr_e( 'auto-généré si vide', MB_TEXT_DOMAIN ); ?>"></td>
                      </tr>
                      <tr>
                        <th><label><?php esc_html_e( 'Niveau', MB_TEXT_DOMAIN ); ?></label></th>
                        <td>
                          <select name="mb_level_id" class="widefat">
                            <option value=""><?php esc_html_e( '— Aucun —', MB_TEXT_DOMAIN ); ?></option>
                            <?php foreach ( $levels as $lvl ) : ?>
                              <option value="<?php echo (int) $lvl->id; ?>" <?php selected( $edit->level_id ?? null, $lvl->id ); ?>>
                                <?php echo esc_html( $lvl->name ); ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </td>
                      </tr>
                      <tr>
                        <th><label><?php esc_html_e( 'Description', MB_TEXT_DOMAIN ); ?></label></th>
                        <td><textarea name="mb_description" class="widefat" rows="2"><?php echo esc_textarea( $edit->description ?? '' ); ?></textarea></td>
                      </tr>
                      <tr>
                        <th><label><?php esc_html_e( 'Ordre', MB_TEXT_DOMAIN ); ?></label></th>
                        <td><input type="number" name="mb_menu_order" value="<?php echo (int) ( $edit->menu_order ?? 0 ); ?>" style="width:80px"></td>
                      </tr>
                    </table>

                    <p>
                      <button type="submit" class="button button-primary">
                        <?php echo $edit ? esc_html__( 'Mettre à jour', MB_TEXT_DOMAIN ) : esc_html__( 'Ajouter', MB_TEXT_DOMAIN ); ?>
                      </button>
                      <?php if ( $edit ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=mb-categories' ) ); ?>" class="button">
                          <?php esc_html_e( 'Annuler', MB_TEXT_DOMAIN ); ?>
                        </a>
                      <?php endif; ?>
                    </p>
                  </form>
                </div>
              </div>
            </div>

          </div>
        </div>
        <?php
    }

    // ── admin-post handlers: categories ──────────────────────────────────────
    public static function handle_save_category() {
        check_admin_referer( 'mb_save_cat', 'mb_cat_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', MB_TEXT_DOMAIN ) );
        }

        $id       = isset( $_POST['mb_cat_id'] ) ? (int) $_POST['mb_cat_id'] : 0;
        $name     = sanitize_text_field( wp_unslash( $_POST['mb_name']        ?? '' ) );
        $slug     = sanitize_title( wp_unslash( $_POST['mb_slug']             ?? '' ) ) ?: sanitize_title( $name );
        $desc     = sanitize_textarea_field( wp_unslash( $_POST['mb_description'] ?? '' ) );
        $order    = (int) ( $_POST['mb_menu_order'] ?? 0 );
        $level_id = isset( $_POST['mb_level_id'] ) && $_POST['mb_level_id'] !== ''
                    ? (int) $_POST['mb_level_id'] : null;

        $data = [
            'name'        => $name,
            'slug'        => $slug,
            'description' => $desc,
            'menu_order'  => $order,
            'level_id'    => $level_id,
        ];
        if ( $id ) {
            $data['id'] = $id;
        }

        MB_Category_Repository::save( $data );
        wp_safe_redirect( admin_url( 'admin.php?page=mb-categories&saved=1' ) );
        exit;
    }

    public static function handle_delete_category() {
        $id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
        check_admin_referer( 'mb_delete_cat_' . $id );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', MB_TEXT_DOMAIN ) );
        }

        if ( $id ) {
            MB_Category_Repository::delete( $id );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=mb-categories&deleted=1' ) );
        exit;
    }

    // ── Codes page ────────────────────────────────────────────────────────────
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
            <div id="mb-generated-codes" style="display:none">
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
            <?php if ( ! empty( $data['rows'] ) ) : foreach ( $data['rows'] as $row ) : ?>
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
            <?php endforeach; else : ?>
              <tr><td colspan="8"><?php esc_html_e( 'Aucun code.', MB_TEXT_DOMAIN ); ?></td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
        <script>
        jQuery(function($){
          $('#mb-generate-form').on('submit',function(e){
            e.preventDefault();
            var fd=new FormData(this);
            fd.append('action','mb_admin_generate_codes');
            fd.append('nonce',mbAdmin.nonce);
            $.ajax({url:mbAdmin.ajaxUrl,method:'POST',data:Object.fromEntries(fd),success:function(r){
              if(r.success){$('#mb-codes-output').text(r.data.codes.join('\n'));$('#mb-generated-codes').show();setTimeout(function(){location.reload();},2000);}
            }});
          });
          $('.mb-delete-code').on('click',function(){
            if(!confirm('Supprimer ?'))return;
            var btn=$(this);
            $.post(mbAdmin.ajaxUrl,{action:'mb_admin_delete_code',nonce:mbAdmin.nonce,id:btn.data('id')},function(r){if(r.success)btn.closest('tr').fadeOut();});
          });
        });
        </script>
        <?php
    }

    // ── Reports page ─────────────────────────────────────────────────────────
    public static function page_reports() {
        global $wpdb;
        $table = $wpdb->prefix . 'mb_error_reports';

        // After migration, join with mb_qcms; otherwise join with wp_posts
        if ( MB_Migrator::is_done() ) {
            $reports = $wpdb->get_results(
                "SELECT r.*, q.title AS qcm_title, u.user_login
                 FROM {$table} r
                 LEFT JOIN {$wpdb->prefix}mb_qcms q ON q.id = r.qcm_id
                 LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id
                 ORDER BY r.created_at DESC LIMIT 100"
            );
        } else {
            $reports = $wpdb->get_results(
                "SELECT r.*, p.post_title AS qcm_title, u.user_login
                 FROM {$table} r
                 LEFT JOIN {$wpdb->posts} p ON p.ID = r.qcm_id
                 LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id
                 ORDER BY r.created_at DESC LIMIT 100"
            );
        }
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
                <td><?php echo esc_html( $r->qcm_title ?? '#' . $r->qcm_id ); ?></td>
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

    // ── Settings page ─────────────────────────────────────────────────────────
    public static function register_settings() {
        foreach ( [
            'mb_paypal_client_id', 'mb_paypal_secret', 'mb_price', 'mb_currency',
            'mb_max_sessions', 'mb_free_locked_count', 'mb_premium_duration',
            'mb_email_contact', 'mb_payment_page_url',
        ] as $opt ) {
            register_setting( 'mb_settings_group', $opt );
        }
    }

    public static function page_settings() {
        ?>
        <div class="wrap">
          <h1><?php esc_html_e( 'Réglages MathBoost', MB_TEXT_DOMAIN ); ?></h1>
          <form method="post" action="options.php">
            <?php settings_fields( 'mb_settings_group' ); ?>
            <table class="form-table">
              <tr><th><?php esc_html_e( 'PayPal Client ID', MB_TEXT_DOMAIN ); ?></th>
                <td><input type="text" name="mb_paypal_client_id" value="<?php echo esc_attr( get_option( 'mb_paypal_client_id' ) ); ?>" class="regular-text"></td></tr>
              <tr><th><?php esc_html_e( 'PayPal Secret', MB_TEXT_DOMAIN ); ?></th>
                <td><input type="password" name="mb_paypal_secret" value="<?php echo esc_attr( get_option( 'mb_paypal_secret' ) ); ?>" class="regular-text"></td></tr>
              <tr><th><?php esc_html_e( 'Prix (€)', MB_TEXT_DOMAIN ); ?></th>
                <td><input type="number" name="mb_price" value="<?php echo esc_attr( get_option( 'mb_price', '15' ) ); ?>" step="0.01"></td></tr>
              <tr><th><?php esc_html_e( 'Devise', MB_TEXT_DOMAIN ); ?></th>
                <td><select name="mb_currency">
                  <option value="EUR" <?php selected( get_option( 'mb_currency' ), 'EUR' ); ?>>EUR</option>
                  <option value="USD" <?php selected( get_option( 'mb_currency' ), 'USD' ); ?>>USD</option>
                </select></td></tr>
              <tr><th><?php esc_html_e( 'Sessions max par utilisateur', MB_TEXT_DOMAIN ); ?></th>
                <td><input type="number" name="mb_max_sessions" value="<?php echo esc_attr( get_option( 'mb_max_sessions', '2' ) ); ?>" min="1" max="10"></td></tr>
              <tr><th><?php esc_html_e( 'QCMs verrouillés (derniers N)', MB_TEXT_DOMAIN ); ?></th>
                <td><input type="number" name="mb_free_locked_count" value="<?php echo esc_attr( get_option( 'mb_free_locked_count', '3' ) ); ?>" min="0"></td></tr>
              <tr><th><?php esc_html_e( 'Durée premium (jours, 0=illimité)', MB_TEXT_DOMAIN ); ?></th>
                <td><input type="number" name="mb_premium_duration" value="<?php echo esc_attr( get_option( 'mb_premium_duration', '365' ) ); ?>" min="0"></td></tr>
              <tr><th><?php esc_html_e( 'Email contact', MB_TEXT_DOMAIN ); ?></th>
                <td><input type="email" name="mb_email_contact" value="<?php echo esc_attr( get_option( 'mb_email_contact' ) ); ?>" class="regular-text"></td></tr>
              <tr><th><?php esc_html_e( 'URL page paiement', MB_TEXT_DOMAIN ); ?></th>
                <td><input type="url" name="mb_payment_page_url" value="<?php echo esc_attr( get_option( 'mb_payment_page_url' ) ); ?>" class="regular-text"
                           placeholder="https://mathboost.net/abonnement/">
                  <p class="description"><?php esc_html_e( 'URL vers la page PayPal quand un QCM est verrouillé.', MB_TEXT_DOMAIN ); ?></p></td></tr>
            </table>
            <?php submit_button(); ?>
          </form>
        </div>
        <?php
    }

    // ── Legacy WP CPT support (pre-migration) ─────────────────────────────────
    public static function disable_gutenberg( bool $use, string $post_type ): bool {
        return $post_type === 'mb_qcm' ? false : $use;
    }

    public static function add_meta_boxes() {
        add_meta_box(
            'mb-qcm-questions',
            __( 'Questions & Contenu du QCM', MB_TEXT_DOMAIN ),
            [ __CLASS__, 'render_qcm_meta_box' ],
            'mb_qcm', 'normal', 'high'
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
              <th style="width:130px;padding-top:10px"><label for="mb_subtitle"><?php esc_html_e( 'Sous-titre', MB_TEXT_DOMAIN ); ?></label></th>
              <td><input type="text" id="mb_subtitle" name="_mb_subtitle" class="widefat" value="<?php echo esc_attr( $subtitle ); ?>"></td>
            </tr>
            <tr>
              <th style="padding-top:10px"><label for="mb_intro"><?php esc_html_e( 'Introduction', MB_TEXT_DOMAIN ); ?></label></th>
              <td><textarea id="mb_intro" name="_mb_intro" class="widefat" rows="3"><?php echo esc_textarea( $intro ); ?></textarea></td>
            </tr>
            <tr>
              <th style="padding-top:10px"><?php esc_html_e( 'Accès', MB_TEXT_DOMAIN ); ?></th>
              <td><label>
                <input type="checkbox" name="_mb_locked" value="1" <?php checked( $locked, '1' ); ?>>
                🔒 <?php esc_html_e( 'Verrouiller ce QCM (réservé aux abonnés premium)', MB_TEXT_DOMAIN ); ?>
              </label></td>
            </tr>
          </table>
          <hr style="margin:16px 0 0">
          <div class="mb-import-section">
            <div class="mb-import-header">
              <span class="mb-import-icon">📋</span>
              <strong><?php esc_html_e( 'Importer depuis vos fichiers HTML', MB_TEXT_DOMAIN ); ?></strong>
              <button type="button" id="mb-toggle-import" class="button button-small mb-import-toggle-btn">▲ <?php esc_html_e( 'Réduire', MB_TEXT_DOMAIN ); ?></button>
            </div>
            <div id="mb-import-panel">
              <textarea id="mb-import-code" rows="10" class="widefat mb-import-textarea"></textarea>
              <div class="mb-import-actions">
                <button type="button" id="mb-do-import" class="button button-primary">📥 <?php esc_html_e( 'Charger les questions', MB_TEXT_DOMAIN ); ?></button>
                <button type="button" id="mb-clear-import" class="button">🗑 <?php esc_html_e( 'Vider', MB_TEXT_DOMAIN ); ?></button>
                <button type="button" id="mb-insert-template" class="button">📄 <?php esc_html_e( 'Voir un exemple', MB_TEXT_DOMAIN ); ?></button>
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
              <button type="button" id="mb-add-q" class="button mb-btn-add-q">+ <?php esc_html_e( 'Ajouter une question', MB_TEXT_DOMAIN ); ?></button>
              <button type="button" id="mb-save-questions" class="button button-primary mb-btn-save-q">💾 <?php esc_html_e( 'Sauvegarder les questions', MB_TEXT_DOMAIN ); ?></button>
            </div>
          </div>
          <div id="mb-questions-list"></div>
          <div class="mb-qbuilder-footer">
            <button type="button" id="mb-add-q-bottom" class="button mb-btn-add-q">+ <?php esc_html_e( 'Ajouter une question', MB_TEXT_DOMAIN ); ?></button>
            <button type="button" id="mb-save-questions-bottom" class="button button-primary mb-btn-save-q">💾 <?php esc_html_e( 'Sauvegarder les questions', MB_TEXT_DOMAIN ); ?></button>
          </div>
          <input type="hidden" id="mb-questions-json" name="_mb_questions" value="<?php echo esc_attr( $questions ); ?>">
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

        if ( isset( $_POST['_mb_subtitle'] ) ) {
            update_post_meta( $post_id, '_mb_subtitle', sanitize_text_field( wp_unslash( $_POST['_mb_subtitle'] ) ) );
        }

        if ( isset( $_POST['_mb_intro'] ) ) {
            update_post_meta( $post_id, '_mb_intro', wp_kses_post( wp_unslash( $_POST['_mb_intro'] ) ) );
        }

        if ( isset( $_POST['_mb_questions'] ) ) {
            $raw       = wp_unslash( $_POST['_mb_questions'] );
            $questions = json_decode( $raw, true );
            if ( is_array( $questions ) ) {
                $clean = [];
                foreach ( $questions as $q ) {
                    if ( ! is_array( $q ) ) continue;
                    $ans_raw = isset( $q['ans'] ) && is_array( $q['ans'] ) ? $q['ans'] : [];
                    $clean[] = [
                        'text'    => wp_kses_post( (string) ( $q['text']  ?? '' ) ),
                        'layout'  => in_array( $q['layout'] ?? '', [ 'grid', 'stack' ], true ) ? $q['layout'] : 'grid',
                        'ans'     => [
                            'a' => wp_kses_post( (string) ( $ans_raw['a'] ?? '' ) ),
                            'b' => wp_kses_post( (string) ( $ans_raw['b'] ?? '' ) ),
                            'c' => wp_kses_post( (string) ( $ans_raw['c'] ?? '' ) ),
                            'd' => wp_kses_post( (string) ( $ans_raw['d'] ?? '' ) ),
                        ],
                        'correct' => in_array( $q['correct'] ?? '', [ 'a', 'b', 'c', 'd' ], true ) ? $q['correct'] : 'a',
                        'corr'    => wp_kses_post( (string) ( $q['corr']  ?? '' ) ),
                    ];
                }
                update_post_meta( $post_id, '_mb_questions', wp_json_encode( $clean ) );
            }
        }

        $locked = isset( $_POST['_mb_locked'] ) && $_POST['_mb_locked'] === '1' ? '1' : '0';
        update_post_meta( $post_id, '_mb_locked', $locked );
    }

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
        $locked    = get_post_meta( $post_id, '_mb_locked', true );
        $is_locked = $locked === '1';
        $label = $is_locked
            ? '<span class="mb-badge mb-badge-locked">🔒 ' . esc_html__( 'Premium', MB_TEXT_DOMAIN ) . '</span>'
            : '<span class="mb-badge mb-badge-free">🔓 ' . esc_html__( 'Libre', MB_TEXT_DOMAIN ) . '</span>';

        printf(
            '<button class="mb-toggle-lock-btn" data-id="%d" data-locked="%s">%s</button>',
            $post_id,
            $is_locked ? '1' : '0',
            $label
        );
    }
}
