<?php
/*
	Plugin Name: IMAP Authentication 3
	Version: 4.0
	Plugin URI: https://github.com/imsoftware/wp-imap-auth/
	Description: Authenticate users using IMAP authentication. For Wordpress 4.0+
	Author: A. Parecki, R. Magliocchetti, L. Schmid, M. Müller
	Author URI: http://imsoftware.de


	Copyright 2009 by Aaron Parecki (email : aaron@parecki.com)
	Copyright 2013 by Unbit sas (author : Riccardo Magliocchetti)
	Copyright 2014 by Lorenz Schmid (email : schmid.lorenz@gmail.com) 
	Copyright 2016 by Marius Müller / imsoftware.de (email : mm@imsoftware.de) 

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/* Plugin Update Routine */
	include_once( plugin_dir_path( __FILE__ ) . 'wp-updater/updater.php' );
	$updater = new Smashing_Updater( __FILE__ );
	$updater->set_username( 'imsoftware' );
	$updater->set_repository( 'wp-imap-auth' );
	$updater->initialize();

/* Replace Wordpress authentication */
	add_filter( 'authenticate', array('IMAPAuthentication', 'authenticate'), 1, 3 );

/* Wordpress Options */
	add_option('imapauth_mailbox', '{localhost:143}INBOX');
	add_option('imapauth_pad_domain', '');
	add_option('imapauth_create_new', 'false');
	
	add_action('admin_menu', array('IMAPAuthentication', 'admin_menu'));

/* Suppress password reset */
	add_action('lost_password', array('IMAPAuthentication', 'disable_password'));
	add_action('retrieve_password', array('IMAPAuthentication', 'disable_password'));
	add_action('password_reset', array('IMAPAuthentication', 'disable_password'));
	add_filter('show_password_fields', array('IMAPAuthentication', 'show_password_fields'));

/* Suppress email change */
	function ims_script_enqueuer(){
        	if(current_user_can('edit_users')==false) {
            		echo '
            		<script type="text/javascript">
            			jQuery(document).ready( function($) {
                			$(".user-email-wrap #email").prop('disabled', true);
            			});     
            		</script>
            		';
        	}
	}
    add_action('admin_head-profile.php', 'ims_script_enqueuer');

/* Logging Function Wrapper */
	if ( ! function_exists('write_log')) {
		function write_log ( $log )  {
			if ( is_array( $log ) || is_object( $log ) ) {
				error_log( print_r( $log, true ) );
			} else {
				error_log( $log );
			}
		}
	}


