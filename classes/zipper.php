<?php
namespace GalleryZip\Zipper;

class Zipper
{
	private $filesystem = null;

	public $errors = array();

	public static $cache_dir_exists = false;

	public static $cache_dir = 'galleryzip-cache/';

	public function __construct() {
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$this->filesystem = &$wp_filesystem;

		if ( false == self::$cache_dir_exists || ! is_dir( self::$cache_dir ) )
			$this->create_cache_dir();

	}

	protected function add_error( $msg = '' ) {
		array_push( $this->errors, $msg );
		return ! empty( $msg );
	}

	public function create_cache_dir() {

		if ( true === self::$cache_dir_exists )
			return self::$cache_dir;

		if ( true === is_dir( self::$cache_dir ) ) {
			self::$cache_dir_exists = true;
			return self::$cache_dir;
		}

		$cachedir = sprintf( '%s/%s', WP_CONTENT_DIR, ltrim( self::$cache_dir, '/' ) );
		$this->filesystem->mkdir( $cachedir, true );

		self::$cache_dir = rtrim( $cachedir, '/' ) . '/';
		self::$cache_dir_exists = true;

		return self::$cache_dir;

	}

	public function to_url( $filename ) {
		return str_replace( WP_CONTENT_DIR, WP_CONTENT_URL . '/', $filename );
	}

	public function zip_files( $target, $file_list ) {
		if ( ! is_array( $file_list ) )
			$file_list = (array) $file_list;

		$zip = new \ZipArchive();

		if ( $zip->open( $target, \ZIPARCHIVE::CREATE ) !== true )
			return $this->add_error( "Could not create temporary zip archive {$target}" );

		foreach ( $file_list as $file ) {
			if ( file_exists( $file ) && is_readable( $file ) )
				$zip->addFile( $file, basename( $file ) );
		}

		$zip->close();

		return true;
	}

	public function zip_images( $zipname, $images ) {
		$zipname = preg_replace( '/\.zip$/is', '', $zipname ) . '.zip';
		$target  = self::$cache_dir . ltrim( $zipname, '/' );

		if ( ! file_exists( $target ) )
			$is_zip  = $this->zip_files( $target, $images );
		else
			$is_zip = true;

		return ( true === $is_zip ) ? $this->to_url( $target ) : '';
	}
}