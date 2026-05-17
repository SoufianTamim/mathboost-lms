<?php
defined( 'ABSPATH' ) || exit;

class MB_Session_Manager {

    const COOKIE_NAME   = 'mb_session';
    const COOKIE_EXPIRY = 30 * DAY_IN_SECONDS;

    public static function init() {
        add_action( 'wp_login',  [ __CLASS__, 'on_login' ], 10, 2 );
        add_action( 'wp_logout', [ __CLASS__, 'on_logout' ] );
        add_action( 'init',      [ __CLASS__, 'validate_session' ] );

        // Cleanup stale sessions hourly
        add_action( 'mb_cleanup_sessions', [ __CLASS__, 'cleanup_old_sessions' ] );
        if ( ! wp_next_scheduled( 'mb_cleanup_sessions' ) ) {
            wp_schedule_event( time(), 'hourly', 'mb_cleanup_sessions' );
        }
    }

    // ── Called on login ───────────────────────────────────────────────────────
    public static function on_login( string $user_login, WP_User $user ) {
        $max = (int) get_option( 'mb_max_sessions', 2 );

        self::enforce_session_limit( $user->ID, $max );

        $token = self::generate_token();
        self::store_session( $user->ID, $token );
        self::set_cookie( $token );
    }

    // ── Called on logout ──────────────────────────────────────────────────────
    public static function on_logout() {
        if ( is_user_logged_in() ) {
            $token = isset( $_COOKIE[ self::COOKIE_NAME ] )
                ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) )
                : '';

            if ( $token ) {
                self::delete_session_by_token( $token );
            }
        }
        self::clear_cookie();
    }

    // ── Validate on every request ─────────────────────────────────────────────
    public static function validate_session() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $token = isset( $_COOKIE[ self::COOKIE_NAME ] )
            ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) )
            : '';

        if ( ! $token ) {
            // Session from another device — create new token
            $token = self::generate_token();
            self::store_session( get_current_user_id(), $token );
            self::set_cookie( $token );
            return;
        }

        // Update last_active
        self::touch_session( $token );
    }

    // ── Enforce max sessions ──────────────────────────────────────────────────
    private static function enforce_session_limit( int $user_id, int $max ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mb_sessions';

        $sessions = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, session_token FROM {$table} WHERE user_id = %d ORDER BY last_active ASC",
            $user_id
        ) );

        if ( count( $sessions ) >= $max ) {
            $to_remove = array_slice( $sessions, 0, count( $sessions ) - $max + 1 );
            foreach ( $to_remove as $s ) {
                $wpdb->delete( $table, [ 'id' => $s->id ], [ '%d' ] );
            }
        }
    }

    // ── Store session in DB ───────────────────────────────────────────────────
    private static function store_session( int $user_id, string $token ) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'mb_sessions',
            [
                'user_id'       => $user_id,
                'session_token' => $token,
                'ip_address'    => self::get_ip(),
                'user_agent'    => isset( $_SERVER['HTTP_USER_AGENT'] )
                    ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
                    : '',
                'last_active'   => current_time( 'mysql' ),
                'created_at'    => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s' ]
        );
    }

    private static function touch_session( string $token ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'mb_sessions',
            [ 'last_active' => current_time( 'mysql' ) ],
            [ 'session_token' => $token ],
            [ '%s' ],
            [ '%s' ]
        );
    }

    private static function delete_session_by_token( string $token ) {
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'mb_sessions',
            [ 'session_token' => $token ],
            [ '%s' ]
        );
    }

    public static function cleanup_old_sessions() {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}mb_sessions WHERE last_active < DATE_SUB(NOW(), INTERVAL 60 DAY)"
        );
    }

    // ── Get all sessions for a user ───────────────────────────────────────────
    public static function get_user_sessions( int $user_id ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mb_sessions WHERE user_id = %d ORDER BY last_active DESC",
            $user_id
        ) );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    private static function generate_token(): string {
        return bin2hex( random_bytes( 32 ) );
    }

    private static function set_cookie( string $token ) {
        setcookie(
            self::COOKIE_NAME,
            $token,
            [
                'expires'  => time() + self::COOKIE_EXPIRY,
                'path'     => '/',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    private static function clear_cookie() {
        setcookie( self::COOKIE_NAME, '', time() - 3600, '/' );
    }

    private static function get_ip(): string {
        foreach ( [ 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
                return explode( ',', $ip )[0];
            }
        }
        return '0.0.0.0';
    }
}