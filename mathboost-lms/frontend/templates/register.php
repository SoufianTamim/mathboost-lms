<?php defined( 'ABSPATH' ) || exit; ?>

<?php
// Already logged in
if ( is_user_logged_in() ) {
    $user       = wp_get_current_user();
    $login_url  = get_option( 'mb_login_page_url', '' ) ?: wp_login_url();
    $logout_url = wp_logout_url( $login_url );
    ?>
    <div class="mb-login-wrap">
      <div class="mb-login-card">
        <div class="mb-login-panel is-active">
          <div class="mb-login-header">
            <div class="mb-login-icon">👤</div>
            <h2 class="mb-login-title"><?php esc_html_e( 'Déjà connecté', MB_TEXT_DOMAIN ); ?></h2>
            <p class="mb-login-desc">
              <?php printf( esc_html__( 'Bienvenue, %s !', MB_TEXT_DOMAIN ), esc_html( $user->display_name ) ); ?>
            </p>
          </div>
          <?php if ( MB_Access::current_user_is_premium() ) : ?>
            <div class="mb-login-premium-badge">⭐ <?php esc_html_e( 'Compte Premium', MB_TEXT_DOMAIN ); ?></div>
          <?php endif; ?>
          <div class="mb-login-actions" style="margin-top:20px;">
            <?php if ( current_user_can( 'manage_options' ) ) : ?>
              <a class="mb-btn mb-btn-start" href="<?php echo esc_url( admin_url() ); ?>">
                ⚙️ <?php esc_html_e( 'Tableau de bord', MB_TEXT_DOMAIN ); ?>
              </a>
            <?php else : ?>
              <a class="mb-btn mb-btn-start" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                ← <?php esc_html_e( 'Retour au site', MB_TEXT_DOMAIN ); ?>
              </a>
            <?php endif; ?>
            <a class="mb-btn mb-btn-outline" href="<?php echo esc_url( $logout_url ); ?>">
              <?php esc_html_e( 'Se déconnecter', MB_TEXT_DOMAIN ); ?>
            </a>
          </div>
        </div>
      </div>
    </div>
    <?php
    return;
}

$login_url        = get_option( 'mb_login_page_url', '' ) ?: wp_login_url();
$redirect_to      = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';
$error_msg        = isset( $_GET['mb_login_error'] )   ? sanitize_text_field( urldecode( wp_unslash( $_GET['mb_login_error'] ) ) )   : '';
$success_msg      = isset( $_GET['mb_login_success'] ) ? sanitize_text_field( urldecode( wp_unslash( $_GET['mb_login_success'] ) ) ) : '';
$register_allowed = (bool) get_option( 'mb_allow_register', 1 );
?>

