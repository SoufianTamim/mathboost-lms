<?php
defined( 'ABSPATH' ) || exit;

class MB_Activator {

    public static function activate() {
        self::create_tables();
        self::create_default_options();
        self::seed_levels();
        self::maybe_complete_migration();
        flush_rewrite_rules();
        self::create_pages();
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

        // ── User progress ─────────────────────────────────────────────────────
        $sql_progress = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mb_user_progress (
            id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id      BIGINT(20) UNSIGNED NOT NULL,
            qcm_id       BIGINT(20) UNSIGNED NOT NULL,
            score        TINYINT UNSIGNED    NOT NULL DEFAULT 0,
            total        TINYINT UNSIGNED    NOT NULL DEFAULT 0,
            completed_at DATETIME            NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY   uq_user_qcm (user_id, qcm_id),
            KEY          user_id (user_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_codes );
        dbDelta( $sql_sessions );
        dbDelta( $sql_reports );
        dbDelta( $sql_levels );
        dbDelta( $sql_categories );
        dbDelta( $sql_qcms );
        dbDelta( $sql_qcm_cats );
        dbDelta( $sql_progress );

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
        add_option( 'mb_allow_register',    '1' );
        add_option( 'mb_login_page_url',    '' );
        add_option( 'mb_register_page_url', '' );
        add_option( 'mb_payment_page_url',  '' );
    }

    // ── Auto-create login and payment pages if they don't exist ──────────────
    private static function create_pages(): void {
        $pages = [
            'mb_login_page_url'    => [
                'title'   => 'Connexion',
                'content' => '[mathboost_login]',
                'slug'    => 'connexion',
            ],
            'mb_register_page_url' => [
                'title'   => 'Inscription',
                'content' => '[mathboost_register]',
                'slug'    => 'inscription',
            ],
            'mb_payment_page_url'  => [
                'title'   => 'Accès Premium',
                'content' => '[mathboost_payment]',
                'slug'    => 'premium',
            ],
        ];

        foreach ( $pages as $option => $page ) {
            if ( get_option( $option ) ) {
                continue; // Already configured, leave it alone
            }

            $existing = get_page_by_path( $page['slug'] );
            if ( $existing ) {
                update_option( $option, get_permalink( $existing->ID ) );
                continue;
            }

            $page_id = wp_insert_post( [
                'post_title'   => $page['title'],
                'post_content' => $page['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_name'    => $page['slug'],
            ] );

            if ( $page_id && ! is_wp_error( $page_id ) ) {
                update_option( $option, get_permalink( $page_id ) );
            }
        }
    }

    // ── Seed level hierarchy from page-accueil-ressources.html ───────────────
    private static function seed_levels(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'mb_levels';

        $groups = [
            [ 'name' => 'Primaire',    'slug' => 'primaire',    'color' => 'teal',   'order' => 1, 'children' => [
                [ 'name' => 'CP',  'slug' => 'cp',  'order' => 1 ],
                [ 'name' => 'CE1', 'slug' => 'ce1', 'order' => 2 ],
                [ 'name' => 'CE2', 'slug' => 'ce2', 'order' => 3 ],
                [ 'name' => 'CM1', 'slug' => 'cm1', 'order' => 4 ],
                [ 'name' => 'CM2', 'slug' => 'cm2', 'order' => 5 ],
            ] ],
            [ 'name' => 'Pré-collège', 'slug' => 'pre-college', 'color' => 'orange', 'order' => 2, 'children' => [
                [ 'name' => 'Renforcement bases', 'slug' => 'renforcement-bases', 'order' => 1 ],
            ] ],
            [ 'name' => 'Collège',     'slug' => 'college',     'color' => 'teal',   'order' => 3, 'children' => [
                [ 'name' => 'Cinquième', 'slug' => 'cinquieme', 'order' => 1 ],
                [ 'name' => 'Quatrième', 'slug' => 'quatrieme', 'order' => 2 ],
                [ 'name' => 'Troisième', 'slug' => 'troisieme', 'order' => 3 ],
            ] ],
            [ 'name' => 'Pré-Lycée',   'slug' => 'pre-lycee',   'color' => 'orange', 'order' => 4, 'children' => [
                [ 'name' => 'Vérifier les acquis', 'slug' => 'verifier-les-acquis', 'order' => 1 ],
            ] ],
            [ 'name' => 'Lycée',       'slug' => 'lycee',       'color' => 'teal',   'order' => 5, 'children' => [
                [ 'name' => 'Seconde (tronc commun)',          'slug' => 'seconde-tronc-commun',            'order' => 1 ],
                [ 'name' => 'Première Spé Mathématiques',      'slug' => 'premiere-spe-mathematiques',      'order' => 2 ],
                [ 'name' => 'Première techno',                 'slug' => 'premiere-techno',                 'order' => 3 ],
                [ 'name' => 'Terminale Spé Mathématiques',     'slug' => 'terminale-spe-mathematiques',     'order' => 4 ],
                [ 'name' => 'Terminale techno',                'slug' => 'terminale-techno',                'order' => 5 ],
                [ 'name' => 'Terminale Maths + Expertes',      'slug' => 'terminale-maths-expertes',        'order' => 6 ],
                [ 'name' => 'Terminale Maths Complémentaires', 'slug' => 'terminale-maths-complementaires', 'order' => 7 ],
            ] ],
            [ 'name' => 'Pré-prépa',   'slug' => 'pre-prepa',   'color' => 'orange', 'order' => 6, 'children' => [
                [ 'name' => 'Remise à niveau lycée',    'slug' => 'remise-a-niveau-lycee',    'order' => 1 ],
                [ 'name' => 'Préparation entrée prépa', 'slug' => 'preparation-entree-prepa', 'order' => 2 ],
            ] ],
            [ 'name' => 'Prépa',       'slug' => 'prepa',       'color' => 'teal',   'order' => 7, 'children' => [
                [ 'name' => 'PTSI', 'slug' => 'ptsi', 'order' => 1 ],
                [ 'name' => 'PCSI', 'slug' => 'pcsi', 'order' => 2 ],
                [ 'name' => 'MPSI', 'slug' => 'mpsi', 'order' => 3 ],
            ] ],
            [ 'name' => 'Concours',    'slug' => 'concours',    'color' => 'coral',  'order' => 8, 'children' => [
                [ 'name' => 'CAPES',         'slug' => 'capes',         'order' => 1 ],
                [ 'name' => 'CRPE',          'slug' => 'crpe',          'order' => 2 ],
                [ 'name' => 'CAPLP',         'slug' => 'caplp',         'order' => 3 ],
                [ 'name' => 'AGREG',         'slug' => 'agreg',         'order' => 4 ],
                [ 'name' => 'ADMINISTRATIF', 'slug' => 'administratif', 'order' => 5 ],
                [ 'name' => 'Oraux CPGE',    'slug' => 'oraux-cpge',    'order' => 6 ],
            ] ],
            [ 'name' => 'Autres',      'slug' => 'autres',      'color' => 'teal',   'order' => 9, 'children' => [
                [ 'name' => "École d'architecture", 'slug' => 'ecole-architecture', 'order' => 1 ],
            ] ],
        ];

        // Pass 1 — insert parent groups
        foreach ( $groups as $group ) {
            $wpdb->query( $wpdb->prepare(
                "INSERT IGNORE INTO {$table} (parent_id, name, slug, description, color, menu_order)
                 VALUES (NULL, %s, %s, '', %s, %d)",
                $group['name'], $group['slug'], $group['color'], $group['order']
            ) );
        }

        // Pass 2 — insert children, resolving parent ID by slug
        foreach ( $groups as $group ) {
            $parent_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE slug = %s LIMIT 1",
                $group['slug']
            ) );
            if ( ! $parent_id ) {
                continue;
            }
            foreach ( $group['children'] as $child ) {
                $wpdb->query( $wpdb->prepare(
                    "INSERT IGNORE INTO {$table} (parent_id, name, slug, description, color, menu_order)
                     VALUES (%d, %s, %s, '', %s, %d)",
                    $parent_id, $child['name'], $child['slug'], $group['color'], $child['order']
                ) );
            }
        }
    }

    // ── Auto-mark migration done when there is no legacy CPT data ────────────
    private static function maybe_complete_migration(): void {
        if ( get_option( 'mb_migration_v2_done' ) ) {
            return;
        }
        global $wpdb;
        $has_cpt = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ('mb_level','mb_category')"
        ) + (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='mb_qcm' AND post_status NOT IN ('auto-draft','trash')"
        );
        if ( ! $has_cpt ) {
            update_option( 'mb_migration_v2_done', 1 );
        }
    }
}