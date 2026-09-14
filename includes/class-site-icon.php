<?php
/**
 * Bake rounded corners into the WordPress site icon files.
 *
 * @package ImageRadius
 */

namespace ImageRadius;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Regenerates site-icon PNG sizes with a rounded-rectangle mask.
 */
class SiteIcon {
	/**
	 * @var Options
	 */
	private $options;

	public function __construct( Options $options ) {
		$this->options = $options;
	}

	public function register(): void {
		add_action( 'update_option_site_icon', array( $this, 'onSiteIconChanged' ), 20, 2 );
		add_filter( 'get_site_icon_url', array( $this, 'bustUrl' ), 20, 3 );
	}

	/**
	 * Append a version query so Cloudflare and browsers do not keep the old square PNG.
	 *
	 * @param string $url Site icon URL.
	 * @param int    $size Requested size.
	 * @param int    $blogId Blog ID.
	 * @return string
	 */
	public function bustUrl( $url, $size, $blogId ) {
		unset( $size, $blogId );
		if ( ! is_string( $url ) || $url === '' ) {
			return $url;
		}
		$version = $this->urlVersion( $url );
		if ( $version === '' ) {
			return $url;
		}
		return add_query_arg( 'ir', $version, $url );
	}

	private function urlVersion( string $url ): string {
		$parts   = array( (string) $this->options->siteIconPercent() );
		$uploads = wp_get_upload_dir();
		if ( empty( $uploads['error'] ) && ! empty( $uploads['baseurl'] ) && ! empty( $uploads['basedir'] ) ) {
			$baseUrl = trailingslashit( $uploads['baseurl'] );
			if ( strpos( $url, $baseUrl ) === 0 ) {
				$relative = substr( $url, strlen( $baseUrl ) );
				$relative = strtok( $relative, '?' );
				$path     = trailingslashit( $uploads['basedir'] ) . ltrim( (string) $relative, '/' );
				if ( is_file( $path ) ) {
					$mtime = filemtime( $path );
					if ( $mtime !== false ) {
						$parts[] = (string) $mtime;
					}
				}
			}
		}
		return implode( '-', $parts );
	}

	/**
	 * Re-apply rounding after Customizer / Settings updates the site icon.
	 *
	 * @param mixed $oldValue Previous attachment ID.
	 * @param mixed $newValue New attachment ID.
	 */
	public function onSiteIconChanged( $oldValue, $newValue ): void {
		unset( $oldValue );
		if ( ! $this->options->enabled() || ! $this->options->applySiteIcon() ) {
			return;
		}
		$result = $this->apply( (int) $newValue, true );
		if ( is_wp_error( $result ) ) {
			error_log( 'Image Radius: site icon update failed: ' . $result->get_error_message() );
		}
	}

	/**
	 * @param int  $attachmentId Site icon attachment ID. 0 reads the option.
	 * @param bool $replaceBackup Copy current files as the unrounded source first.
	 * @return true|\WP_Error
	 */
	public function apply( int $attachmentId = 0, bool $replaceBackup = false ) {
		if ( ! $this->options->enabled() || ! $this->options->applySiteIcon() ) {
			return true;
		}
		$percent = $this->options->siteIconPercent();
		if ( $percent <= 0 ) {
			return true;
		}
		if ( $attachmentId <= 0 ) {
			$attachmentId = (int) get_option( 'site_icon' );
		}
		if ( $attachmentId <= 0 ) {
			return true;
		}

		$paths = $this->iconPaths( $attachmentId );
		if ( $paths === array() ) {
			return new \WP_Error( 'image_radius_site_icon_missing', '找不到網站圖示檔案。' );
		}

		$backupDir = $this->backupDir();
		if ( $backupDir === '' ) {
			return new \WP_Error( 'image_radius_backup_dir', '無法建立網站圖示備份目錄。' );
		}

		foreach ( $paths as $path ) {
			$backupPath = $backupDir . '/' . basename( $path );
			if ( $replaceBackup || ! is_file( $backupPath ) ) {
				$copied = copy( $path, $backupPath );
				if ( $copied === false ) {
					return new \WP_Error( 'image_radius_backup', '無法備份網站圖示：' . basename( $path ) );
				}
			}
			$source = is_file( $backupPath ) ? $backupPath : $path;
			$rounded = $this->roundFile( $source, $path, $percent );
			if ( is_wp_error( $rounded ) ) {
				return $rounded;
			}
		}

		$this->refreshAttachmentFilesizes( $attachmentId );
		return true;
	}

