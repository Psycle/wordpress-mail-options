<?php
/**
 * Created by PhpStorm.
 * User: jamesrobinson
 * Date: 15/10/15
 * Time: 11:25
 *
 * @package PsyclePluginTests
 * @subpackage Boostrap
 */

use Psycle\WordPress\Mu\MailOptions;

/**
 * Class MailOptionsTest
 *
 * Tests for main plugin class.
 */
class MailOptionsTest extends PHPUnit_Framework_TestCase {

	/**
	 * A valid email address
	 *
	 * @var string
	 */
	public $validEmailOne = 'validsender@example.org';

	/**
	 * A valid email address
	 *
	 * @var string
	 */
	public $ValidEmailTwo = 'validsender2@example.org';

	/**
	 * The original from address for testing.
	 *
	 * @var string
	 */
	public $validEmailThree = 'originalfrom@example.org';

	/**
	 * An invalid email address for testing email validation.
	 *
	 * @var string
	 */
	public $invalidEmail = 'invalidS£nd3r!!!';

	/**
	 * Test form field filter callback.
	 */
	public function test_callback_settings_field() {
		$instance = MailOptions::get_instance();
		$fields = array(
			'from' =>
				array(
					'type' => 'email',
					'label' => 'From email',
					'description' => 'The From email address for the message.',
					'default' => 'webmaster@example.org',
				),
				'from_name' =>
				array(
					'type' => 'text',
					'label' => 'From name',
					'description' => 'The From name of the message.',
					'default' => 'Webmaster at example.org',
				),
				'return_path' =>
				array(
					'type' => 'email',
					'label' => 'Return path',
					'description' => 'The Return-Path of the message.<br>If empty, it will be set to either From or Sender.',
					'default' => 'webmaster@example.org',
				),
				'sender' =>
				array(
					'type' => 'email',
					'label' => 'Reply-To address',
					'description' => 'The Sender email (Return-Path) of the message. <br>If not empty, will be sent via -f to sendmail or as \'MAIL FROM\' in smtp mode. This will become the \'Reply-To\' address if one isn\'t already set.',
					'default' => 'webmaster@example.org',
				),
				'reply_to_name' =>
				array(
					'type' => 'email',
					'label' => 'Reply-To name',
					'description' => 'This will become the \'Reply-To\' name if one isn\'t already set.',
				),
				'textarea_test' =>
				array(
					'type' => 'textarea',
					'label' => 'Text area test',
					'description' => 'Testing text area field',
				),
		);
		foreach ( $fields as $name => $option_field ) {
			$formFieldSettings = wp_parse_args( $option_field, array(
				'label' => ucfirst( str_replace( '_', ' ', $name ) ),
				'type' => 'text',
				'size' => '60',
				'name' => $name,
			) );

			// Capture the output.
			ob_start();
			$instance->callback_settings_field( $formFieldSettings );
			// <phpunitroot> is added to allow us to use the XML assertion functions. We're not currently using these but should leave this in for future use.
			$field = '<phpunitroot>' . ob_get_contents() . '</phpunitroot>';
			ob_end_clean();

			switch ( $name ) {
				case 'from':
					$expected = '<phpunitroot><input type="email" name="' . MailOptions::get_instance()->get_option_prefix() . 'from" value="webmaster@example.org" size="60"><p class="description">The From email address for the message.</p></phpunitroot>';
					break;
				case 'from_name':
					$expected = '<phpunitroot><input type="text" name="' . MailOptions::get_instance()->get_option_prefix() . 'from_name" value="Webmaster at example.org" size="60"><p class="description">The From name of the message.</p></phpunitroot>';
					break;
				case 'return_path':
					$expected = '<phpunitroot><input type="email" name="' . MailOptions::get_instance()->get_option_prefix() . 'return_path" value="webmaster@example.org" size="60"><p class="description">The Return-Path of the message.&lt;br&gt;If empty, it will be set to either From or Sender.</p></phpunitroot>';
					break;
				case 'sender':
					$expected = '<phpunitroot><input type="email" name="' . MailOptions::get_instance()->get_option_prefix() . 'sender" value="webmaster@example.org" size="60"><p class="description">The Sender email (Return-Path) of the message. &lt;br&gt;If not empty, will be sent via -f to sendmail or as &#039;MAIL FROM&#039; in smtp mode. This will become the &#039;Reply-To&#039; address if one isn&#039;t already set.</p></phpunitroot>';
					break;
				case 'reply_to_name':
					$expected = '<phpunitroot><input type="email" name="' . MailOptions::get_instance()->get_option_prefix() . 'reply_to_name" value="" size="60"><p class="description">This will become the &#039;Reply-To&#039; name if one isn&#039;t already set.</p></phpunitroot>';
					break;
				case 'textarea_test':
					$expected = '<phpunitroot><textarea name="' . MailOptions::get_instance()->get_option_prefix() . 'textarea_test"></textarea><p class="description">Testing text area field</p></phpunitroot>';
					break;
			}
			$this->assertEquals( $expected,$field );
			// $this->assertXmlStringEqualsXmlString($expected, $field); Ideally we would use this but it won't check HTML5 self closing tags.
		}
	}

