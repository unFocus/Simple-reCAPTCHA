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
		
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'login_enqueue_scripts' ) );
		// Add reCAPTCHA Form
		add_action( 'comment_form_after_fields', array( __CLASS__, 'reCAPTCHA' ) );
		add_action( 'lostpassword_form', array( __CLASS__, 'reCAPTCHA' ) );
		add_action( 'register_form', array( __CLASS__, 'reCAPTCHA' ) );
		add_action( 'login_form', array( __CLASS__, 'reCAPTCHA' ) );
		
		add_action( 'login_form', array( __CLASS__, 'validate' ) );
		
	}
	function validate() {
		$url = "http://www.google.com/recaptcha/api/verify";
		$args = array();
		$result = wp_remote_post( $url, $args );
		
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
		$public_key = "6LddNMsSAAAAAJX8eJeTG1aOBJG0zc1Cp2RYLTL9"; // valid for *.org.dev only :-)
		$private_key = "6LddNMsSAAAAAJz9ctsxTxWVICqj4FNVa04yOJ4Y"; // valid for *.org.dev only :-)
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
		$hook_suffix = add_options_page( __( 'Location', 'simple-recaptcha' ), __( 'Location', 'simple-recaptcha' ), 'manage_options', self::OPTION_NAME, array( __CLASS__, 'page' ) );
		add_action( "load-$hook_suffix", array( __CLASS__, 'settings' ) );
		add_action( "load-options.php", array( __CLASS__, 'settings' ) ); // Needed for options.php posting
		add_action( "load-$hook_suffix", array( __CLASS__, 'help' ) );
		add_action( "admin_print_styles-$hook_suffix", array( __CLASS__, 'admin_enqueue_scripts' ) );
	}
	function settings() {
		register_setting(
			self::OPTION_NAME,
			self::OPTION_NAME );
		
		add_settings_section(
			'settings',
			__( 'Settings', 'simple-recaptcha' ),
			'__return_false',
			self::OPTION_NAME );
		
		add_settings_field(
			'test',
			__( '<strong>A Test</strong>: ', 'simple-recaptcha' ),
			array( __CLASS__, 'radio' ),
			self::OPTION_NAME,
			'settings',
			array(
				'label_for' => 'test',
				'setting' => self::OPTION_NAME,
				'choices' => array( 'yes', 'no' ),
				'default' => 'yes',
				'legend' => __( 'A Test', 'simple-recaptcha' ),
				'description' => __( '<span class="description" style="max-width: 500px; display: inline-block;">This is just a test.</span>', 'simple-recaptcha' )
			) );
	}
	function page() {
		?>
		<div class="wrap">
			<?php screen_icon(); ?>
			<h2>Simple Location</h2>
			<form action="options.php" method="post" autocomplete="off">
			<?php settings_fields( self::OPTION_NAME ); ?>
			<?php do_settings_sections( self::OPTION_NAME ); ?>
			<?php submit_button(); ?>
			</form>
		</div>
		<?php
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
					'title' => __( 'Location', 'simple-recaptcha' ),
					'id' => 'simple-recaptcha',
					'content' => $help . $sidebar
					) );
			} else {
				$screen->add_help_tab( array(
					'title' => __( 'Location', 'simple-recaptcha' ),
					'id' => 'simple-recaptcha',
					'content' => $help
					) );
				$screen->set_help_sidebar( $sidebar );
			}
		} else {
			add_contextual_help( $screen, $help . $sidebar );
		}
	}
	function admin_enqueue_scripts() {
		$option = 'none yet';
		wp_enqueue_script( 'simple-recaptcha-settings', plugins_url('js/settings.js', __FILE__ ), array( 'jquery' ), self::VERSION, true );
		wp_localize_script( 'simple-recaptcha-settings', 'simple_recaptcha_options', array( 'option' => $option ) );
		wp_enqueue_style( 'simple-recaptcha-settings', plugins_url('css/settings.css', __FILE__ ), array(), self::VERSION );
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
		if ( isset( $setting ) ) {
			$name = ' name="' . $setting . '[' . $label_for . ']"';
			$options = get_option( $setting );
		} else {
			$name = ' name="' . $label_for . '"';
			$options = get_option( $label_for );
		}
		
		$value =  isset( $value ) ? ' value="' . $value . '"': '';
		$type =  isset( $type ) ? ' type="' . $type . '"': ' type="text"';
		$placeholder =  isset( $placeholder ) ? ' placeholder="' . $placeholder . '"': '';
		$id = ' id="' . $label_for . '"';
		
		if ( isset( $label ) && ! empty( $label ) ) echo '<label style="display: inline-block; width: 94px; margin: 0 5px; padding: 0 8px" for="' . $label_for . '">' . $label . '</label>';
		echo '<input' . $type . $value . $name . $id . $placeholder . '>';
		if ( isset( $description ) && ! empty( $description ) ) echo $description;
	}
	function radio( $args ) {
		extract( $args );
		$options = get_option( $setting );
		$default =  isset( $default ) ? $default : '';
		$value =  isset( $options[ $label_for ] ) ? $options[ $label_for ] : $default;
		$output = '<fieldset>';
		if ( $legend ) {
			$output .= '<legend class="screen-reader-text"><span>';
			$output .= $legend;
			$output .= '</span></legend>';
		}
		$output .= '<p>';
		foreach ( $choices as $choice ) {
			$output .= '<label>';
			$output .= '<input type="radio"';
			$output .= checked( $value, $choice, false );
			$output .= ' value="' . $choice . '" name="' . $setting . '[' . $label_for . ']"> ' . $choice;
			$output .= '</label>';
			$output .= '<br>';
		}
		$output .= '</p></fieldset>';
		if ( $description ) {
			$output .= $description;
		}
		echo $output;
	}
	function select( $args ) {
		extract( $args );
		$options = get_option( $setting );
		$selected = isset( $options[ $label_for ] ) ? $options[ $label_for ] : array();
		
		$output = '<select';
		$output .= ' id="' . $label_for . '"';
		$output .= ' name="' . $setting . '[' . $label_for . ']';
		if ( isset( $multiple ) && $multiple )
			$output .= '[]" multiple="multiple"';
		else
			$output .= '"';
		$output .= ( $size ) ? ' size="' . $size . '"': '';
		$output .= ( $style ) ? ' style="' . $style . '"': '';
		$output .= '>';
		foreach ( $choices as $choice ) {
			$output .= '<option value="' . $choice . '"';
			if ( isset( $multiple ) && $multiple )
				foreach ( $selected as $handle ) $output .= selected( $handle, $choice, false );
			else
				$output .= selected( $selected, $choice, false );
			$output .= '>' . $choice . '</option> ';
		}
		$output .= '</select>';
		if ( ! empty( $show_current ) && ! empty( $selected ) ) {
			$output .= '<p>' . $show_current;
			foreach ( $selected as $handle ) $output .= '<code>' . $handle . '</code> ';
			$output .= '</p>';
		}
		echo $output;
	}
}
new Simple_reCAPTCHA();

?>