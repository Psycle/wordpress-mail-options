<?php

/*
  Plugin Name: Psycle WordPress Mail Plugin
  Description: Filters required for mail functions in wordpress.
  Version: 0.1
  Author: James Robinson <james.robinson@psycle.com>
  Network: True
 */

namespace Psycle\WordPress\Mu;

MailOptions::getInstance()->init();

class MailOptions {
	protected $_reply_to_set = false;
	protected $_option_page_slug = 'psycle_mail_options';
	protected $_form_post_array_name = 'psycle_mail';
	protected $_option_prefix = '_psycle_mail_';
	protected $_option_form_fields = array(
		'from' => array(
			'type' => 'email',
			'label' => 'From email',
			'description' => 'The From email address for the message.'
		),
		'from_name' => array(
			'type' => 'text',
			'label' => 'From name',
			'description' => 'The From name of the message.',
		),
		'return_path' => array(
			'type' => 'email',
			'label' => 'Return path',
			'description' => 'The Return-Path of the message.<br>If empty, it will be set to either From or Sender.',
		),
		'sender' => array(
			'type' => 'email',
			'label' => 'Reply-To address',
			'description' => 'The Sender email (Return-Path) of the message. <br>If not empty, will be sent via -f to sendmail or as \'MAIL FROM\' in smtp mode. This will become the \'Reply-To\' address if one isn\'t already set.'
		),
		'reply_to_name' => array(
			'type' => 'email',
			'label' => 'Reply-To name',
			'description' => 'TThis will become the \'Reply-To\' name if one isn\'t already set.'
		),
	);

	/**
	 *
	 * @var MailOptions 
	 */
	private static $_instance;

	/**
	 * Singleton pattern
	 * 
	 * @return MailOptions
	 */
	public static function getInstance() {
		if ( is_null( self::$_instance ) ) {
			$mailPlugin = new MailOptions();
			self::$_instance = $mailPlugin;
		}
		return self::$_instance;
	}

	/**
	 * Check that the class hasn't already been instantiated. Using singleton pattern.
	 * 
	 * @throws Exception
	 */
	public function __construct() {
		if ( !is_null( self::$_instance ) ) {
			throw new Exception( 'Cannot insantiate Psycle_Mail class twice. Please use the Psycle_Mail::getInstance() method' );
		}
	}

	/**
	 * Set all required actions and filters.
	 */
	public function init() {
		\add_action( 'phpmailer_init', array( $this, 'actionPhpMailerInit' ) );
		\add_action( 'admin_init', array( $this, 'actionInit' ) );
		\add_filter( 'psycle_mail_form_fields', array( $this, 'filterPsycleMailFormFields' ), 1, 1 );
		\add_filter( 'wp_mail', array( $this, 'filterWpMail' ), 1, 1);		
	}
	
	/**
	 * We want to check any custom headers passed to the wp_mail function so that we don't add a second Reply-To address.
	 * 
	 * @param array $args
	 */
	public function filterWpMail($args) {
		$headers = $args['headers'];
		$this->_reply_to_set = false;
		if( is_string( $headers ) ) {
			if(preg_match('@Reply\-To\:@', $headers)) {
				$this->_reply_to_set = true;
			}
		} elseif( is_array( $headers ) ) {
			foreach ($headers AS $header) {
				if(preg_match('@Reply\-To\:@', $header)) {
					$this->_reply_to_set = true;
				}
			}
		}		
	}
	
	/**
	 * Callback used to modify PHPMailer headers before sending.
	 */
	public function actionPhpMailerInit( \PHPMailer &$phpMailer ) {		
		$originalFrom = $phpMailer->From;
		$phpMailer->set( 'From', $this->getOption( 'from' ) );
		$phpMailer->set( 'FromName', $this->getOption( 'from_name' ) );
		
		
		if(empty($phpMailer->Sender) && $originalFrom != 'wordpress@' . $this->getHostName()) {
			if(empty($this->getOption( 'sender' ))) {
				$phpMailer->set( 'Sender', $originalFrom );
			} else {
				$phpMailer->set( 'Sender', $this->getOption( 'sender' ) );		
			}
		} else {
			$phpMailer->set( 'Sender', $this->getOption( 'sender' ) );
		}		
		
		$phpMailer->ReturnPath = $this->getOption( 'return_path' );	
		
		if(!$this->_reply_to_set) {
			$phpMailer->AddReplyTo( $this->getOption( 'sender' ), $this->getOption( 'reply_to_name', $this->getOption( 'from_name' ) ) );
		}		
	}

