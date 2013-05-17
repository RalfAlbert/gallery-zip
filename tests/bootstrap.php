<?php
// Load WordPress test environment
// https://github.com/nb/wordpress-tests
//
if ( ! defined( 'PHPUnitClassesPath' ) )
	define( 'PHPUnitClassesPath', dirname( dirname( __FILE__ ) ) . '/classes/' );

// The path to wordpress-tests
$path = 'wordpress-tests/bootstrap.php';

if( file_exists( $path ) ) {
    require_once $path;
} /*else {
    exit( "Couldn't find path to wordpress-tests/bootstrap.php\n" );
}*/