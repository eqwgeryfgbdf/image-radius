<?php
/**
 * Plugin bootstrap.
 *
 * @package ImageRadius
 */

namespace ImageRadius;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires settings, CSS, site-icon rounding, admin, and Customizer.
 */
class Plugin {
	/**
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * @var Options
	 */
	private $options;

	/**
	 * @var SiteIcon
	 */
	private $siteIcon;

	public static function instance(): Plugin {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function activate(): void {
		$plugin = self::instance();
		$plugin->options->load();
		if ( get_option( Options::OPTION_KEY, null ) === null ) {
			add_option( Options::OPTION_KEY, Options::defaults(), '', false );
		}
	}

	private function __construct() {
		$this->options  = new Options();
		$this->siteIcon = new SiteIcon( $this->options );
	}

	public function boot(): void {
		$this->options->load();
		( new Frontend( $this->options ) )->register();
		( new Admin( $this->options, $this->siteIcon ) )->register();
		( new Customizer( $this->options, $this->siteIcon ) )->register();
		$this->siteIcon->register();
	}

	public function options(): Options {
		return $this->options;
	}
}
