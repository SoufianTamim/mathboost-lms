<?php defined( 'ABSPATH' ) || exit; ?>

<div class="mb-activation-wrap">
  <div class="mb-activation-card">
    <div class="mb-activation-icon">🔑</div>
    <h3 class="mb-activation-title"><?php esc_html_e( 'Activer votre code', MB_TEXT_DOMAIN ); ?></h3>
    <p class="mb-activation-desc">
      <?php esc_html_e( 'Entrez votre code d\'activation pour débloquer l\'accès premium.', MB_TEXT_DOMAIN ); ?>
    </p>

    <?php if ( ! is_user_logged_in() ) : ?>
      <div class="mb-notice mb-notice-info">
        <?php esc_html_e( 'Vous devez être connecté pour activer un code.', MB_TEXT_DOMAIN ); ?>
        <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">
          <?php esc_html_e( 'Se connecter', MB_TEXT_DOMAIN ); ?>
        </a>
      </div>
    <?php else : ?>

      <?php if ( MB_Access::current_user_is_premium() ) :
        $expires = get_user_meta( get_current_user_id(), 'mb_premium_expires', true );
      ?>
        <div class="mb-notice mb-notice-success">
          <strong>⭐ <?php esc_html_e( 'Vous êtes déjà Premium !', MB_TEXT_DOMAIN ); ?></strong>
          <?php if ( $expires && $expires !== '2099-01-01 00:00:00' ) :
            $exp_date = wp_date( get_option( 'date_format' ), strtotime( $expires ) );
          ?>
            <br><?php printf( esc_html__( 'Expire le : %s', MB_TEXT_DOMAIN ), esc_html( $exp_date ) ); ?>
          <?php else : ?>
            <br><?php esc_html_e( 'Accès à vie.', MB_TEXT_DOMAIN ); ?>
          <?php endif; ?>
        </div>
      <?php else : ?>

        <div id="mb-activation-form-el">
          <div class="mb-code-input-row">
            <input type="text"
                   id="mb-activation-code"
                   class="mb-code-input"
                   placeholder="XXXX-XXXX-XXXX"
                   maxlength="14"
                   autocomplete="off"
                   autocapitalize="characters"
                   spellcheck="false"
            >
            <button id="mb-activate-btn" class="mb-btn mb-btn-start">
              <?php esc_html_e( 'Activer', MB_TEXT_DOMAIN ); ?>
            </button>
          </div>
          <div id="mb-activation-msg" class="mb-activation-msg" style="display:none;"></div>
        </div>

      <?php endif; ?>

    <?php endif; ?>
  </div>
</div>
