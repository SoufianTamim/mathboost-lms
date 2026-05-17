<?php
defined( 'ABSPATH' ) || exit;

class MB_Access {

    public static function can_access( int $qcm_id ): bool {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        if ( self::current_user_is_premium() ) {
            return true;
        }
        return ! self::is_qcm_locked( $qcm_id );
    }

    public static function current_user_is_premium(): bool {
        if ( ! is_user_logged_in() ) {
            return false;
        }
        return MB_Activation_Codes::is_premium( get_current_user_id() );
    }

    public static function get_qcm_list_with_access( int $category_id ): array {
        $query = new WP_Query( [
            'post_type'      => 'mb_qcm',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'menu_order date',
            'order'          => 'ASC',
            'tax_query'      => [ [
                'taxonomy' => 'mb_category',
                'field'    => 'term_id',
                'terms'    => $category_id,
            ] ],
        ] );

        $posts        = $query->posts;
        $total        = count( $posts );
        $locked_count = (int) get_option( 'mb_free_locked_count', 3 );
        $is_premium   = self::current_user_is_premium();
        $items        = [];

        $free_count = max( 0, $total - $locked_count );

        foreach ( $posts as $index => $post ) {
            $is_locked = false;
            if ( ! $is_premium && ! current_user_can( 'manage_options' ) ) {
                $explicit = get_post_meta( $post->ID, '_mb_locked', true );
                if ( $explicit === '1' ) {
                    $is_locked = true;
                } elseif ( $explicit === '0' ) {
                    $is_locked = false;
                } else {
                    $is_locked = $index >= $free_count;
                }
            }
            $items[] = [ 'post' => $post, 'is_locked' => $is_locked ];
        }

        return $items;
    }

    public static function is_qcm_locked( int $qcm_id ): bool {
        if ( current_user_can( 'manage_options' ) || self::current_user_is_premium() ) {
            return false;
        }

        // Explicit per-QCM lock overrides position-based logic
        $explicit = get_post_meta( $qcm_id, '_mb_locked', true );
        if ( $explicit === '1' ) {
            return true;
        }
        if ( $explicit === '0' ) {
            return false;
        }

        // Fallback: position-based (last N in category)
        $categories = wp_get_post_terms( $qcm_id, 'mb_category', [ 'fields' => 'ids' ] );
        if ( empty( $categories ) || is_wp_error( $categories ) ) {
            return false;
        }

        $cat_id       = $categories[0];
        $locked_count = (int) get_option( 'mb_free_locked_count', 3 );

        $all_qcms = get_posts( [
            'post_type'      => 'mb_qcm',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'menu_order date',
            'order'          => 'ASC',
            'fields'         => 'ids',
            'tax_query'      => [ [
                'taxonomy' => 'mb_category',
                'field'    => 'term_id',
                'terms'    => $cat_id,
            ] ],
        ] );

        $total      = count( $all_qcms );
        $free_count = max( 0, $total - $locked_count );
        $position   = array_search( $qcm_id, $all_qcms, true );

        return $position !== false && $position >= $free_count;
    }
}
