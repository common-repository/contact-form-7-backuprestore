<?php
/* ===============================================================================================
	Plugin Name: Contact Form Backup/Restore
	Plugin URI: http://www.codestuff.com/projects/kih-contact-form-backup
	Description: Backup and restore your Contact Form 7 configuration data.
	Version: 0.2.0
	Author: Gerry Ilagan
	Author URI: http://gerry.ws

==================================================================================================
0.1.0 - 2009-07-08 - Initial version
==================================================================================================
This software is provided "as is" and any express or implied warranties, including,
but not limited to, the implied warranties of merchantibility and fitness for a particular purpose
are disclaimed. In no event shall the copyright owner or contributors be liable for any direct,
indirect, incidental, special, exemplary, or consequential damages (including, but not limited to,
procurement of substitute goods or services; loss of use, data, or profits; or business
interruption) however caused and on any theory of liability, whether in contract, strict
liability, or tort (including negligence or otherwise) arising in any way out of the use of this
software, even if advised of the possibility of such damage.

For full license details see license.txt
=============================================================================================== */

define( _KIH_CONTACTFORMBACKUP_, true );

$kih_cfbr_ver = '0.2.0';

load_plugin_textdomain('kih-contact-form-backup', 'kih-contact-form-backup');

class _KIH_CONTACTFORM_BACKUP {

	var $version = 0;
	var $cfghdr  = 'Contact Form 7 Backup/Restore by Gerry Ilagan (www.codestuff.com)';
	var $check   = 'http://gerry.ws';

	function _KIH_CONTACTFORM_BACKUP( $ver ) {
		$this->version = $ver;
	}

	/**
	 * Create the admin page of this plugin.
	 */
	function add_admin() {

	    // Create a submenu under Links:
	    add_submenu_page( 'tools.php', __('Backup/Restore Contact Form Config Data'),
	    				__('Contact Form Backup'), 8,
	    				'kih-contact-form-backup', array($this, 'admin') );
	}

	function save_file($content, $fname='wpcf7-backup.ser') {

		$serializedcfg = serialize( $content );

		$cfg = array(
				$this->cfghdr,
				$this->version,
				$serializedcfg,
				md5( $serializedcfg . '|' . $this->cfghdr .
						 '|' . $this->version . '|' . $this->check )
		);

		$fspec = ABSPATH . '/wp-content/uploads/' . $fname;
		$fp = fopen($fspec, 'w+');
		if ( !$fp ) return false;

		$stat = fwrite( $fp, serialize($cfg) );

		fclose($fp);

		return $stat;
	}

	function read_file( $fname='wpcf7-backup.ser' ) {

		$fspec = ABSPATH . '/wp-content/uploads/' . $fname;
		return file_get_contents( $fspec );
	}

	function restore_config() {

		$cfg = unserialize($this->read_file());

		if ( !is_array($cfg) ) return __('Not proper format');

		if ( $this->version != $cfg[1] ) return __('Incorrect version');

		if ( $this->cfghdr != $cfg[0] ) return __('Invalid header');

		$md5sum = md5($cfg[2] . '|' . $cfg[0] . '|' . $cfg[1] . '|' . $this->check);

		if ( $md5sum != $cfg[3] ) return __('Data corrupt'). ' ['.$md5sum.' != '.$cfg[3].']';

		$contactformdata = unserialize( $cfg[2] );

		// TODO: would have to cross-check for wpcf7 versioning of data
		// in future version of plugin
		update_option( 'wpcf7', $contactformdata );

		return '';

	}

