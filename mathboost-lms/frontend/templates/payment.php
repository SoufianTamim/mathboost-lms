<?php defined( 'ABSPATH' ) || exit; ?>

<?php
$price      = get_option( 'mb_price', '15' );
$currency   = get_option( 'mb_currency', 'EUR' );
$symbol     = $currency === 'EUR' ? '€' : '$';
$client_id  = get_option( 'mb_paypal_client_id', '' );
$is_logged  = is_user_logged_in();
$is_prem    = $is_logged && MB_Access::current_user_is_premium();
$login_url  = get_option( 'mb_login_page_url', '' ) ?: wp_login_url( get_permalink() );
$reg_url    = get_option( 'mb_register_page_url', '' ) ?: wp_registration_url();
?>

<div class="mb-payment-wrap">

  <?php if ( $is_prem ) : ?>

    <!-- ── Already premium ────────────────────────────────────────────── -->
    <div class="mb-already-premium">
      <div class="mb-already-premium-icon">⭐</div>
      <h2><?php esc_html_e( 'Vous êtes déjà Premium !', MB_TEXT_DOMAIN ); ?></h2>
      <p><?php esc_html_e( 'Vous avez accès à l\'intégralité des QCMs et des corrections détaillées.', MB_TEXT_DOMAIN ); ?></p>
      <?php
        $resources_url = get_option( 'mb_resources_page_url', '' ) ?: home_url( '/ressources/' );
      ?>
      <a class="mb-btn mb-btn-primary mb-btn-large" href="<?php echo esc_url( $resources_url ); ?>">
        🚀 <?php esc_html_e( 'Accéder aux ressources', MB_TEXT_DOMAIN ); ?>
      </a>
    </div>

  <?php else : ?>

    <!-- ── Pricing hero ───────────────────────────────────────────────── -->
    <div class="mb-pay-hero">

      <div class="mb-pay-hero-badge">⭐ <?php esc_html_e( 'Accès Premium', MB_TEXT_DOMAIN ); ?></div>

      <h1 class="mb-pay-hero-title">
        <?php esc_html_e( 'Débloquez tous les QCMs MathBoost', MB_TEXT_DOMAIN ); ?>
      </h1>
      <p class="mb-pay-hero-sub">
        <?php esc_html_e( 'Un seul paiement. Un accès complet et illimité à toutes les ressources.', MB_TEXT_DOMAIN ); ?>
      </p>

      <div class="mb-pay-price-block">
        <span class="mb-pay-price-amount"><?php echo esc_html( $price . $symbol ); ?></span>
        <span class="mb-pay-price-label"><?php esc_html_e( 'paiement unique', MB_TEXT_DOMAIN ); ?></span>
      </div>

      <ul class="mb-pay-features">
        <li>✅ <?php esc_html_e( 'Tous les QCMs débloqués — sans exception', MB_TEXT_DOMAIN ); ?></li>
        <li>✅ <?php esc_html_e( 'Corrections complètes et détaillées avec MathJax', MB_TEXT_DOMAIN ); ?></li>
        <li>✅ <?php esc_html_e( 'Tous les niveaux : Primaire, Collège, Lycée, Prépa', MB_TEXT_DOMAIN ); ?></li>
        <li>✅ <?php esc_html_e( 'Accès illimité — aucun abonnement requis', MB_TEXT_DOMAIN ); ?></li>
        <li>✅ <?php esc_html_e( 'Code d\'activation envoyé immédiatement par email', MB_TEXT_DOMAIN ); ?></li>
      </ul>

    </div>

    <!-- ── Payment action zone ────────────────────────────────────────── -->
    <div class="mb-pay-action-zone">

      <?php if ( ! $is_logged ) : ?>

        <!-- Guest: show CTA to create account first -->
        <div class="mb-pay-guest-block">
          <p class="mb-pay-guest-label">
            🔐 <?php esc_html_e( 'Créez un compte gratuit pour finaliser votre achat.', MB_TEXT_DOMAIN ); ?>
          </p>
          <a class="mb-btn mb-btn-primary mb-btn-large" href="<?php echo esc_url( $reg_url ); ?>">
            🚀 <?php esc_html_e( 'Créer mon compte — c\'est gratuit', MB_TEXT_DOMAIN ); ?>
          </a>
          <p class="mb-pay-guest-login">
            <?php esc_html_e( 'Déjà inscrit ?', MB_TEXT_DOMAIN ); ?>
            <a href="<?php echo esc_url( add_query_arg( 'redirect_to', rawurlencode( get_permalink() ), $login_url ) ); ?>">
              <?php esc_html_e( 'Se connecter', MB_TEXT_DOMAIN ); ?>
            </a>
          </p>
        </div>

      <?php elseif ( $client_id ) : ?>

        <!-- Logged in + PayPal configured -->
        <div class="mb-pay-cta-label">
          💳 <?php printf(
            esc_html__( 'Payer %s%s maintenant — accès instantané', MB_TEXT_DOMAIN ),
            esc_html( $price ),
            esc_html( $symbol )
          ); ?>
        </div>

        <div id="mb-paypal-loading" class="mb-paypal-loading">
          <div class="mb-paypal-loading-dots">
            <span></span><span></span><span></span>
          </div>
          <p><?php esc_html_e( 'Chargement du paiement sécurisé…', MB_TEXT_DOMAIN ); ?></p>
        </div>

        <div id="mb-paypal-container"></div>
        <div id="mb-paypal-status" style="display:none;" class="mb-activation-msg"></div>

        <p class="mb-pay-security-note">
          🔒 <?php esc_html_e( 'Paiement 100 % sécurisé via PayPal. Vous recevrez votre code par email immédiatement.', MB_TEXT_DOMAIN ); ?>
        </p>

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

        <!-- PayPal not yet configured — show only to admin, show activation form to users -->
        <?php if ( current_user_can( 'manage_options' ) ) : ?>
          <div class="mb-notice mb-notice-warning">
            ⚙️ <?php esc_html_e( 'PayPal n\'est pas encore configuré. Ajoutez votre Client ID dans les réglages MathBoost.', MB_TEXT_DOMAIN ); ?>
          </div>
        <?php else : ?>
          <div class="mb-pay-no-paypal">
            <p><?php esc_html_e( 'Le paiement en ligne n\'est pas encore disponible. Si vous avez un code d\'activation, vous pouvez l\'utiliser ci-dessous.', MB_TEXT_DOMAIN ); ?></p>
          </div>
        <?php endif; ?>

        <div class="mb-pay-activation-alt">
          <p class="mb-pay-activation-label">
            🎟️ <?php esc_html_e( 'Vous avez déjà un code d\'activation ?', MB_TEXT_DOMAIN ); ?>
          </p>
          <?php echo do_shortcode( '[mathboost_activation_form]' ); ?>
        </div>

      <?php endif; ?>

    </div>

  <?php endif; ?>

</div>
