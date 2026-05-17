<?php defined( 'ABSPATH' ) || exit; ?>

<?php
// Route based on URL params: level+category → QCM list; level only → course selector
$mb_level_param = isset( $_GET['mb_level'] ) ? sanitize_text_field( wp_unslash( $_GET['mb_level'] ) ) : '';
$mb_cat_param   = isset( $_GET['mb_cat'] )   ? sanitize_text_field( wp_unslash( $_GET['mb_cat'] ) )   : '';

if ( $mb_level_param && $mb_cat_param ) {
    $atts = [ 'level' => $mb_level_param, 'category' => $mb_cat_param ];
    include MB_PLUGIN_DIR . 'frontend/templates/qcm-list.php';
    return;
}

if ( $mb_level_param ) {
    $atts = [ 'level' => $mb_level_param ];
    include MB_PLUGIN_DIR . 'frontend/templates/course-selector.php';
    return;
}

$levels = get_terms( [
    'taxonomy'   => 'mb_level',
    'hide_empty' => false,
    'parent'     => 0,
    'orderby'    => 'name',
    'order'      => 'ASC',
] );

// URL of the course selector page (filterable)
$course_page_url = apply_filters( 'mb_course_page_url', get_permalink() );
?>

<div class="mb-resources-wrap">

  <div class="mb-resources-header">
    <h1 class="mb-resources-title">
      <?php esc_html_e( 'Choisissez votre niveau', MB_TEXT_DOMAIN ); ?>
    </h1>
    <p class="mb-resources-sub">
      <?php esc_html_e( 'Sélectionnez votre niveau pour accéder aux QCMs, cours et exercices correspondants.', MB_TEXT_DOMAIN ); ?>
    </p>
  </div>

  <?php if ( ! empty( $levels ) && ! is_wp_error( $levels ) ) : ?>

    <?php foreach ( $levels as $level ) : ?>
      <?php
      // Get children (sub-levels)
      $children = get_terms( [
          'taxonomy'   => 'mb_level',
          'hide_empty' => false,
          'parent'     => $level->term_id,
      ] );
      ?>

      <div class="mb-level-section" data-color="<?php echo esc_attr( get_term_meta( $level->term_id, 'mb_color', true ) ?: 'teal' ); ?>">
        <div class="mb-section-title">
          <?php echo esc_html( $level->name ); ?>
        </div>

        <div class="mb-level-grid">
          <?php if ( ! empty( $children ) && ! is_wp_error( $children ) ) : ?>
            <?php foreach ( $children as $child ) : ?>
              <a class="mb-level-card"
                 href="<?php echo esc_url( add_query_arg( 'mb_level', $child->slug, $course_page_url ) ); ?>">
                <?php echo esc_html( $child->name ); ?>
              </a>
            <?php endforeach; ?>
          <?php else : ?>
            <a class="mb-level-card"
               href="<?php echo esc_url( add_query_arg( 'mb_level', $level->slug, $course_page_url ) ); ?>">
              <?php echo esc_html( $level->name ); ?>
            </a>
          <?php endif; ?>
        </div>
      </div>

    <?php endforeach; ?>

  <?php else : ?>

    <?php /* Fallback: show static French curriculum levels */ ?>
    <?php
    $static_sections = [
        [ 'label' => __( 'Primaire', MB_TEXT_DOMAIN ),       'color' => 'teal',   'items' => [ 'CP', 'CE1', 'CE2', 'CM1', 'CM2' ] ],
        [ 'label' => __( 'Pré-collège', MB_TEXT_DOMAIN ),    'color' => 'amber',  'items' => [ __( 'Renforcement bases', MB_TEXT_DOMAIN ) ] ],
        [ 'label' => __( 'Collège', MB_TEXT_DOMAIN ),        'color' => 'teal',   'items' => [ __( 'Cinquième', MB_TEXT_DOMAIN ), __( 'Quatrième', MB_TEXT_DOMAIN ), __( 'Troisième', MB_TEXT_DOMAIN ) ] ],
        [ 'label' => __( 'Pré-Lycée', MB_TEXT_DOMAIN ),      'color' => 'amber',  'items' => [ __( 'Vérifier les acquis', MB_TEXT_DOMAIN ) ] ],
        [ 'label' => __( 'Lycée', MB_TEXT_DOMAIN ),          'color' => 'teal',   'items' => [ __( 'Seconde (tronc commun)', MB_TEXT_DOMAIN ), __( 'Première Spé Mathématiques', MB_TEXT_DOMAIN ), __( 'Terminale Spé Mathématiques', MB_TEXT_DOMAIN ) ] ],
        [ 'label' => __( 'Prépa', MB_TEXT_DOMAIN ),          'color' => 'teal',   'items' => [ 'PTSI', 'PCSI', 'MPSI' ] ],
        [ 'label' => __( 'Concours', MB_TEXT_DOMAIN ),       'color' => 'coral',  'items' => [ 'CAPES', 'CRPE', 'AGREG' ] ],
    ];
    ?>
    <?php foreach ( $static_sections as $sec ) : ?>
      <div class="mb-level-section" data-color="<?php echo esc_attr( $sec['color'] ); ?>">
        <div class="mb-section-title"><?php echo esc_html( $sec['label'] ); ?></div>
        <div class="mb-level-grid">
          <?php foreach ( $sec['items'] as $item ) : ?>
            <a class="mb-level-card"
               href="<?php echo esc_url( add_query_arg( 'mb_level', sanitize_title( $item ), $course_page_url ) ); ?>">
              <?php echo esc_html( $item ); ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

  <?php endif; ?>

</div>
