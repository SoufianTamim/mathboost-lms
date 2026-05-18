<?php
defined( 'ABSPATH' ) || exit;

class MB_Level_Repository {

    private static function t(): string {
        global $wpdb;
        return $wpdb->prefix . 'mb_levels';
    }

    public static function get_all(): array {
        global $wpdb;
        return $wpdb->get_results(
            'SELECT * FROM ' . self::t() . ' ORDER BY parent_id ASC, menu_order ASC, name ASC'
        ) ?: [];
    }

    public static function get_top_level(): array {
        global $wpdb;
        return $wpdb->get_results(
            'SELECT * FROM ' . self::t() . ' WHERE parent_id IS NULL ORDER BY menu_order ASC, name ASC'
        ) ?: [];
    }

    public static function get_children( int $parent_id ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::t() . ' WHERE parent_id = %d ORDER BY menu_order ASC, name ASC',
                $parent_id
            )
        ) ?: [];
    }

    public static function get_by_slug( string $slug ): ?object {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE slug = %s LIMIT 1', $slug )
        ) ?: null;
    }

    public static function get_by_id( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE id = %d LIMIT 1', $id )
        ) ?: null;
    }

    /** @return int  New or updated ID. */
    public static function save( array $data ): int {
        global $wpdb;

        $id         = ! empty( $data['id'] ) ? (int) $data['id'] : 0;
        $parent_id  = ( isset( $data['parent_id'] ) && $data['parent_id'] !== '' && $data['parent_id'] !== null )
                      ? (int) $data['parent_id'] : null;
        $name       = $data['name']        ?? '';
        $slug       = $data['slug']        ?? sanitize_title( $name );
        $desc       = $data['description'] ?? '';
        $color      = $data['color']       ?? 'teal';
        $order      = (int) ( $data['menu_order'] ?? 0 );

        if ( $id ) {
            if ( $parent_id === null ) {
                $wpdb->query( $wpdb->prepare(
                    'UPDATE ' . self::t() . ' SET parent_id=NULL, name=%s, slug=%s, description=%s, color=%s, menu_order=%d WHERE id=%d',
                    $name, $slug, $desc, $color, $order, $id
                ) );
            } else {
                $wpdb->query( $wpdb->prepare(
                    'UPDATE ' . self::t() . ' SET parent_id=%d, name=%s, slug=%s, description=%s, color=%s, menu_order=%d WHERE id=%d',
                    $parent_id, $name, $slug, $desc, $color, $order, $id
                ) );
            }
            return $id;
        }

        if ( $parent_id === null ) {
            $wpdb->query( $wpdb->prepare(
                'INSERT INTO ' . self::t() . ' (parent_id,name,slug,description,color,menu_order) VALUES (NULL,%s,%s,%s,%s,%d)',
                $name, $slug, $desc, $color, $order
            ) );
        } else {
            $wpdb->query( $wpdb->prepare(
                'INSERT INTO ' . self::t() . ' (parent_id,name,slug,description,color,menu_order) VALUES (%d,%s,%s,%s,%s,%d)',
                $parent_id, $name, $slug, $desc, $color, $order
            ) );
        }
        return (int) $wpdb->insert_id;
    }

    public static function delete( int $id ): void {
        global $wpdb;
        $wpdb->delete( self::t(), [ 'id' => $id ], [ '%d' ] );
        // Detach child levels
        $wpdb->query( $wpdb->prepare(
            'UPDATE ' . self::t() . ' SET parent_id=NULL WHERE parent_id=%d', $id
        ) );
    }
}
