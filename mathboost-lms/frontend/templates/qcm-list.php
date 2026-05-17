<?php defined( 'ABSPATH' ) || exit; ?>

<?php
$cat_slug  = ! empty( $atts['category'] )
    ? sanitize_text_field( $atts['category'] )
    : ( isset( $_GET['mb_cat'] ) ? sanitize_text_field( wp_unslash( $_GET['mb_cat'] ) ) : '' );

$level_slug = ! empty( $atts['level'] )
    ? sanitize_text_field( $atts['level'] )
    : ( isset( $_GET['mb_level'] ) ? sanitize_text_field( wp_unslash( $_GET['mb_level'] ) ) : '' );

$cat_term = $cat_slug ? get_term_by( 'slug', $cat_slug, 'mb_category' ) : null;

if ( ! $cat_term ) {
    // Show category overview
    $cats = get_terms( [
        'taxonomy'   => 'mb_category',
        'hide_empty' => true,
        'parent'     => 0,
    ] );
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

$qcm_items    = MB_Access::get_qcm_list_with_access( $cat_term->term_id );
$is_logged_in = is_user_logged_in();
$is_premium   = MB_Access::current_user_is_premium();

$upgrade_url = apply_filters( 'mb_upgrade_url', '#upgrade' );
?>

<div class="mb-qcm-list-wrap">

  <div class="mb-breadcrumb">
    <a href="<?php echo esc_url( remove_query_arg( 'mb_cat' ) ); ?>">
      ← <?php esc_html_e( 'Cours', MB_TEXT_DOMAIN ); ?>
    </a>
    <span><?php echo esc_html( $cat_term->name ); ?></span>
  </div>

  <?php /* Category header */ ?>
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
        /** @var WP_Post $post */
        $post      = $item['post'];
        $is_locked = $item['is_locked'];
        $subtitle  = get_post_meta( $post->ID, '_mb_subtitle', true );
        $q_count   = count( json_decode( get_post_meta( $post->ID, '_mb_questions', true ) ?: '[]', true ) );
      ?>

        <div class="mb-qcm-item <?php echo $is_locked ? 'is-locked' : ''; ?>">

          <div class="mb-qcm-item-left">
            <span class="mb-qcm-num">QCM <?php echo esc_html( $i + 1 ); ?></span>
            <div class="mb-qcm-item-info">
              <span class="mb-qcm-item-title"><?php echo esc_html( $post->post_title ); ?></span>
              <?php if ( $subtitle ) : ?>
                <span class="mb-qcm-item-sub"><?php echo esc_html( $subtitle ); ?></span>
              <?php endif; ?>
              <?php if ( $q_count ) : ?>
                <span class="mb-qcm-item-meta">
                  📝 <?php echo esc_html( $q_count ); ?> <?php esc_html_e( 'questions', MB_TEXT_DOMAIN ); ?>
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
            <?php elseif ( ! $is_logged_in ) :
                $start_url = esc_url( add_query_arg( [ 'mb_cat' => $cat_slug, 'mb_qid' => $post->ID ], get_permalink() ) );
              ?>
              <a class="mb-btn mb-btn-login" href="<?php echo esc_url( wp_login_url( $start_url ) ); ?>">
                <?php esc_html_e( 'Se connecter', MB_TEXT_DOMAIN ); ?>
              </a>
            <?php else :
                $start_url = esc_url( add_query_arg( [ 'mb_cat' => $cat_slug, 'mb_qid' => $post->ID ], get_permalink() ) );
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
                  esc_html__( 'Les %d derniers QCMs de chaque liste sont réservés aux membres premium. Débloquez un accès complet pour %s€.', MB_TEXT_DOMAIN ),
                  (int) get_option( 'mb_free_locked_count', 3 ),
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
