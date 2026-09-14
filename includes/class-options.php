<?php
/**
 * Plugin settings.
 *
 * @package ImageRadius
 */

namespace ImageRadius;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes the image_radius option.
 */
class Options {
	public const OPTION_KEY = 'image_radius';

	/**
	 * @var array<string, mixed>
	 */
	private $settings = array();

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'enabled'         => '1',
			'radiusPx'        => 16,
			'applyContent'    => '1',
			'applyFeatured'   => '1',
			'applyAvatar'     => '1',
			'applyLogo'       => '1',
			'applySiteIcon'   => '1',
			'siteIconPercent' => 22,
		);
	}

	public function load(): void {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			error_log( 'Image Radius: stored settings are not an array.' );
			$stored = array();
		}
		$this->settings = array_merge( self::defaults(), $stored );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function all(): array {
		return $this->settings;
	}

	/**
	 * @param mixed $default Default when missing.
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		if ( array_key_exists( $key, $this->settings ) ) {
			return $this->settings[ $key ];
		}
		return $default;
	}

	public function enabled(): bool {
		return $this->isOn( 'enabled' );
	}

	public function radiusPx(): int {
		return $this->clampInt( (int) $this->get( 'radiusPx', 16 ), 0, 64 );
	}

	public function siteIconPercent(): int {
		return $this->clampInt( (int) $this->get( 'siteIconPercent', 22 ), 0, 45 );
	}

	public function applyContent(): bool {
		return $this->isOn( 'applyContent' );
	}

	public function applyFeatured(): bool {
		return $this->isOn( 'applyFeatured' );
	}

	public function applyAvatar(): bool {
		return $this->isOn( 'applyAvatar' );
	}

	public function applyLogo(): bool {
		return $this->isOn( 'applyLogo' );
	}

	public function applySiteIcon(): bool {
		return $this->isOn( 'applySiteIcon' );
	}

	private function isOn( string $key ): bool {
		$value = $this->get( $key, '1' );
		return $value === '1' || $value === 1 || $value === true || $value === 'true';
	}

	/**
	 * @param array<string, mixed> $input Raw settings.
	 * @return true|\WP_Error
	 */
	public function save( array $input ) {
		$next = array(
			'enabled'         => empty( $input['enabled'] ) ? '' : '1',
			'radiusPx'        => $this->clampInt( (int) ( $input['radiusPx'] ?? 16 ), 0, 64 ),
			'applyContent'    => empty( $input['applyContent'] ) ? '' : '1',
			'applyFeatured'   => empty( $input['applyFeatured'] ) ? '' : '1',
			'applyAvatar'     => empty( $input['applyAvatar'] ) ? '' : '1',
			'applyLogo'       => empty( $input['applyLogo'] ) ? '' : '1',
			'applySiteIcon'   => empty( $input['applySiteIcon'] ) ? '' : '1',
			'siteIconPercent' => $this->clampInt( (int) ( $input['siteIconPercent'] ?? 22 ), 0, 45 ),
		);
		$updated = update_option( self::OPTION_KEY, $next, false );
		if ( false === $updated && get_option( self::OPTION_KEY ) !== $next ) {
			return new \WP_Error( 'image_radius_save', '無法儲存圖片圓角設定，請再試一次。' );
		}
		$this->settings = $next;
		return true;
	}

	private function clampInt( int $value, int $min, int $max ): int {
		if ( $value < $min ) {
			return $min;
		}
		if ( $value > $max ) {
			return $max;
		}
		return $value;
	}
}
