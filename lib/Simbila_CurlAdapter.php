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
	 * Execute Simbila API request
	 *
	 * @param string $url Url to the API action
	 * @param string $metohd REST method of the call (GET|POST|PUT|DELETE)
	 * @param string $username Username
	 * @param string $password Password
	 * @param array|null $args HTTP post key value pairs
	 * @return string Body of the response from the Simbila API
	 * @throws Simbila_Exception Throws an exception if the curl session results in an error.
	 */
	public function request($url, $method, $username, $password, array $args = null) {
		if ($method!='GET' && $method!="POST" && $method!="PUT" && $method!="DELETE") 
						throw new Simbila_Exception('The REST method is invalid.', Simbila_Exception::REQUEST_INVALID);

		if (!$this->_resource) {
			$this->_resource = curl_init($url);
			$userAgent = (isset($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'] . ' - Simbila PHP' : 'Simbila PHP';
			
			
			$options = array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_CONNECTTIMEOUT => 10,
				CURLOPT_TIMEOUT => 60,
				CURLOPT_USERAGENT => $userAgent,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_VERBOSE, true,
			);
			foreach ($options as $key=>$val) {
				curl_setopt($this->_resource, $key, $val);
			}
			
		} else {
			curl_setopt($this->_resource, CURLOPT_HTTPGET, true);
			curl_setopt($this->_resource, CURLOPT_URL, $url);
		}


		switch($method) {
			case 'GET':
				curl_setopt($this->_resource, CURLOPT_HTTPHEADER, array('X-HTTP-Method-Override: GET'));
				curl_setopt($this->_resource, CURLOPT_HTTPGET, true);
				curl_setopt($this->_resource, CURLOPT_CUSTOMREQUEST, 'GET');
				if ($args) curl_setopt($this->_resource, CURLOPT_URL, $url . '?'.http_build_query($args));
				break;
			case 'POST':
				curl_setopt($this->_resource, CURLOPT_HTTPHEADER, array('X-HTTP-Method-Override: POST'));
				curl_setopt($this->_resource, CURLOPT_POST, true);
				curl_setopt($this->_resource, CURLOPT_POSTFIELDS, http_build_query($args));
				break;
			case 'PUT':
				curl_setopt($this->_resource, CURLOPT_POST, true);
				curl_setopt($this->_resource, CURLOPT_CUSTOMREQUEST, "PUT");
				curl_setopt($this->_resource, CURLOPT_HTTPHEADER, array('X-HTTP-Method-Override: PUT'));
				curl_setopt($this->_resource, CURLOPT_POSTFIELDS, http_build_query($args));
				break;
			case 'DELETE':
				curl_setopt($this->_resource, CURLOPT_CUSTOMREQUEST, "DELETE");
				curl_setopt($this->_resource, CURLOPT_HTTPHEADER, array('X-HTTP-Method-Override: DELETE'));
				break;
		}

		//set HTTP auth headers
		curl_setopt($this->_resource, CURLOPT_HTTPHEADER, array('X_SIMBILA_USERNAME: '.$username,'X_SIMBILA_PASSWORD: ' . $password));

		/*
		curl_setopt($this->_resource, CURLOPT_HEADER, true);
		curl_setopt($this->_resource, CURLOPT_VERBOSE, true);
		*/
		$result = curl_exec($this->_resource);

		if ($result === false || curl_error($this->_resource) != '') {
			throw new Simbila_Exception('cUrl session resulted in an error: (' . curl_errno($this->_resource) . ')' . curl_error($this->_resource), Simbila_Exception::UNKNOWN);
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