if( !class_exists('IMAPAuthentication') ) {
	class IMAPAuthentication {

		static function authenticate($user=null, $user_name='', $password='') {

			/* remove existing authentication function */
			remove_action('authenticate', 'wp_authenticate_username_password', 20);

			/* see if user already logged in */
			if ( is_a($user, 'WP_User') ) { return $user; }

			/* check non-void arguments */
			if ( empty($user_name) || empty($password) ) {
				$error = new WP_Error();
				if ( empty($user_name) ) $error->add('empty_username', __('<strong>ERROR</strong>: Missing user name value.'));
				if ( empty($password) ) $error->add('empty_password', __('<strong>ERROR</strong>: Missing password value.'));
				return $error;
			}

			/* authenticate over IMAP */
			list( $auth_result, $user_email ) = IMAPAuthentication::imap_authenticate($user_name, $password);
			if ( !$auth_result ) {
				if ( is_a($auth_result, 'WP_Error')) {
					return $auth_result;
				} else {
					return new WP_Error('invalid_user_name', __('<strong>ERROR</strong>: Could not authenticate your credentials.'));
				}
			}

			$user_id = email_exists($user_email);
			if ( !$user_id ) {
			
				/* user does not yet exist */
				if( IMAPAuthentication::get_opt_create_new() ) {

 					/* create new user */
					$random_password = wp_generate_password( $length=12, $include_standard_special_chars=false );
					$user_id = wp_create_user( IMAPAuthentication::get_user_name( $user_email ), $random_password, $user_email );

				} else {
					return new WP_Error('registration_not_allowed', __('<strong>ERROR</strong>: New registrations not allowed.'));
				}
			}

			/* return existing user */
			$user = new WP_User($user_id);
			return $user;
		}

		static function imap_authenticate($user_name, $password) {
			/* Try to login over IMAP */
			$user_email = IMAPAuthentication::get_user_mail($user_name);
			$mbox = @imap_open( IMAPAuthentication::get_mailbox(), $user_email, $password, OP_HALFOPEN|OP_READONLY );

			/* Login successful */
			if($mbox) {
				imap_close($mbox);
				
				if(WP_DEBUG === true) {
					write_log( "[wp-imapauth] Login successful! User: ". sanitize_email( $user_name ) );
				}	
				return array( true, $user_email );
			}

			/* Login failed */
			$imap_error = imap_last_error();
			if(WP_DEBUG === true) {
				write_log( "[wp-imapauth] Login failed! User: ". sanitize_email( $user_name ) .", Error: ". $imap_error );
			}
			return array( false, imap_last_error() );
		}

		/* Get option mfunctions */
		static function get_mailbox() {
			return get_option( 'imapauth_mailbox', '{localhost:143}INBOX' );
		}

		static function get_pad_domain() {
			return get_option( 'imapauth_pad_domain' );
		}

		static function get_opt_create_new() {
			return get_option( 'imapauth_create_new', 'false' );
		}

		static function get_user_mail( $user_name ) {
			if( IMAPAuthentication::get_pad_domain() !== '' ) {
				if( !preg_match( '#@#', $user_name ) ) {
					$user_name .= '@' . IMAPAuthentication::get_pad_domain();
				}
			}
			return $user_name;
		}

		static function get_user_name( $user_mail ) {
			$user_mail = explode( '@', $user_mail );
			return $user_mail[0];
		}


		/* Suppresion of password retrieving/reset */
		function disable_password() {
			login_header( 'Log In', '', new WP_Error('password_reset_suppressed', '<strong>ERROR</strong>: This blog uses the IMAP login mechanism. Your password is set with your email account and cannot be reseted here.' ) );
		?>
	<p id="nav"><a href="<?php echo wp_login_url(); ?>" title="<?php esc_attr_e( 'Try again!' ); ?>"><?php printf( __( 'Log In' ) ); ?></a></p>
		<?php
			login_footer();
			die();
		}

		/* disable password field visibility */
		function show_password_fields($username) {
			return false;
		}


		/* options pane for this plugin */
		static function admin_menu() {
			add_options_page('IMAP Authentication', 'IMAP Authentication', 10, __FILE__, array('IMAPAuthentication', 'show_plugin_page')) ;
		}

		/* plugin option page */
		static function show_plugin_page() {
			$mailbox = IMAPAuthentication::get_mailbox();
			$pad_domain = IMAPAuthentication::get_pad_domain();
			$opt_create_new = IMAPAuthentication::get_opt_create_new();
			?>

	<div class="wrap">
		<h2>IMAP Authentication Options</h2>
		<form name="imapauthenticationoptions" method="post" action="options.php">
			<?php wp_nonce_field('update-options'); ?>
			<input type="hidden" name="action" value="update" />
			<input type="hidden" name="page_options" value="imapauth_mailbox,imapauth_pad_domain,imapauth_create_new" />
			<fieldset class="options">
				<table width="100%" cellspacing="2" cellpadding="5" class="form-table">
					<tr valign="top">
						<th width="33%" scope="row"><label for="imapauth_mailbox">Mailbox</label></th>
						<td><input name="imapauth_mailbox" type="text" id="imapauth_mailbox" value="<?php echo htmlspecialchars($mailbox) ?>" size="80" /><br />eg: {mail.domain.com/readonly}INBOX or {mail.domain.com:993/ssl/novalidate-cert/readonly}INBOX</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="imapauth_pad_domain">User Suffix</label></th>
						<td><input name="imapauth_pad_domain" type="text" id="imapauth_pad_domain" value="<?php echo htmlspecialchars($pad_domain) ?>" size="50" /><br />A suffix to add to usernames (typically used to automatically add the domain part of the login).<br />eg: domain.com</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="imapauth_create_new">User Registration</label></th>
						<td><input name="imapauth_create_new" type="checkbox" id="imapauth_create_new" value="1" <?php checked(true, $opt_create_new); ?> /> Register new user automatically if succesful IMAP authentification.</td>
					</tr>
				</table>
			</fieldset>
			<p class="submit">
				<input class="button button-primary" type="submit" name="Submit" value="<?php _e('Save Changes') ?>" />
			</p>
		</form>
	</div>
			<?php
		}
	}
}
?>
