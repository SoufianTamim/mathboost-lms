<?php
defined( 'ABSPATH' ) || exit;

class MB_Frontend {

    public static function init() {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
        add_filter( 'mb_upgrade_url',     [ __CLASS__, 'get_upgrade_url' ] );

        // CPT-specific hooks are only relevant before migration
        if ( ! MB_Migrator::is_done() ) {
            add_filter( 'the_content',      [ __CLASS__, 'maybe_inject_qcm' ] );
            add_action( 'template_include', [ __CLASS__, 'maybe_use_qcm_template' ] );
        }
    }

    public static function get_upgrade_url( string $url ): string {
        $saved = get_option( 'mb_payment_page_url', '' );
        return $saved ?: $url;
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
