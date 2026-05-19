<?php
defined( 'ABSPATH' ) || exit;

class MB_Frontend {

    public static function init() {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
        add_filter( 'mb_upgrade_url',     [ __CLASS__, 'get_upgrade_url' ] );
        add_filter( 'login_url',          [ __CLASS__, 'custom_login_url' ], 10, 3 );
        add_filter( 'body_class',         [ __CLASS__, 'add_plugin_page_class' ] );

        // Block non-admins from wp-admin and remove their admin bar
        add_filter( 'show_admin_bar', [ __CLASS__, 'hide_admin_bar_for_users' ] );
        add_action( 'admin_init',     [ __CLASS__, 'block_non_admin_from_dashboard' ] );

        // CPT-specific hooks are only relevant before migration
        if ( ! MB_Migrator::is_done() ) {
            add_filter( 'the_content',      [ __CLASS__, 'maybe_inject_qcm' ] );
            add_action( 'template_include', [ __CLASS__, 'maybe_use_qcm_template' ] );
        }
    }

    // Hide the admin bar for everyone except admins
    public static function hide_admin_bar_for_users( bool $show ): bool {
        if ( current_user_can( 'manage_options' ) ) {
            return $show;
        }
        return false;
    }

    // Redirect non-admins who try to access any wp-admin page
    public static function block_non_admin_from_dashboard(): void {
        if ( current_user_can( 'manage_options' ) ) {
            return;
        }
        // Allow admin-ajax.php — AJAX handlers for logged-in users
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return;
        }
        // Allow admin-post.php — login, register, and all form POST handlers use it
        global $pagenow;
        if ( $pagenow === 'admin-post.php' ) {
            return;
        }
        $redirect = get_option( 'mb_login_page_url', '' ) ?: home_url( '/' );
        wp_safe_redirect( $redirect );
        exit;
    }

    // Detect if current page uses any plugin shortcode
    private static function is_plugin_page(): bool {
        if ( ! is_page() ) {
            return false;
        }
        global $post;
        if ( ! $post ) {
            return false;
        }
        $shortcodes = [
            'mathboost_resources', 'mathboost_levels', 'mathboost_payment',
            'mathboost_login', 'mathboost_register', 'mathboost_activation_form',
            'mathboost_course_selector', 'mathboost_qcm_list', 'mathboost_qcm',
        ];
        foreach ( $shortcodes as $sc ) {
            if ( has_shortcode( $post->post_content, $sc ) ) {
                return true;
            }
        }
        return false;
    }

    public static function add_plugin_page_class( array $classes ): array {
        if ( self::is_plugin_page() ) {
            $classes[] = 'mb-plugin-page';
        }
        return $classes;
    }

    // Find the URL of the page containing [mathboost_resources] or [mathboost_levels]
    private static function get_resources_url(): string {
        $saved = get_option( 'mb_resources_page_url', '' );
        if ( $saved ) {
            return $saved;
        }
        global $wpdb;
        $page_id = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type = 'page'
               AND (post_content LIKE '%mathboost_resources%' OR post_content LIKE '%mathboost_levels%')
             LIMIT 1"
        );
        return $page_id ? (string) get_permalink( (int) $page_id ) : home_url( '/ressources/' );
    }

    public static function get_upgrade_url( string $url ): string {
        $saved = get_option( 'mb_payment_page_url', '' );
        return $saved ?: $url;
    }

    public static function custom_login_url( string $login_url, string $redirect, bool $force_reauth ): string {
        if ( $force_reauth ) {
            return $login_url;
        }
        $custom = get_option( 'mb_login_page_url', '' );
        if ( ! $custom ) {
            return $login_url;
        }
        if ( $redirect ) {
            $custom = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $custom );
        }
        return $custom;
    }

    public static function enqueue() {
        wp_enqueue_style(
            'mb-frontend',
            MB_PLUGIN_URL . 'assets/css/mb-frontend.css',
            [],
            MB_VERSION
        );

        wp_enqueue_style(
            'nunito-font',
            'https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap',
            [],
            null
        );

        wp_enqueue_script( 'mb-nav', MB_PLUGIN_URL . 'assets/js/mb-nav.js', [], MB_VERSION, true );
        wp_enqueue_script( 'mb-qcm', MB_PLUGIN_URL . 'assets/js/mb-qcm.js', [], MB_VERSION, true );

        // MathJax in <head>
        wp_enqueue_script( 'mathjax', 'https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js', [], null, false );
        wp_add_inline_script( 'mathjax',
            'window.MathJax = {
                tex: { inlineMath: [["\\\\(","\\\\)"]], displayMath: [["\\\\[","\\\\]"]] },
                options: { skipHtmlTags: ["script","noscript","style","textarea","pre"] }
            };',
            'before'
        );

        $price  = get_option( 'mb_price', '15' );
        $client = get_option( 'mb_paypal_client_id', '' );

        wp_localize_script( 'mb-qcm', 'mbConfig', [
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'mb_report_nonce' ),
            'activateNonce' => wp_create_nonce( 'mb_activate_nonce' ),
            'paypalNonce'   => wp_create_nonce( 'mb_paypal_nonce' ),
            'paypalClient'  => esc_js( $client ),
            'price'         => esc_js( $price ),
            'currency'      => esc_js( get_option( 'mb_currency', 'EUR' ) ),
            'resourcesUrl'  => esc_js( self::get_resources_url() ),
            'i18n'          => [
                'score'           => __( 'Score', MB_TEXT_DOMAIN ),
                'correction'      => __( 'Voir la correction', MB_TEXT_DOMAIN ),
                'hide'            => __( 'Masquer la correction', MB_TEXT_DOMAIN ),
                'quizDone'        => __( 'Quiz terminé !', MB_TEXT_DOMAIN ),
                'activateSuccess' => __( 'Accès premium activé !', MB_TEXT_DOMAIN ),
                'activateError'   => __( 'Code invalide ou expiré.', MB_TEXT_DOMAIN ),
            ],
        ] );

        if ( $client ) {
            wp_enqueue_script(
                'paypal-sdk',
                "https://www.paypal.com/sdk/js?client-id={$client}&currency=" . get_option( 'mb_currency', 'EUR' ),
                [],
                null,
                true
            );
        }
    }

    // Pre-migration: auto-inject QCM on singular mb_qcm posts
    public static function maybe_inject_qcm( string $content ): string {
        if ( ! is_singular( 'mb_qcm' ) || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }
        return $content . do_shortcode( '[mathboost_qcm id="' . get_the_ID() . '"]' );
    }

    public static function maybe_use_qcm_template( string $template ): string {
        if ( is_singular( 'mb_qcm' ) ) {
            $custom = MB_PLUGIN_DIR . 'frontend/templates/single-qcm.php';
            if ( file_exists( $custom ) ) {
                return $custom;
            }
        }
        return $template;
    }
}
