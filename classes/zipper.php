<?php
namespace GalleryZip\Zipper;

class Zipper
{
	public $errors = array();

	private $filesystem = null;

	private $tempdir  = '/zipper_temp';
	private $tempfile = 'zip_temp.zip';
	private static $tempdir_exists = false;

	public function __construct() {
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$this->filesystem = &$wp_filesystem;

		$tempdir = $this->get_tempdir();
		self::$tempdir_exists = is_dir( $tempdir );

	}

	protected function add_error( $msg = '' ) {
		array_push( $this->errors, $msg );
		return ! empty( $msg );
	}

	public function get_errors() {
		return $this->errors;
	}

	protected function get_tempdir() {
		return rtrim( WP_CONTENT_DIR . $this->tempdir, '/' ) . '/';
	}

	public function create_tempdir() {
		// nothing to do
		if ( true === self::$tempdir_exists )
			return true;

		$tempdir = $this->get_tempdir();

		return ( ! is_dir( $tempdir ) ) ?
			$this->filesystem->mkdir( $tempdir ) : true;
	}

	public function check_target( $target ) {
		if ( empty( $target ) )
			$this->add_error( 'No target filename given' );

		if ( basename( $target ) === $target )
			$this->add_error( "Cannot resolve path to {$target}" );

		if ( ! is_dir( dirname( $target ) ) || ! is_writeable( dirname( $target ) ) )
			$this->add_error( "Path to {$target} does not exists or is not writeable" );

		return empty( $this->errors ) ? $target : '';
	}

	public function check_source_dir( $source ) {
		if ( empty( $source ) )
			$this->add_error( 'No source given' );

		$source = rtrim( $source, '/' ) . '/';

		if ( ! is_dir( $source ) )
			$this->add_error( "Source {$source} is not a directory" );

		$files = glob( $source . '*' );

		if ( empty( $files ) )
			$this->add_error( "No files for zipping found in {$source}" );

		return empty( $this->errors ) ? $files : array();

	}

	public function zip_dir( $target, $source_dir ) {
		$source_files = $this->check_source_dir( $source_dir );

		if ( empty( $source_files ) )
			return $this->add_error( 'No source with files for zipping given' );

		$target = $this->check_target( $target );

		if ( empty( $target ) )
			return false;

		$zip     = new \ZipArchive();
		$tempdir = $this->get_tempdir();
		$zipfile = $tempdir . $this->tempfile;

		if ( $zip->open( $zipfile, \ZIPARCHIVE::CREATE ) !== true )
			return $this->add_error( "Could not create temporary zip archive {$zipfile}" );

		foreach ( $source_files as $file )
			$zip->addFile( $file, basename( $file ) );

		$zip->close();

		$copy = $this->filesystem->copy( $zipfile, $target, true );

		if ( ! $copy )
			return $this->add_error( "Cannot copy temporary zip file to target {$target}" );
		else
			unlink( $zipfile );

		return true;
	}

	public function copy_files( $file_list ) {
		if ( empty( $file_list ) || ! is_array( $file_list ) )
			return false;

		if ( true !== self::$tempdir_exists )
			$this->create_tempdir();

		$tempdir = $this->get_tempdir();

		// trash all content inside tempdir
		$trash = glob( $tempdir . '*' );
		array_walk( $trash, function( $file ) { unlink( $file ); } );

		foreach ( $file_list as $file ) {
			$destination = sprintf( '%s/%s', $tempdir, basename( $file ) );
			$this->filesystem->copy( $file, $destination, true, '0777' );
		}

		return true;
	}

	public function zip_images( $zipname, $images ) {
		$this->copy_files( $images );

		$tempdir = $this->get_tempdir();
		$target  = WP_CONTENT_DIR . '/' . $zipname;

		$this->zip_dir( $target, $tempdir );
	}
}