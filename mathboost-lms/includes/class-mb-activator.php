<?php
defined( 'ABSPATH' ) || exit;

class MB_Activator {

    public static function activate() {
        self::create_tables();
        self::create_default_options();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Activation codes table
        $sql_codes = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mb_activation_codes (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            code        VARCHAR(64)         NOT NULL,
            user_id     BIGINT(20) UNSIGNED          DEFAULT NULL,
            used_at     DATETIME                    DEFAULT NULL,
            expires_at  DATETIME                    DEFAULT NULL,
            created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            notes       TEXT                         DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Sessions table
        $sql_sessions = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mb_sessions (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT(20) UNSIGNED NOT NULL,
            session_token VARCHAR(128)      NOT NULL,
            ip_address  VARCHAR(45)                  DEFAULT NULL,
            user_agent  TEXT                         DEFAULT NULL,
            last_active DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY session_token (session_token),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Error reports table
        $sql_reports = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mb_error_reports (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            qcm_id      BIGINT(20) UNSIGNED NOT NULL,
            question_num INT UNSIGNED       NOT NULL,
            user_id     BIGINT(20) UNSIGNED          DEFAULT NULL,
            message     TEXT                NOT NULL,
            status      ENUM('open','resolved') NOT NULL DEFAULT 'open',
            created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY qcm_id (qcm_id)
        ) $charset_collate;";

        // ── Levels ───────────────────────────────────────────────────────────
        $sql_levels = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mb_levels (
            id          BIGINT(20) UNSIGNED  NOT NULL AUTO_INCREMENT,
            parent_id   BIGINT(20) UNSIGNED           DEFAULT NULL,
            name        VARCHAR(200)         NOT NULL  DEFAULT '',
            slug        VARCHAR(200)         NOT NULL  DEFAULT '',
            description TEXT,
            color       VARCHAR(50)          NOT NULL  DEFAULT 'teal',
            menu_order  INT                  NOT NULL  DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY   slug (slug),
            KEY          parent_id (parent_id)
        ) $charset_collate;";

        // ── Categories ────────────────────────────────────────────────────────
        $sql_categories = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mb_categories (
            id          BIGINT(20) UNSIGNED  NOT NULL AUTO_INCREMENT,
            level_id    BIGINT(20) UNSIGNED           DEFAULT NULL,
            name        VARCHAR(200)         NOT NULL  DEFAULT '',
            slug        VARCHAR(200)         NOT NULL  DEFAULT '',
            description TEXT,
            menu_order  INT                  NOT NULL  DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY   slug (slug),
            KEY          level_id (level_id)
        ) $charset_collate;";

        // ── QCMs ─────────────────────────────────────────────────────────────
        $sql_qcms = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mb_qcms (
            id          BIGINT(20) UNSIGNED  NOT NULL AUTO_INCREMENT,
            title       VARCHAR(500)         NOT NULL  DEFAULT '',
            subtitle    VARCHAR(500)         NOT NULL  DEFAULT '',
            intro       TEXT,
            questions   LONGTEXT,
            is_locked   TINYINT(1)           NOT NULL  DEFAULT 0,
            menu_order  INT                  NOT NULL  DEFAULT 0,
            status      VARCHAR(20)          NOT NULL  DEFAULT 'publish',
            created_at  DATETIME             NOT NULL  DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME             NOT NULL  DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY          status (status),
            KEY          menu_order (menu_order)
        ) $charset_collate;";

        // ── QCM ↔ Category pivot ──────────────────────────────────────────────
        $sql_qcm_cats = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mb_qcm_categories (
            qcm_id      BIGINT(20) UNSIGNED  NOT NULL,
            category_id BIGINT(20) UNSIGNED  NOT NULL,
            PRIMARY KEY  (qcm_id, category_id),
            KEY          category_id (category_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_codes );
        dbDelta( $sql_sessions );
        dbDelta( $sql_reports );
        dbDelta( $sql_levels );
        dbDelta( $sql_categories );
        dbDelta( $sql_qcms );
        dbDelta( $sql_qcm_cats );

        update_option( 'mb_db_version', MB_VERSION );
    }

    private static function create_default_options() {
        add_option( 'mb_paypal_client_id',  '' );
        add_option( 'mb_price',             '15' );
        add_option( 'mb_currency',          'EUR' );
        add_option( 'mb_max_sessions',      '2' );
        add_option( 'mb_free_locked_count', '3' );
        add_option( 'mb_email_contact',     get_option( 'admin_email' ) );
        add_option( 'mb_premium_duration',  '365' ); // days, 0 = lifetime
    }
}