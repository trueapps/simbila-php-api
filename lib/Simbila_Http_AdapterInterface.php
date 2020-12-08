<?php

/**
 * @package Simbila
 * @author Tomas Hnilica <tomas@tomashnilica.com>
 */
/**
 * HTTP related data interface
 * @package Simbila
 * @author Christophe Coevoet <stof@notk.org>
 */

interface Simbila_Http_AdapterInterface {

	/**
	 * Checks whether a cookie exists.
	 *
	 * @param string $name Cookie name
	 * @return boolean
	 */
	function hasCookie($name);

	/**
	 * Gets the value of a cookie.
	 *
	 * @param string $name Cookie name
	 * @return mixed
	 */
	function getCookie($name);

	/**
	 * Sets the value of a cookie.
	 *
	 * @param string $name Cookie name
	 * @param string $data Value of the cookie
	 * @param int $expire
	 * @param string $path
	 * @param string $domain
	 * @param boolean $secure
	 * @param boolean $httpOnly
	 */
	function setCookie($name, $data, $expire, $path, $domain, $secure = false, $httpOnly = false);

	/**
	 * Gets a request parameter.
	 *
	 * null is returned if the key is not set.
	 *
	 * @param string $key
	 * @return mixed
	 */
	function getRequestValue($key);

	/**
	 * @return boolean
	 */
	function hasReferrer();

	/**
	 * @return string
	 */
	function getReferrer();

	/**
	 * @return boolean
	 */
	function hasIp();

	/**
	 * @return string
	 */
	function getIp();

}
