<?php
/**
 * Plugin Name: WP Imgproxy
 * Description: A plugin to proxy images through imgproxy
 * Version: 1.0
 * Author: Josh Betz
 */

namespace Imgproxy;

require_once __DIR__ . '/class-imgproxy.php';
require_once __DIR__ . '/class-imgproxy-image.php';
require_once __DIR__ . '/blurhash.php';
require_once __DIR__ . '/admin.php';

if ( ! baseurl() ) {
	return;
}

// Disable intermediate image generation
add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );

function prefetch_dns($urls, $relation_type) {
	if ($relation_type === 'dns-prefetch') {
		$urls[] = imgproxy()->domain();
	}

	return $urls;
}
add_filter('wp_resource_hints', 'Imgproxy\prefetch_dns', 10, 2);

function the_content($content) {
	if (is_admin()) {
		return $content;
	}

	$urls = get_urls_from_content($content);
	if (empty($urls)) {
		return $content;
	}

	// Replace the src attribute of each <img> tag with the imgproxy URL
	foreach ($urls as $url) {
		$tag = $url[0];
		$original_url = $url[1];

		// don't proxy external images
		if (parse_url(home_url(), PHP_URL_HOST) !== parse_url($original_url, PHP_URL_HOST)) {
			continue;
		}

		// Get attachment id from image tag wp-image-*
		if (preg_match('/wp-image-(\d+)/', $tag, $matches) && isset($matches[1])) {
			preg_match('/size-(\w+)/', $tag, $size_matches);
			$size = isset($size_matches[1]) ? $size_matches[1] : 'full';

			$tags = [];

			// extract properties from image tag
			preg_match('/width="(\d+)"/', $tag, $width_matches);
			if (isset($width_matches[1])) {
				$tags['width'] = $width_matches[1];
			}

			preg_match('/height="(\d+)"/', $tag, $height_matches);
			if (isset($height_matches[1])) {
				$tags['height'] = $height_matches[1];
			}

			preg_match('/style="(.*?)"/', $tag, $style_matches);
			if (isset($style_matches[1])) {
				$tags['style'] = $style_matches[1];
			}

			$attachment_id = $matches[1];
			$html = wp_get_attachment_image($attachment_id, $size, false, $tags);
			if (!empty($html)) {
				$content = str_replace($tag, $html, $content);
				continue;
			}
		}

		if (imgproxy()->is_imgproxy_url($original_url)) {
			continue;
		}

		$imgproxy_image = imgproxy()->image($original_url);
		$url = $imgproxy_image->url();

		$content = str_replace($image_url, $url, $content);
	}

	return $content;
}
add_filter('the_content', 'Imgproxy\the_content');

function attachment_image_src($image, $attachment_id, $size, $icon) {
	if (is_admin()) {
		return $image;
	}

	list($url, $width, $height) = $image;
	if (imgproxy()->is_imgproxy_url($url)) {
		return $image;
	}

	$image_url = wp_get_attachment_url($attachment_id);
	if (!$image_url) {
		return $image;
	}

	// don't proxy external images
	if (parse_url(home_url(), PHP_URL_HOST) !== parse_url($url, PHP_URL_HOST)) {
		return $image;
	}

	list($width, $height) = get_image_dimensions($attachment_id, $size);
	if (!$width || !$height) {
		return $image;
	}

	$imgproxy_image = imgproxy()->image($image_url);
	$imgproxy_image->resize($width, $height);

	return [$imgproxy_image->url(), $width, $height];
}
add_filter('wp_get_attachment_image_src', 'Imgproxy\attachment_image_src', 10, 4);

function get_urls_from_content($content) {
    // Regex to match <img> tags and capture the src attribute that starts with http or https
    $pattern = '/(<img[^>]*\bsrc\s*=\s*["\'](https?:\/\/[^"\']+)["\'][^>]*>)/i';

    // Initialize an array to store the matches
    $matches = [];

    // Perform the regex match
    preg_match_all($pattern, $content, $matches);

    // Initialize an array to store the result
    $result = [];

    // Loop through the matches and prepare the result array
    foreach ($matches[0] as $index => $imgTag) {
        $result[] = [$imgTag, $matches[2][$index]];
    }

    // Return the result array
    return $result;
}