	/**
	 * @return array<int, string>
	 */
	private function iconPaths( int $attachmentId ): array {
		$main = get_attached_file( $attachmentId );
		if ( ! is_string( $main ) || $main === '' || ! is_file( $main ) ) {
			return array();
		}
		$paths = array( $main );
		$meta  = wp_get_attachment_metadata( $attachmentId );
		if ( ! is_array( $meta ) || empty( $meta['sizes'] ) || ! is_array( $meta['sizes'] ) ) {
			return $paths;
		}
		$baseDir = trailingslashit( dirname( $main ) );
		foreach ( $meta['sizes'] as $size ) {
			if ( empty( $size['file'] ) ) {
				continue;
			}
			$sizePath = $baseDir . $size['file'];
			if ( is_file( $sizePath ) ) {
				$paths[] = $sizePath;
			}
		}
		return array_values( array_unique( $paths ) );
	}

	private function backupDir(): string {
		$uploads = wp_get_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			error_log( 'Image Radius: upload dir error: ' . $uploads['error'] );
			return '';
		}
		$dir = trailingslashit( $uploads['basedir'] ) . 'image-radius-backup';
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			error_log( 'Image Radius: could not create ' . $dir );
			return '';
		}
		return $dir;
	}

	/**
	 * @return true|\WP_Error
	 */
	private function roundFile( string $sourcePath, string $destPath, int $percent ) {
		$convert = $this->convertBinary();
		if ( $convert !== '' ) {
			return $this->roundWithImageMagick( $convert, $sourcePath, $destPath, $percent );
		}
		return $this->roundWithGd( $sourcePath, $destPath, $percent );
	}

	private function convertBinary(): string {
		$candidates = array( '/bin/convert', '/usr/bin/convert' );
		foreach ( $candidates as $path ) {
			if ( is_executable( $path ) ) {
				return $path;
			}
		}
		return '';
	}

	/**
	 * @return true|\WP_Error
	 */
	private function roundWithImageMagick( string $convert, string $sourcePath, string $destPath, int $percent ) {
		$size = @getimagesize( $sourcePath );
		if ( $size === false || empty( $size[0] ) || empty( $size[1] ) ) {
			return new \WP_Error( 'image_radius_size', '無法讀取圖示尺寸：' . basename( $sourcePath ) );
		}
		$width  = (int) $size[0];
		$height = (int) $size[1];
		$radius = (int) round( min( $width, $height ) * ( $percent / 100 ) );
		if ( $radius < 1 ) {
			$copied = copy( $sourcePath, $destPath );
			if ( $copied === false ) {
				return new \WP_Error( 'image_radius_copy', '無法寫入網站圖示：' . basename( $destPath ) );
			}
			return true;
		}

		$tmpDir = trailingslashit( get_temp_dir() ) . 'image-radius-' . wp_generate_password( 8, false );
		if ( ! wp_mkdir_p( $tmpDir ) ) {
			return new \WP_Error( 'image_radius_tmp', '無法建立暫時目錄。' );
		}

		$opaque = $tmpDir . '/opaque.png';
		$mask   = $tmpDir . '/mask.png';
		$output = $tmpDir . '/rounded.png';

		$cmdOpaque = escapeshellarg( $convert ) . ' ' . escapeshellarg( $sourcePath ) . ' -alpha off PNG32:' . escapeshellarg( $opaque );
		$cmdMask = sprintf(
			'%s -size %dx%d xc:none -fill white -draw %s PNG32:%s',
			escapeshellarg( $convert ),
			$width,
			$height,
			escapeshellarg(
				sprintf(
					'roundrectangle 0,0 %d,%d %d,%d',
					$width - 1,
					$height - 1,
					$radius,
					$radius
				)
			),
			escapeshellarg( $mask )
		);
		$cmdComposite = sprintf(
			'%s %s %s -alpha off -compose CopyOpacity -composite PNG32:%s',
			escapeshellarg( $convert ),
			escapeshellarg( $opaque ),
			escapeshellarg( $mask ),
			escapeshellarg( $output )
		);

		foreach ( array( $cmdOpaque, $cmdMask, $cmdComposite ) as $command ) {
			$exec = $this->runCommand( $command );
			if ( is_wp_error( $exec ) ) {
				$this->cleanupDir( $tmpDir );
				return $exec;
			}
		}

		if ( ! is_file( $output ) ) {
			$this->cleanupDir( $tmpDir );
			return new \WP_Error( 'image_radius_output', '圓角圖示沒有產出。' );
		}
		$copied = copy( $output, $destPath );
		$this->cleanupDir( $tmpDir );
		if ( $copied === false ) {
			return new \WP_Error( 'image_radius_write', '無法覆寫網站圖示：' . basename( $destPath ) );
		}
		return true;
	}

	/**
	 * @return true|\WP_Error
	 */
	private function runCommand( string $command ) {
		$output     = array();
		$statusCode = 0;
		exec( $command . ' 2>&1', $output, $statusCode );
		if ( $statusCode !== 0 ) {
			$message = implode( "\n", $output );
			return new \WP_Error( 'image_radius_convert', '處理圖示失敗：' . $message );
		}
		return true;
	}

	/**
	 * @return true|\WP_Error
	 */
	private function roundWithGd( string $sourcePath, string $destPath, int $percent ) {
		$image = @imagecreatefrompng( $sourcePath );
		if ( $image === false ) {
			return new \WP_Error( 'image_radius_gd_read', 'GD 無法讀取：' . basename( $sourcePath ) );
		}
		$width  = imagesx( $image );
		$height = imagesy( $image );
		$radius = (int) round( min( $width, $height ) * ( $percent / 100 ) );
		imagesavealpha( $image, true );

		$canvas = imagecreatetruecolor( $width, $height );
		if ( $canvas === false ) {
			imagedestroy( $image );
			return new \WP_Error( 'image_radius_gd_canvas', 'GD 無法建立畫布。' );
		}
		imagealphablending( $canvas, false );
		imagesavealpha( $canvas, true );
		$transparent = imagecolorallocatealpha( $canvas, 0, 0, 0, 127 );
		if ( $transparent === false ) {
			imagedestroy( $image );
			imagedestroy( $canvas );
			return new \WP_Error( 'image_radius_gd_color', 'GD 無法配置透明色。' );
		}
		imagefilledrectangle( $canvas, 0, 0, $width, $height, $transparent );

		$this->gdFilledRoundedRect( $canvas, $width, $height, $radius, $image );

		$saved = imagepng( $canvas, $destPath );
		imagedestroy( $image );
		imagedestroy( $canvas );
		if ( $saved === false ) {
			return new \WP_Error( 'image_radius_gd_write', 'GD 無法寫入：' . basename( $destPath ) );
		}
		return true;
	}

	/**
	 * @param \GdImage $canvas Destination with alpha.
	 * @param \GdImage $source Source bitmap.
	 */
	private function gdFilledRoundedRect( $canvas, int $width, int $height, int $radius, $source ): void {
		for ( $y = 0; $y < $height; $y++ ) {
			for ( $x = 0; $x < $width; $x++ ) {
				if ( ! $this->pointInsideRoundedRect( $x, $y, $width, $height, $radius ) ) {
					continue;
				}
				$color = imagecolorat( $source, $x, $y );
				imagesetpixel( $canvas, $x, $y, $color );
			}
		}
	}

	private function pointInsideRoundedRect( int $x, int $y, int $width, int $height, int $radius ): bool {
		if ( $radius <= 0 ) {
			return true;
		}
		$r = $radius;
		$insideX = ( $x >= $r && $x < $width - $r );
		$insideY = ( $y >= $r && $y < $height - $r );
		if ( $insideX || $insideY ) {
			return true;
		}
		$cx = $x < $r ? $r : $width - 1 - $r;
		$cy = $y < $r ? $r : $height - 1 - $r;
		$dx = $x - $cx;
		$dy = $y - $cy;
		return ( $dx * $dx + $dy * $dy ) <= ( $r * $r );
	}

	private function refreshAttachmentFilesizes( int $attachmentId ): void {
		$meta = wp_get_attachment_metadata( $attachmentId );
		if ( ! is_array( $meta ) ) {
			return;
		}
		$main = get_attached_file( $attachmentId );
		if ( is_string( $main ) && is_file( $main ) ) {
			$bytes = filesize( $main );
			if ( $bytes !== false ) {
				$meta['filesize'] = $bytes;
			}
		}
		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) && is_string( $main ) ) {
			$baseDir = trailingslashit( dirname( $main ) );
			foreach ( $meta['sizes'] as $sizeName => $sizeMeta ) {
				if ( empty( $sizeMeta['file'] ) ) {
					continue;
				}
				$sizePath = $baseDir . $sizeMeta['file'];
				if ( ! is_file( $sizePath ) ) {
					continue;
				}
				$bytes = filesize( $sizePath );
				if ( $bytes !== false ) {
					$meta['sizes'][ $sizeName ]['filesize'] = $bytes;
				}
			}
		}
		wp_update_attachment_metadata( $attachmentId, $meta );
	}

	private function cleanupDir( string $dir ): void {
		$files = glob( trailingslashit( $dir ) . '*' );
		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) && ! unlink( $file ) ) {
					error_log( 'Image Radius: could not delete temp file ' . $file );
				}
			}
		}
		if ( is_dir( $dir ) && ! rmdir( $dir ) ) {
			error_log( 'Image Radius: could not remove temp dir ' . $dir );
		}
	}
}
