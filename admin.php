<?php


namespace Imgproxy;

function baseurl() {
	if ( defined('IMGPROXY_URL') ) {
		return IMGPROXY_URL;
	}

	return get_option('imgproxy_url');
}

// Add the custom field to the Media Settings screen.
function imgproxy_settings_field() {
    $imgproxy_url = baseurl();
    ?>
		<input type="url" id="imgproxy_url" name="imgproxy_url" placeholder="https://imgproxy.example.com" value="<?php echo esc_attr($imgproxy_url); ?>" class="regular-text" <?php disabled( defined( 'IMGPROXY_URL' ) ); ?>>
		<p class="description">Enter the base URL of your imgproxy service</p>
    <?php
}

function imgproxy_settings_page() {
    add_settings_section('imgproxy_section', 'Imgproxy Settings', '', 'media');
    add_settings_field('imgproxy_url', 'Imgproxy URL', 'Imgproxy\imgproxy_settings_field', 'media', 'imgproxy_section');
    register_setting('media', 'imgproxy_url');
}

add_action('admin_init', 'Imgproxy\imgproxy_settings_page');

// Save the imgproxy URL value.
function save_imgproxy_url() {
    if (isset($_POST['imgproxy_url'])) {
        update_option('imgproxy_url', sanitize_text_field($_POST['imgproxy_url']));
    }
}

add_action('admin_init', 'Imgproxy\save_imgproxy_url');
