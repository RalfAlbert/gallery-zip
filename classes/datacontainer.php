<?php
namespace GalleryZip;

class GalleryZip_DataContainer
{
	const TRANSIENT_KEY = 'galleryzip-transient';

	public static $data = array();

	protected static $protected = array();

	public function __construct() {
		if ( empty( self::$data ) && false != ( $transient = get_transient( self::TRANSIENT_KEY ) ) )
			self::$data = $transient;

		// save the data in a transient when the script end
		register_shutdown_function( array( __CLASS__, 'shutdown' ) );
	}

	public function __set( $name, $value ) {
		self::$data[$name] = $value;
	}

	public function __get( $name ) {
		if ( isset( self::$data[$name] ) )
			return self::$data[$name];
		else
			return null;
	}

	public static function shutdown() {
		// save the transient for one hour
		set_transient( self::TRANSIENT_KEY, self::$data, 3600 );
	}

}
