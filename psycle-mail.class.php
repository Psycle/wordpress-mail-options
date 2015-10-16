<?php
/**
 * Main class file for Psycle Mail Options WordPress plugin.
 *
 * @package Psycle\WordPress\Mu
 * @subpackage MailOptions
 */

namespace Psycle\WordPress\Mu;

MailOptions::get_instance()->init();

/**
 * Class MailOptions
 * @package Psycle\WordPress\Mu
 */
class MailOptions {
	/**
	 * Has the Reply-To header already been set?
	 *
	 * @var bool
	 */
	protected $_reply_to_set = false;

	/**
	 * The array key for the posted variables when saving in the settings page.
	 *
	 * @var string
	 */
	protected $_form_post_array_name = 'psycle_mail';

	/**
	 * The prefixed used for the option keys.
	 *
	 * @var string
	 */
	protected $_option_prefix = '_psycle_mail_';

	/**
	 * The form fields shown in the settings page of wp-admin.
	 *
	 * @var array
	 */
	protected $_option_form_fields = array(
		'from' => array(
			'type' => 'email',
			'label' => 'From email',
			'description' => 'The From email address for the message.',
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
			'description' => 'The Sender email (Return-Path) of the message. <br>If not empty, will be sent via -f to sendmail or as \'MAIL FROM\' in smtp mode. This will become the \'Reply-To\' address if one isn\'t already set.',
		),
		'reply_to_name' => array(
			'type' => 'email',
			'label' => 'Reply-To name',
			'description' => 'This will become the \'Reply-To\' name if one isn\'t already set.',
		),
	);

	/**
	 * The instance for the singleton pattern.
	 *
	 * @var MailOptions
	 */
	private static $_instance;

	/**
	 * Singleton pattern
	 *
	 * @codeCoverageIgnore
	 * @return MailOptions
	 */
	public static function get_instance() {
		if ( is_null( static::$_instance ) ) {
			$mailPlugin = new MailOptions();
			static::$_instance = $mailPlugin;
		}
		return static::$_instance;
	}

	/**
	 * Check that the class hasn't already been instantiated. Using singleton pattern.
	 *
	 * @codeCoverageIgnore
	 * @throws \Exception This will throw an exception if an instance already exists.
	 */
	public function __construct() {
		if ( ! is_null( self::$_instance ) ) {
			throw new \Exception( 'Cannot insantiate Psycle_Mail class twice. Please use the Psycle_Mail::get_instance() method' );
		}
	}

	/**
	 * Set all required actions and filters.
	 */
	public function init() {
		\add_action( 'phpmailer_init', array( $this, 'action_php_mailerinit' ) );
		\add_action( 'admin_init', array( $this, 'action_init' ) );
		\add_filter( 'psycle_mail_form_fields', array( $this, 'filter_psycle_mail_form_fields' ), 1, 1 );
		\add_filter( 'wp_mail', array( $this, 'filter_wp_mail' ), 1, 1 );
	}

	/**
	 * We want to check any custom headers passed to the wp_mail function so that we don't add a second Reply-To address.
	 *
	 * @param array $args A list of arguments passed to the wp_mail() function.
	 * @return The un-modified $args array. We don't want to do anthing to the passed arguments we just want to check for a Reply-To header.
	 */
	public function filter_wp_mail( $args ) {
		if ( isset( $args['headers'] ) ) {
			$headers = $args['headers'];
			$this->_reply_to_set = false;
			if ( is_string( $headers ) ) {
				if ( preg_match( '@Reply\-To\:@', $headers ) ) {
					$this->_reply_to_set = true;
				}
			} elseif ( is_array( $headers ) ) {
				foreach ( $headers as $header ) {
					if ( preg_match( '@Reply\-To\:@', $header ) ) {
						$this->_reply_to_set = true;
					}
				}
			}
		}

		return $args;
	}

	/**
	 * Callback used to modify PHPMailer headers before sending.
	 *
	 * @param \PHPMailer $phpMailer The PHPMailer instance.
	 */
	public function action_php_mailerinit( \PHPMailer &$phpMailer ) {
		$originalFrom = $phpMailer->From;
		$phpMailer->set( 'From', $this->get_option( 'from' ) );

		$fromName = $this->get_option( 'from_name' );

		if ( ! empty( $fromName ) ) {
			$phpMailer->set( 'FromName', $fromName );
		}

		$senderAddress = $this->get_option( 'sender' );

		if ( empty( $phpMailer->Sender ) && 'wordpress@' . $this->get_host_name() != $originalFrom ) {
			if ( ! $phpMailer->validateAddress( $senderAddress ) ) {
				$phpMailer->set( 'Sender', $originalFrom );
			} else {
				$phpMailer->set( 'Sender', $senderAddress );
			}
		} elseif ( $phpMailer->validateAddress( $senderAddress ) ) {
			$phpMailer->set( 'Sender', $senderAddress );
		}

		$returnPath = $this->get_option( 'return_path' );
		if ( $phpMailer->validateAddress( $returnPath ) ) {
			$phpMailer->ReturnPath = $this->get_option( 'return_path' );
		}

		$replyToOption = $this->get_option( 'sender' );
		if ( ! $this->_reply_to_set && ! empty( $replyToOption ) ) {
			$phpMailer->AddReplyTo( $replyToOption, $this->get_option( 'reply_to_name', $this->get_option( 'from_name' ) ) );
		}
	}

