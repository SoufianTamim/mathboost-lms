<?php
/**
 * Plugin Name:       MathBoost LMS
 * Plugin URI:        https://mathboost.net
 * Description:       Système LMS complet pour QCMs de mathématiques avec freemium, codes d'activation, MathJax, navigation par niveaux/cours/catégories et restriction de sessions.
 * Version:           1.0.2
 * Author:            MathBoost
 * Author URI:        https://mathboost.net
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mathboost-lms
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

defined( 'ABSPATH' ) || exit;

// ── Constants ────────────────────────────────────────────────────────────────
define( 'MB_VERSION',     '1.0.2' );
define( 'MB_PLUGIN_FILE', __FILE__ );
define( 'MB_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'MB_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'MB_TEXT_DOMAIN', 'mathboost-lms' );

// ── Autoload includes ─────────────────────────────────────────────────────────
$mb_includes = [
    'includes/class-mb-activator.php',
    'includes/class-mb-post-types.php',
    'includes/class-mb-taxonomies.php',
    'includes/class-mb-session-manager.php',
    'includes/class-mb-activation-codes.php',
    'includes/class-mb-access.php',
    'includes/class-mb-ajax.php',
    'includes/class-mb-shortcodes.php',
    'frontend/class-mb-frontend.php',
    'admin/class-mb-admin.php',
];

foreach ( $mb_includes as $file ) {
    require_once MB_PLUGIN_DIR . $file;
}

// ── Activation / Deactivation ─────────────────────────────────────────────────
register_activation_hook(   __FILE__, [ 'MB_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'MB_Activator', 'deactivate' ] );

// ── Bootstrap ─────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', 'mathboost_lms_init' );

function mathboost_lms_init() {
    load_plugin_textdomain( MB_TEXT_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    MB_Post_Types::init();
    MB_Taxonomies::init();
    MB_Session_Manager::init();
    MB_Shortcodes::init();
    MB_Frontend::init();
    MB_Ajax::init();

    if ( is_admin() ) {
        MB_Admin::init();
    }
}