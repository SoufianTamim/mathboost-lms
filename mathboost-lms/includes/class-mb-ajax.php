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
        add_action( 'wp_ajax_mb_save_progress',        [ __CLASS__, 'handle_save_progress' ] );
        add_action( 'wp_ajax_nopriv_mb_save_progress', [ __CLASS__, 'handle_save_progress' ] );
        add_action( 'admin_post_nopriv_mb_do_login',   [ __CLASS__, 'handle_do_login' ] );
        add_action( 'admin_post_mb_do_login',          [ __CLASS__, 'handle_do_login' ] );
        add_action( 'admin_post_nopriv_mb_do_register',[ __CLASS__, 'handle_do_register' ] );
        add_action( 'admin_post_mb_do_register',       [ __CLASS__, 'handle_do_register' ] );
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
            return;
        }

        $order_id = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : '';
        if ( ! $order_id ) {
            wp_send_json_error( [ 'message' => __( 'Commande invalide.', MB_TEXT_DOMAIN ) ] );
            return;
        }

        // Duplicate order guard — prevent the same PayPal order from being processed twice
        global $wpdb;
        $already_processed = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}mb_paypal_purchases WHERE paypal_order_id = %s LIMIT 1",
            $order_id
        ) );
        if ( $already_processed ) {
            wp_send_json_error( [ 'message' => __( 'Cette commande a déjà été traitée.', MB_TEXT_DOMAIN ) ] );
            return;
        }

        // Verify with PayPal REST API: must be COMPLETED, EUR, >= €15
        $verified = self::verify_paypal_order( $order_id );
        if ( ! $verified ) {
            wp_send_json_error( [ 'message' => __( 'Paiement non vérifié. Veuillez contacter le support.', MB_TEXT_DOMAIN ) ] );
            return;
        }

        $user_id = get_current_user_id();
        $user    = get_userdata( $user_id );

        // Grant premium immediately
        $days = (int) get_option( 'mb_premium_duration', 365 );
        MB_Activation_Codes::grant_premium( $user_id, $days );

        // Generate a unique activation code and pre-assign it to this purchase
        $codes           = MB_Activation_Codes::generate( 1, null, 'PayPal: ' . $order_id );
        $activation_code = $codes[0] ?? '';

        if ( $activation_code ) {
            // Mark code as used so it cannot be activated again manually
            $wpdb->update(
                $wpdb->prefix . 'mb_activation_codes',
                [
                    'user_id' => $user_id,
                    'used_at' => current_time( 'mysql' ),
                ],
                [ 'code' => $activation_code ],
                [ '%d', '%s' ],
                [ '%s' ]
            );
            update_user_meta( $user_id, 'mb_activation_code', $activation_code );
        }

        update_user_meta( $user_id, 'mb_last_paypal_order', $order_id );
        update_user_meta( $user_id, 'mb_payment_date',      current_time( 'mysql' ) );

        // Send activation code by email; log delivery status regardless
        $email_sent = 0;
        if ( $activation_code && $user && $user->user_email ) {
            $email_sent = self::send_activation_code_email(
                $user->user_email,
                $user->display_name ?: $user->user_login,
                $activation_code
            ) ? 1 : 0;
        }

        // Log purchase with code and email delivery status
        $wpdb->insert(
            $wpdb->prefix . 'mb_paypal_purchases',
            [
                'paypal_order_id' => $order_id,
                'user_id'         => $user_id,
                'amount'          => '15.00',
                'currency'        => 'EUR',
                'activation_code' => $activation_code,
                'email_sent'      => $email_sent,
            ],
            [ '%s', '%d', '%s', '%s', '%s', '%d' ]
        );

        wp_send_json_success( [
            'message' => __( 'Paiement confirmé ! Votre accès premium est activé et votre code d\'activation a été envoyé par email.', MB_TEXT_DOMAIN ),
        ] );
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

    // ── Custom login ──────────────────────────────────────────────────────────
    public static function handle_do_login(): void {
        $nonce = isset( $_POST['mb_login_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['mb_login_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'mb_do_login' ) ) {
            wp_die( esc_html__( 'Erreur de sécurité — rechargez la page.', MB_TEXT_DOMAIN ) );
        }

        $login_page  = get_option( 'mb_login_page_url', '' ) ?: wp_login_url();
        $redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';

        $credentials = [
            'user_login'    => isset( $_POST['log'] ) ? sanitize_user( wp_unslash( $_POST['log'] ) ) : '',
            'user_password' => isset( $_POST['pwd'] ) ? wp_unslash( $_POST['pwd'] ) : '',
            'remember'      => isset( $_POST['rememberme'] ),
        ];

        if ( ! $credentials['user_login'] || ! $credentials['user_password'] ) {
            wp_safe_redirect( add_query_arg( 'mb_login_error', rawurlencode( 'Identifiant et mot de passe requis.' ), $login_page ) );
            exit;
        }

        $user = wp_signon( $credentials, is_ssl() );

        if ( is_wp_error( $user ) ) {
            wp_safe_redirect( add_query_arg( 'mb_login_error', rawurlencode( 'Identifiant ou mot de passe incorrect.' ), $login_page ) );
            exit;
        }

        if ( $redirect_to ) {
            $safe = wp_validate_redirect( $redirect_to, $login_page );
            wp_safe_redirect( $safe );
        } elseif ( $user->has_cap( 'manage_options' ) ) {
            wp_safe_redirect( admin_url() );
        } else {
            wp_safe_redirect( $login_page );
        }
        exit;
    }

    // ── Custom register ───────────────────────────────────────────────────────
    public static function handle_do_register(): void {
        $nonce = isset( $_POST['mb_register_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['mb_register_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'mb_do_register' ) ) {
            wp_die( esc_html__( 'Erreur de sécurité — rechargez la page.', MB_TEXT_DOMAIN ) );
        }

        $login_page    = get_option( 'mb_login_page_url',    '' ) ?: wp_login_url();
        $register_page = get_option( 'mb_register_page_url', '' ) ?: home_url( '/inscription/' );
        $redirect_to   = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';

        if ( ! get_option( 'mb_allow_register', 1 ) ) {
            wp_safe_redirect( add_query_arg( 'mb_login_error', rawurlencode( 'Les inscriptions sont fermées.' ), $register_page ) );
            exit;
        }

        // Temporarily enable WP registration so wp_create_user() doesn't fail
        $wp_reg_was = get_option( 'users_can_register' );
        if ( ! $wp_reg_was ) {
            update_option( 'users_can_register', 1 );
        }

        $user_login      = isset( $_POST['user_login'] )      ? sanitize_user( wp_unslash( $_POST['user_login'] ) )          : '';
        $user_email      = isset( $_POST['user_email'] )      ? sanitize_email( wp_unslash( $_POST['user_email'] ) )         : '';
        $user_pass       = isset( $_POST['user_pass'] )       ? wp_unslash( $_POST['user_pass'] )                            : '';
        $activation_code = isset( $_POST['activation_code'] ) ? sanitize_text_field( wp_unslash( $_POST['activation_code'] ) ) : '';

        if ( ! $user_login || ! $user_email || ! $user_pass ) {
            wp_safe_redirect( add_query_arg( 'mb_login_error', rawurlencode( 'Tous les champs sont requis.' ), $register_page ) );
            exit;
        }

        if ( strlen( $user_pass ) < 8 ) {
            wp_safe_redirect( add_query_arg( 'mb_login_error', rawurlencode( 'Le mot de passe doit contenir au moins 8 caractères.' ), $register_page ) );
            exit;
        }

        $user_id = wp_create_user( $user_login, $user_pass, $user_email );

        // Restore WP registration setting if we changed it
        if ( ! $wp_reg_was ) {
            update_option( 'users_can_register', 0 );
        }

        if ( is_wp_error( $user_id ) ) {
            wp_safe_redirect( add_query_arg( 'mb_login_error', rawurlencode( $user_id->get_error_message() ), $register_page ) );
            exit;
        }

        // Force subscriber role — never let the WP default_role grant admin
        $new_user = new WP_User( $user_id );
        $new_user->set_role( 'subscriber' );

        // Auto-login
        wp_set_auth_cookie( $user_id, false, is_ssl() );

        // Try activation code if provided
        $code_msg = '';
        if ( $activation_code ) {
            $result = MB_Activation_Codes::activate( $activation_code, $user_id );
            $code_msg = $result['success']
                ? ' Votre code premium a été activé !'
                : ' (Code invalide — non appliqué.)';
        }

        if ( $redirect_to ) {
            $safe = wp_validate_redirect( $redirect_to, $login_page );
            wp_safe_redirect( $safe );
        } else {
            $success = rawurlencode( 'Compte créé avec succès ! Bienvenue !' . $code_msg );
            wp_safe_redirect( add_query_arg( 'mb_login_success', $success, $login_page ) );
        }
        exit;
    }

    // ── Save user progress ────────────────────────────────────────────────────
    public static function handle_save_progress(): void {
        check_ajax_referer( 'mb_report_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Non connecté.' ] );
            return;
        }

        $qcm_id = isset( $_POST['qcm_id'] ) ? (int) $_POST['qcm_id'] : 0;
        $score  = isset( $_POST['score'] )   ? (int) $_POST['score']   : 0;
        $total  = isset( $_POST['total'] )   ? (int) $_POST['total']   : 0;

        if ( ! $qcm_id || $total <= 0 ) {
            wp_send_json_error( [ 'message' => 'Données invalides.' ] );
            return;
        }

        global $wpdb;
        $wpdb->replace(
            $wpdb->prefix . 'mb_user_progress',
            [
                'user_id'      => get_current_user_id(),
                'qcm_id'       => $qcm_id,
                'score'        => min( $score, $total ),
                'total'        => $total,
                'completed_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%d', '%d', '%s' ]
        );

        wp_send_json_success( [ 'message' => 'Progression enregistrée.' ] );
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
            'body'    => 'grant_type=client_credentials',
            'timeout' => 30,
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
            'timeout' => 30,
        ] );

        if ( is_wp_error( $order_response ) ) {
            return false;
        }

        $order_data = json_decode( wp_remote_retrieve_body( $order_response ), true );
        $status     = $order_data['status']                                        ?? '';
        $amount     = (float) ( $order_data['purchase_units'][0]['amount']['value']         ?? 0 );
        $currency   = $order_data['purchase_units'][0]['amount']['currency_code']  ?? '';

        // Price is locked at €15 — never read from option to prevent price manipulation
        return $status === 'COMPLETED'
            && $currency === 'EUR'
            && $amount >= 15.00;
    }

    // ── Send activation code email ────────────────────────────────────────────
    public static function send_activation_code_email( string $to, string $name, string $code ): bool {
        $site_name   = get_bloginfo( 'name' );
        $admin_email = get_option( 'admin_email' );

        $subject = sprintf(
            /* translators: %s site name */
            __( '[%s] Votre code d\'activation Premium', MB_TEXT_DOMAIN ),
            $site_name
        );

        $message  = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px">' . "\n";
        $message .= '<h2 style="color:#2563eb;margin-bottom:8px">Bienvenue dans MathBoost Premium !</h2>' . "\n";
        $message .= '<p>Bonjour <strong>' . esc_html( $name ) . '</strong>,</p>' . "\n";
        $message .= '<p>Votre paiement de <strong>15,00 €</strong> a bien été confirmé.<br>';
        $message .= 'Votre accès premium est désormais <strong>actif</strong>.</p>' . "\n";
        $message .= '<p style="margin-top:24px">Voici votre code d\'activation personnel :</p>' . "\n";
        $message .= '<div style="background:#f0f4ff;border:2px solid #2563eb;border-radius:8px;';
        $message .= 'padding:24px;text-align:center;margin:16px 0">' . "\n";
        $message .= '<span style="font-size:30px;font-weight:bold;letter-spacing:6px;color:#1e40af">';
        $message .= esc_html( $code );
        $message .= '</span>' . "\n";
        $message .= '</div>' . "\n";
        $message .= '<p style="font-size:13px;color:#555">Conservez ce code précieusement — il est lié à votre compte ';
        $message .= 'et peut vous servir pour réactiver votre accès si nécessaire.</p>' . "\n";
        $message .= '<hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0">' . "\n";
        $message .= '<p style="margin:0">Bonne préparation !<br><strong>L\'équipe ' . esc_html( $site_name ) . '</strong></p>' . "\n";
        $message .= '</div>';

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . $admin_email . '>',
        ];

        return (bool) wp_mail( $to, $subject, $message, $headers );
    }
}
