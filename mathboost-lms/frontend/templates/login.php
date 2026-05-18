<?php defined( 'ABSPATH' ) || exit; ?>

<?php
// If already logged in, show account info
if ( is_user_logged_in() ) {
    $user       = wp_get_current_user();
    $logout_url = wp_logout_url( get_permalink() );
    ?>
    <div class="mb-login-wrap">
      <div class="mb-login-card">
        <div class="mb-login-icon">👤</div>
        <h2 class="mb-login-title"><?php esc_html_e( 'Connecté', MB_TEXT_DOMAIN ); ?></h2>
        <p class="mb-login-desc">
          <?php printf( esc_html__( 'Bienvenue, %s !', MB_TEXT_DOMAIN ), esc_html( $user->display_name ) ); ?>
        </p>
        <?php if ( MB_Access::current_user_is_premium() ) : ?>
          <div class="mb-login-premium-badge">⭐ <?php esc_html_e( 'Compte Premium', MB_TEXT_DOMAIN ); ?></div>
        <?php endif; ?>
        <div class="mb-login-actions">
          <?php if ( current_user_can( 'manage_options' ) ) : ?>
            <a class="mb-btn mb-btn-start" href="<?php echo esc_url( admin_url() ); ?>">
              ⚙️ <?php esc_html_e( 'Tableau de bord', MB_TEXT_DOMAIN ); ?>
            </a>
          <?php else : ?>
            <a class="mb-btn mb-btn-start" href="<?php echo esc_url( remove_query_arg( [ 'mb_login_error', 'mb_login_success', 'tab' ] ) ); ?>">
              ← <?php esc_html_e( 'Retour aux QCMs', MB_TEXT_DOMAIN ); ?>
            </a>
          <?php endif; ?>
          <a class="mb-btn mb-btn-outline" href="<?php echo esc_url( $logout_url ); ?>">
            <?php esc_html_e( 'Se déconnecter', MB_TEXT_DOMAIN ); ?>
          </a>
        </div>
      </div>
    </div>
    <?php
    return;
}

$redirect_to      = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';
$error_msg        = isset( $_GET['mb_login_error'] )   ? sanitize_text_field( urldecode( wp_unslash( $_GET['mb_login_error'] ) ) )   : '';
$success_msg      = isset( $_GET['mb_login_success'] ) ? sanitize_text_field( urldecode( wp_unslash( $_GET['mb_login_success'] ) ) ) : '';
$tab              = ( isset( $_GET['tab'] ) && sanitize_key( $_GET['tab'] ) === 'register' ) ? 'register' : 'login';
$register_allowed = get_option( 'users_can_register' );
?>

