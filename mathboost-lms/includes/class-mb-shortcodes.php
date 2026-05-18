<?php
defined( 'ABSPATH' ) || exit;

class MB_Shortcodes {

    public static function init() {
        add_shortcode( 'mathboost_resources',       [ __CLASS__, 'resources' ] );
        add_shortcode( 'mathboost_levels',          [ __CLASS__, 'levels' ] );
        add_shortcode( 'mathboost_course_selector', [ __CLASS__, 'course_selector' ] );
        add_shortcode( 'mathboost_qcm_list',        [ __CLASS__, 'qcm_list' ] );
        add_shortcode( 'mathboost_qcm',             [ __CLASS__, 'qcm_single' ] );
        add_shortcode( 'mathboost_activation_form', [ __CLASS__, 'activation_form' ] );
        add_shortcode( 'mathboost_payment',         [ __CLASS__, 'payment_block' ] );
        add_shortcode( 'mathboost_login',           [ __CLASS__, 'login_block' ] );
    }

    public static function resources( array $atts = [] ): string {
        $atts = shortcode_atts( [], $atts );
        ob_start();
        include MB_PLUGIN_DIR . 'frontend/templates/resources.php';
        return ob_get_clean();
    }

    public static function levels( array $atts = [] ): string {
        return self::resources( $atts );
    }

    public static function course_selector( array $atts = [] ): string {
        $atts = shortcode_atts( [ 'level' => '' ], $atts );
        ob_start();
        include MB_PLUGIN_DIR . 'frontend/templates/course-selector.php';
        return ob_get_clean();
    }

    public static function qcm_list( array $atts = [] ): string {
        $atts = shortcode_atts( [ 'category' => '', 'level' => '', 'course' => '' ], $atts );
        ob_start();
        include MB_PLUGIN_DIR . 'frontend/templates/qcm-list.php';
        return ob_get_clean();
    }

    public static function qcm_single( array $atts = [] ): string {
        $atts   = shortcode_atts( [ 'id' => 0 ], $atts );
        $qcm_id = (int) $atts['id'];

        if ( ! $qcm_id ) {
            // Fallback for WP singular CPT pages (pre-migration)
            $qcm_id = (int) get_the_ID();
        }

        if ( ! $qcm_id ) {
            return '<p>' . esc_html__( 'QCM introuvable.', MB_TEXT_DOMAIN ) . '</p>';
        }

        // Verify QCM existence (repository after migration, WP post type before)
        if ( MB_Migrator::is_done() ) {
            if ( ! MB_QCM_Repository::get_by_id( $qcm_id ) ) {
                return '<p>' . esc_html__( 'QCM introuvable.', MB_TEXT_DOMAIN ) . '</p>';
            }
        } else {
            if ( get_post_type( $qcm_id ) !== 'mb_qcm' ) {
                return '<p>' . esc_html__( 'QCM introuvable.', MB_TEXT_DOMAIN ) . '</p>';
            }
        }

        if ( ! MB_Access::can_access( $qcm_id ) ) {
            ob_start();
            include MB_PLUGIN_DIR . 'frontend/templates/locked.php';
            return ob_get_clean();
        }

        ob_start();
        include MB_PLUGIN_DIR . 'frontend/templates/qcm-single.php';
        return ob_get_clean();
    }

    public static function activation_form( array $atts = [] ): string {
        ob_start();
        include MB_PLUGIN_DIR . 'frontend/templates/activation-form.php';
        return ob_get_clean();
    }

    public static function payment_block( array $atts = [] ): string {
        ob_start();
        include MB_PLUGIN_DIR . 'frontend/templates/payment.php';
        return ob_get_clean();
    }

    public static function login_block( array $atts = [] ): string {
        ob_start();
        include MB_PLUGIN_DIR . 'frontend/templates/login.php';
        return ob_get_clean();
    }
}
