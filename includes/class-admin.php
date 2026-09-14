<?php
/**
 * Settings screen under Settings → 圖片圓角.
 *
 * @package ImageRadius
 */

namespace ImageRadius;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI for radius sliders and apply-to checkboxes.
 */
class Admin {
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
		add_action( 'admin_menu', array( $this, 'addPage' ) );
		add_action( 'admin_post_image_radius_save', array( $this, 'handleSave' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( IMAGE_RADIUS_FILE ), array( $this, 'actionLinks' ) );
	}

	/**
	 * @param array<int, string> $links Plugin links.
	 * @return array<int, string>
	 */
	public function actionLinks( $links ) {
		if ( ! is_array( $links ) ) {
			$links = array();
		}
		$links[] = '<a href="' . esc_url( admin_url( 'options-general.php?page=image-radius' ) ) . '">設定</a>';
		return $links;
	}

	public function addPage(): void {
		add_options_page(
			'圖片圓角',
			'圖片圓角',
			'manage_options',
			'image-radius',
			array( $this, 'renderPage' )
		);
	}

	public function enqueue( string $hook ): void {
		if ( $hook !== 'settings_page_image-radius' ) {
			return;
		}
		wp_enqueue_style(
			'image-radius-admin',
			IMAGE_RADIUS_URL . 'assets/admin.css',
			array(),
			IMAGE_RADIUS_VERSION
		);
		wp_enqueue_script(
			'image-radius-admin',
			IMAGE_RADIUS_URL . 'assets/admin.js',
			array(),
			IMAGE_RADIUS_VERSION,
			true
		);
	}

	public function handleSave(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( '你沒有權限修改這些設定。' ) );
		}
		check_admin_referer( 'image_radius_save' );
		$posted = wp_unslash( $_POST );
		$saved  = $this->options->save(
			array(
				'enabled'         => empty( $posted['enabled'] ) ? '' : '1',
				'radiusPx'        => absint( $posted['radiusPx'] ?? 16 ),
				'applyContent'    => empty( $posted['applyContent'] ) ? '' : '1',
				'applyFeatured'   => empty( $posted['applyFeatured'] ) ? '' : '1',
				'applyAvatar'     => empty( $posted['applyAvatar'] ) ? '' : '1',
				'applyLogo'       => empty( $posted['applyLogo'] ) ? '' : '1',
				'applySiteIcon'   => empty( $posted['applySiteIcon'] ) ? '' : '1',
				'siteIconPercent' => absint( $posted['siteIconPercent'] ?? 22 ),
			)
		);
		if ( $saved !== true ) {
			$message = is_wp_error( $saved ) ? $saved->get_error_message() : '儲存設定失敗，請再試一次。';
			wp_die( esc_html( $message ) );
		}

		$iconResult = $this->siteIcon->apply( 0, false );
		if ( is_wp_error( $iconResult ) ) {
			wp_die( esc_html( $iconResult->get_error_message() ) );
		}

		Cache::flush();
		wp_safe_redirect( admin_url( 'options-general.php?page=image-radius&saved=1' ) );
		exit;
	}

	public function renderPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( '你沒有權限查看這個頁面。' ) );
		}
		$radiusPx        = $this->options->radiusPx();
		$siteIconPercent = $this->options->siteIconPercent();
		$previewSrc      = get_site_icon_url( 192 );
		if ( $previewSrc === '' ) {
			$previewSrc = includes_url( 'images/w-logo-blue.png' );
		}
		?>
		<div class="wrap image-radius-wrap">
			<h1>圖片圓角</h1>
			<p>調整前台圖片四邊弧度。文章內容、封面、頭像、Logo 用 CSS；網站圖示會寫進 PNG，瀏覽器分頁才看得到圓角。</p>
			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>設定已儲存，頁面快取已清除。</p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'image_radius_save' ); ?>
				<input type="hidden" name="action" value="image_radius_save">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">啟用</th>
						<td>
							<label>
								<input type="checkbox" name="enabled" value="1" <?php checked( $this->options->enabled() ); ?>>
								套用圖片圓角
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="image-radius-px">內容圖片圓角</label></th>
						<td>
							<div class="image-radius-control">
								<input id="image-radius-px" name="radiusPx" type="range" min="0" max="64" step="1" value="<?php echo esc_attr( (string) $radiusPx ); ?>">
								<input id="image-radius-px-number" class="small-text" type="number" min="0" max="64" value="<?php echo esc_attr( (string) $radiusPx ); ?>" aria-label="圓角像素">
								<span>px</span>
							</div>
							<div class="image-radius-preview" data-preview="content">
								<img src="<?php echo esc_url( $previewSrc ); ?>" alt="" width="96" height="96">
								<span>預覽</span>
							</div>
							<p class="description">套用在文章、頁面、封面與 Logo。0 表示直角。</p>
						</td>
					</tr>
					<tr>
						<th scope="row">套用範圍</th>
						<td>
							<fieldset>
								<label><input type="checkbox" name="applyContent" value="1" <?php checked( $this->options->applyContent() ); ?>> 文章與頁面內圖片</label><br>
								<label><input type="checkbox" name="applyFeatured" value="1" <?php checked( $this->options->applyFeatured() ); ?>> 列表封面與精選圖片</label><br>
								<label><input type="checkbox" name="applyAvatar" value="1" <?php checked( $this->options->applyAvatar() ); ?>> 留言頭像</label><br>
								<label><input type="checkbox" name="applyLogo" value="1" <?php checked( $this->options->applyLogo() ); ?>> 網站 Logo</label><br>
								<label><input type="checkbox" name="applySiteIcon" value="1" <?php checked( $this->options->applySiteIcon() ); ?>> 網站圖示（favicon / 外觀設定）</label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="image-radius-icon">網站圖示圓角</label></th>
						<td>
							<div class="image-radius-control">
								<input id="image-radius-icon" name="siteIconPercent" type="range" min="0" max="45" step="1" value="<?php echo esc_attr( (string) $siteIconPercent ); ?>">
								<input id="image-radius-icon-number" class="small-text" type="number" min="0" max="45" value="<?php echo esc_attr( (string) $siteIconPercent ); ?>" aria-label="網站圖示圓角百分比">
								<span>%</span>
							</div>
							<div class="image-radius-preview" data-preview="icon">
								<img src="<?php echo esc_url( $previewSrc ); ?>" alt="" width="96" height="96">
								<span>網站圖示預覽</span>
							</div>
							<p class="description">以圖示邊長的百分比計算。約 22% 接近 App 圖示的弧度。儲存後會重寫網站圖示檔案。</p>
						</td>
					</tr>
				</table>
				<?php submit_button( '儲存設定' ); ?>
			</form>
			<p class="description">也可以在「外觀 → 自訂」的「圖片圓角」區塊調整。網站圖示網址會帶版本參數，避免 Cloudflare 繼續送出舊的直角圖。若分頁仍顯示舊圖，請關掉該分頁再開一次，或到網址列開啟網站圖示連結一次。</p>
		</div>
		<?php
	}
}
