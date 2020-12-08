<?php

/**
 * @package Simbila
 * @author Tomas Hnilica <tomas@tomashnilica.com>
 */
/**
 * HTTP related data default implementation
 * @package Simbila
 * @author Christophe Coevoet <stof@notk.org>
 * @author Tomas Hnilica <tomas@tomashnilica.com>
 */

class Simbila_Http_NativeAdapter implements Simbila_Http_AdapterInterface {

	/**
	 * Checks whether a cookie exists.
	 *
	 * @param string $name Cookie name
	 * @return boolean
	 */
	public function hasCookie($name) {
		return isset($_COOKIE[$name]);
	}

	/**
	 * Gets the value of a cookie.
	 *
	 * @param string $name Cookie name
	 * @return mixed
	 */
	public function getCookie($name) {
		return $_COOKIE[$name];
	}

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
	public function setCookie($name, $data, $expire, $path, $domain, $secure = false, $httpOnly = false) {
		if (!headers_sent()) {
			// set the cookie
			setcookie($name, $data, $expire, $path, $domain, $secure, $httpOnly);
		}
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function getRequestValue($key) {
		return isset($_REQUEST[$key]) ? $_REQUEST[$key] : null;
	}

	/**
	 * @return boolean
	 */
	public function hasReferrer() {
		return !empty($_SERVER['HTTP_REFERER']);
	}

	/**
	 * @return string
	 */
	public function getReferrer() {
		return $this->hasReferrer() ? $_SERVER['HTTP_REFERER'] : '';
	}

	/**
	 * @return boolean
	 */
	public function hasIp() {
		return !empty($_SERVER['REMOTE_ADDR']);
	}

	/**
	 * @return string
	 */
	public function getIp() {
		return $this->hasIp() ? $_SERVER['REMOTE_ADDR'] : '';
	}
}
