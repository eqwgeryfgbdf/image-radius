<?php
/**
 * Front-end CSS for rounded images.
 *
 * @package ImageRadius
 */

namespace ImageRadius;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prints CSS variables and selectors for content, covers, avatars, and logo.
 */
class Frontend {
	/**
	 * @var Options
	 */
	private $options;

	public function __construct( Options $options ) {
		$this->options = $options;
	}

	public function register(): void {
		add_action( 'wp_head', array( $this, 'printCss' ), 40 );
	}

	public function printCss(): void {
		if ( is_admin() || ! $this->options->enabled() ) {
			return;
		}
		$css = $this->buildCss();
		if ( $css === '' ) {
			return;
		}
		echo '<style id="image-radius-css">' . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS built from integers.
	}

	public function buildCss(): string {
		$radiusPx = $this->options->radiusPx();
		$rules     = array();
		$rules[]   = ':root{--image-radius:' . $radiusPx . 'px;}';

		$exclude = ':not(.emoji):not(.wp-smiley):not(.smiley-img)';

		if ( $this->options->applyContent() ) {
			$contentSelectors = array(
				'.content-main img' . $exclude,
				'.entry-content img' . $exclude,
				'.entry-content-box img' . $exclude,
				'.wp-block-image img',
				'.wp-block-gallery img',
				'figure.wp-block-image',
				'figure.wp-block-gallery',
				'.wp-block-media-text__media img',
				'.wp-block-cover',
			);
			$rules[]          = implode( ',', $contentSelectors ) . '{border-radius:var(--image-radius)!important;overflow:hidden;}';
		}

		if ( $this->options->applyFeatured() ) {
			$featuredSelectors = array(
				'.post-item .thumbnail',
				'.post-item .thumbnail img',
				'.post-item-card .thumbnail',
				'.post-item-block .thumbnail',
				'.post-item-block .thumbnail img',
				'img.wp-post-image',
				'.related-reading img',
				'.related-reading__card',
				'.related-reading__card img',
				'.widget img' . $exclude,
				'a.img img',
				'a.img',
			);
			$rules[]           = implode( ',', $featuredSelectors ) . '{border-radius:var(--image-radius)!important;overflow:hidden;}';
		}

		if ( $this->options->applyAvatar() ) {
			$rules[] = 'img.avatar,.avatar img,#comments .avatar{border-radius:var(--image-radius)!important;overflow:hidden;}';
		}

		if ( $this->options->applyLogo() ) {
			$rules[] = '#logo img,#logo-light,#logo-dark,.custom-logo{border-radius:var(--image-radius)!important;overflow:hidden;}';
		}

		return implode( '', $rules );
	}
}
