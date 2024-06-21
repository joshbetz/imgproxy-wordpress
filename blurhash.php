<?php

namespace Imgproxy;

require_once 'vendor/autoload.php';
use kornrunner\Blurhash\Blurhash;

function calculate_blurhash($attachment_id) {
	$blurhash = blurhash($attachment_id);
	if (!$blurhash) {
		return;
	}

	update_post_meta($attachment_id, '_blurhash', $blurhash);
}
add_action('add_attachment', 'Imgproxy\calculate_blurhash');

function add_background_image_style_to_attachment_images($attr, $attachment, $size) {
	if (defined('REST_REQUEST') && REST_REQUEST) {
		return $attr;
	}

	if (is_admin()) {
		return $attr;
	}

	$blurhash = get_post_meta($attachment->ID, '_blurhash', true);
	// if blurhash is not a string
	if (!$blurhash || !is_string($blurhash)) {
		return $attr;
	}

	list($width, $height) = get_image_dimensions($attachment->ID, $size);
	if (!$width || !$height) {
		return $attr;
	}

	$style = blurhashToBase64($blurhash, $width, $height);
	if (!$style) {
		return $attr;
	}

	$attr['style'] = $style;
	return $attr;
}
add_filter('wp_get_attachment_image_attributes', 'Imgproxy\add_background_image_style_to_attachment_images', 10, 3);

function blurhash($attachment_id) {
	$file = get_attached_file($attachment_id);
	if (!$file) {
		return false;
	}

	$image = imagecreatefromstring(file_get_contents($file));
	if (!$image) {
		return false;
	}

	$width = imagesx($image);
	$height = imagesy($image);

	list($width, $height) = wp_constrain_dimensions($width, $height, BLURHASH_MAX_RESIZE, BLURHASH_MAX_RESIZE);
	$image = imagescale($image, $width, $height);

	$pixels = [];
	for ($y = 0; $y < $height; ++$y) {
		$row = [];
		for ($x = 0; $x < $width; ++$x) {
			$index = imagecolorat($image, $x, $y);
			$colors = imagecolorsforindex($image, $index);

			$row[] = [$colors['red'], $colors['green'], $colors['blue']];
		}
		$pixels[] = $row;
	}

	// Adjust components for landscape vs portrait images
	// Wide images should have more components on the x-axis
	// Tall images should have more components on the y-axis
	$components_x = BLURHASH_COMPONENTS_Y;
	$components_y = BLURHASH_COMPONENTS_Y;
	if ($width > $height) {
		$components_x = BLURHASH_COMPONENTS_X;
	} else if ($height > $width) {
		$components_y = BLURHASH_COMPONENTS_X;
	}

	return Blurhash::encode($pixels, $components_x, $components_y);
}

function blurhashToBase64($blurhash, $width, $height) {
	// Decode the BlurHash to get the color components
	$pixels = Blurhash::decode($blurhash, $width, $height);

	// Create an image resource
	$image = imagecreatetruecolor($width, $height);

	// Fill the image with the decoded pixel colors
	for ($y = 0; $y < $height; $y++) {
		for ($x = 0; $x < $width; $x++) {
			$color = $pixels[$y][$x];
			$r = round($color[0]);
			$g = round($color[1]);
			$b = round($color[2]);
			$colorIndex = imagecolorallocate($image, $r, $g, $b);
			imagesetpixel($image, $x, $y, $colorIndex);
		}
	}

	// Capture the output to a variable
	ob_start();
	imagepng($image);
	$imageData = ob_get_contents();
	ob_end_clean();

	// Destroy the image resource
	imagedestroy($image);

	// Encode the image data to base64
	$base64 = base64_encode($imageData);
	$base64Url = 'data:image/png;base64,' . $base64;
	return sprintf('background-size: cover; background-image: url(%s);', $base64Url);
}