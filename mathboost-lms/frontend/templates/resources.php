<?php defined( 'ABSPATH' ) || exit; ?>

<?php
$mb_level_param = isset( $_GET['mb_level'] ) ? sanitize_text_field( wp_unslash( $_GET['mb_level'] ) ) : '';
$mb_cat_param   = isset( $_GET['mb_cat'] )   ? sanitize_text_field( wp_unslash( $_GET['mb_cat'] ) )   : '';
$mb_qcm_param   = isset( $_GET['mb_qid'] )   ? (int) $_GET['mb_qid']                                   : 0;

$course_page_url = apply_filters( 'mb_course_page_url', get_permalink() );

// ── Route 1 : Inline QCM view ─────────────────────────────────────────────
if ( $mb_qcm_param ) {
    $qcm_id   = $mb_qcm_param;
    $back_url = esc_url( remove_query_arg( 'mb_qid' ) );

    // Resolve breadcrumb label
    $back_label = '← ' . esc_html__( 'Retour', MB_TEXT_DOMAIN );
    if ( $mb_level_param ) {
        $level_t = get_term_by( 'slug', $mb_level_param, 'mb_level' );
        if ( $level_t ) {
            $back_label = '← ' . esc_html( $level_t->name );
        }
    }
    ?>
    <div class="mb-resources-wrap">
      <div class="mb-breadcrumb mb-breadcrumb-top">
        <a href="<?php echo $back_url; ?>"><?php echo $back_label; ?></a>
      </div>
      <?php echo MB_Shortcodes::qcm_single( [ 'id' => (string) $qcm_id ] ); ?>
    </div>
    <?php
    return;
}

// ── Route 2 : Level + optional category → QCM list ───────────────────────
if ( $mb_level_param ) {
    $atts = [ 'level' => $mb_level_param, 'category' => $mb_cat_param ];
    include MB_PLUGIN_DIR . 'frontend/templates/course-selector.php';
    return;
}

// ── Route 3 : Home — level grid ──────────────────────────────────────────
$levels = get_terms( [
    'taxonomy'   => 'mb_level',
    'hide_empty' => false,
    'parent'     => 0,
    'orderby'    => 'name',
    'order'      => 'ASC',
] );
?>

<div class="mb-resources-wrap">

  <div class="mb-resources-header">
    <h1 class="mb-resources-title">
      <?php esc_html_e( 'Choisissez votre niveau', MB_TEXT_DOMAIN ); ?>
    </h1>
    <p class="mb-resources-sub">
      <?php esc_html_e( 'Sélectionnez votre niveau pour accéder aux QCMs, cours et exercices.', MB_TEXT_DOMAIN ); ?>
    </p>
  </div>

  <?php if ( ! empty( $levels ) && ! is_wp_error( $levels ) ) : ?>

    <?php foreach ( $levels as $level ) :
      $children = get_terms( [
          'taxonomy'   => 'mb_level',
          'hide_empty' => false,
          'parent'     => $level->term_id,
      ] );
    ?>
      <div class="mb-level-section" data-color="<?php echo esc_attr( get_term_meta( $level->term_id, 'mb_color', true ) ?: 'teal' ); ?>">
        <div class="mb-section-title"><?php echo esc_html( $level->name ); ?></div>
        <div class="mb-level-grid">
          <?php if ( ! empty( $children ) && ! is_wp_error( $children ) ) : ?>
            <?php foreach ( $children as $child ) : ?>
              <a class="mb-level-card" href="<?php echo esc_url( add_query_arg( 'mb_level', $child->slug, $course_page_url ) ); ?>">
                <?php echo esc_html( $child->name ); ?>
              </a>
            <?php endforeach; ?>
          <?php else : ?>
            <a class="mb-level-card" href="<?php echo esc_url( add_query_arg( 'mb_level', $level->slug, $course_page_url ) ); ?>">
              <?php echo esc_html( $level->name ); ?>
            </a>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

  <?php else : ?>

    <?php
    $static_sections = [
        [ 'label' => __( 'Primaire', MB_TEXT_DOMAIN ),    'color' => 'teal',  'items' => [ 'CP', 'CE1', 'CE2', 'CM1', 'CM2' ] ],
        [ 'label' => __( 'Collège', MB_TEXT_DOMAIN ),     'color' => 'teal',  'items' => [ 'Cinquième', 'Quatrième', 'Troisième' ] ],
        [ 'label' => __( 'Lycée', MB_TEXT_DOMAIN ),       'color' => 'teal',  'items' => [ 'Seconde', 'Première', 'Terminale' ] ],
        [ 'label' => __( 'Prépa', MB_TEXT_DOMAIN ),       'color' => 'teal',  'items' => [ 'PTSI', 'PCSI', 'MPSI' ] ],
        [ 'label' => __( 'Concours', MB_TEXT_DOMAIN ),    'color' => 'coral', 'items' => [ 'CAPES', 'CRPE', 'AGREG' ] ],
    ];
    ?>
    <?php foreach ( $static_sections as $sec ) : ?>
      <div class="mb-level-section" data-color="<?php echo esc_attr( $sec['color'] ); ?>">
        <div class="mb-section-title"><?php echo esc_html( $sec['label'] ); ?></div>
        <div class="mb-level-grid">
          <?php foreach ( $sec['items'] as $item ) : ?>
            <a class="mb-level-card" href="<?php echo esc_url( add_query_arg( 'mb_level', sanitize_title( $item ), $course_page_url ) ); ?>">
              <?php echo esc_html( $item ); ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

  <?php endif; ?>

</div>
