<?php
/**
 * Remove plugin settings. Rounded site-icon files are left in place.
 *
 * @package ImageRadius
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$deleted = delete_option( 'image_radius' );
if ( false === $deleted && get_option( 'image_radius', null ) !== null ) {
	error_log( 'Image Radius: uninstall could not delete settings.' );
}

$backupDir = WP_CONTENT_DIR . '/uploads/image-radius-backup';
if ( is_dir( $backupDir ) ) {
	$files = glob( $backupDir . '/*' );
	if ( is_array( $files ) ) {
		foreach ( $files as $file ) {
			if ( is_file( $file ) && ! unlink( $file ) ) {
				error_log( 'Image Radius: uninstall could not delete backup file ' . $file );
			}
		}
	}
	if ( ! rmdir( $backupDir ) ) {
		error_log( 'Image Radius: uninstall could not remove backup directory.' );
	}
}
