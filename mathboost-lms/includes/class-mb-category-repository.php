<?php
defined( 'ABSPATH' ) || exit;

class MB_Category_Repository {

    private static function t(): string {
        global $wpdb;
        return $wpdb->prefix . 'mb_categories';
    }

    public static function get_all(): array {
        global $wpdb;
        return $wpdb->get_results(
            'SELECT * FROM ' . self::t() . ' ORDER BY level_id ASC, menu_order ASC, name ASC'
        ) ?: [];
    }

    public static function get_by_level( int $level_id ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::t() . ' WHERE level_id=%d ORDER BY menu_order ASC, name ASC',
                $level_id
            )
        ) ?: [];
    }

    public static function get_by_slug( string $slug ): ?object {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE slug=%s LIMIT 1', $slug )
        ) ?: null;
    }

    public static function get_by_id( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE id=%d LIMIT 1', $id )
        ) ?: null;
    }

    /** @return int  New or updated ID. */
    public static function save( array $data ): int {
        global $wpdb;

        $id       = ! empty( $data['id'] ) ? (int) $data['id'] : 0;
        $level_id = ( isset( $data['level_id'] ) && $data['level_id'] !== '' && $data['level_id'] !== null )
                    ? (int) $data['level_id'] : null;
        $name     = $data['name']        ?? '';
        $slug     = $data['slug']        ?? sanitize_title( $name );
        $desc     = $data['description'] ?? '';
        $order    = (int) ( $data['menu_order'] ?? 0 );

        if ( $id ) {
            if ( $level_id === null ) {
                $wpdb->query( $wpdb->prepare(
                    'UPDATE ' . self::t() . ' SET level_id=NULL, name=%s, slug=%s, description=%s, menu_order=%d WHERE id=%d',
                    $name, $slug, $desc, $order, $id
                ) );
            } else {
                $wpdb->query( $wpdb->prepare(
                    'UPDATE ' . self::t() . ' SET level_id=%d, name=%s, slug=%s, description=%s, menu_order=%d WHERE id=%d',
                    $level_id, $name, $slug, $desc, $order, $id
                ) );
            }
            return $id;
        }

        if ( $level_id === null ) {
            $wpdb->query( $wpdb->prepare(
                'INSERT INTO ' . self::t() . ' (level_id,name,slug,description,menu_order) VALUES (NULL,%s,%s,%s,%d)',
                $name, $slug, $desc, $order
            ) );
        } else {
            $wpdb->query( $wpdb->prepare(
                'INSERT INTO ' . self::t() . ' (level_id,name,slug,description,menu_order) VALUES (%d,%s,%s,%s,%d)',
                $level_id, $name, $slug, $desc, $order
            ) );
        }
        return (int) $wpdb->insert_id;
    }

    public static function delete( int $id ): void {
        global $wpdb;
        $wpdb->delete( self::t(), [ 'id' => $id ], [ '%d' ] );
    }
}
