<?php
/*
Plugin Name: WP Boyka
Plugin URI: http://wordpress.org/extend/plugins/wp-boyka/
Description: This plugin increases your website's performance by reducing image size without an apparent change for the human eye. You can choose a different compression level for each image and see a preview before make the changes permanent.
Author: Salvatore Fresta
Version: 0.1
Author URI: http://www.salvatorefresta.net/
*/

/*  Copyright 2013  Salvatore Fresta  (salvatorefresta@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( !class_exists( 'boyka' ) ) {

	class Boyka {
	
		var $version = "0.1";
	
		/**
	     * Constructor
	     */
	
		function __construct( ) {
	
			/**
			 * Constants
			 */
			
			define('BOYKA_DOMAIN', 'wp_boyka');
			define('BOYKA_PATH', plugin_dir_path(__FILE__) );  
			define('BOYKA_URL', plugin_dir_url(__FILE__) ); 
			
			define('BOYKA_DEFAULT_IMG_COMPRESSION_LEVEL', 20 );
			define('BOYKA_CACHE_PATH', BOYKA_PATH.'cache/');
			define('BOYKA_CACHE_URL', BOYKA_URL.'cache/');
			
			
			/**
			 * Default options
			 */
			
			add_option('boyka_compression_level', BOYKA_DEFAULT_IMG_COMPRESSION_LEVEL);
			add_option('boyka_compress_thumbnails', 1);
			add_option('boyka_compress_on_upload', 0);
			add_option('boyka_disable_preview', 0);
			add_option('boyka_verbose_mode', 0);
			
			/* Activate group of options */
			add_action('admin_init', array( &$this, 'boyka_register_options_group'));
			
			
			/**
			 * Pages, menu and hooks
			 */

			add_action('admin_menu', array(&$this, 'boyka_add_menu_and_pages'));
			add_filter('manage_media_columns', array( &$this, 'boyka_media_columns'));
			add_action('manage_media_custom_column', array( &$this, 'boyka_media_custom_column'), 10, 2);
			add_action('admin_action_boyka_compress_single', array( &$this, 'boyka_compress_single'));
			add_action('admin_action_boyka_clear_cache', array(&$this, 'boyka_clear_cache'));
			add_action('plugins_loaded', array(&$this, 'boyka_language_init'));
			
			
			if(get_option('boyka_compress_on_upload')) {
				add_filter( 'wp_generate_attachment_metadata', array(&$this, 'boyka_compress_when_uploading'), 10, 2 );
			}
			
			/**
			 * Scripts and CSS
			 */
			
			wp_enqueue_script('boyka_common_jquery', BOYKA_URL.'js/common.js', 'jquery');
			
		}
		
		
		function boyka_register_options_group() {
		    register_setting('boyka_options_group', 'boyka_compression_level');
		    register_setting('boyka_options_group', 'boyka_compress_thumbnails');
		    register_setting('boyka_options_group', 'boyka_compress_on_upload');
		    register_setting('boyka_options_group', 'boyka_disable_preview');
		    register_setting('boyka_options_group', 'boyka_verbose_mode');
		}


		function boyka_language_init() {
		    load_plugin_textdomain(BOYKA_DOMAIN, false, dirname( plugin_basename( __FILE__ )).'/languages'); 
		}

		
		/** MENUS **/
		
		function boyka_add_menu_and_pages() {	    	
	    	add_menu_page('Boyka Settings', 'Boyka', 'administrator', 'boyka-settings-page', array(&$this, 'boyka_settings_page'));
			add_submenu_page(NULL, 'Boyka Preview', 'Boyka', 'administrator', 'boyka-preview-page', array(&$this, 'boyka_preview_page'));
		}
		

		function boyka_media_columns( $defaults ) {
			$defaults['boyka'] = 'Boyka';
			$defaults['boyka_settings'] = 'Boyka Settings';
			return $defaults;
		}
		

		function boyka_media_custom_column( $column_name, $id ) {
			
			$data = wp_get_attachment_metadata($id);
			
			if($column_name == 'boyka_settings'):
			
				if(!isset($data['boyka'])):
			
			?>
			
				<label for="boyka_compression_level"><?php _e('Compression Level', BOYKA_DOMAIN); ?>:</label>
				<select name="boyka_compression_level" class="<?php echo $id?>" style="width: 90px">

					<option value="<?php echo get_option('boyka_compression_level'); ?>" selected><?php echo get_option('boyka_compression_level'); ?> %</option>
				
				</select>
			
			<?php 
			
				else:
				
			?>
			
				<p><?php _e('Already processed', BOYKA_DOMAIN); ?></p>
			
			<?php 
			
				endif;
			endif;
			
			if($column_name == 'boyka') {
				
				/* Already processed */
				if(isset($data['boyka']) && is_array($data['boyka'])) {
					
					$boyka_results = $data['boyka'];
					
					?>
					
					<ul style="margin-top: 0">
						<?php if(get_option('boyka_verbose_mode')): ?>
						<li><strong><?php _e('Compression Level', BOYKA_DOMAIN)?></strong>: <?php echo $boyka_results['compression_level']; ?> %</li>
						<li><strong><?php _e('Old Size', BOYKA_DOMAIN)?></strong>: <?php echo $this->boyka_format_size($boyka_results['old_size']); ?></li>
						<li><strong><?php _e('New Size', BOYKA_DOMAIN)?></strong>: <?php echo $this->boyka_format_size($boyka_results['new_size']); ?></li>
						<li><strong><?php _e('Gain', BOYKA_DOMAIN)?></strong>: <?php echo $this->boyka_format_size($boyka_results['gain']); ?></li>
						<?php endif; ?>
						<li><strong><?php _e('Reduced by', BOYKA_DOMAIN)?></strong>: <?php echo $boyka_results['gain_percentage']; ?>%</li>
					</ul>
					
					<?php
				
					
				}
				
				/* Not processed yet */
				else if (wp_attachment_is_image($id)) {
					
					?>
					
					<p><?php _e('Not processed', BOYKA_DOMAIN); ?></p>
					
					<ul style="margin-top: 0">
						
						<?php if(!get_option('boyka_disable_preview')): ?>
						
						<li><a class="<?php echo $id?>" href="admin.php?page=boyka-preview-page&img_id=<?php echo $id; ?>" target="_blank"><?php _e('Preview', BOYKA_DOMAIN); ?></a></li>
						
						<?php endif; ?>
						
						<li><a class="<?php echo $id?>" href="admin.php?action=boyka_compress_single&img_id=<?php echo $id; ?>"><?php _e('Compress now!', BOYKA_DOMAIN); ?></a></li>
											
					</ul>
					
					<?php 
				  					  
				}
				
			}
			
		}
		
		
		/** PAGES **/
		
		function boyka_settings_page() {
			
			/* Check if the cache is writable */
			if(!is_writable(BOYKA_CACHE_PATH))
				$this->boyka_print_notice(sprintf( __( "The directory <strong>%s</strong> is not writable. If you want use the preview, you <strong>must</strong> change its permissions to 777.", BOYKA_CACHE_PATH, BOYKA_DOMAIN ), BOYKA_CACHE_PATH ));
			
			$cache_empty = count(@scandir(BOYKA_CACHE_PATH)) > 2 ? false : true;
	
			?>
			<div class="wrap">
	    		<div class="icon32" id="icon-options-general"><br /></div>
			
				<h2><?php _e('Boyka Settings Page', BOYKA_DOMAIN); ?></h2>
				
				<?php 
				
					/* Check if the user has the right permissions */
					if(!current_user_can('upload_files')) {
						$this->boyka_print_notice( __( 'You don\'t have permission to manage media files.', BOYKA_DOMAIN), true );
						return false;
					}
					
					/* Check if GD is installed */
					if(!$this->is_gd_installed()) {
						$this->boyka_print_notice( __( 'The GD library is required by this plugin but it was not found on this server. Please proceed with the installation or contact your hosting support center.', BOYKA_DOMAIN), true );
						return false;
					}
					
				?>
				
				<p><?php _e('Change the following information in according to your taste.', BOYKA_DOMAIN); ?></p>
				
				<form method="post" action="options.php">
					
					<?php 
						/* Update the right options group */
						settings_fields('boyka_options_group'); 
					?>
					
					<table class="form-table">
					
						<tr>
						    <th scope="row">
						        <label for="boyka_compression_level_input"><?php _e('Default Compression Level', BOYKA_DOMAIN); ?></label>
						    </th>
						 
						    <td>
						    	<select id="boyka_compression_level" name="boyka_compression_level">
						    		<option value="<?php echo get_option('boyka_compression_level'); ?>" selected><?php echo get_option('boyka_compression_level'); ?> %</option>
						    	</select>
						        <br>
						    </td>
						</tr>
						
						<tr>
						    <th scope="row">
						        <label for="boyka_compression_level_input"><?php _e('Cache', BOYKA_DOMAIN); ?></label>
						    </th>
						 
						    <td>
						    	<?php if(!$cache_empty): ?>
						    	<a href="admin.php?action=boyka_clear_cache"><?php _e('Clear the cache', BOYKA_DOMAIN); ?></a>
						    	<?php else: ?>
						    	<?php _e('Cache is empty', BOYKA_DOMAIN); ?>
						    	<?php endif; ?>
						        <br>
						    </td>
						</tr>
						
						<tr>
						    <th scope="row"><?php _e('Features', BOYKA_DOMAIN); ?></th>
						    <td>
						        <fieldset>
						            <label for="moderation_notify">
						                <input type="checkbox" <?php if(get_option('boyka_compress_thumbnails') == 1): ?> checked="checked" <?php endif; ?> value="1" id="boyka_compress_thumbnails" name="boyka_compress_thumbnails"> <?php _e('Compress the thumbnails', BOYKA_DOMAIN); ?>
						            </label>
						            <br>
						            <label for="moderation_notify">
						                <input type="checkbox" <?php if(get_option('boyka_compress_on_upload') == 1): ?> checked="checked" <?php endif; ?> value="1" id="boyka_compress_on_upload" name="boyka_compress_on_upload"> <?php _e('Compress the image while uploading', BOYKA_DOMAIN); ?>
						            </label>
						            <br>
						            <label for="moderation_notify">
						                <input type="checkbox" <?php if(get_option('boyka_disable_preview') == 1): ?> checked="checked" <?php endif; ?> value="1" id="boyka_disable_preview" name="boyka_disable_preview"> <?php _e('Disable the preview', BOYKA_DOMAIN); ?>
						            </label>
						            <br>
						            <label for="moderation_notify">
						                <input type="checkbox" <?php if(get_option('boyka_verbose_mode') == 1): ?> checked="checked" <?php endif; ?> value="1" id="boyka_verbose_mode" name="boyka_verbose_mode"> <?php _e('Display more information on Media page', BOYKA_DOMAIN); ?>
						            </label>
						        </fieldset>
						    </td>
						</tr>
						
						<tr valign="top">
	                        <th scope="row"></th>
	                        <td>
	                        	<input type="submit" class="button-primary" id="submit" name="submit" value="<?php _e('Save changes', BOYKA_DOMAIN) ?>" />
	                        </td>
	                    </tr>
						
					</table>
				
				</form>
				
			</div>			

	
			<?php 
	
			
			
		}
		
		
		function boyka_preview_page() {
			
			$change_compression_level = false;
			$compression_level = get_option('boyka_compression_level');
			
			/* Image ID */
			if(!isset($_GET['img_id'])) wp_die(__( 'No attachment ID was provided.', BOYKA_DOMAIN ));
			$img_id = intval( $_GET['img_id'] );
			
			/* Image compression_level */
			if(isset($_GET['compression_level'])) $compression_level = intval($_GET['compression_level']);
			
			if($compression_level < 0 || $compression_level > 100) wp_die(__( 'Invalid compression level.', BOYKA_DOMAIN ));
			
			//* Retrive image metadata */
			$original_meta = wp_get_attachment_metadata( $img_id );
			if(!$original_meta) wp_die( __( 'Invalid image.', BOYKA_DOMAIN ) );
				
			$upload_directory_data = wp_upload_dir();
			$upload_directory     = $upload_directory_data['basedir'].'/';
			$upload_directory_url = $upload_directory_data['baseurl'].'/';
				
			$src_image = $upload_directory.$original_meta['file'];
			$dst_image = BOYKA_CACHE_PATH.basename($original_meta['file']);

			$src_image_url = $upload_directory_url.$original_meta['file'];
			$dst_image_url = BOYKA_CACHE_URL.basename($original_meta['file']);
				
			$result = $this->boyka_compression($src_image, $dst_image, $compression_level, $old_image_size, $new_image_size, $gain, $gain_percentage, $error);
			if(!$result) {
				$this->boyka_print_notice($error, true);
				return false;
			}
			
			if($gain <= 0) $change_compression_level = true;
							
			?>
			
			<div class="wrap">
	    		<div class="icon32" id="icon-options-general"><br /></div>
			
				<h2><?php _e('Boyka Preview Page', BOYKA_DOMAIN); ?></h2>
				<p><?php _e('The following are the compression process results in preview. Using this feature you can see the changes before to make them permanent.', BOYKA_DOMAIN); ?></p>
				<br>
				
				<?php if($change_compression_level): ?>
				
				<div id="message" class="updated fade"><?php _e('<strong>Warning:</strong> using the chosen compression level, it\'s not possible improve your website\'s performance. Please, choose an higher compression level.', BOYKA_DOMAIN);?></div>
				<br>
				
				<?php endif; ?>
				
				<h3><?php _e('OVERVIEW', BOYKA_DOMAIN); ?></h3>
				
				<ul>
				
					<li><strong><?php _e('Old Size', BOYKA_DOMAIN); ?></strong>: <?php echo $this->boyka_format_size($old_image_size); ?></li>
					<li><strong><?php _e('New Size', BOYKA_DOMAIN); ?></strong>: <?php echo $this->boyka_format_size($new_image_size); ?></li>
					<li><strong><?php _e('Compression level', BOYKA_DOMAIN); ?></strong>: <?php echo $compression_level; ?>%</li>
					<li><strong><?php _e('Reduced by', BOYKA_DOMAIN); ?></strong>: <?php echo $gain_percentage; ?>% (<?php echo $this->boyka_format_size($gain); ?>)</li>

				</ul>
				
				
				
				<br>
				
				<h3><?php _e('IMAGE PREVIEW', BOYKA_DOMAIN); ?></h3>
				
				<ul>
				
					<li><strong><?php _e('Before Compression', BOYKA_DOMAIN); ?></strong>: <a href="<?php echo $src_image_url; ?>" target="_blank"><?php echo $src_image_url; ?></a></li>
					<li><strong><?php _e('After Compression', BOYKA_DOMAIN); ?></strong>: <a href="<?php echo $dst_image_url; ?>" target="_blank"><?php echo $dst_image_url; ?></a></li>
				
				</ul>
	    	
	    	    		
	    	</div>	
			
			<?php 
			
		}
				
		
		/** MAIN FUNCTIONS **/
		
		/* Function called by clicking on compress link on Media page */
		function boyka_compress_single() {
			
			$compression_level = get_option('boyka_compression_level');
			
			/* Image ID */
			if(!isset($_GET['img_id'])) wp_die(__( 'No attachment ID was provided.', BOYKA_DOMAIN ));
			$img_id = intval( $_GET['img_id'] );

			/* Image compression_level */
			if(isset($_GET['compression_level'])) $compression_level = intval($_GET['compression_level']);
			
			if($compression_level < 0 || $compression_level > 100) wp_die(__( 'Invalid compression level.', BOYKA_DOMAIN ));
	
			/* Retrive image metadata */
			$original_meta = wp_get_attachment_metadata( $img_id );
			if(!$original_meta) wp_die( __( 'Invalid image.', BOYKA_DOMAIN ) );
			
			/* Compress */
			$this->boyka_read_meta_and_compress($img_id, $original_meta, $compression_level);

			/* Go back to media page */
			wp_redirect( preg_replace( '|[^a-z0-9-~+_.?#=&;,/:]|i', '', wp_get_referer( ) ) );
		
		}
		
		
		/* Function called when uploading a file */
		function boyka_compress_when_uploading($meta, $img_id = null) {
			
			$compression_level = get_option('boyka_compression_level');
			
			if($img_id && wp_attachment_is_image($img_id) === false ) {
				return $meta;
			}
			
			$this->boyka_read_meta_and_compress($img_id, $meta, $compression_level);
			
			return $meta;
			
		}
		
		
		/* Print a notice */
		function boyka_print_notice($message, $errormsg = false) {
			
			$class = "updated fade";
			
			if($errormsg) $class = "error";
			
			?>
			
			<div id="message" class="<?php echo $class; ?>"><?php echo $message;?></div>
			
			<?php 
	
		}
		
		
		/* Print sizes in the most readable format */
		function boyka_format_size( $bytes ) {
			
        	$types = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB' );
        	
        	for( $i = 0; $bytes >= 1024 && $i < ( count( $types ) -1 ); $bytes /= 1024, $i++ );
                return( round( $bytes, 2 ) . " " . $types[$i] );
                
		}
		
		
		/* Read the input metadata and, in according to options, execute the compression function */
		function boyka_read_meta_and_compress($img_id, &$original_meta, $compression_level) {

			$upload_directory_data = wp_upload_dir();
			$upload_directory = $upload_directory_data['basedir'].'/';
			
			/** COMPRESSION **/
			
			$src_image = $upload_directory.$original_meta['file'];
			$dst_image = $src_image;
			
			$result = $this->boyka_compression($src_image, $dst_image, $compression_level, $old_image_size, $new_image_size, $gain, $gain_percentage, $error);
			if(!$result) {
				$this->boyka_print_notice($error, true);
				return false;
			}
			
			/* Update image metadata */
			$original_meta['boyka'] = array("old_size" => $old_image_size, "new_size" => $new_image_size, "gain" => $gain, "gain_percentage" => $gain_percentage, "compression_level" => $compression_level);
			
			/* Compress Thumbnails */
			if(get_option('boyka_compress_thumbnails') == 1) {
				
				$thumbnail_path = pathinfo($original_meta['file'], PATHINFO_DIRNAME).'/';
				
				/* Compress each thumbnail */
				
				$thumbnail_keys = array_keys($original_meta['sizes']);

				foreach($thumbnail_keys as $thumbnail_key => $key_value) {
					
					if(empty($key_value)) continue;
					
					$src_image = $upload_directory.$thumbnail_path.$original_meta['sizes'][$key_value]['file'];
					$dst_image = $src_image;
					
					$result = $this->boyka_compression($src_image, $dst_image, $compression_level, $old_image_size, $new_image_size, $gain, $gain_percentage, $error);

					$original_meta['sizes'][$key_value]['boyka'] = array("old_size" => $old_image_size, "new_size" => $new_image_size, "gain" => $gain, "gain_percentage" => $gain_percentage, "compression_level" => $compression_level);
										
				}
				
				
			} 
			
			/* Make image metadata permanent */
			wp_update_attachment_metadata($img_id, $original_meta);
			
		}
		
		/* Calculate some useful values and compress the image */
		function boyka_compression($src_image, $dst_image, $compression_level, &$old_image_size, &$new_image_size, &$gain, &$gain_percentage, &$error) {

			$img_quality = 100-$compression_level;
			$old_image_size = filesize($src_image);
			
			/* Compression */
			$result = $this->boyka_img_compress($src_image, $dst_image, $img_quality, $error);			
			if(!$result) return false;
			
			/* I need to clear file status cache to get the new image size */
			clearstatcache();
			$new_image_size = filesize($dst_image);
			
			/* Gain of compression */
			$gain = $old_image_size-$new_image_size;
			$gain_percentage = round(($gain*100)/$old_image_size, 2);
			
			return true;
			
			
		}
		
		
		/* Compress function - Needs GD */
		function boyka_img_compress($src, $dst, $quality, &$error) { 

			list($w, $h) = getimagesize($src); 
			
			switch(exif_imagetype($src)) {

				case IMAGETYPE_JPEG:
					$old_img=ImageCreateFromJpeg($src);
				break;
				
				case IMAGETYPE_PNG:
					$old_img=ImageCreateFromPng($src); 
				break;
				
				case IMAGETYPE_GIF:
					$old_img=ImageCreateFromGif($src);
				break;
				
				default:
					$error = __('Unsupported image type', BOYKA_DOMAIN);
					return false;
				break;
				
			}
			
			// Resize img 
			$img_resized=ImageCreateTrueColor($w,$h); 
			
			ImageCopyResampled($img_resized, $old_img, 0,0,0,0,$w,$h,$w,$h); 
			
			imagejpeg($img_resized,$dst,$quality); 
			ImageDestroy($img_resized); 
			ImageDestroy($old_img); 
			
			return true; 
		
		} 
		
		
		/* Clear Cache */
		function boyka_clear_cache() {
			
			$files = @scandir(BOYKA_CACHE_PATH);

			foreach($files as $file) {
				if(is_file(BOYKA_CACHE_PATH.$file)) @unlink(BOYKA_CACHE_PATH.$file);
			}
			
			wp_redirect( preg_replace( '|[^a-z0-9-~+_.?#=&;,/:]|i', '', wp_get_referer( ) ) );
			
		}
		
		
		/* Check for GD library */
		function is_gd_installed() {

			return (extension_loaded('gd') && function_exists('gd_info')) ? true : false;
			
		}
		
		
	}
	
	$boyka = new Boyka();
	global $boyka;

}
