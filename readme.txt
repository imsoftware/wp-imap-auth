=== WP IMAP Auth ===
Contributors: imsoftware
Donate link: http://imsoftware.de/
Tags:  authentication, imap, login, email
Requires at least: 4.4
Tested up to: 4.6
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Use IMAP accounts to login in to WordPress. Replaces the regular authentication.

== Description ==

This plugins replaces the regular authentication of WordPress and offers instead the authentication of users via IMAP accounts. Password reset and retrieving are suppressed in order to avoid confussion.

The IMAP authentication server as well as an automatic padding of the domain to a user name can be set on the plugins setting page under Settings > IMAP Authentication. Furthermore, users having a IMAP account on the defined server can automatically be register if set so.

Contributors: Aaron Parecki, Riccardo Magliocchetti, Lorenz Schmid, Marius Müller

== Installation ==

1. **Important notice**: Existing user will **not** be able to log in after installation. Make sure you can access your WordPress database directly to make one new user to an adminsitrator.
2. Install the plugin through the WordPress plugins screen directly and activate WP IMAP Authentication plugin.
3.  Activate the plugin through the 'Plugins' screen in WordPress
4. Use the Settings->IMAP Authentication screen to enter your Mailbox, e.g.: {mail.domain.com/readonly}INBOX or {mail.domain.com:993/ssl/novalidate-cert/readonly}INBOX
6. Check "Register new user automatically if succesful IMAP authentification.""
7. Log in with your email address and email password. 
8. Go to your WordPress database table `wp_usermeta` and set `wp_capabilities` to `a:1:{s:13:"administrator";b:1;}`.
9. Now you can log in with your new account as administratior. If you want you can disable "Register new user automatically".
10. That's it!

== Frequently Asked Questions ==

= Commercial Support =

Contact [IMSoftware.de](http:/imsoftware.de/ "IMSoftware.de") for professional support.

== Screenshots ==

1. This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Note that the screenshot is taken from
the /assets directory or the directory that contains the stable readme.txt (tags or trunk). Screenshots in the /assets 
directory take precedence. For example, `/assets/screenshot-1.png` would win over `/tags/4.3/screenshot-1.png` 
(or jpg, jpeg, gif).
2. This is the second screen shot

== Changelog ==

= 4.0.1 =
* First stable version.

== Upgrade Notice ==

= 4.0.1 =
First stable version.
