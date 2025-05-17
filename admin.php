<?php


namespace Imgproxy;

function baseurl() {
	if ( defined('IMGPROXY_URL') ) {
		return IMGPROXY_URL;
	}

	return get_option('imgproxy_url');
}

function signing_key()
{
	if (defined('IMGPROXY_KEY')) {
		return IMGPROXY_KEY;
	}

	return get_option('imgproxy_key');
}

function signing_salt()
{
	if (defined('IMGPROXY_SALT')) {
		return IMGPROXY_SALT;
	}

	return get_option('imgproxy_salt');
}

// Add the custom field to the Media Settings screen.
function imgproxy_settings_field() {
	$imgproxy_url = baseurl();
	$imgproxy_key = signing_key();
	$imgproxy_salt = signing_salt();

	?>
		<input type="url" id="imgproxy_url" name="imgproxy_url" placeholder="https://imgproxy.example.com" value="<?php echo esc_attr($imgproxy_url); ?>" class="regular-text" <?php disabled( defined( 'IMGPROXY_URL' ) ); ?>>
		<p class="description">Enter the base URL of your imgproxy service</p>

		<input type="text" id="imgproxy_key" name="imgproxy_key" placeholder="key" value="<?php echo esc_attr($imgproxy_key); ?>" class="regular-text" <?php disabled(defined('IMGPROXY_KEY')); ?>>
		<p class="description">Enter the signing key. Signing will be disabled if left empty</p>

		<input type="password" id="imgproxy_salt" name="imgproxy_salt" placeholder="salt" value="<?php echo esc_attr($imgproxy_salt); ?>" class="regular-text" <?php disabled(defined('IMGPROXY_SALT')); ?>>
		<p class="description">Enter the signing salt. Signing will be disabled if left empty</p>
	<?php
}

function imgproxy_settings_page() {
	add_settings_section('imgproxy_section', 'Imgproxy Settings', '', 'media');
	add_settings_field('imgproxy_url', 'Imgproxy URL', 'Imgproxy\imgproxy_settings_field', 'media', 'imgproxy_section');
	register_setting('media', 'imgproxy_url');
	register_setting('media', 'imgproxy_key');
	register_setting('media', 'imgproxy_salt');
}

add_action('admin_init', 'Imgproxy\imgproxy_settings_page');

// Save the imgproxy URL value.
function save_imgproxy_url() {
	if (isset($_POST['imgproxy_url'])) {
		update_option('imgproxy_url', sanitize_text_field($_POST['imgproxy_url']));
	}
}

// Save the imgproxy signing key
function save_imgproxy_key()
{
	if (isset($_POST['imgproxy_key'])) {
		update_option('imgproxy_key', sanitize_text_field($_POST['imgproxy_key']));
	}
}

// Save the imgproxy signing salt
function save_imgproxy_salt()
{
	if (isset($_POST['imgproxy_salt'])) {
		update_option('imgproxy_salt', sanitize_text_field($_POST['imgproxy_salt']));
	}
}

add_action('admin_init', 'Imgproxy\save_imgproxy_url');
add_action('admin_init', 'Imgproxy\save_imgproxy_key');
add_action('admin_init', 'Imgproxy\save_imgproxy_salt');
