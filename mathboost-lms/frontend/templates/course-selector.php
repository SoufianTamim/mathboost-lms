<?php defined( 'ABSPATH' ) || exit; ?>

<?php
$level_slug = ! empty( $atts['level'] )
    ? sanitize_text_field( $atts['level'] )
    : ( isset( $_GET['mb_level'] ) ? sanitize_text_field( wp_unslash( $_GET['mb_level'] ) ) : '' );

$filter_cat  = ! empty( $atts['category'] )
    ? sanitize_text_field( $atts['category'] )
    : ( isset( $_GET['mb_cat'] ) ? sanitize_text_field( wp_unslash( $_GET['mb_cat'] ) ) : '' );

$course_page_url = apply_filters( 'mb_course_page_url', get_permalink() );
$upgrade_url     = apply_filters( 'mb_upgrade_url', '#upgrade' );
$is_logged_in    = is_user_logged_in();
$is_premium      = MB_Access::current_user_is_premium();

if ( ! MB_Migrator::is_done() ) {
    echo '<div class="mb-empty">' . esc_html__( 'Migration requise — veuillez migrer les données depuis l\'administration.', MB_TEXT_DOMAIN ) . '</div>';
    return;
}

$level_term = $level_slug ? MB_Level_Repository::get_by_slug( $level_slug ) : null;

if ( ! $level_term ) {
    echo '<div class="mb-empty">' . esc_html__( 'Niveau introuvable.', MB_TEXT_DOMAIN ) . '</div>';
    return;
}

// Resolve optional category filter
$filter_term = $filter_cat ? MB_Category_Repository::get_by_slug( $filter_cat ) : null;

// Fetch QCMs grouped by category for this level
$cat_groups = MB_QCM_Repository::get_by_level_grouped_by_category( (int) $level_term->id );

// Count total QCMs
$total_qcms = 0;
foreach ( $cat_groups as $group ) {
    $total_qcms += count( $group['qcms'] );
}

// Apply category filter if set
if ( $filter_term && isset( $cat_groups[ (int) $filter_term->id ] ) ) {
    $cat_groups = [ (int) $filter_term->id => $cat_groups[ (int) $filter_term->id ] ];
}
?>

<div class="mb-course-wrap">

  <!-- ── Breadcrumb ──────────────────────────────────────────────────── -->
  <div class="mb-breadcrumb">
    <a href="<?php echo esc_url( remove_query_arg( [ 'mb_level', 'mb_cat', 'mb_qid' ] ) ); ?>">
      ← <?php esc_html_e( 'Niveaux', MB_TEXT_DOMAIN ); ?>
    </a>
    <span><?php echo esc_html( $level_term->name ); ?></span>
    <?php if ( $filter_term ) : ?>
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
        <?php printf(
            esc_html( _n( '%d QCM disponible', '%d QCMs disponibles', $total_qcms, MB_TEXT_DOMAIN ) ),
            $total_qcms
        ); ?>
      </p>
    </div>
    <?php if ( $is_premium ) : ?>
      <span class="mb-premium-pill">⭐ <?php esc_html_e( 'Premium', MB_TEXT_DOMAIN ); ?></span>
    <?php endif; ?>
  </div>

  <?php if ( empty( $cat_groups ) ) : ?>
    <div class="mb-empty">
      <?php esc_html_e( 'Aucun QCM disponible pour ce niveau.', MB_TEXT_DOMAIN ); ?>
    </div>
  <?php else : ?>

    <!-- ── QCMs grouped by category ─────────────────────────────────── -->
    <?php foreach ( $cat_groups as $cat_id => $group ) :
      $items = $group['qcms'];
    ?>
      <div class="mb-qcm-section">

        <?php if ( $group['category'] ) : ?>
          <div class="mb-section-heading">
            <span class="mb-section-heading-name"><?php echo esc_html( $group['category']->name ); ?></span>
            <span class="mb-section-count"><?php echo count( $items ); ?> QCM<?php echo count( $items ) > 1 ? 's' : ''; ?></span>
          </div>
        <?php endif; ?>

        <div class="mb-qcm-items">
          <?php foreach ( $items as $idx => $qcm ) :
            $is_locked = false;
            if ( ! $is_premium && ! current_user_can( 'manage_options' ) ) {
                $is_locked = (bool) $qcm->is_locked;
            }
            $start_url = esc_url( add_query_arg( [
                'mb_level' => $level_slug,
                'mb_qid'   => $qcm->id,
            ], $course_page_url ) );
            $q_count = count( json_decode( $qcm->questions ?: '[]', true ) ?? [] );
          ?>

            <div class="mb-qcm-item <?php echo $is_locked ? 'is-locked' : ''; ?>">

              <div class="mb-qcm-item-left">
                <span class="mb-qcm-num"><?php echo $is_locked ? '🔒' : ( $idx + 1 ); ?></span>
                <div class="mb-qcm-item-info">
                  <span class="mb-qcm-item-title"><?php echo esc_html( $qcm->title ); ?></span>
                  <?php if ( $qcm->subtitle ) : ?>
                    <span class="mb-qcm-item-sub"><?php echo esc_html( $qcm->subtitle ); ?></span>
                  <?php endif; ?>
                  <?php if ( $q_count ) : ?>
                    <span class="mb-qcm-item-meta">
                      📝 <?php echo $q_count; ?> <?php esc_html_e( 'questions', MB_TEXT_DOMAIN ); ?>
                    </span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="mb-qcm-item-right">
                <?php if ( $is_locked ) : ?>
                  <a class="mb-btn mb-btn-upgrade" href="<?php echo esc_url( $upgrade_url ); ?>">
                    🔓 <?php esc_html_e( 'Débloquer', MB_TEXT_DOMAIN ); ?>
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
