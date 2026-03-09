<?php

/**
 * @package Simbila
 * @author Tomas Hnilica <tomas@tomashnilica.com>
 */
/**
 * Requests to Simbila service
 * @package Simbila
 */

interface Simbila_AdapterInterface {

	/**
	 * Execute Simbila API v1 request
	 *
	 * @param string     $url    Full URL to the API action
	 * @param string     $method REST method (GET, POST, PUT, DELETE)
	 * @param string     $apiKey Bearer token for Authorization header
	 * @param array|null $args   Request payload (will be JSON-encoded for non-GET requests)
	 * @return string Body of the response from the Simbila API
	 * @throws Simbila_Exception
	 */
	function request($url, $method, $apiKey, array $args = null);

}
