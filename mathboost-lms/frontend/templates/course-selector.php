<?php defined( 'ABSPATH' ) || exit; ?>

<?php
$level_slug  = ! empty( $atts['level'] )
    ? sanitize_text_field( $atts['level'] )
    : ( isset( $_GET['mb_level'] ) ? sanitize_text_field( wp_unslash( $_GET['mb_level'] ) ) : '' );

$filter_cat  = ! empty( $atts['category'] )
    ? sanitize_text_field( $atts['category'] )
    : ( isset( $_GET['mb_cat'] ) ? sanitize_text_field( wp_unslash( $_GET['mb_cat'] ) ) : '' );

$level_term      = $level_slug ? get_term_by( 'slug', $level_slug, 'mb_level' ) : null;
$course_page_url = apply_filters( 'mb_course_page_url', get_permalink() );
$upgrade_url     = apply_filters( 'mb_upgrade_url', '#upgrade' );
$is_logged_in    = is_user_logged_in();
$is_premium      = MB_Access::current_user_is_premium();

if ( ! $level_term ) {
    echo '<div class="mb-empty">' . esc_html__( 'Niveau introuvable.', MB_TEXT_DOMAIN ) . '</div>';
    return;
}

// ── Fetch all published QCMs for this level ─────────────────────────────
$qcm_args = [
    'post_type'      => 'mb_qcm',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'orderby'        => 'menu_order title',
    'order'          => 'ASC',
    'tax_query'      => [ [
        'taxonomy' => 'mb_level',
        'field'    => 'term_id',
        'terms'    => $level_term->term_id,
    ] ],
];

// If a category filter is active, add it
if ( $filter_cat ) {
    $filter_term = get_term_by( 'slug', $filter_cat, 'mb_category' );
    if ( $filter_term ) {
        $qcm_args['tax_query'][] = [
            'taxonomy' => 'mb_category',
            'field'    => 'term_id',
            'terms'    => $filter_term->term_id,
            'include_children' => true,
        ];
    }
}

$all_qcms = get_posts( $qcm_args );

// ── Group QCMs by their mb_category ────────────────────────────────────
$cat_groups = []; // [ cat_id => [ 'term' => WP_Term|null, 'qcms' => [...] ] ]

foreach ( $all_qcms as $qcm_post ) {
    $cats = wp_get_post_terms( $qcm_post->ID, 'mb_category', [
        'orderby' => 'parent',
        'order'   => 'ASC',
    ] );

    if ( empty( $cats ) || is_wp_error( $cats ) ) {
        $cat_id = 0;
        if ( ! isset( $cat_groups[0] ) ) {
            $cat_groups[0] = [ 'term' => null, 'qcms' => [] ];
        }
    } else {
        // Use the deepest (most specific) category
        $cat    = end( $cats );
        $cat_id = (int) $cat->term_id;
        if ( ! isset( $cat_groups[ $cat_id ] ) ) {
            $cat_groups[ $cat_id ] = [ 'term' => $cat, 'qcms' => [] ];
        }
    }

    // Check access
    $is_locked = false;
    if ( ! $is_premium && ! current_user_can( 'manage_options' ) ) {
        $is_locked = MB_Access::is_qcm_locked( $qcm_post->ID );
    }

    $q_json   = get_post_meta( $qcm_post->ID, '_mb_questions', true );
    $q_count  = count( json_decode( $q_json ?: '[]', true ) );
    $subtitle = get_post_meta( $qcm_post->ID, '_mb_subtitle', true );

    $cat_groups[ $cat_id ]['qcms'][] = [
        'post'      => $qcm_post,
        'is_locked' => $is_locked,
        'q_count'   => $q_count,
        'subtitle'  => $subtitle,
    ];
}

// Sort: named categories alphabetically, "uncategorised" last
uksort( $cat_groups, function ( $a, $b ) use ( $cat_groups ) {
    if ( $a === 0 ) return 1;
    if ( $b === 0 ) return -1;
    return strcmp( $cat_groups[ $a ]['term']->name, $cat_groups[ $b ]['term']->name );
} );
?>