function calculate_srcset_meta($image_meta, $size_array, $image_src, $attachment_id) {
	if (is_admin()) {
		return $image_meta;
	}

	list($width, $height) = $size_array;
	if (!$width || !$height) {
		return $image_meta;
	}

	// Only use 1/2 and 1/4 sizes for srcset
	$srcset_threshold = get_srcset_threshold();
	$image_meta['sizes'] = [];
	foreach ([4, 2, 1] as $srcset) {
		if ($width > ($srcset_threshold / $srcset) || $height > ($srcset_threshold / $srcset)) {
			$size = sprintf('%dx%d', $width / $srcset, $height / $srcset);
			$image_meta['sizes'][$size] = [
				'file' => wp_basename( $image_meta['file'] ),
				'width' => $width / $srcset,
				'height' => $height / $srcset,
				'resized' => true,
			];
		}
	}

	return $image_meta;
}
add_filter('wp_calculate_image_srcset_meta', 'Imgproxy\calculate_srcset_meta', 10, 4);

function calculate_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
	if (is_admin()) {
		return $sources;
	}

	list($width, $height) = $size_array;
	if (!$width || !$height) {
		return $sources;
	}

	$sources = array_map(function($source) use ($width, $height) {
		$ratio = $width / $source['value'];
		list($width, $height) = wp_constrain_dimensions($width, $height, $source['value'], $height * $ratio);
		$source['url'] = imgproxy()->image($source['url'])->resize($width, $height)->url();

		return $source;
	}, $sources);

	return $sources;
}
add_filter('wp_calculate_image_srcset', 'Imgproxy\calculate_srcset', 10, 5);

function max_image_width() {
	return 3000;
}
add_filter('max_srcset_image_width', 'Imgproxy\max_image_width');

function calculate_image_sizes($sizes, $size_array, $image_src, $image_meta, $attachment_id) {
	$content_width = isset( $GLOBALS['content_width'] ) ? $GLOBALS['content_width'] : null;
	if ( ! $content_width ) {
		$content_width = 3000;
	}

	list($width, $height) = $size_array;

	$sizes = [];
	$srcset_threshold = get_srcset_threshold();
	$image_meta['sizes'] = [];
	foreach ([4, 2, 1] as $srcset) {
		if ($width > ($srcset_threshold / $srcset) || $height > ($srcset_threshold / $srcset)) {
			$sizes[] = sprintf( '(max-width: %1$dpx) %1$dpx', $width / $srcset );
		}
	}

	$sizes[] = sprintf( '(max-width: %1$dpx) 100vw, %1$dpx', $content_width );

	return implode(', ', $sizes);
}
add_filter('wp_calculate_image_sizes', 'Imgproxy\calculate_image_sizes', 10, 5);

function get_dimensions($width, $height, $size) {
	$sizes = wp_get_registered_image_subsizes();
	if (!is_string($size)) {
		return [$width, $height];
	}

	if (!isset($sizes[$size])) {
		return [$width, $height];
	}

	return wp_constrain_dimensions($width, $height, $sizes[$size]['width'], $sizes[$size]['height']);
}

function get_image_dimensions($id, $size) {
	$metadata = wp_get_attachment_metadata($id);
	if (!$metadata) {
		return false;
	}

	if (!isset($metadata['width']) || !isset($metadata['height'])) {
		return false;
	}

	$width = $metadata['width'];
	$height = $metadata['height'];

	return get_dimensions($width, $height, $size);
}

function attachment_image($id, $size = 'full') {
	$image_url = wp_get_attachment_url($id);
	if (!$image_url) {
		return false;
	}

	$image = imgproxy()->image($image_url);

	$content_width = isset( $GLOBALS['content_width'] ) ? $GLOBALS['content_width'] : null;
	list($width, $height) = get_image_dimensions($id, $size);
	if ($width && $height) {
		list($width, $height) = wp_constrain_dimensions($width, $height, $content_width);
		$image = $image->resize($width, $height);
	}

	return $image;
}

function get_srcset_threshold() {
	return apply_filters('imgproxy_srcset_threshold', 100);
}

function imgproxy() {
	$url = baseurl();
	$key = signing_key();
	$salt = signing_salt();

	return new Imgproxy($url, $key, $salt);
}