	/**
	 * This tests the get instance method. We don't want the possiblity of two instances existing in the same session.
	 *
	 * @covers \Psycle\WordPress\Mu\MailOptions::get_instance
	 */
	public function test_get_instance() {
		$instance = MailOptions::get_instance();
		$e = false;
		try {
			$newInstance = new MailOptions();
		} catch ( \Exception $e ) {
			error_log( 'Caught exception in test' );
		}
		$this->assertInstanceOf( 'Exception', $e );
	}

	/**
	 * Check to see that all the expected actions are being registered.
	 */
	public function test_init() {
		global $wp_filter, $merged_filters;
		$mailOptions = MailOptions::get_instance();
		$expectedFilters = array(
			'phpmailer_init' => array( $mailOptions, 'action_php_mailerinit' ),
			'admin_init' => array( $mailOptions, 'action_init' ),
			'psycle_mail_form_fields' => array( $mailOptions, 'filter' . MailOptions::get_instance()->get_option_prefix() . 'form_fields' ),
			'wp_mail' => array( $mailOptions, 'filter_wp_mail' ),
		);

		MailOptions::get_instance()->init();

		foreach ( $expectedFilters as $filterName => $classCallback ) {
			$found = false;
			if ( array_key_exists( $filterName, $wp_filter ) && is_array( $wp_filter[ $filterName ] ) ) {
				foreach ( $wp_filter[ $filterName ] as $foundCallbacks ) {
					foreach ( $foundCallbacks as $foundCallback ) {
						if ( $foundCallback['function'] == $classCallback ) {
							$found = true;
							break;
						}
					}
				}
			}
			if ( ! $found ) {
				$this->fail( 'Callback not registered for "' . $filterName . '"' );
			}
		}
	}

	/**
	 * Test the wp_mail filter to check that we can deal with arguments passed as a string.
	 */
	public function test_filter_wp_mail_with_string() {
		$mailOptions = MailOptions::get_instance();
		$mailOptions->set_reply_to_set( false );
		/**
		 * Parse headers as string
		 * Arguments should be untouched by the filter method.
		 * The get_reply_to_set() method should return false in this case.
		 */
		$args = '';
		$filteredArgs = $mailOptions->filter_wp_mail( $args );

		$this->assertEquals( $args, $filteredArgs );
		$this->assertFalse( $mailOptions->get_reply_to_set() );

		/**
		 * Parse headers as array with Reply-To set.
		 * The get_reply_to_set() method should return true in this case.
		 */
		$args = array(
			'headers' => 'Reply-To: test@example.org' . PHP_EOL,
		);
		$filteredArgs = $mailOptions->filter_wp_mail( $args );
		$this->assertEquals( $args, $filteredArgs );
		$this->assertTrue( $mailOptions->get_reply_to_set() );
	}

	/**
	 * Test the wp_mail filter with arguments passed as an array.
	 */
	public function test_filter_wp_mail_with_array() {
		$mailOptions = MailOptions::get_instance();
		$mailOptions->set_reply_to_set( false );
		/**
		 * Parse headers as array with Reply-To not set
		 * Arguments should be untouched by the filter method.
		 * The get_reply_to_set() method should return false in this case.
		 */
		$args = array();
		$filteredArgs = $mailOptions->filter_wp_mail( $args );
		/**
		 * Arguments should be untouched by the filter method.
		 * The get_reply_to_set() method should return false in this case.
		 */
		$this->assertEquals( $args, $filteredArgs );
		$this->assertFalse( $mailOptions->get_reply_to_set() );

		/**
		 * Parse headers as array with Reply-To set.
		 * The get_reply_to_set() method should return true in this case.
		 */
		$args = array(
			'headers' => array(
				'Reply-To: test@example.org',
			),
		);
		$filteredArgs = $mailOptions->filter_wp_mail( $args );
		$this->assertEquals( $args, $filteredArgs );
		$this->assertTrue( $mailOptions->get_reply_to_set() );
	}