<div class="mb-course-wrap">

  <!-- ── Breadcrumb ──────────────────────────────────────────────────── -->
  <div class="mb-breadcrumb">
    <a href="<?php echo esc_url( remove_query_arg( [ 'mb_level', 'mb_cat', 'mb_qcm' ] ) ); ?>">
      ← <?php esc_html_e( 'Niveaux', MB_TEXT_DOMAIN ); ?>
    </a>
    <span><?php echo esc_html( $level_term->name ); ?></span>
    <?php if ( $filter_cat && isset( $filter_term ) && $filter_term ) : ?>
      <a href="<?php echo esc_url( remove_query_arg( 'mb_cat' ) ); ?>">
        <?php echo esc_html( $level_term->name ); ?>
      </a>
      <span><?php echo esc_html( $filter_term->name ); ?></span>
    <?php endif; ?>
  </div>

  <!-- ── Page header ────────────────────────────────────────────────── -->
  <div class="mb-course-header">
    <div class="mb-course-header-inner">
      <h2 class="mb-course-title"><?php echo esc_html( $level_term->name ); ?></h2>
      <p class="mb-course-sub">
        <?php
        $total_qcms = count( $all_qcms );
        printf(
            /* translators: %d number of QCMs */
            esc_html( _n( '%d QCM disponible', '%d QCMs disponibles', $total_qcms, MB_TEXT_DOMAIN ) ),
            $total_qcms
        );
        ?>
      </p>
    </div>
    <?php if ( $is_premium ) : ?>
      <span class="mb-premium-pill">⭐ <?php esc_html_e( 'Premium', MB_TEXT_DOMAIN ); ?></span>
    <?php endif; ?>
  </div>

  <?php if ( empty( $all_qcms ) ) : ?>
    <div class="mb-empty">
      <?php esc_html_e( 'Aucun QCM disponible pour ce niveau.', MB_TEXT_DOMAIN ); ?>
    </div>
  <?php else : ?>

    <!-- ── QCMs grouped by category ─────────────────────────────────── -->
    <?php foreach ( $cat_groups as $cat_id => $group ) :
      $items      = $group['qcms'];
      $free_count = 0;
      foreach ( $items as $it ) { if ( ! $it['is_locked'] ) $free_count++; }
    ?>
      <div class="mb-qcm-section">

        <?php if ( $group['term'] ) : ?>
          <div class="mb-section-heading">
            <span class="mb-section-heading-name"><?php echo esc_html( $group['term']->name ); ?></span>
            <span class="mb-section-count"><?php echo count( $items ); ?> QCM<?php echo count( $items ) > 1 ? 's' : ''; ?></span>
          </div>
        <?php endif; ?>

        <div class="mb-qcm-items">
          <?php foreach ( $items as $idx => $item ) :
            $post      = $item['post'];
            $is_locked = $item['is_locked'];
            $start_url = esc_url( add_query_arg( [
                'mb_level' => $level_slug,
                'mb_qid'   => $post->ID,
            ], $course_page_url ) );
          ?>

            <div class="mb-qcm-item <?php echo $is_locked ? 'is-locked' : ''; ?>">

              <div class="mb-qcm-item-left">
                <span class="mb-qcm-num">
                  <?php echo $is_locked ? '🔒' : ( $idx + 1 ); ?>
                </span>
                <div class="mb-qcm-item-info">
                  <span class="mb-qcm-item-title"><?php echo esc_html( $post->post_title ); ?></span>
                  <?php if ( $item['subtitle'] ) : ?>
                    <span class="mb-qcm-item-sub"><?php echo esc_html( $item['subtitle'] ); ?></span>
                  <?php endif; ?>
                  <?php if ( $item['q_count'] ) : ?>
                    <span class="mb-qcm-item-meta">
                      📝 <?php echo (int) $item['q_count']; ?> <?php esc_html_e( 'questions', MB_TEXT_DOMAIN ); ?>
                    </span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="mb-qcm-item-right">
                <?php if ( $is_locked ) : ?>
                  <a class="mb-btn mb-btn-upgrade" href="<?php echo esc_url( $upgrade_url ); ?>">
                    🔓 <?php esc_html_e( 'Débloquer', MB_TEXT_DOMAIN ); ?>
                  </a>
                <?php elseif ( ! $is_logged_in ) : ?>
                  <a class="mb-btn mb-btn-login" href="<?php echo esc_url( wp_login_url( $start_url ) ); ?>">
                    <?php esc_html_e( 'Se connecter', MB_TEXT_DOMAIN ); ?>
                  </a>
                <?php else : ?>
                  <a class="mb-btn mb-btn-start" href="<?php echo $start_url; ?>">
                    <?php esc_html_e( 'Commencer', MB_TEXT_DOMAIN ); ?> →
                  </a>
                <?php endif; ?>
              </div>

            </div>

          <?php endforeach; ?>
        </div>

      </div>
    <?php endforeach; ?>

    <!-- ── Upgrade CTA ──────────────────────────────────────────────── -->
    <?php if ( ! $is_premium && ! current_user_can( 'manage_options' ) ) : ?>
      <div class="mb-upgrade-cta">
        <div class="mb-upgrade-cta-inner">
          <div class="mb-upgrade-icon">🔓</div>
          <div class="mb-upgrade-text">
            <strong><?php esc_html_e( 'Débloquez tous les QCMs Premium', MB_TEXT_DOMAIN ); ?></strong>
            <p>
              <?php printf(
                  esc_html__( 'Accès illimité à tous les QCMs pour %s€.', MB_TEXT_DOMAIN ),
                  esc_html( get_option( 'mb_price', '15' ) )
              ); ?>
            </p>
          </div>
          <a class="mb-btn mb-btn-upgrade mb-btn-large" href="<?php echo esc_url( $upgrade_url ); ?>">
            🚀 <?php esc_html_e( 'Passer Premium', MB_TEXT_DOMAIN ); ?>
          </a>
        </div>
      </div>
    <?php endif; ?>

  <?php endif; ?>

</div>
