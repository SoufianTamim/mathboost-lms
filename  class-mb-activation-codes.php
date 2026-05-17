<?php
defined( 'ABSPATH' ) || exit;

class MB_Activation_Codes {

    // ── Generate a batch of codes ─────────────────────────────────────────────
    public static function generate( int $count = 1, ?string $expires_at = null, string $notes = '' ): array {
        global $wpdb;
        $table    = $wpdb->prefix . 'mb_activation_codes';
        $codes    = [];

        for ( $i = 0; $i < $count; $i++ ) {
            $code = self::make_code();

            $wpdb->insert(
                $table,
                [
                    'code'       => $code,
                    'expires_at' => $expires_at,
                    'notes'      => sanitize_text_field( $notes ),
                    'created_at' => current_time( 'mysql' ),
                ],
                [ '%s', '%s', '%s', '%s' ]
            );

            if ( $wpdb->insert_id ) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    // ── Validate & activate ───────────────────────────────────────────────────
    public static function activate( string $raw_code, int $user_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'mb_activation_codes';
        $code  = strtoupper( sanitize_text_field( $raw_code ) );

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE code = %s LIMIT 1",
            $code
        ) );

        if ( ! $row ) {
            return [ 'success' => false, 'message' => __( 'Code invalide.', MB_TEXT_DOMAIN ) ];
        }

        if ( ! is_null( $row->used_at ) ) {
            return [ 'success' => false, 'message' => __( 'Ce code a déjà été utilisé.', MB_TEXT_DOMAIN ) ];
        }

        if ( $row->expires_at && strtotime( $row->expires_at ) < time() ) {
            return [ 'success' => false, 'message' => __( 'Ce code a expiré.', MB_TEXT_DOMAIN ) ];
        }

        // Mark as used
        $wpdb->update(
            $table,
            [
                'user_id' => $user_id,
                'used_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $row->id ],
            [ '%d', '%s' ],
            [ '%d' ]
        );

        // Grant premium
        $days = (int) get_option( 'mb_premium_duration', 365 );
        self::grant_premium( $user_id, $days );

        return [ 'success' => true, 'message' => __( 'Félicitations ! Votre accès premium est activé.', MB_TEXT_DOMAIN ) ];
    }

    // ── Grant premium to user ─────────────────────────────────────────────────
    public static function grant_premium( int $user_id, int $days = 365 ) {
        $expires = $days > 0
            ? gmdate( 'Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS )
            : '2099-01-01 00:00:00';

        update_user_meta( $user_id, 'mb_premium',         1 );
        update_user_meta( $user_id, 'mb_premium_expires', $expires );
    }

    // ── Check premium ─────────────────────────────────────────────────────────
    public static function is_premium( int $user_id ): bool {
        if ( user_can( $user_id, 'manage_options' ) ) {
            return true;
        }

        $is_premium = (int) get_user_meta( $user_id, 'mb_premium', true );
        if ( ! $is_premium ) {
            return false;
        }

        $expires = get_user_meta( $user_id, 'mb_premium_expires', true );
        if ( $expires && strtotime( $expires ) < time() ) {
            update_user_meta( $user_id, 'mb_premium', 0 );
            return false;
        }

        return true;
    }

    // ── Get all codes (for admin) ─────────────────────────────────────────────
    public static function get_all( int $per_page = 20, int $paged = 1 ): array {
        global $wpdb;
        $table  = $wpdb->prefix . 'mb_activation_codes';
        $offset = ( $paged - 1 ) * $per_page;

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT c.*, u.user_login, u.user_email
             FROM {$table} c
             LEFT JOIN {$wpdb->users} u ON u.ID = c.user_id
             ORDER BY c.created_at DESC
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ) );

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

        return [ 'rows' => $rows, 'total' => $total ];
    }

    // ── Delete a code ─────────────────────────────────────────────────────────
    public static function delete( int $id ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'mb_activation_codes', [ 'id' => $id ], [ '%d' ] );
    }

    // ── Generate formatted code: XXXX-XXXX-XXXX ──────────────────────────────
    private static function make_code(): string {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code  = '';
        for ( $i = 0; $i < 12; $i++ ) {
            if ( $i > 0 && $i % 4 === 0 ) {
                $code .= '-';
            }
            $code .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
        }
        return $code; // e.g. ABCD-EFGH-1234
    }
}