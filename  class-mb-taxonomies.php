<?php
defined( 'ABSPATH' ) || exit;

class MB_Taxonomies {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register' ] );
    }

    public static function register() {

        // ── Levels (Niveaux) ─────────────────────────────────────────────────
        register_taxonomy( 'mb_level', 'mb_qcm', [
            'labels'            => self::make_labels(
                __( 'Niveaux', MB_TEXT_DOMAIN ),
                __( 'Niveau', MB_TEXT_DOMAIN )
            ),
            'hierarchical'      => true,
            'public'            => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'niveau', 'with_front' => false ],
        ] );

        // ── Courses (Cours / Matières) ────────────────────────────────────────
        register_taxonomy( 'mb_course', 'mb_qcm', [
            'labels'            => self::make_labels(
                __( 'Cours', MB_TEXT_DOMAIN ),
                __( 'Cours', MB_TEXT_DOMAIN )
            ),
            'hierarchical'      => true,
            'public'            => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'cours', 'with_front' => false ],
        ] );

        // ── QCM Categories ────────────────────────────────────────────────────
        register_taxonomy( 'mb_category', 'mb_qcm', [
            'labels'            => self::make_labels(
                __( 'Catégories QCM', MB_TEXT_DOMAIN ),
                __( 'Catégorie QCM', MB_TEXT_DOMAIN )
            ),
            'hierarchical'      => true, // supports unlimited nesting
            'public'            => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'categorie', 'with_front' => false ],
        ] );
    }

    private static function make_labels( string $plural, string $singular ): array {
        return [
            'name'              => $plural,
            'singular_name'     => $singular,
            'search_items'      => sprintf( __( 'Rechercher %s', MB_TEXT_DOMAIN ), $plural ),
            'all_items'         => sprintf( __( 'Tous les %s', MB_TEXT_DOMAIN ), $plural ),
            'parent_item'       => sprintf( __( '%s parent', MB_TEXT_DOMAIN ), $singular ),
            'parent_item_colon' => sprintf( __( '%s parent :', MB_TEXT_DOMAIN ), $singular ),
            'edit_item'         => sprintf( __( 'Modifier %s', MB_TEXT_DOMAIN ), $singular ),
            'update_item'       => sprintf( __( 'Mettre à jour %s', MB_TEXT_DOMAIN ), $singular ),
            'add_new_item'      => sprintf( __( 'Ajouter un %s', MB_TEXT_DOMAIN ), $singular ),
            'new_item_name'     => sprintf( __( 'Nom du nouveau %s', MB_TEXT_DOMAIN ), $singular ),
            'menu_name'         => $plural,
        ];
    }
}