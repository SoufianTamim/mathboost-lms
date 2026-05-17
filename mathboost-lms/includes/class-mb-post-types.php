<?php
defined( 'ABSPATH' ) || exit;

class MB_Post_Types {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register' ] );
    }

    public static function register() {
        register_post_type( 'mb_qcm', [
            'labels'  => [
                'name'               => __( 'QCMs', MB_TEXT_DOMAIN ),
                'singular_name'      => __( 'QCM', MB_TEXT_DOMAIN ),
                'add_new'            => __( 'Ajouter un QCM', MB_TEXT_DOMAIN ),
                'add_new_item'       => __( 'Ajouter un nouveau QCM', MB_TEXT_DOMAIN ),
                'edit_item'          => __( 'Modifier le QCM', MB_TEXT_DOMAIN ),
                'new_item'           => __( 'Nouveau QCM', MB_TEXT_DOMAIN ),
                'view_item'          => __( 'Voir le QCM', MB_TEXT_DOMAIN ),
                'search_items'       => __( 'Rechercher des QCMs', MB_TEXT_DOMAIN ),
                'not_found'          => __( 'Aucun QCM trouvé', MB_TEXT_DOMAIN ),
                'not_found_in_trash' => __( 'Aucun QCM dans la corbeille', MB_TEXT_DOMAIN ),
                'menu_name'          => __( 'MathBoost QCMs', MB_TEXT_DOMAIN ),
            ],
            'public'              => true,
            'query_var'           => false, // prevent WP from hijacking ?mb_qcm= / ?mb_qid= URLs
            'has_archive'         => false,
            'show_in_rest'        => true,
            'menu_icon'           => 'dashicons-welcome-learn-more',
            'menu_position'       => 5,
            'supports'            => [ 'title', 'editor', 'thumbnail', 'revisions' ],
            'rewrite'             => [ 'slug' => 'qcm', 'with_front' => false ],
            'show_in_nav_menus'   => false,
            'capability_type'     => 'post',
        ] );
    }
}