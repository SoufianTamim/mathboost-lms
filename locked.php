<?php defined( 'ABSPATH' ) || exit; ?>

<?php
$upgrade_url = apply_filters( 'mb_upgrade_url', '#upgrade' );
$price       = get_option( 'mb_price', '15' );
$currency    = get_option( 'mb_currency', 'EUR' );
$symbol      = $currency === 'EUR' ? '€' : '$';
?>

<div class="mb-locked-wrap">
  <div class="mb-locked-icon">🔒</div>
  <h2 class="mb-locked-title"><?php esc_html_e( 'Contenu Premium', MB_TEXT_DOMAIN ); ?></h2>
  <p class="mb-locked-desc">
    <?php esc_html_e( 'Ce QCM est réservé aux membres premium. Débloquez l\'accès complet pour accéder à tous les QCMs, corrections détaillées et bien plus.', MB_TEXT_DOMAIN ); ?>
  </p>

  <div class="mb-locked-features">
    <div class="mb-locked-feature">✅ <?php esc_html_e( 'Accès à tous les QCMs', MB_TEXT_DOMAIN ); ?></div>
    <div class="mb-locked-feature">✅ <?php esc_html_e( 'Corrections détaillées', MB_TEXT_DOMAIN ); ?></div>
    <div class="mb-locked-feature">✅ <?php esc_html_e( 'Tous les niveaux', MB_TEXT_DOMAIN ); ?></div>
    <div class="mb-locked-feature">✅ <?php esc_html_e( 'Accès illimité', MB_TEXT_DOMAIN ); ?></div>
  </div>

  <div class="mb-locked-price">
    <span class="mb-price-tag">
      <?php echo esc_html( $price . $symbol ); ?>
      <small><?php esc_html_e( 'accès complet', MB_TEXT_DOMAIN ); ?></small>
    </span>
  </div>

  <div class="mb-locked-actions">
    <a class="mb-btn mb-btn-upgrade mb-btn-large" href="<?php echo esc_url( $upgrade_url ); ?>">
      🚀 <?php esc_html_e( 'Débloquer l\'accès Premium', MB_TEXT_DOMAIN ); ?>
    </a>
    <?php if ( ! is_user_logged_in() ) : ?>
      <a class="mb-btn mb-btn-outline" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">
        <?php esc_html_e( 'Se connecter', MB_TEXT_DOMAIN ); ?>
      </a>
    <?php endif; ?>
  </div>

  <?php if ( is_user_logged_in() ) : ?>
    <p class="mb-locked-code-hint">
      <?php esc_html_e( 'Vous avez un code d\'activation ?', MB_TEXT_DOMAIN ); ?>
      <a href="#activation-form"><?php esc_html_e( 'Entrer votre code ici', MB_TEXT_DOMAIN ); ?></a>
    </p>
    <div id="activation-form">
      <?php echo do_shortcode( '[mathboost_activation_form]' ); ?>
    </div>
  <?php endif; ?>
</div>