	function admin() {

        $configname = 'wpcf7';

        $plugindir = '/wp-content/plugins/kih-contact-form-backup/';

        $wpcf7_config = get_option('wpcf7');

		if (isset($_POST['action']) && $_POST['action'] == 'backup') {

        	check_admin_referer('kih-cntctfrmbckp-opts');

			if ( !empty($wpcf7_config) ) {
			// display option to backup data

				$stat = $this->save_file( $wpcf7_config );

			}

			if ( $stat ) {
        		echo "<div class='updated fade'><p>" . __('Backup saved.') . "</p></div>";
			} else {
        		echo "<div class='error fade'><p>" . __('Unable to save data.') . "</p></div>";
			}

        } else if (isset($_POST['action']) && $_POST['action'] == 'upload') {

        	check_admin_referer('kih-cntctfrmbckp-opts');

        	// $_FILES['wpcf7_config_file']['name'];
			// $_FILES['wpcf7_config_file']['type'];
			// $_FILES['wpcf7_config_file']['size'];
			// $_FILES['wpcf7_config_file']['tmp_name'];
			// $_FILES['wpcf7_config_file']['error'];
        	$file = $_FILES['wpcf7_config_file'];

        	if ( $file['size'] < 500000 && $file['error'] == 0  ) {

				move_uploaded_file( $file['tmp_name'],
					ABSPATH.'/wp-content/uploads/wpcf7-backup.ser' );

	        	echo "<div class='updated fade'><p>" . __('File uploaded.') . "</p></div>";

			} else {

	        	echo "<div class='error fade'><p>" . __('File is invalid.') .
	        			"</p></div>";

			}

        } else if (isset($_POST['action']) && $_POST['action'] == 'restore') {

        	check_admin_referer('kih-cntctfrmbckp-opts');

			$stat = $this->restore_config();

			if ( empty($stat) ) {
	        	echo "<div class='updated fade'><p>" . __('Restore successful.') . "</p></div>";
			} else {
        		echo "<div class='error fade'><p>" . __('Unable to restore data').' ('.$stat.')'.
        				 "</p></div>";
			}

        }

	?>
	<div class="wrap">
		<h2><?php _e('Backup/Restore Contact Form 7 Data'); ?></h2>

		<p>This plugin will backup and restore your Contact Form 7 configuration
		data. You can use this tool in several ways.</p>
		<ol style="list-style:decimal;margin-left:20px;margin-right:20px;">
		<li>Backup your contact form data for later restore
		in case you accidentally deleted your Contact Forms.</li>
		<li>Restore previously saved contact forms.</li>
		<li>Backup forms from one WordPress setup and then
		restore them into another WordPress setup thereby saving you time in having
		to re-type the configuration files between WordPress sites.</li></ol>

		<h3>Backup/Download contact form data</h3>

		<?php if ( !empty($wpcf7_config) && is_array($wpcf7_config['contact_forms'])
					&& function_exists('wpcf7_contact_forms') ) {
			// display option to backup data
			?>

			<p>WordPress Contact Form 7 configuration data was found. You can backup and
			download the data by clicking on the button below.</p>

			<form method="post" style="float:left;">

				<?php if ( function_exists('wp_nonce_field') )
							wp_nonce_field('kih-cntctfrmbckp-opts'); ?>

				<input type="image" src="<?php echo $plugindir; ?>images/backup-enabled.png" />

	         	<input type="hidden" name="action" id="action" value="backup" />
			</form>

			<?php if (file_exists(ABSPATH.'/wp-content/uploads/wpcf7-backup.ser')) { ?>

				<a href="/wp-content/uploads/wpcf7-backup.ser"><img
				src="<?php echo $plugindir; ?>images/download-enabled.png"
				 style="margin-top:4px" alt="Download" /></a>

			<?php } else { ?>

				<img src="<?php echo $plugindir; ?>images/download-disabled.png"
				style="margin-top:4px" alt="Download (disabled)" />


			<?php } ?>

		<?php } else { ?>

			<p>There is <strong style="color:#aa0000">NO VALID WordPress Contact
			Form 7</strong> setup found on your blog. You have to ensure that you have
			the plugin installed and the config data has been created in your
			WordPress database.</p>

			<img src="<?php echo $plugindir; ?>images/backup-disabled.png"
						alt="Backup (disabled)" />

			<img src="<?php echo $plugindir; ?>images/download-disabled.png"
			style="margin-top:4px" alt="Download (disabled)" />

		<?php } ?>

			<p style="margin-bottom:30px;"></p>

			<h3>Upload/Restore contact form data</h3>

			<?php if ( 	file_exists(ABSPATH.'/wp-content/uploads/wpcf7-backup.ser')
						&& function_exists('wpcf7_contact_forms') ) {
				?>

				<p>Restore your previously downloaded Contact Form 7 data by following the
				steps below.</p>

			<?php } else { ?>

				<p>The Contact Form 7 plugin <strong style="color:#aa0000">WAS NOT
				DETECTED</strong> on your blog. You CAN upload a previously backed up
				data file but will not be able to restore it.</p>

			<?php } ?>


			<form enctype="multipart/form-data" action="" method="POST"
					 style="float:left;">

				<div style="float:left;background-repeat:no-repeat;margin-top:5px;background-image:url('<?php
					echo $plugindir; ?>images/select-file.png');height:76px;width:314px;">


				<?php if ( function_exists('wp_nonce_field') )
							wp_nonce_field('kih-cntctfrmbckp-opts'); ?>

				<input style="margin:37px 0 0 30px;padding:0;"
								type="file" name="wpcf7_config_file" />

				</div>

				<input type="image"
					src="<?php echo $plugindir; ?>images/upload-enabled.png" />

        	 	<input type="hidden" name="action" id="action" value="upload" />
			</form>


			<?php if ( 	file_exists(ABSPATH.'/wp-content/uploads/wpcf7-backup.ser')
						&& function_exists('wpcf7_contact_forms') ) {
				?>

				<form method="post" style="float:left;">

					<?php if ( function_exists('wp_nonce_field') )
								wp_nonce_field('kih-cntctfrmbckp-opts'); ?>

					<input type="image"
						src="<?php echo $plugindir; ?>images/restore-enabled.png" />

	        	 	<input type="hidden" name="action" id="action" value="restore" />
				</form>

			<?php } else { ?>

				<img src="<?php echo $plugindir; ?>images/restore-disabled.png"
				style="margin-top:4px" alt="Restore (disabled)" />


			<?php } ?>

		<p style="clear:both;margin-bottom:30px;"></p>

		<h3>Support the continued development of this plugin</h3>

		<p>Thanks for downloading and using the plugin. Developing software takes
		time and resources. This started out as a need but I'm thinking of adding
		some features like selective backup/restore, if a lot of people will use
		the features.</p>

		<a href=""><img src="<?php echo $plugindir; ?>images/donate-via-paypal.png"
						alt="Donate via PayPal" /></a>


	</div>
	<?php
	}
}

$cfbackup = new _KIH_CONTACTFORM_BACKUP( $kih_cfbr_ver );

if (is_admin()) add_action('admin_menu', array($cfbackup, 'add_admin'));
?>