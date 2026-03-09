<?php

/**
 * @category Simbila
 * @package Simbila
 * @author Tomas Hnilica <tomas@tomashnilica.com>
 */
/**
 * Client object for interaction with www.simbila.com service
 * @category Simbila
 * @package Simbila
 * @author Tomas Hnilica <tomas@tomashnilica.com>
 */

class Simbila {

	/*Constants returned from Simbila service*/
	const NO_PLAN = 'no_plan';
	const OK = 'ok';
	const INVOICE_DUE = 'invoice_due';
	const INVOICE_PAID = 'paid';
	const INVOICE_AFTER_MATURITY = 'after maturity';
	const INVOICE_BEFORE_MATURITY = 'before maturity';


	/**
	 * @var string Bearer API key for accessing the Simbila API v1
	 */
	private $_apiKey;

	/**
	 * @var string|null 3rd-party application identifier (stored as firms.fy_app in Simbila)
	 */
	private $_appId;

	/**
	 * @var string|int|null 3rd-party application user identifier (stored as firms.fy_app_user in Simbila)
	 */
	private $_appUser;

	/**
	 * @var string Base URL for the Simbila API v1
	 */
	private $_url;

	/**
	 * @var Simbila_AdapterInterface
	 */
	private $_httpClient;

	/**
	 * Constructor
	 *
	 * @param string                    $apiKey   Bearer API key (obtained from Simbila account settings)
	 * @param string|null               $appId    3rd-party app identifier (e.g. 'lunchdrive'); stored as firms.fy_app
	 * @param string|int|null           $appUser  3rd-party app user identifier (e.g. 345); stored as firms.fy_app_user
	 * @param Simbila_AdapterInterface  $adapter  HTTP adapter (defaults to Simbila_CurlAdapter)
	 */
	public function __construct($apiKey, $appId = null, $appUser = null, Simbila_AdapterInterface $adapter = null) {
		$this->setUrl('https://simbila.com/api/v1');
		$this->setApiKey($apiKey);
		$this->_appId   = $appId;
		$this->_appUser = $appUser;

		if (!$adapter) {
			$adapter = new Simbila_CurlAdapter();
		}
		$this->_httpClient = $adapter;
	}

	/**
	 * Set base URL for the Simbila API
	 *
	 * @param string $url
	 * @return Simbila
	 */
	public function setUrl($url) {
		$this->_url = $url;
		return $this;
	}

	/**
	 * Get base URL
	 *
	 * @return string
	 */
	public function getUrl() {
		return $this->_url;
	}

	/**
	 * Set Bearer API key
	 *
	 * @param string $apiKey
	 * @return Simbila
	 */
	public function setApiKey($apiKey) {
		$this->_apiKey = $apiKey;
		return $this;
	}

	/**
	 * Get Bearer API key
	 *
	 * @return string
	 */
	public function getApiKey() {
		return $this->_apiKey;
	}

	/**
	 * Set the 3rd-party application identifier (stored as firms.fy_app)
	 *
	 * @param string|null $appId
	 * @return Simbila
	 */
	public function setAppId($appId) {
		$this->_appId = $appId;
		return $this;
	}

	/**
	 * Get the 3rd-party application identifier
	 *
	 * @return string|null
	 */
	public function getAppId() {
		return $this->_appId;
	}

	/**
	 * Set the 3rd-party application user identifier (stored as firms.fy_app_user)
	 *
	 * @param string|int|null $appUser
	 * @return Simbila
	 */
	public function setAppUser($appUser) {
		$this->_appUser = $appUser;
		return $this;
	}

	/**
	 * Get the 3rd-party application user identifier
	 *
	 * @return string|int|null
	 */
	public function getAppUser() {
		return $this->_appUser;
	}

	/**
	 * Build the appId/appUser query string used by billing endpoints.
	 * Returns e.g. "?appId=lunchdrive&appUser=345" or "" when not set.
	 *
	 * @return string
	 */
	private function appQueryString() {
		$params = array();
		if ($this->_appId !== null && $this->_appId !== '') {
			$params[] = 'appId=' . urlencode($this->_appId);
		}
		if ($this->_appUser !== null && $this->_appUser !== '') {
			$params[] = 'appUser=' . urlencode($this->_appUser);
		}
		return $params ? '?' . implode('&', $params) : '';
	}

	/**
	 * Get the Simbila firm (client) record linked to the 3rd-party app user.
	 * Returns the firm's attributes (name, email, address, etc.).
	 * Corresponds to GET /api/v1/billing/client?appId=X&appUser=Y
	 */
	public function billingClient() {
		return new Simbila_Response(
			$this->request('/billing/client' . $this->appQueryString())
		);
	}

	/**
	 * Get billing status for the 3rd-party app user (identified by appId + appUser)
	 */
	public function billingStatus() {
		return new Simbila_Response(
			$this->request('/billing/status' . $this->appQueryString())
		);
	}

