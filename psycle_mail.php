<?php
/*
  Plugin Name: Psycle WordPress Mail Plugin
  Description: Filters required for mail functions in wordpress.
  Version: 0.1
  Author: James Robinson <james.robinson@psycle.com>
  Network: True
 */

if(version_compare(PHP_VERSION, '5.3.0') >= 0) {
	include_once( __DIR__ . DIRECTORY_SEPARATOR . 'psycle_mail.class.php');
}