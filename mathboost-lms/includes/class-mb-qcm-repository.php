<?php
defined( 'ABSPATH' ) || exit;

class MB_QCM_Repository {

    private static function t(): string {
        global $wpdb;
        return $wpdb->prefix . 'mb_qcms';
    }

    private static function pt(): string {
        global $wpdb;
        return $wpdb->prefix . 'mb_qcm_categories';
    }

    public static function get_by_id( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE id=%d LIMIT 1', $id )
        ) ?: null;
    }

    /** Returns QCMs for a given category, ordered by menu_order then id. */
    public static function get_by_category( int $category_id, string $status = 'publish' ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT q.* FROM ' . self::t() . ' q
                 INNER JOIN ' . self::pt() . ' p ON p.qcm_id=q.id
                 WHERE p.category_id=%d AND q.status=%s
                 ORDER BY q.menu_order ASC, q.id ASC',
                $category_id, $status
            )
        ) ?: [];
    }

    /**
     * Returns all QCMs for a level grouped by category.
     *
     * @return array  [ cat_id => [ 'category' => object|null, 'qcms' => object[] ] ]
     */
    public static function get_by_level_grouped_by_category( int $level_id, string $status = 'publish' ): array {
        global $wpdb;

        $cats = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . $wpdb->prefix . 'mb_categories WHERE level_id=%d ORDER BY menu_order ASC, name ASC',
                $level_id
            )
        ) ?: [];

        if ( empty( $cats ) ) {
            return [];
        }

        $cat_ids      = array_column( $cats, 'id' );
        $placeholders = implode( ',', array_fill( 0, count( $cat_ids ), '%d' ) );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT q.*, p.category_id
                 FROM " . self::t() . " q
                 INNER JOIN " . self::pt() . " p ON p.qcm_id=q.id
                 WHERE p.category_id IN ($placeholders) AND q.status=%s
                 ORDER BY p.category_id ASC, q.menu_order ASC, q.id ASC",
                array_merge( $cat_ids, [ $status ] )
            )
        ) ?: [];

        $cat_map = [];
        foreach ( $cats as $c ) {
            $cat_map[ (int) $c->id ] = $c;
        }

        $groups = [];
        foreach ( $rows as $row ) {
            $cid = (int) $row->category_id;
            if ( ! isset( $groups[ $cid ] ) ) {
                $groups[ $cid ] = [ 'category' => $cat_map[ $cid ] ?? null, 'qcms' => [] ];
            }
            $groups[ $cid ]['qcms'][] = $row;
        }

        // Return in the same order as categories are sorted
        $ordered = [];
        foreach ( $cats as $c ) {
            $cid = (int) $c->id;
            if ( isset( $groups[ $cid ] ) ) {
                $ordered[ $cid ] = $groups[ $cid ];
            }
        }
        foreach ( $groups as $cid => $group ) {
            if ( ! isset( $ordered[ $cid ] ) ) {
                $ordered[ $cid ] = $group;
            }
        }

        return $ordered;
    }

    public static function get_all( string $status = 'publish' ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::t() . ' WHERE status=%s ORDER BY menu_order ASC, id ASC',
                $status
            )
        ) ?: [];
    }

    public static function get_all_any_status(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT q.*, GROUP_CONCAT(p.category_id ORDER BY p.category_id SEPARATOR ',') AS cat_ids
             FROM " . self::t() . " q
             LEFT JOIN " . self::pt() . " p ON p.qcm_id=q.id
             GROUP BY q.id
             ORDER BY q.menu_order ASC, q.id ASC"
        ) ?: [];
    }

    /** Returns the list of category IDs assigned to a QCM. */
    public static function get_category_ids( int $qcm_id ): array {
        global $wpdb;
        return array_map( 'intval', $wpdb->get_col(
            $wpdb->prepare( 'SELECT category_id FROM ' . self::pt() . ' WHERE qcm_id=%d', $qcm_id )
        ) );
    }

    /** Insert or update a QCM row. Returns the ID. */
    public static function save( array $data ): int {
        global $wpdb;
        $table = self::t();

        if ( ! empty( $data['id'] ) ) {
            $id = (int) $data['id'];
            unset( $data['id'] );
            $data['updated_at'] = current_time( 'mysql' );
            $wpdb->update( $table, $data, [ 'id' => $id ] );
            return $id;
        }

        $defaults = [
            'title'      => '',
            'subtitle'   => '',
            'intro'      => '',
            'questions'  => '[]',
            'is_locked'  => 0,
            'menu_order' => 0,
            'status'     => 'publish',
        ];
        $data = array_merge( $defaults, $data );
        $data['created_at'] = current_time( 'mysql' );
        $data['updated_at'] = current_time( 'mysql' );
        $wpdb->insert( $table, $data );
        return (int) $wpdb->insert_id;
    }

    /** Replaces all category assignments for a QCM. */
    public static function set_categories( int $qcm_id, array $category_ids ): void {
        global $wpdb;
        $wpdb->delete( self::pt(), [ 'qcm_id' => $qcm_id ], [ '%d' ] );
        foreach ( $category_ids as $cat_id ) {
            $cat_id = (int) $cat_id;
            if ( $cat_id ) {
                $wpdb->insert( self::pt(), [ 'qcm_id' => $qcm_id, 'category_id' => $cat_id ] );
            }
        }
    }

    public static function delete( int $id ): void {
        global $wpdb;
        $wpdb->delete( self::t(),  [ 'id'     => $id ], [ '%d' ] );
        $wpdb->delete( self::pt(), [ 'qcm_id' => $id ], [ '%d' ] );
    }
}
