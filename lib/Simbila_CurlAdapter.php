<?php

/**
 * @package Simbila
 * @author Tomas Hnilica <tomas@tomashnilica.com>
 */
/**
 * php-curl for requesting the Simbila service adapter implementation
 * @package Simbila
 * @author Tomas Hnilica <tomas@tomashnilica.com>
 */

class Simbila_CurlAdapter implements Simbila_AdapterInterface {

	protected $_resource;

	/**
	 * @param resource $resource
	 * @throws Simbila_Exception Throws an exception if php-curl is not available.
	 */
	public function __construct($resource = null) {
		if (!function_exists('curl_init')) {
			throw new Simbila_Exception('The curl extension is not loaded.', Simbila_Exception::USAGE_INVALID);
		}

		if ($resource && (!is_resource($resource) || get_resource_type($resource) != 'curl')) {
			throw new Simbila_Exception('The curl resource is invalid.', Simbila_Exception::USAGE_INVALID);
		}

		$this->_resource = $resource;
	}

	/**
	 * Execute Simbila API v1 request
	 *
	 * @param string     $url    Full URL to the API action
	 * @param string     $method REST method (GET|POST|PUT|DELETE)
	 * @param string     $apiKey Bearer token for the Authorization header
	 * @param array|null $args   Request payload (JSON-encoded for non-GET; appended as query string for GET)
	 * @return string Body of the response from the Simbila API
	 * @throws Simbila_Exception Throws an exception if the curl session results in an error.
	 */
	public function request($url, $method, $apiKey, array $args = null) {
		if (!in_array($method, array('GET', 'POST', 'PUT', 'DELETE'))) {
			throw new Simbila_Exception('The REST method is invalid.', Simbila_Exception::REQUEST_INVALID);
		}

		$userAgent = (isset($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'] . ' - Simbila PHP' : 'Simbila PHP';

		if (!$this->_resource) {
			$this->_resource = curl_init();
			curl_setopt_array($this->_resource, array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_SSL_VERIFYHOST => 2,
				CURLOPT_CONNECTTIMEOUT => 10,
				CURLOPT_TIMEOUT        => 60,
				CURLOPT_USERAGENT      => $userAgent,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS      => 10,
			));
		}

		// Common headers for all requests
		$headers = array(
			'Authorization: Bearer ' . $apiKey,
			'Accept: application/json',
		);

		switch ($method) {
			case 'GET':
				curl_setopt($this->_resource, CURLOPT_HTTPGET, true);
				if ($args) {
					// The URL may already carry a query string (e.g. ?appId=X&appUser=Y).
					// Use '&' as the separator when a '?' is already present.
					$separator  = (strpos($url, '?') !== false) ? '&' : '?';
					$requestUrl = $url . $separator . http_build_query($args);
				} else {
					$requestUrl = $url;
				}
				curl_setopt($this->_resource, CURLOPT_URL, $requestUrl);
				break;

			case 'POST':
				$body = $args ? json_encode($args) : '{}';
				curl_setopt($this->_resource, CURLOPT_URL, $url);
				curl_setopt($this->_resource, CURLOPT_POST, true);
				curl_setopt($this->_resource, CURLOPT_POSTFIELDS, $body);
				$headers[] = 'Content-Type: application/json';
				$headers[] = 'Content-Length: ' . strlen($body);
				break;

			case 'PUT':
				$body = $args ? json_encode($args) : '{}';
				curl_setopt($this->_resource, CURLOPT_URL, $url);
				curl_setopt($this->_resource, CURLOPT_CUSTOMREQUEST, 'PUT');
				curl_setopt($this->_resource, CURLOPT_POSTFIELDS, $body);
				$headers[] = 'Content-Type: application/json';
				$headers[] = 'Content-Length: ' . strlen($body);
				break;

			case 'DELETE':
				curl_setopt($this->_resource, CURLOPT_URL, $url);
				curl_setopt($this->_resource, CURLOPT_CUSTOMREQUEST, 'DELETE');
				break;
		}

		curl_setopt($this->_resource, CURLOPT_HTTPHEADER, $headers);

		$result = curl_exec($this->_resource);

		if ($result === false || curl_error($this->_resource) !== '') {
			throw new Simbila_Exception(
				'cUrl session resulted in an error: (' . curl_errno($this->_resource) . ') ' . curl_error($this->_resource),
				Simbila_Exception::UNKNOWN
			);
		}

		return $result;
	}

	/**
	 * @return null|resource
	 */
	public function getCurlResource() {
		return $this->_resource;
	}
}
