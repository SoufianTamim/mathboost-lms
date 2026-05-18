<?php
defined( 'ABSPATH' ) || exit;

class MB_Ajax {

    public static function init() {
        add_action( 'wp_ajax_mb_get_questions',        [ __CLASS__, 'handle_get_questions' ] );
        add_action( 'wp_ajax_mb_activate_code',        [ __CLASS__, 'handle_activate_code' ] );
        add_action( 'wp_ajax_mb_report_error',         [ __CLASS__, 'handle_report_error' ] );
        add_action( 'wp_ajax_nopriv_mb_report_error',  [ __CLASS__, 'handle_report_error' ] );
        add_action( 'wp_ajax_mb_paypal_confirm',       [ __CLASS__, 'handle_paypal_confirm' ] );
        add_action( 'wp_ajax_mb_admin_generate_codes', [ __CLASS__, 'handle_generate_codes' ] );
        add_action( 'wp_ajax_mb_admin_delete_code',    [ __CLASS__, 'handle_delete_code' ] );
        add_action( 'wp_ajax_mb_admin_revoke_premium', [ __CLASS__, 'handle_revoke_premium' ] );
        add_action( 'wp_ajax_mb_save_questions',       [ __CLASS__, 'handle_save_questions' ] );
    }

    // ── Fetch questions ───────────────────────────────────────────────────────
    public static function handle_get_questions() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'mb_admin_nonce' ) ) {
            wp_send_json_error( [ 'message' => 'Nonce invalide.' ] );
            return;
        }

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Accès refusé.' ] );
            return;
        }

        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;

        if ( ! $post_id ) {
            wp_send_json_success( [ 'questions' => [], 'count' => 0 ] );
            return;
        }

        if ( MB_Migrator::is_done() ) {
            $qcm = MB_QCM_Repository::get_by_id( $post_id );
            if ( ! $qcm ) {
                wp_send_json_error( [ 'message' => 'QCM introuvable.' ] );
                return;
            }
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( [ 'message' => 'Accès refusé.' ] );
                return;
            }
            $questions = json_decode( $qcm->questions ?: '[]', true ) ?? [];
        } else {
            if ( get_post_type( $post_id ) !== 'mb_qcm' ) {
                wp_send_json_error( [ 'message' => 'Post introuvable.' ] );
                return;
            }
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                wp_send_json_error( [ 'message' => 'Accès refusé.' ] );
                return;
            }
            $raw       = get_post_meta( $post_id, '_mb_questions', true ) ?: '[]';
            $questions = json_decode( $raw, true ) ?? [];
        }

        wp_send_json_success( [ 'questions' => $questions, 'count' => count( $questions ) ] );
    }

    // ── Save questions ────────────────────────────────────────────────────────
    public static function handle_save_questions() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'mb_admin_nonce' ) && ! wp_verify_nonce( $nonce, 'mb_save_qcm_meta' ) ) {
            wp_send_json_error( [ 'message' => 'Nonce invalide — rechargez la page.' ] );
        }

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Accès refusé.' ] );
        }

        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;

        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => 'ID manquant.' ] );
        }

        $raw       = isset( $_POST['questions'] ) ? wp_unslash( $_POST['questions'] ) : '[]';
        $questions = json_decode( $raw, true );

        if ( ! is_array( $questions ) ) {
            wp_send_json_error( [ 'message' => 'Format JSON invalide.' ] );
        }

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

        if ( MB_Migrator::is_done() ) {
            $qcm = MB_QCM_Repository::get_by_id( $post_id );
            if ( ! $qcm ) {
                wp_send_json_error( [ 'message' => 'QCM introuvable.' ] );
            }
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( [ 'message' => 'Accès refusé.' ] );
            }
            MB_QCM_Repository::save( [ 'id' => $post_id, 'questions' => wp_json_encode( $clean ) ] );
        } else {
            if ( get_post_type( $post_id ) !== 'mb_qcm' ) {
                wp_send_json_error( [ 'message' => 'Post QCM introuvable.' ] );
            }
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                wp_send_json_error( [ 'message' => 'Accès refusé.' ] );
            }
            update_post_meta( $post_id, '_mb_questions', wp_json_encode( $clean ) );
        }

        wp_send_json_success( [
            'message' => sprintf( __( '%d question(s) sauvegardée(s).', MB_TEXT_DOMAIN ), count( $clean ) ),
            'count'   => count( $clean ),
        ] );
    }

    // ── Activate code ─────────────────────────────────────────────────────────
    public static function handle_activate_code() {
        check_ajax_referer( 'mb_activate_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Vous devez être connecté.', MB_TEXT_DOMAIN ) ] );
        }

        $code = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
        if ( ! $code ) {
            wp_send_json_error( [ 'message' => __( 'Veuillez saisir un code.', MB_TEXT_DOMAIN ) ] );
        }

        $result = MB_Activation_Codes::activate( $code, get_current_user_id() );

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    // ── Report error ──────────────────────────────────────────────────────────
    public static function handle_report_error() {
        check_ajax_referer( 'mb_report_nonce', 'nonce' );

        $qcm_id       = isset( $_POST['qcm_id'] )       ? (int) $_POST['qcm_id']                                      : 0;
        $question_num = isset( $_POST['question_num'] )  ? (int) $_POST['question_num']                               : 0;
        $message      = isset( $_POST['message'] )       ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

        if ( ! $qcm_id || ! $message ) {
            wp_send_json_error( [ 'message' => __( 'Données manquantes.', MB_TEXT_DOMAIN ) ] );
        }

        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'mb_error_reports',
            [
                'qcm_id'       => $qcm_id,
                'question_num' => $question_num,
                'user_id'      => is_user_logged_in() ? get_current_user_id() : null,
                'message'      => $message,
                'created_at'   => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%d', '%s', '%s' ]
        );

        wp_send_json_success( [ 'message' => __( 'Signalement envoyé, merci !', MB_TEXT_DOMAIN ) ] );
    }

    // ── PayPal confirm ────────────────────────────────────────────────────────
    public static function handle_paypal_confirm() {
        check_ajax_referer( 'mb_paypal_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Non connecté.', MB_TEXT_DOMAIN ) ] );
        }

        $order_id = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : '';
        if ( ! $order_id ) {
            wp_send_json_error( [ 'message' => __( 'Commande invalide.', MB_TEXT_DOMAIN ) ] );
        }

        $verified = self::verify_paypal_order( $order_id );
        if ( ! $verified ) {
            wp_send_json_error( [ 'message' => __( 'Paiement non vérifié.', MB_TEXT_DOMAIN ) ] );
        }

        $user_id = get_current_user_id();
        $days    = (int) get_option( 'mb_premium_duration', 365 );
        MB_Activation_Codes::grant_premium( $user_id, $days );

        update_user_meta( $user_id, 'mb_last_paypal_order', $order_id );
        update_user_meta( $user_id, 'mb_payment_date',      current_time( 'mysql' ) );

        wp_send_json_success( [ 'message' => __( 'Paiement confirmé ! Accès premium activé.', MB_TEXT_DOMAIN ) ] );
    }

    // ── Admin: generate codes ─────────────────────────────────────────────────
    public static function handle_generate_codes() {
        check_ajax_referer( 'mb_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Accès refusé.' ] );
        }

        $count      = isset( $_POST['count'] )      ? min( 100, max( 1, (int) $_POST['count'] ) ) : 1;
        $expires_at = isset( $_POST['expires_at'] ) && $_POST['expires_at']
            ? sanitize_text_field( wp_unslash( $_POST['expires_at'] ) )
            : null;
        $notes = isset( $_POST['notes'] ) ? sanitize_text_field( wp_unslash( $_POST['notes'] ) ) : '';

        $codes = MB_Activation_Codes::generate( $count, $expires_at, $notes );
        wp_send_json_success( [ 'codes' => $codes ] );
    }

    // ── Admin: delete code ─────────────────────────────────────────────────────
    public static function handle_delete_code() {
        check_ajax_referer( 'mb_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }

        $id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
        MB_Activation_Codes::delete( $id );
        wp_send_json_success();
    }

    // ── Admin: revoke premium ─────────────────────────────────────────────────
    public static function handle_revoke_premium() {
        check_ajax_referer( 'mb_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }

        $user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
        if ( $user_id ) {
            update_user_meta( $user_id, 'mb_premium', 0 );
            delete_user_meta( $user_id, 'mb_premium_expires' );
        }

        wp_send_json_success();
    }

    // ── PayPal server-side verification ───────────────────────────────────────
    private static function verify_paypal_order( string $order_id ): bool {
        $client_id     = get_option( 'mb_paypal_client_id', '' );
        $client_secret = get_option( 'mb_paypal_secret', '' );

        if ( ! $client_id || ! $client_secret ) {
            return false;
        }

        $token_response = wp_remote_post( 'https://api-m.paypal.com/v1/oauth2/token', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( "$client_id:$client_secret" ),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body' => 'grant_type=client_credentials',
        ] );

        if ( is_wp_error( $token_response ) ) {
            return false;
        }

        $token_data   = json_decode( wp_remote_retrieve_body( $token_response ), true );
        $access_token = $token_data['access_token'] ?? '';

        if ( ! $access_token ) {
            return false;
        }

        $order_response = wp_remote_get( "https://api-m.paypal.com/v2/checkout/orders/{$order_id}", [
            'headers' => [
                'Authorization' => "Bearer $access_token",
                'Content-Type'  => 'application/json',
            ],
        ] );

        if ( is_wp_error( $order_response ) ) {
            return false;
        }

        $order_data = json_decode( wp_remote_retrieve_body( $order_response ), true );
        $status     = $order_data['status'] ?? '';
        $amount     = $order_data['purchase_units'][0]['amount']['value'] ?? 0;
        $expected   = (float) get_option( 'mb_price', '15' );

        return $status === 'COMPLETED' && (float) $amount >= $expected;
    }
}
