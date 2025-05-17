<?php

namespace Imgproxy;

class Imgproxy {

	/**
	 * Constructor
	 *
	 * @param string $base_url The base URL of the imgproxy server
	 * @param string $signing_key The signing key of the imgproxy server
	 * @param string $signing_salt The signing salt of the imgproxy server
	 */
	private $base_url;
	private $signing_key;
	private $signing_salt;

	function __construct($base_url, $signing_key, $signing_salt) {
		$this->base_url = $base_url;
		$this->signing_key = $signing_key;
		$this->signing_salt = $signing_salt;
	}

	function is_imgproxy_url($url) {
		return strpos($url, $this->base_url) === 0;
	}

	function domain() {
		return parse_url($this->base_url, PHP_URL_HOST);
	}

	function image($image_url, $params = []) {
		return new ImgproxyImage($this->base_url, $this->signing_key, $this->signing_salt, $image_url, $params);
	}
}
