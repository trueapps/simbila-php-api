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
	 * Execute Simbila API request
	 *
	 * @param string $url Url to the API action
	 * @param string $method REST method (GET, POST, PUT, DELETE)
   * @param string $username Username
   * @param string $password Password
	 * @param array|null $args HTTP post key value pairs
	 * @return string Body of the response from the Simbila API
	 * @throws Simbila_Exception
	 */
	function request($url, $method, $username, $password, array $args = null);

}
