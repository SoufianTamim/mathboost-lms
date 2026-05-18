<?php defined( 'ABSPATH' ) || exit; ?>

<?php
$qcm = MB_QCM_Repository::get_by_id( (int) $qcm_id );

if ( ! $qcm ) {
    echo '<div class="mb-empty">' . esc_html__( 'QCM introuvable.', MB_TEXT_DOMAIN ) . '</div>';
    return;
}

$title     = $qcm->title;
$subtitle  = $qcm->subtitle;
$intro     = $qcm->intro;
$questions = json_decode( $qcm->questions ?: '[]', true ) ?? [];
$total     = count( $questions );

if ( ! $total ) {
    echo '<div class="mb-empty">' . esc_html__( 'Ce QCM ne contient pas encore de questions.', MB_TEXT_DOMAIN ) . '</div>';
    return;
}

$js_questions = wp_json_encode( $questions, JSON_HEX_TAG | JSON_HEX_AMP );

// Resolve category names for the subtitle line
$cat_ids   = MB_QCM_Repository::get_category_ids( (int) $qcm->id );
$cat_names = [];
foreach ( $cat_ids as $cid ) {
    $cat = MB_Category_Repository::get_by_id( $cid );
    if ( $cat ) {
        $cat_names[] = $cat->name;
    }
}
?>

<div class="qcm-wrap" id="mb-qcm-<?php echo esc_attr( $qcm->id ); ?>">

  <!-- HEADER -->
  <div class="qcm-header">
    <h1><?php echo esc_html( $title ); ?></h1>
    <?php if ( $subtitle ) : ?>
      <p><?php echo esc_html( $subtitle ); ?></p>
    <?php elseif ( ! empty( $cat_names ) ) : ?>
      <p><?php echo esc_html( implode( ' › ', $cat_names ) ); ?></p>
    <?php endif; ?>
  </div>

  <!-- INTRO -->
  <?php if ( $intro ) : ?>
  <div class="qcm-intro">
    <h2>🎓 <?php esc_html_e( 'Bienvenue dans ce Quiz', MB_TEXT_DOMAIN ); ?></h2>
    <div class="qcm-intro-content">
      <?php echo wp_kses_post( wpautop( $intro ) ); ?>
    </div>
    <div class="qcm-intro-rules">
      <strong>📋 <?php esc_html_e( 'Feuille de route', MB_TEXT_DOMAIN ); ?></strong>
      <ul>
        <li>
          <span class="rule-title">✏️ <?php esc_html_e( 'Prenez le temps qu\'il faut', MB_TEXT_DOMAIN ); ?></span>
          <span class="rule-desc"><?php esc_html_e( 'Ce n\'est pas une course, c\'est un entraînement sérieux.', MB_TEXT_DOMAIN ); ?></span>
        </li>
        <li>
          <span class="rule-title">📄 <?php esc_html_e( 'Faites vos calculs au brouillon', MB_TEXT_DOMAIN ); ?></span>
          <span class="rule-desc"><?php esc_html_e( 'Ne cochez jamais au hasard.', MB_TEXT_DOMAIN ); ?></span>
        </li>
        <li>
          <span class="rule-title">✅ <?php esc_html_e( 'La correction se débloque après chaque réponse', MB_TEXT_DOMAIN ); ?></span>
          <span class="rule-desc"><?php esc_html_e( 'Profitez-en pour comprendre vos erreurs.', MB_TEXT_DOMAIN ); ?></span>
        </li>
        <li>
          <span class="rule-title">🎯 <?php printf( esc_html__( 'L\'objectif : %d/%d', MB_TEXT_DOMAIN ), $total, $total ); ?></span>
          <span class="rule-desc"><?php esc_html_e( 'Mais surtout repartir en ayant tout compris !', MB_TEXT_DOMAIN ); ?></span>
        </li>
      </ul>
    </div>
  </div>
  <?php endif; ?>

  <!-- STICKY SCORE BAR -->
  <div class="qcm-score-bar">
    <span class="qcm-score-label"><?php esc_html_e( 'Score', MB_TEXT_DOMAIN ); ?></span>
    <div class="qcm-progress-track">
      <div class="qcm-progress-fill" id="score-fill"></div>
    </div>
    <span class="qcm-score-badge" id="score-badge">0 / <?php echo esc_html( $total ); ?></span>
  </div>

  <!-- QUESTIONS (rendered by mb-qcm.js) -->
  <div id="qcm-questions"></div>

  <!-- FINAL SCORE BILAN -->
  <div class="score-bilan" id="score-bilan">
    <div class="bilan-coupe" id="bilan-coupe" style="display:none">🏆</div>
    <div class="bilan-emoji" id="bilan-emoji"></div>
    <div class="bilan-titre"><?php esc_html_e( 'Quiz terminé !', MB_TEXT_DOMAIN ); ?></div>
    <div class="bilan-score" id="bilan-score"></div>
    <div class="bilan-msg"   id="bilan-msg"></div>
    <div class="bilan-actions">
      <button class="mb-btn mb-btn-start" onclick="location.reload()">
        🔄 <?php esc_html_e( 'Recommencer', MB_TEXT_DOMAIN ); ?>
      </button>
      <a class="mb-btn mb-btn-outline" href="<?php echo esc_url( remove_query_arg( 'mb_cat' ) ); ?>">
        📚 <?php esc_html_e( 'Autres QCMs', MB_TEXT_DOMAIN ); ?>
      </a>
    </div>
  </div>

  <!-- REPORT MODAL -->
  <div class="qcm-modal-overlay" id="report-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="qcm-modal">
      <button class="qcm-modal-close" id="modal-close-btn" aria-label="<?php esc_attr_e( 'Fermer', MB_TEXT_DOMAIN ); ?>">✕</button>
      <h3 id="modal-title">⚠️ <?php esc_html_e( 'Signaler une erreur', MB_TEXT_DOMAIN ); ?></h3>
      <p class="modal-sub" id="modal-question-label"></p>
      <label for="report-msg"><?php esc_html_e( 'Décrivez l\'erreur constatée :', MB_TEXT_DOMAIN ); ?></label>
      <textarea id="report-msg"
                placeholder="<?php esc_attr_e( 'Ex : La bonne réponse indiquée me semble incorrecte car…', MB_TEXT_DOMAIN ); ?>">
      </textarea>
      <div class="qcm-modal-actions">
        <button class="qcm-modal-cancel" id="modal-cancel-btn"><?php esc_html_e( 'Annuler', MB_TEXT_DOMAIN ); ?></button>
        <button class="qcm-modal-send"   id="modal-send-btn">📧 <?php esc_html_e( 'Envoyer', MB_TEXT_DOMAIN ); ?></button>
      </div>
    </div>
  </div>

</div><!-- .qcm-wrap -->

<script>
(function () {
  window.MB_QCM_DATA = {
    qcmId    : <?php echo (int) $qcm->id; ?>,
    total    : <?php echo (int) $total; ?>,
    questions: <?php echo $js_questions; ?>,
    nonce    : '<?php echo esc_js( wp_create_nonce( 'mb_report_nonce' ) ); ?>',
    ajaxUrl  : '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>'
  };
})();
</script>
