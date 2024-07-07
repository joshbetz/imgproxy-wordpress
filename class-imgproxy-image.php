<?php

namespace Imgproxy;

class ImgproxyImage {

	/**
	 * The base URL of the imgproxy server
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * Constructor
	 *
	 * @param string $image_url The URL of the image to be processed
	 */
	private $image_url;

	/**
	 * The processing parameters
	 *
	 * @var array
	 */
	private $params;

	function __construct($base_url, $image_url, $params = []) {
		$this->base_url = $base_url;
		$this->image_url = $image_url;
		$this->params = $params;
	}

	/**
	 * Generate the imgproxy URL
	 *
	 * @return string The imgproxy URL
	 */
	function url() {
		// Encode the last path segment because it can have special characters
		// like "@" that need to be encoded
		$basename = wp_basename($this->image_url);
		$encoded_basename = urlencode($basename);
		$encoded_url = str_replace($basename, $encoded_basename, $this->image_url);
		$encoded_params = $this->encode_params($this->params);

		$url = $this->base_url . '/i/';

		if ($encoded_params) {
			$url .= $encoded_params . '/';
		}

		$url .= 'plain/' . $encoded_url;

		return $url;
	}

	/**
	 * Encode the processing parameters
	 *
	 * @param array $params The processing parameters
	 * @return string The encoded parameters
	 */
	private function encode_params($params) {
		$encoded_params = [];

		foreach ($params as $key => $value) {
			$encoded_params[] = $key . ':' . $value;
		}

		return implode('/', $encoded_params);
	}

	/**
	 * Resize the image
	 *
	 * @param int $width The width of the resized image
	 * @param int $height The height of the resized image
	 * @return ImgproxyImage The ImgproxyImage instance
	 */
	function resize($width, $height) {
		$this->params['size'] = $width . ':' . $height;
		return $this;
	}

	function dimensions() {
		if (! isset($this->params['resize'])) {
			return false;
		}

		return explode(':', $this->params['resize']);
		return [$width, $height];
	}
}
