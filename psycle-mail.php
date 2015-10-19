<?php
/**
 * Plugin Name: Psycle WordPress Mail Plugin
 * Description: Filters required for mail functions in wordpress.
 * Author: James Robinson <james.robinson@psycle.com>
 * Network: True
 * Version: 0.1
 *
 * @package PsyclePlugins
 * @subpackage Main
 */

if ( version_compare( PHP_VERSION, '5.3.0' ) >= 0 ) {
	include_once( __DIR__ . DIRECTORY_SEPARATOR . 'psycle-mail.class.php' );
}