<div class="mb-login-wrap">
  <div class="mb-login-card">

    <div class="mb-login-panel is-active">

      <div class="mb-login-header">
        <div class="mb-login-icon">✨</div>
        <h2 class="mb-login-title"><?php esc_html_e( 'Créer un compte', MB_TEXT_DOMAIN ); ?></h2>
        <p class="mb-login-desc"><?php esc_html_e( 'Rejoignez MathBoost gratuitement', MB_TEXT_DOMAIN ); ?></p>
      </div>

      <?php if ( $error_msg ) : ?>
        <div class="mb-activation-msg is-error" style="margin-bottom:16px;"><?php echo esc_html( $error_msg ); ?></div>
      <?php endif; ?>
      <?php if ( $success_msg ) : ?>
        <div class="mb-activation-msg is-success" style="margin-bottom:16px;"><?php echo esc_html( $success_msg ); ?></div>
      <?php endif; ?>

      <?php if ( $register_allowed ) : ?>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mb-login-form">
          <?php wp_nonce_field( 'mb_do_register', 'mb_register_nonce' ); ?>
          <input type="hidden" name="action"      value="mb_do_register">
          <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">

          <div class="mb-form-group">
            <label for="mb-reg-user"><?php esc_html_e( 'Identifiant', MB_TEXT_DOMAIN ); ?></label>
            <input type="text" id="mb-reg-user" name="user_login" class="mb-form-input"
                   required autocomplete="username" placeholder="<?php esc_attr_e( 'mon_identifiant', MB_TEXT_DOMAIN ); ?>">
          </div>

          <div class="mb-form-group">
            <label for="mb-reg-email"><?php esc_html_e( 'Adresse email', MB_TEXT_DOMAIN ); ?></label>
            <input type="email" id="mb-reg-email" name="user_email" class="mb-form-input"
                   required autocomplete="email" placeholder="exemple@email.com">
          </div>

          <div class="mb-form-group">
            <label for="mb-reg-pass"><?php esc_html_e( 'Mot de passe', MB_TEXT_DOMAIN ); ?></label>
            <div class="mb-password-wrap">
              <input type="password" id="mb-reg-pass" name="user_pass" class="mb-form-input"
                     required autocomplete="new-password" minlength="8"
                     placeholder="<?php esc_attr_e( 'Minimum 8 caractères', MB_TEXT_DOMAIN ); ?>">
              <button type="button" class="mb-toggle-pass" data-target="mb-reg-pass" aria-label="<?php esc_attr_e( 'Afficher', MB_TEXT_DOMAIN ); ?>">👁</button>
            </div>
          </div>

          <!-- ── Optional activation code ── -->
          <div class="mb-register-code-section">
            <button type="button" class="mb-code-toggle" id="mb-code-toggle-btn">
              🎟 <?php esc_html_e( 'Vous avez un code d\'activation ?', MB_TEXT_DOMAIN ); ?>
              <span class="mb-code-toggle-arrow">▼</span>
            </button>
            <div class="mb-code-collapse" id="mb-code-collapse">
              <div class="mb-form-group" style="margin-top:12px;">
                <label for="mb-reg-code"><?php esc_html_e( 'Code d\'activation (optionnel)', MB_TEXT_DOMAIN ); ?></label>
                <input type="text" id="mb-reg-code" name="activation_code" class="mb-form-input mb-code-input"
                       autocomplete="off" placeholder="XXXX-XXXX-XXXX" maxlength="14">
                <span class="mb-form-hint"><?php esc_html_e( 'Entrez votre code pour activer l\'accès premium dès l\'inscription.', MB_TEXT_DOMAIN ); ?></span>
              </div>
            </div>
          </div>

          <button type="submit" class="mb-btn mb-btn-upgrade mb-btn-large mb-btn-block">
            ✨ <?php esc_html_e( 'Créer mon compte', MB_TEXT_DOMAIN ); ?>
          </button>

          <div class="mb-form-links">
            <?php esc_html_e( 'Déjà inscrit ?', MB_TEXT_DOMAIN ); ?>
            <a href="<?php echo esc_url( $redirect_to ? add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $login_url ) : $login_url ); ?>">
              <?php esc_html_e( 'Se connecter →', MB_TEXT_DOMAIN ); ?>
            </a>
          </div>
        </form>

      <?php else : ?>

        <div class="mb-register-closed">
          <div class="mb-register-closed-icon">🔒</div>
          <p><?php esc_html_e( 'Les inscriptions sont actuellement fermées.', MB_TEXT_DOMAIN ); ?></p>
          <a class="mb-btn mb-btn-outline" href="<?php echo esc_url( $login_url ); ?>">
            <?php esc_html_e( '← Se connecter', MB_TEXT_DOMAIN ); ?>
          </a>
        </div>

      <?php endif; ?>

    </div>
  </div>
</div>

<script>
(function () {
  // Toggle activation code section
  var btn      = document.getElementById('mb-code-toggle-btn');
  var collapse = document.getElementById('mb-code-collapse');
  var arrow    = document.querySelector('.mb-code-toggle-arrow');
  if (btn && collapse) {
    btn.addEventListener('click', function () {
      var open = collapse.classList.toggle('is-open');
      if (arrow) { arrow.textContent = open ? '▲' : '▼'; }
    });
  }

  // Auto-format activation code: insert dashes
  var codeInput = document.getElementById('mb-reg-code');
  if (codeInput) {
    codeInput.addEventListener('input', function () {
      var v = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
      var parts = [];
      for (var i = 0; i < v.length && i < 12; i += 4) {
        parts.push(v.slice(i, i + 4));
      }
      this.value = parts.join('-');
    });
  }

  // Toggle password visibility
  document.querySelectorAll('.mb-toggle-pass').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(this.dataset.target);
      if (input) {
        input.type = input.type === 'password' ? 'text' : 'password';
        this.textContent = input.type === 'password' ? '👁' : '🙈';
      }
    });
  });
})();
</script>