	/**
	 * Modify form fields to add defaults.
	 * 
	 * @param array $formFields
	 */
	public function filterPsycleMailFormFields( $formFields ) {
		foreach ( $formFields AS $fieldName => $fieldValue ) {
			switch ( $fieldName ) {
				case 'sender':
				case 'from':
				case 'return_path':
					$formFields[ $fieldName ][ 'default' ] = 'webmaster@' . $this->getDefaultMailDomain();
					break;
				case 'from_name':
					$formFields[ $fieldName ][ 'default' ] = 'Webmaster at ' . $this->getDefaultMailDomain();
					break;
			}
		}
		return $formFields;
	}
	
	/**
	 * Get the default hostname for mail and allow other plugins to filter it.
	 * 
	 * @return string
	 */
	public function getDefaultMailDomain() {
		return apply_filters('psycle_mail_mail_domain', $this->getHostName());
	}
	
	/**
	 * Get the host name for the site.
	 * 
	 * @return string
	 */
	public function getHostName() {
		$urlParts = parse_url( home_url() );
		return isset($urlParts[ 'host' ]) ? $urlParts[ 'host' ] : 'localhost';
	}

	/**
	 * The callback method used for the 'init' action.
	 * 
	 */
	public function actionInit() {
		$this->addFieldsToSettingsPage();
	}

	/**
	 * Use the settings API to add fields to the general settings page.
	 * 
	 */
	public function addFieldsToSettingsPage() {
		add_settings_section(
				'psy_mail_options', 'Email options', array( $this, 'callbackSettingsSection' ), 'general'
		);
		foreach ( $this->getOptionFormFields() AS $optionFormField => $optionFormFieldSettings ) {
			register_setting( 'general', $this->getOptionKey( $optionFormField ) );
			$formFieldSettings = wp_parse_args( $optionFormFieldSettings, array(
				'label' => ucfirst( str_replace( '_', ' ', $optionFormField ) ),
				'type' => 'text',
				'size' => '60',
					) );
			$formFieldSettings[ 'name' ] = $optionFormField;
			\add_settings_field( $optionFormField, $formFieldSettings[ 'label' ], array( $this, 'callbackSettingsField' ), 'general', 'psy_mail_options', $formFieldSettings );
		}
	}

	/**
	 * Callback for an individual form field in the admin page.
	 * 
	 * @param array $formFieldSettings
	 */
	public function callbackSettingsField( $formFieldSettings ) {
		extract( $formFieldSettings );
		switch ( $type ) {
			case 'textarea':
				echo sprintf( '<textarea name="%1$s">%2$s</textarea>', $this->getOptionKey( $name ), $this->getOption( $name ) );
				break;
			default:
				echo sprintf( '<input type="%1$s" name="%2$s" value="%3$s" size="%4$s">', $type, $this->getOptionKey( $name ), $this->getOption( $name ), $size );
				break;
		}
		if(isset($formFieldSettings['description'])) {
			echo '<p class="description">' . $formFieldSettings['description'] . '</p>';
		}
	}

	/**
	 * Callback to display admin settings page documentation.
	 * 
	 */
	public function callbackSettingsSection() {
		echo '<p>Mail settings to override from and reply to addresses</p>';
	}

	/**
	 * Get an option from wp_options. Uses the prefix from the class to namespace option keys
	 * 
	 * @param string $option
	 * @param mixed $default
	 * @return mixed
	 */
	public function getOption( $option, $default = null ) {
		$optionKey = $this->getOptionKey( $option );
		if ( is_null( $default ) ) {
			$fields = $this->getOptionFormFields();
			$default = isset( $fields[ $option ][ 'default' ] ) ? $fields[ $option ][ 'default' ] : null;
		}
		$value = \get_option( $optionKey, $default );
		return $value;
	}

	/**
	 * Get the option key with the namespaced prefix.
	 * 
	 * @param string $key
	 * @return string
	 */
	public function getOptionKey( $key ) {
		return $this->getOptionPrefix() . $key;
	}

	/**
	 * Get the option prefix to allow other plugins to change the options namespace.
	 * 
	 * @return string
	 */
	public function getOptionPrefix() {
		return apply_filters( 'psycle_mail_option_prefix', $this->_option_prefix );
	}

	/**
	 * Get the form fields used in the admin settings page. Allow other plugins to add and remove fields via a filter.
	 * 
	 * @return array
	 */
	public function getOptionFormFields() {
		$formFields = apply_filters( 'psycle_mail_form_fields', $this->_option_form_fields );
		if ( !is_array( $formFields ) ) {
			throw new Exception( 'psycle_mail_form_fields filter should always return an array.' );
		}
		return $formFields;
	}

}
