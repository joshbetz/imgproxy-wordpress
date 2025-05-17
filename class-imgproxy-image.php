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
	 * The signing key of the imgproxy server
	 *
	 * @var string
	 */
	private $signing_key;

	/**
	 * The signing salt of the imgproxy server
	 *
	 * @var string
	 */
	private $signing_salt;

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

	function __construct($base_url, $signing_key, $signing_salt, $image_url, $params = []) {
		$this->base_url = $base_url;
		$this->signing_key = $signing_key;
		$this->signing_salt = $signing_salt;
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

		if ($encoded_params) {
			$path = '/' . $encoded_params . '/plain/' . $encoded_url;
		} else {
			$path = '/plain/' . $encoded_url;
		}

		if ($this->signing_key == null or $this->signing_key == '' or $this->signing_salt == null or $this->signing_salt == '') {
			return $this->base_url . '/i' . $path;
		} else {
			$signature = $this->get_path_signature($path);

			return $this->base_url . '/' . $signature . $path;
		}
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
	 * Sign path to the image
	 *
	 * @param string $path Path to be signed
	 * @return string Signed path as string
	 */
	private function get_path_signature($path)
	{
		$keyBin = pack("H*", $this->signing_key);

		if (empty($keyBin)) {
			die('Key expected to be hex-encoded string');
		}

		$saltBin = pack("H*", $this->signing_salt);

		if (empty($saltBin)) {
			die('Salt expected to be hex-encoded string');
		}

		return rtrim(strtr(base64_encode(hash_hmac('sha256', $saltBin . $path, $keyBin, true)), '+/', '-_'), '=');
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
