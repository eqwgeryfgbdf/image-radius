<?php
/**
 * Customizer controls for image radius.
 *
 * @package ImageRadius
 */

namespace ImageRadius;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Appearance → Customize → 圖片圓角.
 */
class Customizer {
	/**
	 * @var Options
	 */
	private $options;

	/**
	 * @var SiteIcon
	 */
	private $siteIcon;

	public function __construct( Options $options, SiteIcon $siteIcon ) {
		$this->options  = $options;
		$this->siteIcon = $siteIcon;
	}

	public function register(): void {
		add_action( 'customize_register', array( $this, 'addControls' ) );
		add_action( 'customize_preview_init', array( $this, 'enqueuePreview' ) );
		add_action( 'customize_save_after', array( $this, 'afterSave' ) );
	}

	public function addControls( \WP_Customize_Manager $wpCustomize ): void {
		$wpCustomize->add_section(
			'image_radius',
			array(
				'title'       => '圖片圓角',
				'description' => '調整文章圖片、封面、頭像、Logo 與網站圖示的四邊弧度。',
				'priority'    => 80,
			)
		);

		$settings = array(
			'enabled'         => array(
				'label'   => '啟用圖片圓角',
				'type'    => 'checkbox',
				'default' => true,
			),
			'radiusPx'        => array(
				'label'       => '內容圖片圓角（px）',
				'type'        => 'number',
				'default'     => 16,
				'input_attrs' => array(
					'min'  => 0,
					'max'  => 64,
					'step' => 1,
				),
			),
			'applyContent'    => array(
				'label'   => '文章與頁面內圖片',
				'type'    => 'checkbox',
				'default' => true,
			),
			'applyFeatured'   => array(
				'label'   => '列表封面與精選圖片',
				'type'    => 'checkbox',
				'default' => true,
			),
			'applyAvatar'     => array(
				'label'   => '留言頭像',
				'type'    => 'checkbox',
				'default' => true,
			),
			'applyLogo'       => array(
				'label'   => '網站 Logo',
				'type'    => 'checkbox',
				'default' => true,
			),
			'applySiteIcon'   => array(
				'label'   => '網站圖示（favicon）',
				'type'    => 'checkbox',
				'default' => true,
			),
			'siteIconPercent' => array(
				'label'       => '網站圖示圓角（%）',
				'type'        => 'number',
				'default'     => 22,
				'input_attrs' => array(
					'min'  => 0,
					'max'  => 45,
					'step' => 1,
				),
			),
		);

		foreach ( $settings as $key => $config ) {
			$settingId = Options::OPTION_KEY . '[' . $key . ']';
			$wpCustomize->add_setting(
				$settingId,
				array(
					'type'              => 'option',
					'capability'        => 'manage_options',
					'default'           => $config['default'],
					'sanitize_callback' => array( $this, 'sanitizeSetting' ),
					'transport'         => in_array( $key, array( 'radiusPx', 'enabled', 'applyContent', 'applyFeatured', 'applyAvatar', 'applyLogo' ), true ) ? 'postMessage' : 'refresh',
				)
			);
			$controlArgs = array(
				'label'   => $config['label'],
				'section' => 'image_radius',
				'type'    => $config['type'],
			);
			if ( isset( $config['input_attrs'] ) ) {
				$controlArgs['input_attrs'] = $config['input_attrs'];
			}
			$wpCustomize->add_control( $settingId, $controlArgs );
		}
	}

	/**
	 * @param mixed $value Raw Customizer value.
	 * @return mixed
	 */
	public function sanitizeSetting( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? '1' : '';
		}
		if ( $value === true || $value === 'true' || $value === '1' || $value === 1 ) {
			return '1';
		}
		if ( $value === false || $value === 'false' || $value === '' || $value === '0' || $value === 0 ) {
			return '';
		}
		if ( is_numeric( $value ) ) {
			return absint( $value );
		}
		return sanitize_text_field( (string) $value );
	}

	public function enqueuePreview(): void {
		wp_enqueue_script(
			'image-radius-customizer',
			IMAGE_RADIUS_URL . 'assets/customizer-preview.js',
			array( 'customize-preview' ),
			IMAGE_RADIUS_VERSION,
			true
		);
	}

	public function afterSave( \WP_Customize_Manager $wpCustomize ): void {
		unset( $wpCustomize );
		$this->options->load();
		$result = $this->siteIcon->apply( 0, false );
		if ( is_wp_error( $result ) ) {
			error_log( 'Image Radius: Customizer site icon update failed: ' . $result->get_error_message() );
		}
		Cache::flush();
	}
}
