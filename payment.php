<?php defined( 'ABSPATH' ) || exit; ?>

<?php
$price     = get_option( 'mb_price', '15' );
$currency  = get_option( 'mb_currency', 'EUR' );
$symbol    = $currency === 'EUR' ? '€' : '$';
$client_id = get_option( 'mb_paypal_client_id', '' );
$is_logged = is_user_logged_in();
$is_prem   = $is_logged && MB_Access::current_user_is_premium();
?>

<div class="mb-payment-wrap">

  <?php if ( $is_prem ) : ?>

    <div class="mb-payment-success-card">
      <div class="mb-payment-success-icon">⭐</div>
      <h3><?php esc_html_e( 'Vous êtes Premium !', MB_TEXT_DOMAIN ); ?></h3>
      <p><?php esc_html_e( 'Vous avez accès à tous les QCMs et contenus de MathBoost.', MB_TEXT_DOMAIN ); ?></p>
    </div>

  <?php else : ?>

    <div class="mb-payment-card">

      <div class="mb-payment-header">
        <div class="mb-payment-badge">⭐ Premium</div>
        <h2 class="mb-payment-title"><?php esc_html_e( 'Accès Premium MathBoost', MB_TEXT_DOMAIN ); ?></h2>
        <div class="mb-payment-price">
          <?php echo esc_html( $price ); ?><span class="mb-price-unit"><?php echo esc_html( $symbol ); ?></span>
        </div>
        <p class="mb-payment-desc"><?php esc_html_e( 'Paiement unique — Accès complet', MB_TEXT_DOMAIN ); ?></p>
      </div>

      <div class="mb-payment-features">
        <div class="mb-pay-feature">✅ <?php esc_html_e( 'Tous les QCMs débloqués', MB_TEXT_DOMAIN ); ?></div>
        <div class="mb-pay-feature">✅ <?php esc_html_e( 'Corrections complètes avec MathJax', MB_TEXT_DOMAIN ); ?></div>
        <div class="mb-pay-feature">✅ <?php esc_html_e( 'Tous les niveaux (Primaire → Prépa)', MB_TEXT_DOMAIN ); ?></div>
        <div class="mb-pay-feature">✅ <?php esc_html_e( 'Accès illimité', MB_TEXT_DOMAIN ); ?></div>
        <div class="mb-pay-feature">✅ <?php esc_html_e( 'Responsive mobile', MB_TEXT_DOMAIN ); ?></div>
      </div>

      <?php if ( ! $is_logged ) : ?>

        <div class="mb-payment-login-note">
          <?php esc_html_e( 'Connectez-vous ou créez un compte pour finaliser votre achat.', MB_TEXT_DOMAIN ); ?>
        </div>
        <a class="mb-btn mb-btn-upgrade mb-btn-large" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">
          <?php esc_html_e( 'Se connecter pour continuer', MB_TEXT_DOMAIN ); ?>
        </a>

      <?php elseif ( $client_id ) : ?>

        <div id="mb-paypal-container"></div>
        <div id="mb-paypal-status" style="display:none;" class="mb-activation-msg"></div>

        <script>
        window.MB_PAYPAL = {
          price   : '<?php echo esc_js( $price ); ?>',
          currency: '<?php echo esc_js( $currency ); ?>',
          ajaxUrl : '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
          nonce   : '<?php echo esc_js( wp_create_nonce( 'mb_paypal_nonce' ) ); ?>',
          redirect: '<?php echo esc_js( get_permalink() ); ?>'
        };
        </script>

      <?php else : ?>

        <div class="mb-notice mb-notice-info">
          <?php esc_html_e( 'Le paiement en ligne n\'est pas encore configuré. Veuillez contacter l\'administrateur.', MB_TEXT_DOMAIN ); ?>
          <br><br>
          <?php echo do_shortcode( '[mathboost_activation_form]' ); ?>
        </div>

      <?php endif; ?>

    </div>

  <?php endif; ?>

</div>
