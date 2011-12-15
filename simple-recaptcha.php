<?php
/*
Plugin Name: Simple reCAPTCHA
Plugin URI: http://github.com/unFocus/Simple-reCAPTCHA
Description: Integrates reCAPTCHA with WordPress.
Version: 1.0
Author: Ken Newman
Author URI: http://www.unfocus.com
*/

/*	Copyright (c) 2011 Kenneth Newman -- http://www.unfocus.com

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/
class Simple_reCAPTCHA
{
	const VERSION = '1.0';
	const OPTION_NAME = 'simple_recaptcha';
	
	function __construct() {
		add_action( 'plugins_loaded', array( __CLASS__, 'upgrade_check' ) );
		add_action( 'admin_init', array( __CLASS__, 'load_plugin_textdomain' ) );
		register_activation_hook( __FILE__, array( __CLASS__, 'upgrade' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		
		$option = get_option( self::OPTION_NAME );
		
		if ( 'yes' == $option[ 'registration' ] ) {
			add_action( 'login_enqueue_scripts', array( __CLASS__, 'login_enqueue_scripts' ) );
			add_action( 'register_form', array( __CLASS__, 'reCAPTCHA' ) );
			add_filter( 'shake_error_codes', array( __CLASS__, 'shake_error_codes' ) );
			add_filter( 'registration_errors', array( __CLASS__, 'validate' ) );
		}
		/*if ( 'yes' == $option[ 'comments' ] ) {
			add_action( 'comment_form_after_fields', array( __CLASS__, 'reCAPTCHA' ) );
		}*/
	}
	function shake_error_codes( $shake_error_codes ) {
		return array_merge( $shake_error_codes,  array(
			'blank_captcha_sol',
			'incorrect_captcha_config',
			'incorrect-captcha-sol',
			'recaptcha-not-reachable',
			'invalid-request-cookie',
			//'recaptcha_result',
			'invalid-site-private-key'
			) );
	}
	function validate( $errors ) {
		if ( empty( $_POST[ 'recaptcha_challenge_field' ] ) || empty( $_POST[ 'recaptcha_response_field' ] ) ) {
			add_filter( 'login_message', '__return_false' );
			$errors->add( 'blank_captcha_sol', __( '<strong>ERROR</strong>: You can not register without passing reCAPTCHA.' ) );
			return $errors;
		}
		
		$option = get_option( self::OPTION_NAME );
		if ( empty( $option[ 'private_key' ] ) || empty( $option[ 'public_key' ] ) ) {
			add_filter( 'login_message', '__return_false' );
			$errors->add( 'incorrect_captcha_config', __( '<strong>ERROR</strong>: reCAPTCHA incorrectly configured: Missing key.' ) );
			return $errors;
		}
		
		$url = "http://www.google.com/recaptcha/api/verify";
		$args = array(
			'body' => array(
				'privatekey' => $option[ 'private_key' ],
				'remoteip' => $_SERVER[ 'REMOTE_ADDR' ],
				'challenge' => $_POST[ 'recaptcha_challenge_field' ],
				'response' => $_POST[ 'recaptcha_response_field' ]
				)
			);
		$result = wp_remote_post( $url, $args );
		
		if ( '200' != $result[ 'response' ][ 'code' ] ) {
			add_filter( 'login_message', '__return_false' );
			$errors->add( 'recaptcha-not-reachable', __( '<strong>ERROR</strong>: Unable to contact the reCAPTCHA verify server: ' . $result[ 'response' ][ 'code' ] ) );
			return $errors;
		}
		
		$response = explode( "\n", $result[ 'body' ] );
		if ( 'false' == $response[0] ) {
			switch ( $response[1] ) {
				case 'incorrect-captcha-sol':
					add_filter( 'login_message', '__return_false' );
					$errors->add( 'incorrect-captcha-sol', __( '<strong>ERROR</strong>: reCAPTCHA sollution was incorrect.' ) );
				break;
				case 'invalid-site-private-key':
					add_filter( 'login_message', '__return_false' );
					$errors->add( 'invalid-site-private-key', __( '<strong>ERROR</strong>: reCAPTCHA incorrectly configured: Invalid Key.' ) );
				break;
				case 'invalid-request-cookie':
					add_filter( 'login_message', '__return_false' );
					$errors->add( 'invalid-request-cookie', __( '<strong>ERROR</strong>: Invalid Request Cookie. Sad Face.' ) );
				break;
			}
			add_filter( 'login_message', '__return_false' );
		} else if ( 'true' == $response[0]  ) {
			return $errors;
		} else {
			add_filter( 'login_message', '__return_false' );
			$errors->add( 'recaptcha_result', 'Unknown error: <pre>' . print_r( $result, true ) . '</pre>' );
		}
		return $errors;
	}
	function login_enqueue_scripts() { ?>
		<style>
			#login {
				width: 375px;
			}
			#recaptcha_widget_div {
				margin-bottom: 16px;
			}
		</style>
	<?php }
	function reCAPTCHA() {
		$option = get_option( self::OPTION_NAME );
		//$public_key = "6LddNMsSAAAAAJX8eJeTG1aOBJG0zc1Cp2RYLTL9";
		//$private_key = "6LddNMsSAAAAAJz9ctsxTxWVICqj4FNVa04yOJ4Y";
		$public_key = $option[ 'public_key' ];
		?>
		<script type="text/javascript" src="//www.google.com/recaptcha/api/challenge?k=<?php echo $public_key ?>"></script>
		<noscript>
		   <iframe src="//www.google.com/recaptcha/api/noscript?k=<?php echo $public_key ?>" height="300" width="500" frameborder="0"></iframe><br>
		   <textarea name="recaptcha_challenge_field" rows="3" cols="40"></textarea>
		   <input type="hidden" name="recaptcha_response_field" value="manual_challenge">
		</noscript>
		<?php
	}
	
	function admin_menu() {
		add_action( "load-options.php", array( __CLASS__, 'register_settings' ) );
		add_action( "load-options-general.php", array( __CLASS__, 'add_settings' ) );
		add_action( "load-options-general.php", array( __CLASS__, 'help' ) );
	}
	function validate_settings( $input ) {
		$option = array();
		extract( $input );
		$option[ 'registration' ] = ( isset( $registration ) ) ? 'yes': 'no';
		$option[ 'comments' ]     = ( isset( $comments ) )     ? 'yes': 'no';
		
		$public_key = sanitize_html_class( $public_key );
		$private_key = sanitize_html_class( $private_key );
		
		if ( 40 == strlen( $public_key ) ) $option[ 'public_key' ] = $public_key;
		else add_settings_error( self::OPTION_NAME, 'public_key', '<strong>ERROR</strong>: reCAPTCHA keys should contain 40 characters.', 'error' );
		
		if ( 40 == strlen( $private_key ) ) $option[ 'private_key' ] = $private_key;
		else add_settings_error( self::OPTION_NAME, 'private_key', '<strong>ERROR</strong>: reCAPTCHA keys should contain 40 characters.', 'error' );
		
		return $option;
	}
	function register_settings() {
		register_setting(
			'general',
			self::OPTION_NAME,
			array( __CLASS__, 'validate_settings' )
			);
	}
	function add_settings() {
		add_settings_section(
			'recaptcha',
			__( 'reCAPTCHA', 'simple-recaptcha' ),
			'__return_false',
			'general' );
		
		add_settings_field(
			'registration',
			__( 'Use for registration: ', 'simple-recaptcha' ),
			array( __CLASS__, 'checkbox' ),
			'general',
			'recaptcha',
			array(
				'label_for' => 'registration',
				'setting' => self::OPTION_NAME,
				//'description' => __( '<span class="description" style="max-width: 500px; display: inline-block;">Use for registration.</span>', 'simple-recaptcha' ),
				'legend' => __( 'Use for registration', 'simple-recaptcha' )
			) );
		/*add_settings_field(
			'comments',
			__( 'Use for comments: ', 'simple-recaptcha' ),
			array( __CLASS__, 'checkbox' ),
			'general',
			'recaptcha',
			array(
				'label_for' => 'comments',
				'setting' => self::OPTION_NAME,
				//'description' => __( '<span class="description" style="max-width: 500px; display: inline-block;">Use for comments.</span>', 'simple-recaptcha' ),
				'legend' => __( 'Use for comments', 'simple-recaptcha' )
			) );*/
			
		add_settings_field(
			'public_key',
			__( 'Public Key: ', 'simple-recaptcha' ),
			array( __CLASS__, 'input' ),
			'general',
			'recaptcha',
			array(
				'label_for' => 'public_key',
				'setting' => self::OPTION_NAME,
				'legend' => __( 'Public Key', 'simple-recaptcha' ),
				'class' => 'regular-text',
				//'description' => __( '<span class="description" style="max-width: 500px; display: inline-block;">Use for comments.</span>', 'simple-recaptcha' ),
				'placeholder' => __( 'Public Key', 'simple-recaptcha' )
			) );
		add_settings_field(
			'private_key',
			__( 'Private Key: ', 'simple-recaptcha' ),
			array( __CLASS__, 'input' ),
			'general',
			'recaptcha',
			array(
				'label_for' => 'private_key',
				'setting' => self::OPTION_NAME,
				'legend' => __( 'Private Key', 'simple-recaptcha' ),
				'class' => 'regular-text',
				//'description' => __( '<span class="description" style="max-width: 500px; display: inline-block;">Use for comments.</span>', 'simple-recaptcha' ),
				'placeholder' => __( 'Private Key', 'simple-recaptcha' )
			) );
	}
	function help() {
		$help    = '<p>' . __( 'There shall eventually be help text.', 'simple-recaptcha' ) . '</p>';
		$sidebar = '<p><strong>' . __( 'For more information:', 'simple-recaptcha' ) . '</strong></p>' .
					'<p>' . __( '<a href="http://wordpress.org/extend/plugins/simple-recaptcha/faq/" target="_blank">Frequently Asked Questions</a>', 'simple-recaptcha' ) . '</p>' .
					'<p>' . __( '<a href="https://github.com/unFocus/simple-recaptcha" target="_blank">Source on github</a>', 'simple-recaptcha' ) . '</p>' .
					'<p>' . __( '<a href="http://wordpress.org/tags/simple-recaptcha" target="_blank">Support Forums</a>', 'simple-recaptcha' ) . '</p>';
		$screen = get_current_screen();
		if ( method_exists( $screen, 'add_help_tab' ) ) {
			if ( 'post' == $screen->base ) {
				$screen->add_help_tab( array(
					'title' => __( 'reCAPTCHA', 'simple-recaptcha' ),
					'id' => 'simple-recaptcha',
					'content' => $help . $sidebar
					) );
			} else {
				$screen->add_help_tab( array(
					'title' => __( 'reCAPTCHA', 'simple-recaptcha' ),
					'id' => 'simple-recaptcha',
					'content' => $help
					) );
				$screen->set_help_sidebar( $sidebar );
			}
		} else {
			add_contextual_help( $screen, $help . $sidebar );
		}
	}
	function load_plugin_textdomain() {
		load_plugin_textdomain( 'simple-recaptcha', false, 'simple-recaptcha/lang' );
	}
	function upgrade_check() {
		$options = get_option( self::OPTION_NAME );
		if ( ! isset( $options[ 'version' ] ) || version_compare( self::VERSION, $options[ 'version' ], '>' ) )
			self::upgrade();
	}
	function upgrade() {
		$options = get_option( self::OPTION_NAME );
		if ( ! $options ) $options = array();
		$options[ 'version' ] = self::VERSION;
		update_option( self::OPTION_NAME, $options );
	}
	/* Utilities */
	function input( $args ) {
		extract( $args );
		if ( ! isset( $label_for ) ) return;
		if ( isset( $setting ) ) {
			$name = ' name="' . $setting . '[' . $label_for . ']"';
			$option = get_option( $setting );
			$value = isset( $option[ $label_for ] ) ? ' value="' . $option[ $label_for ] . '"': '';
		} else {
			$name = ' name="' . $label_for . '"';
			$option = get_option( $label_for );
			$value = isset( $option ) ? ' value="' . $option . '"': '';
		}
		echo ( $legend ) ? '<fieldset><legend class="screen-reader-text"><span>' . $legend . '</span></legend>': '';
		$style =  isset( $style ) ? ' style="' . $style . '"': '';
		$class =  isset( $class ) ? ' class="' . $class . '"': '';
		$type =  isset( $type ) ? ' type="' . $type . '"': ' type="text"';
		$placeholder =  isset( $placeholder ) ? ' placeholder="' . $placeholder . '"': '';
		$id = ' id="' . $label_for . '"';
		
		if ( isset( $label ) && ! empty( $label ) ) echo '<label style="display: inline-block; width: 94px; margin: 0 5px; padding: 0 8px" for="' . $label_for . '">' . $label . '</label>';
		echo '<input' . $type . $value . $name . $id . $placeholder . $style . $class . '>';
		if ( isset( $description ) && ! empty( $description ) ) echo $description;
		echo ( $legend ) ? '</fieldset>': '';
	}
	function checkbox( $args ) {
		extract( $args );
		if ( ! isset( $label_for ) ) return;
		if ( isset( $setting ) ) {
			$name = ' name="' . $setting . '[' . $label_for . ']"';
			$option = get_option( $setting );
			$value = isset( $option[ $label_for ] ) ? ' value="' . $option[ $label_for ] . '"': '';
		} else {
			$name = ' name="' . $label_for . '"';
			$option = get_option( $label_for );
			$value = isset( $option ) ? ' value="' . $option . '"': '';
		}
		echo ( $legend ) ? '<fieldset><legend class="screen-reader-text"><span>' . $legend . '</span></legend>': '';
		$style =  isset( $style ) ? ' style="' . $style . '"': '';
		$class =  isset( $class ) ? ' class="' . $class . '"': '';
		$id = ' id="' . $label_for . '"';
		$checked = checked( $option[ $label_for ], 'yes', false );
		echo '<input type="checkbox"' . $value . $name . $id . $style . $class . $checked . '>';
		if ( isset( $label ) && ! empty( $label ) )
			echo '<label style="display: inline-block; width: 94px; margin: 0 5px; padding: 0 8px" for="' . $label_for . '">' . $label . '</label>';
		if ( isset( $description ) && ! empty( $description ) ) echo $description;
		echo ( $legend ) ? '</fieldset>': '';
	}
}
new Simple_reCAPTCHA();

?>