<?php defined( 'ABSPATH' ) || exit; ?>

<?php
// Get level from shortcode attr or URL param
$level_slug = ! empty( $atts['level'] )
    ? sanitize_text_field( $atts['level'] )
    : ( isset( $_GET['mb_level'] ) ? sanitize_text_field( wp_unslash( $_GET['mb_level'] ) ) : '' );

$level_term = $level_slug ? get_term_by( 'slug', $level_slug, 'mb_level' ) : null;

// Get top-level mb_category terms that actually have QCMs in this level
if ( $level_term ) {
    // Step 1: get IDs of QCMs assigned to this level
    $qcm_ids = get_posts( [
        'post_type'      => 'mb_qcm',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'post_status'    => 'publish',
        'tax_query'      => [ [
            'taxonomy' => 'mb_level',
            'field'    => 'term_id',
            'terms'    => $level_term->term_id,
        ] ],
    ] );

    if ( ! empty( $qcm_ids ) ) {
        // Step 2: get every mb_category term directly assigned to those QCMs
        $assigned_term_ids = get_terms( [
            'taxonomy'   => 'mb_category',
            'hide_empty' => true,
            'object_ids' => $qcm_ids,
            'fields'     => 'ids',
        ] );

        // Step 3: walk up to the root ancestor for each term
        $root_ids = [];
        if ( ! is_wp_error( $assigned_term_ids ) ) {
            foreach ( $assigned_term_ids as $tid ) {
                $ancestors = get_ancestors( (int) $tid, 'mb_category' );
                $root_ids[] = empty( $ancestors ) ? (int) $tid : (int) end( $ancestors );
            }
        }
        $root_ids = array_unique( array_filter( $root_ids ) );

        $categories = ! empty( $root_ids )
            ? get_terms( [
                'taxonomy'   => 'mb_category',
                'hide_empty' => true,
                'include'    => $root_ids,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ] )
            : [];
    } else {
        $categories = [];
    }
} else {
    // No level selected — show all top-level categories
    $categories = get_terms( [
        'taxonomy'   => 'mb_category',
        'hide_empty' => true,
        'parent'     => 0,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ] );
}

$qcm_page_url = apply_filters( 'mb_qcm_list_page_url', get_permalink() );
?>

<div class="mb-course-wrap">

  <?php if ( $level_term ) : ?>
    <div class="mb-breadcrumb">
      <a href="<?php echo esc_url( remove_query_arg( [ 'mb_level', 'mb_cat' ] ) ); ?>">
        <?php esc_html_e( '← Niveaux', MB_TEXT_DOMAIN ); ?>
      </a>
      <span><?php echo esc_html( $level_term->name ); ?></span>
    </div>
  <?php endif; ?>

  <div class="mb-course-header">
    <?php if ( $level_term ) : ?>
      <h2 class="mb-course-title"><?php echo esc_html( $level_term->name ); ?></h2>
    <?php else : ?>
      <h2 class="mb-course-title"><?php esc_html_e( 'Choisissez un cours', MB_TEXT_DOMAIN ); ?></h2>
    <?php endif; ?>
    <p class="mb-course-sub"><?php esc_html_e( 'Sélectionnez un chapitre pour accéder aux QCMs.', MB_TEXT_DOMAIN ); ?></p>
  </div>

  <?php if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) : ?>

    <?php foreach ( $categories as $cat ) : ?>
      <?php
      $sub_cats = get_terms( [
          'taxonomy'   => 'mb_category',
          'hide_empty' => true,
          'parent'     => $cat->term_id,
      ] );
      ?>

      <div class="mb-category-block">
        <div class="mb-category-header">
          <?php echo esc_html( $cat->name ); ?>
        </div>
        <div class="mb-category-content">

          <?php if ( ! empty( $sub_cats ) && ! is_wp_error( $sub_cats ) ) : ?>
            <?php foreach ( $sub_cats as $sub ) : ?>
              <a class="mb-category-item"
                 href="<?php echo esc_url( add_query_arg( [
                     'mb_level' => $level_slug,
                     'mb_cat'   => $sub->slug,
                 ], $qcm_page_url ) ); ?>">
                <?php echo esc_html( $sub->name ); ?>
                <span class="mb-cat-count">
                  <?php echo esc_html( $sub->count ); ?> QCM<?php echo $sub->count > 1 ? 's' : ''; ?>
                </span>
              </a>
            <?php endforeach; ?>
          <?php else : ?>
            <a class="mb-category-item"
               href="<?php echo esc_url( add_query_arg( [
                   'mb_level' => $level_slug,
                   'mb_cat'   => $cat->slug,
               ], $qcm_page_url ) ); ?>">
              <?php echo esc_html( $cat->name ); ?>
              <span class="mb-cat-count">
                <?php echo esc_html( $cat->count ); ?> QCM<?php echo $cat->count > 1 ? 's' : ''; ?>
              </span>
            </a>
          <?php endif; ?>

        </div>
      </div>
    <?php endforeach; ?>

  <?php else : ?>
    <div class="mb-empty">
      <?php esc_html_e( 'Aucun cours disponible pour ce niveau.', MB_TEXT_DOMAIN ); ?>
    </div>
  <?php endif; ?>

</div>