	/**
	 * Testing the action_php_mailerinit function.
	 */
	public function test_action_php_mailerinit() {
		$mailOptions = MailOptions::get_instance();

		$phpMailer = new PHPMailer();
		$phpMailer->Sender = $this->ValidEmailTwo;

		$mailOptions->set_reply_to_set( false );
		$mailOptions->set_option( 'sender', $this->validEmailOne );
		$mailOptions->action_php_mailerinit( $phpMailer );
		$headers = $phpMailer->createHeader();
		$this->assertEquals( $this->validEmailOne, $phpMailer->Sender );
		$this->assertContains( 'From: "Webmaster at example.org" <webmaster@example.org>', $headers );
		$this->assertContains( 'Reply-To: "Webmaster at example.org" <validsender@example.org>', $headers );
	}

	/**
	 * Testing the action_php_mailerinit function.
	 */
	public function test_action_php_mailerinit_with_empty_sender() {
		$mailOptions = MailOptions::get_instance();

		$phpMailer = new PHPMailer();
		$phpMailer->Sender = null;

		$mailOptions->set_reply_to_set( false );
		$mailOptions->set_option( 'sender', $this->validEmailOne );
		$mailOptions->action_php_mailerinit( $phpMailer );
		$headers = $phpMailer->createHeader();

		$this->assertEquals( $this->validEmailOne, $phpMailer->Sender );
		$this->assertContains( 'From: "Webmaster at example.org" <webmaster@example.org>', $headers );
		$this->assertContains( 'Reply-To: "Webmaster at example.org" <validsender@example.org>', $headers );
	}
	//
	// **
	// * Testing the action_php_mailerinit function.
	// */
	// public function test_action_php_mailerinit_invalid_sender() {
	// $mailOptions = MailOptions::get_instance();
	//
	// $phpMailer = new PHPMailer();
	// $phpMailer->setFrom( $this->validEmailThree );
	// $phpMailer->Sender = null;
	//
	// $mailOptions->set_option( 'sender', '!nv41D S£N0£R^^^@' );
	// $mailOptions->action_php_mailerinit( $phpMailer );
	// $this->assertEquals( $this->validEmailThree, $phpMailer->Sender );
	// }
	/**
	 * Test that the init action adds the settings section to wp_admin general
	 */
	public function test_action_init() {
		global $wp_settings_sections;
		$mailOptions = MailOptions::get_instance();
		$mailOptions->action_init();
		$this->assertInternalType( 'array', $wp_settings_sections );
		$this->assertArrayHasKey( 'general', $wp_settings_sections );
		$this->assertArrayHasKey( 'psy_mail_options', $wp_settings_sections['general'] );
	}

	/**
	 * Test the settings callback to make sure that the settings section has a title.
	 */
	public function test_callback_settings_section() {
		$mailOptions = MailOptions::get_instance();
		ob_start();
		$mailOptions->callback_settings_section();
		$contents = ob_get_contents();
		ob_end_clean();
		$this->assertNotEmpty( $contents );
	}

	/**
	 * Test the set_option method to make sure that we can only set options that this class deals with and that options are saved correctly.
	 */
	public function test_set_option() {
		$mailOptions = MailOptions::get_instance();
		$optionKey = 'test_option';
		$optionVal = 'test_option_val';

		$mailOptions->set_option( $optionKey, $optionVal );
		$this->assertNull( $mailOptions->get_option( $optionKey ) );

		$validOptionKey = 'from_name';
		$validOptionVal = 'Test From Name';
		$mailOptions->set_option( $validOptionKey, $validOptionVal );
		$this->assertEquals( $validOptionVal, $mailOptions->get_option( $validOptionKey ) );
	}

	/**
	 * Test that the get_option_form_fields throws an exception when a filter returns an empty string.
	 */
	public function test_get_option_form_fields() {
		$mailOptions = MailOptions::get_instance();

		add_filter( 'psycle_mail_form_fields', function ( $fields ) {return '';
		} );
		try {
			$optionFormFields = $mailOptions->get_option_form_fields();
		} catch (\Exception $e) {
			$this->assertInstanceOf( '\Exception', $e );
		}
	}
}