	/**
	 * Create new billing (subscription) for the 3rd-party app user
	 */
	public function createBilling($params) {
		return new Simbila_Response(
			$this->request('/billing' . $this->appQueryString(), 'POST', $params)
		);
	}

	/**
	 * Update current billing (subscription) for the 3rd-party app user
	 */
	public function updateBilling($params) {
		return new Simbila_Response(
			$this->request('/billing' . $this->appQueryString(), 'PUT', $params)
		);
	}

	/**
	 * Delete billing (subscription) for the 3rd-party app user
	 */
	public function deleteBilling() {
		return new Simbila_Response(
			$this->request('/billing' . $this->appQueryString(), 'DELETE')
		);
	}

	/**
	 * Return all billing invoices for the 3rd-party app user
	 */
	public function billingInvoices() {
		return new Simbila_Response(
			$this->request('/billing/invoices' . $this->appQueryString())
		);
	}
	
/****************************************************/
/*	general Simbila API															*/
/****************************************************/

	/**
	 * Get all invoices of the account
	 */
	public function invoices() {
		return new Simbila_Response(
			$this->request('/invoices')
		);
	}

	/**
	 * Get invoices for the current client/firm
	 */
	public function clientInvoices() {
		return new Simbila_Response(
			$this->request('/invoices/client')
		);
	}

	/**
	 * Get details of a single invoice
	 */
	public function invoice($id) {
		return new Simbila_Response(
			$this->request('/invoices/' . $id)
		);
	}

	/**
	 * Create new invoice
	 */
	public function createInvoice($params) {
		return new Simbila_Response(
			$this->request('/invoices', 'POST', $params)
		);
	}

	/**
	 * Update invoice
	 */
	public function updateInvoice($id, $params) {
		return new Simbila_Response(
			$this->request('/invoices/' . $id, 'PUT', $params)
		);
	}

	/**
	 * Delete invoice
	 */
	public function deleteInvoice($id) {
		return new Simbila_Response(
			$this->request('/invoices/' . $id, 'DELETE')
		);
	}

	/**
	 * Invoke an action on an invoice
	 */
	public function invokeInvoiceAction($id, $action, $params = array()) {
		return new Simbila_Response(
			$this->request('/invoices/' . $id . '/action/' . $action, 'POST', $params)
		);
	}

	/**
	 * Send an invoice
	 */
	public function sendInvoice($id) {
		return $this->invokeInvoiceAction($id, 'send');
	}

	/**
	 * Return all clients (firms)
	 */
	public function clients() {
		return new Simbila_Response(
			$this->request('/firms')
		);
	}

	/**
	 * Get details of a single client (firm)
	 */
	public function client($id) {
		return new Simbila_Response(
			$this->request('/firms/' . $id)
		);
	}

	/**
	 * Return all recurring invoices
	 */
	public function rinvoices() {
		return new Simbila_Response(
			$this->request('/rinvoices')
		);
	}

	/**
	 * Get details of a single recurring invoice
	 */
	public function rinvoice($id) {
		return new Simbila_Response(
			$this->request('/rinvoices/' . $id)
		);
	}

	/**
	 * Create new payment
	 */
	public function createPayment($params) {
		return new Simbila_Response(
			$this->request('/payments', 'POST', $params)
		);
	}

	/**
	 * Generic request helper — call any v1 endpoint directly
	 *
	 * @param string     $path   e.g. '/invoices/42'
	 * @param string     $method GET|POST|PUT|DELETE
	 * @param array|null $args
	 * @return Simbila_Response
	 */
	public function req($path, $method = 'GET', array $args = null) {
		return new Simbila_Response(
			$this->request($path, $method, $args)
		);
	}

	/**
	 * Execute a Simbila API v1 request
	 *
	 * @param string     $path   Relative path, e.g. '/invoices'
	 * @param string     $method GET|POST|PUT|DELETE
	 * @param array|null $args   Request payload
	 * @return string Raw response body
	 * @throws Simbila_Exception
	 */
	protected function request($path, $method = 'GET', array $args = null) {
		$url = $this->_url . $path;
		return $this->_httpClient->request($url, $method, $this->_apiKey, $args);
	}

	/**
	 * Set HTTP client adapter
	 *
	 * @param Simbila_AdapterInterface $client
	 * @return Simbila
	 * @throws Simbila_Exception
	 */
	public function setHttpClient($client) {
		if ($client instanceof Simbila_AdapterInterface) {
			$this->_httpClient = $client;
			return $this;
		}
		throw new Simbila_Exception("httpClient must be an instance of Simbila_AdapterInterface.", Simbila_Exception::USAGE_INVALID);
	}

	/**
	 * Get the current HTTP client adapter
	 *
	 * @return Simbila_AdapterInterface
	 */
	public function getHttpClient() {
		return $this->_httpClient;
	}

	


}