<div class="mb-login-wrap">
  <div class="mb-login-card">

    <?php if ( $register_allowed ) : ?>
    <div class="mb-login-tabs">
      <button type="button" class="mb-login-tab <?php echo $tab === 'login' ? 'is-active' : ''; ?>" data-tab="login">
        <?php esc_html_e( 'Se connecter', MB_TEXT_DOMAIN ); ?>
      </button>
      <button type="button" class="mb-login-tab <?php echo $tab === 'register' ? 'is-active' : ''; ?>" data-tab="register">
        <?php esc_html_e( 'Créer un compte', MB_TEXT_DOMAIN ); ?>
      </button>
    </div>
    <?php endif; ?>

    <?php if ( $error_msg ) : ?>
      <div class="mb-activation-msg is-error"><?php echo esc_html( $error_msg ); ?></div>
    <?php endif; ?>
    <?php if ( $success_msg ) : ?>
      <div class="mb-activation-msg is-success"><?php echo esc_html( $success_msg ); ?></div>
    <?php endif; ?>

    <!-- ── LOGIN FORM ──────────────────────────────────────── -->
    <div class="mb-login-panel <?php echo $tab === 'login' ? 'is-active' : ''; ?>" id="mb-panel-login">
      <div class="mb-login-header">
        <div class="mb-login-icon">🎓</div>
        <h2 class="mb-login-title"><?php esc_html_e( 'Connexion', MB_TEXT_DOMAIN ); ?></h2>
        <p class="mb-login-desc"><?php esc_html_e( 'Accédez à votre espace MathBoost', MB_TEXT_DOMAIN ); ?></p>
      </div>

      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mb-login-form">
        <?php wp_nonce_field( 'mb_do_login', 'mb_login_nonce' ); ?>
        <input type="hidden" name="action"      value="mb_do_login">
        <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">

        <div class="mb-form-group">
          <label for="mb-login-user"><?php esc_html_e( 'Identifiant ou email', MB_TEXT_DOMAIN ); ?></label>
          <input type="text" id="mb-login-user" name="log" class="mb-form-input"
                 required autocomplete="username">
        </div>

        <div class="mb-form-group">
          <label for="mb-login-pass"><?php esc_html_e( 'Mot de passe', MB_TEXT_DOMAIN ); ?></label>
          <input type="password" id="mb-login-pass" name="pwd" class="mb-form-input"
                 required autocomplete="current-password">
        </div>

        <div class="mb-form-check">
          <label>
            <input type="checkbox" name="rememberme" value="forever">
            <?php esc_html_e( 'Se souvenir de moi', MB_TEXT_DOMAIN ); ?>
          </label>
        </div>

        <button type="submit" class="mb-btn mb-btn-start mb-btn-large mb-btn-block">
          <?php esc_html_e( 'Se connecter', MB_TEXT_DOMAIN ); ?>
        </button>

        <div class="mb-form-links">
          <a href="<?php echo esc_url( wp_lostpassword_url( get_permalink() ) ); ?>">
            <?php esc_html_e( 'Mot de passe oublié ?', MB_TEXT_DOMAIN ); ?>
          </a>
        </div>
      </form>
    </div>

    <!-- ── REGISTER FORM ────────────────────────────────────── -->
    <?php if ( $register_allowed ) : ?>
    <div class="mb-login-panel <?php echo $tab === 'register' ? 'is-active' : ''; ?>" id="mb-panel-register">
      <div class="mb-login-header">
        <div class="mb-login-icon">✨</div>
        <h2 class="mb-login-title"><?php esc_html_e( 'Créer un compte', MB_TEXT_DOMAIN ); ?></h2>
        <p class="mb-login-desc"><?php esc_html_e( 'Rejoignez MathBoost gratuitement', MB_TEXT_DOMAIN ); ?></p>
      </div>

      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mb-login-form">
        <?php wp_nonce_field( 'mb_do_register', 'mb_register_nonce' ); ?>
        <input type="hidden" name="action"      value="mb_do_register">
        <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">

        <div class="mb-form-group">
          <label for="mb-reg-user"><?php esc_html_e( 'Identifiant', MB_TEXT_DOMAIN ); ?></label>
          <input type="text" id="mb-reg-user" name="user_login" class="mb-form-input"
                 required autocomplete="username">
        </div>

        <div class="mb-form-group">
          <label for="mb-reg-email"><?php esc_html_e( 'Adresse email', MB_TEXT_DOMAIN ); ?></label>
          <input type="email" id="mb-reg-email" name="user_email" class="mb-form-input"
                 required autocomplete="email">
        </div>

        <div class="mb-form-group">
          <label for="mb-reg-pass"><?php esc_html_e( 'Mot de passe', MB_TEXT_DOMAIN ); ?></label>
          <input type="password" id="mb-reg-pass" name="user_pass" class="mb-form-input"
                 required autocomplete="new-password" minlength="8">
          <span class="mb-form-hint"><?php esc_html_e( 'Minimum 8 caractères', MB_TEXT_DOMAIN ); ?></span>
        </div>

        <button type="submit" class="mb-btn mb-btn-start mb-btn-large mb-btn-block">
          <?php esc_html_e( 'Créer mon compte', MB_TEXT_DOMAIN ); ?>
        </button>
      </form>
    </div>
    <?php else : ?>
    <div class="mb-notice mb-notice-info" style="margin-top:16px;">
      <?php esc_html_e( 'Les inscriptions sont actuellement fermées.', MB_TEXT_DOMAIN ); ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<script>
(function () {
  var tabs   = document.querySelectorAll('.mb-login-tab');
  var panels = document.querySelectorAll('.mb-login-panel');
  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      var target = this.dataset.tab;
      tabs.forEach(function (t) { t.classList.remove('is-active'); });
      panels.forEach(function (p) { p.classList.remove('is-active'); });
      this.classList.add('is-active');
      var panel = document.getElementById('mb-panel-' + target);
      if (panel) { panel.classList.add('is-active'); }
    });
  });
})();
</script>
