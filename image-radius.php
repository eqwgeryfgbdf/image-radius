<?php
/**
 * Plugin Name: 圖片圓角
 * Description: 調整文章圖片、封面、頭像、Logo 與網站圖示的圓角。可在設定頁與自訂器中修改。
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Roy
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: image-radius
 *
 * @package ImageRadius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'IMAGE_RADIUS_VERSION', '1.0.0' );
define( 'IMAGE_RADIUS_FILE', __FILE__ );
define( 'IMAGE_RADIUS_DIR', plugin_dir_path( __FILE__ ) );
define( 'IMAGE_RADIUS_URL', plugin_dir_url( __FILE__ ) );

require_once IMAGE_RADIUS_DIR . 'includes/class-options.php';
require_once IMAGE_RADIUS_DIR . 'includes/class-cache.php';
require_once IMAGE_RADIUS_DIR . 'includes/class-site-icon.php';
require_once IMAGE_RADIUS_DIR . 'includes/class-frontend.php';
require_once IMAGE_RADIUS_DIR . 'includes/class-admin.php';
require_once IMAGE_RADIUS_DIR . 'includes/class-customizer.php';
require_once IMAGE_RADIUS_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'ImageRadius\\Plugin', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		\ImageRadius\Plugin::instance()->boot();
	}
);