	/**
	 * Modify form fields to add defaults.
	 *
	 * @param array $formFields Filter to add defaults to settings form fields.
	 */
	public function filter_psycle_mail_form_fields( $formFields ) {
		foreach ( $formFields as $fieldName => $fieldValue ) {
			switch ( $fieldName ) {
				case 'sender':
				case 'from':
				case 'return_path':
					$formFields[ $fieldName ]['default'] = 'webmaster@' . $this->get_default_mail_domain();
					break;
				case 'from_name':
					$formFields[ $fieldName ]['default'] = 'Webmaster at ' . $this->get_default_mail_domain();
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
	public function get_default_mail_domain() {
		$hostname = $this->get_host_name();
		$hostname = preg_replace( '@^www\.@','',$hostname );
		return apply_filters( 'psycle_mail_mail_domain', $hostname );
	}

	/**
	 * Get the host name for the site.
	 *
	 * @return string
	 */
	public function get_host_name() {
		$urlParts = parse_url( home_url() );
		return isset( $urlParts['host'] ) ? $urlParts['host'] : 'localhost';
	}

	/**
	 * The callback method used for the 'init' action.
	 */
	public function action_init() {
		$this->add_fields_to_settings_page();
	}

	/**
	 * Use the settings API to add fields to the general settings page.
	 */
	public function add_fields_to_settings_page() {
		add_settings_section(
			'psy_mail_options',
			'Email options',
			array( $this, 'callback_settings_section' ),
			'general'
		);
		foreach ( $this->get_option_form_fields() as $optionFormField => $optionFormFieldSettings ) {
			register_setting( 'general', $this->get_option_key( $optionFormField ) );
			$formFieldSettings = wp_parse_args( $optionFormFieldSettings, array(
				'label' => ucfirst( str_replace( '_', ' ', $optionFormField ) ),
				'type' => 'text',
				'size' => '60',
					) );
			$formFieldSettings['name'] = $optionFormField;
			\add_settings_field( $optionFormField, $formFieldSettings['label'], array( $this, 'callback_settings_field' ), 'general', 'psy_mail_options', $formFieldSettings );
		}
	}

	/**
	 * Callback for an individual form field in the admin page.
	 *
	 * @param array $formFieldSettings The config to display the form field.
	 */
	public function callback_settings_field( $formFieldSettings ) {
		extract( $formFieldSettings );
		switch ( $type ) {
			case 'textarea':
				echo sprintf( '<textarea name="%1$s">%2$s</textarea>', esc_attr( $this->get_option_key( $name ) ), esc_html( $this->get_option( $name ) ) );
				break;
			default:
				echo sprintf( '<input type="%1$s" name="%2$s" value="%3$s" size="%4$s">', esc_attr( $type ), esc_html( $this->get_option_key( $name ) ), esc_html( $this->get_option( $name ) ), esc_attr( $size ) );
				break;
		}
		if ( isset( $formFieldSettings['description'] ) ) {
			echo '<p class="description">' . esc_html( $formFieldSettings['description'] ) . '</p>';
		}
	}

	/**
	 * Callback to display admin settings page documentation.
	 */
	public function callback_settings_section() {
		echo '<p>Mail settings to override from and reply to addresses</p>';
	}

	/**
	 * Get an option from wp_options. Uses the prefix from the class to namespace option keys
	 *
	 * @param string $option The option key.
	 * @param mixed  $default The default value to return if the key is not found.
	 * @return mixed
	 */
	public function get_option( $option, $default = null ) {
		$optionKey = $this->get_option_key( $option );
		if ( is_null( $default ) ) {
			$fields = $this->get_option_form_fields();
			$default = isset( $fields[ $option ]['default'] ) ? $fields[ $option ]['default'] : null;
		}
		$value = \get_option( $optionKey, $default );
		error_log($option . ' => ' . $optionKey . ' => ' . $value);
		$value = \apply_filters( 'psycle_mailer_option_' . $option, $value );

		return $value;
	}

	/**
	 * Allow setting of the options directly.
	 *
	 * @param string $option The option key without a prefix.
	 * @param mixed  $value The value to set.
	 */
	public function set_option( $option, $value ) {
		// We only want to set options that are used by this plugin.
		if ( array_key_exists( $option, $this->get_option_form_fields() ) ) {
			$optionKey = $this->get_option_key( $option );
			\update_option( $optionKey, $value, true );
		}
	}

	/**
	 * Get the option key with the namespaced prefix.
	 *
	 * @param string $key The base option key.
	 * @return string The complete option key.
	 */
	public function get_option_key( $key ) {
		return $this->get_option_prefix() . $key;
	}

	/**
	 * Get the option prefix to allow other plugins to change the options namespace.
	 *
	 * @return string
	 */
	public function get_option_prefix() {
		return apply_filters( 'psycle_mail_option_prefix', $this->_option_prefix );
	}

	/**
	 * Get the form fields used in the admin settings page. Allow other plugins to add and remove fields via a filter.
	 *
	 * @return array
	 * @throws \Exception Thrown when the filter doesn't return an array.
	 */
	public function get_option_form_fields() {
		$formFields = apply_filters( 'psycle_mail_form_fields', $this->_option_form_fields );
		if ( ! is_array( $formFields ) ) {
			throw new \Exception( 'psycle_mail_form_fields filter should always return an array.' );
		}
		return $formFields;
	}

	/**
	 * Is the reply to set. This function only works if the 'wp_mail' has been run.
	 *
	 * @return bool
	 */
	public function get_reply_to_set() {
		return $this->_reply_to_set;
	}

	/**
	 * Is the reply to set. This function only works if the 'wp_mail' has been run.
	 *
	 * @param boolean $is_set Is the Reply-To header set.
	 */
	public function set_reply_to_set( $is_set ) {
		$this->_reply_to_set = $is_set;
	}
}
