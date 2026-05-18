<?php
defined( 'ABSPATH' ) || exit;

/**
 * One-time migration: WP CPT / taxonomies → plugin-owned tables.
 * Idempotent: uses INSERT IGNORE so re-runs are safe.
 * Sets option mb_migration_v2_done = 1 when complete.
 */
class MB_Migrator {

    public static function init(): void {
        add_action( 'admin_notices',                      [ __CLASS__, 'maybe_show_notice' ] );
        add_action( 'admin_post_mb_run_migration',        [ __CLASS__, 'handle_run_migration' ] );
    }

    public static function is_done(): bool {
        return (bool) get_option( 'mb_migration_v2_done' );
    }

    // ── Admin notice ──────────────────────────────────────────────────────────
    public static function maybe_show_notice(): void {
        if ( self::is_done() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Check that there is data to migrate
        global $wpdb;
        $has_data = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ('mb_level','mb_category')"
        );
        // Also check for QCMs
        $has_qcms = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='mb_qcm'"
        );

        if ( ! $has_data && ! $has_qcms ) {
            // Nothing to migrate yet — mark as done to suppress notice
            update_option( 'mb_migration_v2_done', 1 );
            return;
        }

        $url = wp_nonce_url(
            admin_url( 'admin-post.php?action=mb_run_migration' ),
            'mb_run_migration'
        );
        ?>
        <div class="notice notice-warning">
            <p>
                <strong>MathBoost LMS :</strong>
                <?php esc_html_e( 'Migration requise — les QCMs, niveaux et catégories doivent être transférés vers les nouvelles tables dédiées.', MB_TEXT_DOMAIN ); ?>
                &nbsp;
                <a href="<?php echo esc_url( $url ); ?>" class="button button-primary">
                    ⚡ <?php esc_html_e( 'Migrer les données → tables MathBoost', MB_TEXT_DOMAIN ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    // ── Handler ───────────────────────────────────────────────────────────────
    public static function handle_run_migration(): void {
        check_admin_referer( 'mb_run_migration' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', MB_TEXT_DOMAIN ) );
        }

        $result = self::run();

        wp_safe_redirect( add_query_arg(
            [
                'page'        => 'mb-qcms',
                'mb_migrated' => 1,
                'mb_levels'   => $result['levels'],
                'mb_cats'     => $result['categories'],
                'mb_qcms'     => $result['qcms'],
                'mb_pivots'   => $result['pivots'],
            ],
            admin_url( 'admin.php' )
        ) );
        exit;
    }

    // ── Core migration ────────────────────────────────────────────────────────
    public static function run(): array {
        global $wpdb;

        $counts = [ 'levels' => 0, 'categories' => 0, 'qcms' => 0, 'pivots' => 0 ];

        // ── 1. mb_level terms → mb_levels ─────────────────────────────────────
        $level_terms = $wpdb->get_results(
            "SELECT t.term_id, t.name, t.slug, tt.parent, tt.description
             FROM {$wpdb->terms} t
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
             WHERE tt.taxonomy = 'mb_level'
             ORDER BY tt.parent ASC, t.name ASC"
        );

        foreach ( $level_terms as $term ) {
            $parent_id  = $term->parent > 0 ? (int) $term->parent : null;
            $menu_order = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->termmeta} WHERE term_id=%d AND meta_key='menu_order' LIMIT 1",
                $term->term_id
            ) );
            $color = (string) $wpdb->get_var( $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->termmeta} WHERE term_id=%d AND meta_key='mb_color' LIMIT 1",
                $term->term_id
            ) ) ?: 'teal';

            if ( $parent_id === null ) {
                $wpdb->query( $wpdb->prepare(
                    "INSERT IGNORE INTO {$wpdb->prefix}mb_levels (id,parent_id,name,slug,description,color,menu_order)
                     VALUES (%d,NULL,%s,%s,%s,%s,%d)",
                    $term->term_id, $term->name, $term->slug, $term->description ?: '', $color, $menu_order
                ) );
            } else {
                $wpdb->query( $wpdb->prepare(
                    "INSERT IGNORE INTO {$wpdb->prefix}mb_levels (id,parent_id,name,slug,description,color,menu_order)
                     VALUES (%d,%d,%s,%s,%s,%s,%d)",
                    $term->term_id, $parent_id, $term->name, $term->slug, $term->description ?: '', $color, $menu_order
                ) );
            }
            if ( $wpdb->rows_affected ) {
                $counts['levels']++;
            }
        }

        // ── 2. mb_category terms → mb_categories ──────────────────────────────
        $cat_terms = $wpdb->get_results(
            "SELECT t.term_id, t.name, t.slug, tt.description
             FROM {$wpdb->terms} t
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
             WHERE tt.taxonomy = 'mb_category'
             ORDER BY t.name ASC"
        );

        foreach ( $cat_terms as $term ) {
            $level_id   = self::guess_level_for_category( (int) $term->term_id );
            $menu_order = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->termmeta} WHERE term_id=%d AND meta_key='menu_order' LIMIT 1",
                $term->term_id
            ) );

            if ( $level_id === null ) {
                $wpdb->query( $wpdb->prepare(
                    "INSERT IGNORE INTO {$wpdb->prefix}mb_categories (id,level_id,name,slug,description,menu_order)
                     VALUES (%d,NULL,%s,%s,%s,%d)",
                    $term->term_id, $term->name, $term->slug, $term->description ?: '', $menu_order
                ) );
            } else {
                $wpdb->query( $wpdb->prepare(
                    "INSERT IGNORE INTO {$wpdb->prefix}mb_categories (id,level_id,name,slug,description,menu_order)
                     VALUES (%d,%d,%s,%s,%s,%d)",
                    $term->term_id, $level_id, $term->name, $term->slug, $term->description ?: '', $menu_order
                ) );
            }
            if ( $wpdb->rows_affected ) {
                $counts['categories']++;
            }
        }

        // ── 3. mb_qcm posts → mb_qcms ─────────────────────────────────────────
        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_status, menu_order, post_date, post_modified
             FROM {$wpdb->posts}
             WHERE post_type='mb_qcm' AND post_status IN ('publish','draft','private')
             ORDER BY menu_order ASC, ID ASC"
        );

        $locked_count = (int) get_option( 'mb_free_locked_count', 3 );

        foreach ( $posts as $post ) {
            $meta = $wpdb->get_results( $wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$wpdb->postmeta}
                 WHERE post_id=%d AND meta_key IN ('_mb_subtitle','_mb_intro','_mb_questions','_mb_locked')",
                $post->ID
            ), OBJECT_K );

            $subtitle   = $meta['_mb_subtitle']  ? (string) $meta['_mb_subtitle']->meta_value  : '';
            $intro      = $meta['_mb_intro']      ? (string) $meta['_mb_intro']->meta_value      : '';
            $questions  = $meta['_mb_questions']  ? (string) $meta['_mb_questions']->meta_value  : '[]';
            $explicit   = $meta['_mb_locked']     ? (string) $meta['_mb_locked']->meta_value     : '';

            // Determine is_locked: explicit meta first, then position-based fallback computed at migration
            if ( $explicit === '1' ) {
                $is_locked = 1;
            } elseif ( $explicit === '0' ) {
                $is_locked = 0;
            } else {
                $is_locked = self::compute_position_lock( $post->ID, $locked_count ) ? 1 : 0;
            }

            $wpdb->query( $wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->prefix}mb_qcms
                 (id,title,subtitle,intro,questions,is_locked,menu_order,status,created_at,updated_at)
                 VALUES (%d,%s,%s,%s,%s,%d,%d,%s,%s,%s)",
                $post->ID, $post->post_title, $subtitle, $intro, $questions,
                $is_locked, $post->menu_order, $post->post_status,
                $post->post_date, $post->post_modified
            ) );
            if ( $wpdb->rows_affected ) {
                $counts['qcms']++;
            }
        }

        // ── 4. QCM ↔ mb_category relationships → mb_qcm_categories ───────────
        $rels = $wpdb->get_results(
            "SELECT tr.object_id AS qcm_id, tt.term_id AS category_id
             FROM {$wpdb->term_relationships} tr
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id
             WHERE tt.taxonomy='mb_category'"
        );

        foreach ( $rels as $rel ) {
            $wpdb->query( $wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->prefix}mb_qcm_categories (qcm_id,category_id) VALUES (%d,%d)",
                $rel->qcm_id, $rel->category_id
            ) );
            if ( $wpdb->rows_affected ) {
                $counts['pivots']++;
            }
        }

        update_option( 'mb_migration_v2_done', 1 );

        return $counts;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Returns the most common mb_level term_id for a given mb_category term. */
    private static function guess_level_for_category( int $category_id ): ?int {
        global $wpdb;

        $qcm_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT tr.object_id
             FROM {$wpdb->term_relationships} tr
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id
             WHERE tt.taxonomy='mb_category' AND tt.term_id=%d LIMIT 100",
            $category_id
        ) );

        if ( empty( $qcm_ids ) ) {
            return null;
        }

        $in = implode( ',', array_map( 'intval', $qcm_ids ) );

        $level_id = $wpdb->get_var(
            "SELECT tt.term_id
             FROM {$wpdb->term_relationships} tr
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id
             WHERE tt.taxonomy='mb_level' AND tr.object_id IN ($in)
             GROUP BY tt.term_id ORDER BY COUNT(*) DESC LIMIT 1"
        );

        return $level_id ? (int) $level_id : null;
    }

    /** Computes whether a QCM should be locked based on its position in its category. */
    private static function compute_position_lock( int $post_id, int $locked_count ): bool {
        global $wpdb;

        $cat_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT tt.term_id
             FROM {$wpdb->term_relationships} tr
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id
             WHERE tt.taxonomy='mb_category' AND tr.object_id=%d LIMIT 1",
            $post_id
        ) );

        if ( ! $cat_id ) {
            return false;
        }

        $all_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id=p.ID
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id
             WHERE tt.taxonomy='mb_category' AND tt.term_id=%d
               AND p.post_type='mb_qcm' AND p.post_status='publish'
             ORDER BY p.menu_order ASC, p.ID ASC",
            $cat_id
        ) );

        $total      = count( $all_ids );
        $free_count = max( 0, $total - $locked_count );
        $position   = array_search( (string) $post_id, $all_ids, true );

        return $position !== false && $position >= $free_count;
    }
}
