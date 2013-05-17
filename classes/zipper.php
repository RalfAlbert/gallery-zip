<?php
class Zipper
{
	public $errors = array();

	private $filesystem = null;


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
	}

	public function get_errors() {
		return $this->errors;
	}

	public function zip_dir( $target, $source ) {
		if ( empty( $source ) )
			throw new Exception( 'No source given' );

		if ( ! is_dir( $source ) ) {
			$this->add_error( 'Source is not a directory' );
			return false;
		}
	}

}