<?php
namespace GalleryZip\Zipper;

class Zipper
{
	/**
	 * WordPress filesystem
	 * @var object
	 */
	private $filesystem = null;

	/**
	 * Internal error array
	 * @var array
	 */
	public $errors = array();

	/**
	 * Either to use PclZip or not
	 * @var boolean
	 */
	public static $pclzip = false;

	/**
	 * Flag if the cache directory already exists or not
	 * @var boolean
	 */
	public static $cache_dir_exists = false;

	/**
	 * Name of the cache directory
	 * @var string
	 */
	public static $cache_dir = 'galleryzip-cache/';

	/**
	 * Constructor
	 * - setup the WordPress filesystem
	 * - checks (and create) the cache directory
	 * - setup the zip-method (PclZip or ZipArchive)
	 */
	public function __construct() {
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$this->filesystem = &$wp_filesystem;

		if ( false == self::$cache_dir_exists || ! is_dir( self::$cache_dir ) )
			$this->create_cache_dir();

		self::$pclzip = ! class_exists( 'ZipArchive' );

	}

	/**
	 * Add message to internal error array
	 * @param	string	$msg	Message to add
	 * @return	boolean			Returns true if a message was set, else false
	 */
	protected function add_error( $msg = '' ) {
		array_push( $this->errors, $msg );
		return ! empty( $msg );
	}

	/**
	 * Creates the cache directory
	 * @return	string	self::$cache_dir	Complete path to the cache directory
	 */
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

	/**
	 * Converts absolute path to an url-path. Use WP_CONTENT_DIR/URL as base path
	 * @param	string	$path	Path to be converted
	 * @return	string			Converted path
	 */
	public function to_url( $path ) {
		return str_replace( WP_CONTENT_DIR, WP_CONTENT_URL . '/', $path );
	}

	/**
	 * Create a zip-file with name and path defined in target from a given file list
	 * @param	string	$target		Name and path of the zip file
	 * @param	string	$file_list	Array with pathes and filenames
	 * @return	bool				True on success, false on error
	 */
	public function zip_files( $target, $file_list ) {
		if ( ! is_array( $file_list ) )
			$file_list = (array) $file_list;

		if ( false == self::$pclzip )
			$result = $this->ziparchive( $target, $file_list );
		else
			$result = $this->pclzip( $target, $file_list );

		return $result;
	}

	/**
	 * Zipping files with ZipArchive
	 * @param	string	$target		Name and path of the zip file
	 * @param	string	$file_list	Array with pathes and filenames
	 * @return	bool				True on success, false on error
	 */
	protected function ziparchive( $target, $file_list ) {
		$zip = new \ZipArchive();

		if ( $zip->open( $target, \ZIPARCHIVE::CREATE ) !== true )
			return $this->add_error( "Could not create temporary zip archive {$target}" );

		foreach ( $file_list as $file ) {
			if ( file_exists( $file ) && is_readable( $file ) )
				$zip->addFile( $file, basename( $file ) );
		}

		$zip->close();

		return file_exists( $target );
	}

	/**
	 * Zipping files with PclZip
	 * @param	string	$target		Name and path of the zip file
	 * @param	string	$file_list	Array with pathes and filenames
	 * @return	bool				True on success, false on error
	 */
	protected function pclzip( $target, $file_list ) {
		if ( ! class_exists( 'PclZip' ) )
			require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';

		$archive = new \PclZip( $target );

		$zip = $archive->create( $file_list );
		if ( 0 == $zip ) {
			return $this->add_error( $archive->errorInfo( true ) );
		}

		return file_exists( $target );
	}

	/**
	 * Create a zip file from list with images
	 * @param	string	$zipname	Name of the zip-file (no path, no extension. just name)
	 * @param	array	$images		Array with full pathes to images
	 * @return	string				URL to the zip-file on success, empty string on error
	 */
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