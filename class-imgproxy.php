<?php

namespace Imgproxy;

class Imgproxy {

	/**
	 * Constructor
	 *
	 * @param string $base_url The base URL of the imgproxy server
	 */
	private $base_url;

	function __construct($base_url) {
		$this->base_url = $base_url;
	}

	function is_imgproxy_url($url) {
		return strpos($url, $this->base_url) === 0;
	}

	function domain() {
		return parse_url($this->base_url, PHP_URL_HOST);
	}

	function image($image_url, $params = []) {
		return new ImgproxyImage($this->base_url, $image_url, $params);
	}
}
