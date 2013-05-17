<?php
namespace GalleryZip\Zipper;

class Zipper
{
	public $errors = array();

	private $filesystem = null;

	private $tempdir  = '/zipper_temp';
	private $tempfile = 'zip_temp.zip';

	public function __construct() {
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$this->filesystem = &$wp_filesystem;

	}

	protected function add_error( $msg = '' ) {
		array_push( $this->errors, $msg );
		return ! empty( $msg );
	}

	public function get_errors() {
		return $this->errors;
	}

	public function create_tempdir() {
		$this->tempdir = WP_CONTENT_DIR . $this->tempdir;

		return ( ! is_dir( $this->tempdir ) ) ?
			$this->filesystem->mkdir( $this->tempdir ) : true;
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
			return false;

		$target = $this->check_target( $target );

		if ( empty( $target ) )
			return false;

		$zip     = new \ZipArchive();
		$zipfile = $this->tempdir . $this->tempfile;

		if ( $zip->open( $zipfile, \ZIPARCHIVE::CREATE ) !== true )
			return $this->add_error( 'Could not create temporary zip archive ' . $zipfile );

		foreach ( $source_files as $file )
			$zip->addFile( $file, basename( $file ) );

		$zip->close();

		$copy = $this->filesystem->copy( $zipfile, $target );

		if ( ! $copy )
			return $this->add_error( "Cannot copy temporary zip file to target {$target}" );
		else
			unlink( $zipfile );

		return true;
	}

}