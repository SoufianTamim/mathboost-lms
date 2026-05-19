<?php defined( 'ABSPATH' ) || exit; ?>

<?php
$cat_slug = ! empty( $atts['category'] )
    ? sanitize_text_field( $atts['category'] )
    : ( isset( $_GET['mb_cat'] ) ? sanitize_text_field( wp_unslash( $_GET['mb_cat'] ) ) : '' );

if ( ! MB_Migrator::is_done() ) {
    echo '<div class="mb-empty">' . esc_html__( 'Migration requise — veuillez migrer les données depuis l\'administration.', MB_TEXT_DOMAIN ) . '</div>';
    return;
}

$cat_term = $cat_slug ? MB_Category_Repository::get_by_slug( $cat_slug ) : null;

if ( ! $cat_term ) {
    // Show all categories
    $cats        = MB_Category_Repository::get_all();
    $upgrade_url = apply_filters( 'mb_upgrade_url', '#upgrade' );
    ?>
    <div class="mb-qcm-list-wrap">
      <h2 class="mb-list-title"><?php esc_html_e( 'Catégories', MB_TEXT_DOMAIN ); ?></h2>
      <div class="mb-list-grid">
        <?php foreach ( $cats as $c ) : ?>
          <a class="mb-list-cat-card" href="<?php echo esc_url( add_query_arg( 'mb_cat', $c->slug ) ); ?>">
            <?php echo esc_html( $c->name ); ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php
    return;
}

$qcm_items    = MB_Access::get_qcm_list_with_access( (int) $cat_term->id );
$is_logged_in = is_user_logged_in();
$is_premium   = MB_Access::current_user_is_premium();
$upgrade_url  = apply_filters( 'mb_upgrade_url', '#upgrade' );
?>

<div class="mb-qcm-list-wrap">

  <div class="mb-breadcrumb">
    <a href="<?php echo esc_url( remove_query_arg( 'mb_cat' ) ); ?>">
      ← <?php esc_html_e( 'Cours', MB_TEXT_DOMAIN ); ?>
    </a>
    <span><?php echo esc_html( $cat_term->name ); ?></span>
  </div>

  <div class="mb-qcm-list-header">
    <div class="mb-qcm-list-header-inner">
      <h2><?php echo esc_html( $cat_term->name ); ?></h2>
      <?php if ( $cat_term->description ) : ?>
        <p><?php echo esc_html( $cat_term->description ); ?></p>
      <?php endif; ?>
    </div>
    <div class="mb-premium-badge <?php echo $is_premium ? 'is-premium' : ''; ?>">
      <?php if ( $is_premium ) : ?>
        ⭐ <?php esc_html_e( 'Premium', MB_TEXT_DOMAIN ); ?>
      <?php else : ?>
        🔒 <?php esc_html_e( 'Compte gratuit', MB_TEXT_DOMAIN ); ?>
      <?php endif; ?>
    </div>
  </div>

  <?php if ( empty( $qcm_items ) ) : ?>
    <div class="mb-empty">
      <?php esc_html_e( 'Aucun QCM disponible dans cette catégorie.', MB_TEXT_DOMAIN ); ?>
    </div>
  <?php else : ?>

    <div class="mb-qcm-items">
      <?php foreach ( $qcm_items as $i => $item ) :
        $qcm       = $item['qcm'];
        $is_locked = $item['is_locked'];
        $q_count   = count( json_decode( $qcm->questions ?: '[]', true ) ?? [] );
      ?>

        <div class="mb-qcm-item <?php echo $is_locked ? 'is-locked' : ''; ?>">

          <div class="mb-qcm-item-left">
            <span class="mb-qcm-num">QCM <?php echo esc_html( $i + 1 ); ?></span>
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
              <div class="mb-lock-icon" title="<?php esc_attr_e( 'Accès premium requis', MB_TEXT_DOMAIN ); ?>">🔒</div>
              <a class="mb-btn mb-btn-upgrade" href="<?php echo esc_url( $upgrade_url ); ?>">
                <?php esc_html_e( 'Débloquer', MB_TEXT_DOMAIN ); ?>
              </a>
            <?php else :
                $start_url = esc_url( add_query_arg( [ 'mb_cat' => $cat_slug, 'mb_qid' => $qcm->id ], get_permalink() ) );
              ?>
              <a class="mb-btn mb-btn-start" href="<?php echo $start_url; ?>">
                <?php esc_html_e( 'Commencer', MB_TEXT_DOMAIN ); ?> →
              </a>
            <?php endif; ?>
          </div>

        </div>

      <?php endforeach; ?>
    </div>

    <?php if ( ! $is_premium ) : ?>
      <div class="mb-upgrade-cta">
        <div class="mb-upgrade-cta-inner">
          <div class="mb-upgrade-icon">🔓</div>
          <div class="mb-upgrade-text">
            <strong><?php esc_html_e( 'Débloquez tous les QCMs Premium', MB_TEXT_DOMAIN ); ?></strong>
            <p>
              <?php printf(
                  esc_html__( 'Les QCMs premium sont réservés aux membres. Débloquez un accès complet pour %s€.', MB_TEXT_DOMAIN ),
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
