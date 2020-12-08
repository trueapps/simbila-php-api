<?php

/**
 * @package Simbila
 * @author Tomas Hnilica <tomas@tomashnilica.com>
 */
/**
 * Response object
 * @package Simbila
 * @author Tomas Hnilica <tomas@tomashnilica.com>
 */

class Simbila_Response {

	private $_responseType;
	private $_array;

	/**
	 * Constructor
	 *
	 * @param $response string well formed xml
	 * @throws Simbila_Response_Exception in the event the json is not well formed
	 */
	public function __construct($response) {
		$this->_response = $response;
		$this->_array = json_decode($response, true);

		/*
		if (empty($this->_array)) {
			throw new Simbila_Response_Exception("Response failed to load JSON.\n\n$response", Simbila_Response_Exception::UNKNOWN);
		}
		*/

		//th: ?? type of response?
		//$this->_responseType = $this->documentElement->nodeName;

	}



	/**
	 * Get a nested array representation of the response doc
	 *
	 * @return array
	 */
	public function obj() {
		return $this->_array;
	}

	public function response() {
		return $this->_response;
	}

	/**
	 * Get a JSON encoded string representation of the response doc
	 *
	 * @return string
	 */
	public function toJson() {
		return json_encode($this->toArray());
	}



}
