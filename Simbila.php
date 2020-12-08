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
	 * The adapter to access cookie data etc.
	 * By default, it will use PHP superglobals directly but an implementation based on the
	 * abstraction of a framework can be used.
	 *
	 * @var Simbila_Http_AdapterInterface
	 */
	static private $_requestAdapter;
	

	/**
	 * @var string Username credential for accessing the Simbila API
	 */
	private $_username;

	/**
	 * @var string Password credential for accessing the Simbila API
	 */
	private $_password;

	/**
	 * @var string This Application ID
	 */
	private $_appId;


	/**
	 * @var string This Application user ID
	 */
	private $_appUser;

	/**
	 * @var string URL for accessing the Simbila API
	 */
	private $_url;


	/**
	 *
	 * @var Simbila_Client_AdapterInterface
	 */
	private $_httpClient;

	/**
	 * Constructor
	 *
	 * @param $url string
	 * @param $username string
	 * @param $password string
	 * @param $productCode string
	 * @param string $productId
	 * @param Simbila_Client_AdapterInterface $adapter
	 */
	public function __construct( $username, $password, $appId, $appUser = '', Simbila_Client_AdapterInterface $adapter = null) {

		$this->setUrl('https://www.simbila.com/api');
		$this->setUsername($username);
		$this->setPassword($password);
		$this->setAppId($appId);
		$this->setAppUser($appUser);

		if (!$adapter) {
				$adapter = new Simbila_CurlAdapter();
		}
		$this->_httpClient = $adapter;
	}

	/**
	 * Set URL neccessary for for accessing the Simbila API
	 *
	 * @param $url string
	 * @return Simbila_Client
	 */
	public function setUrl($url) {
		$this->_url = $url;
		return $this;
	}

	/**
	 * Get URL
	 *
	 * @return string
	 */
	public function getUrl() {
		return $this->_url;
	}

	/**
	 * Set username neccessary for for accessing the Simbila API
	 *
	 * @param $username string
	 * @return Simbila_Client
	 */
	public function setUsername($username) {
		$this->_username = $username;
		return $this;
	}

	/**
	 * Get username
	 *
	 * @return string
	 */
	public function getUsername() {
		return $this->_username;
	}

	/**
	 * Set password neccessary for accessing the Simbila API
	 *
	 * @param $password string
	 * @return Simbila_Client
	 */
	public function setPassword($password) {
		$this->_password = $password;
		return $this;
	}

	/**
	 * Get current password
	 *
	 * @return string
	 */
	private function _getPassword() {
		return $this->_password;
	}

	/**
	 * Set application ID
	 *
	 * @param $appId string
	 * @return Simbila_Client
	 */
	public function setAppId($appId) {
		$this->_appId = $appId;
		return $this;
	}

	/**
	 * Get current application ID
	 *
	 * @return string
	 */
	public function getAppId() {
		return $this->_appId;
	}
	
		/**
	 * Get current application user
	 *
	 * @return string
	 */
	public function getAppUser() {
		return $this->_appUser;
	}

	/**
	 * Set application user
	 *
	 * @param $appId string
	 * @return Simbila_Client
	 */
	public function setAppUser($appUser) {
		$this->_appUser = $appUser;
		return $this;
	}

	/**
	Clear cached variable /session/
	*/
	public function clearCache() {
		$session_key = "simbila-".$this->_appId."-".$this->_appUser;
		if(isset($_SESSION[$session_key])) {
			unset($_SESSION[$session_key]);
		}
	}
	
	/**
	Get status data of the user
	*/
	public function billingStatus($cache = true) {
		$session_key = "simbila-".$this->_appId."-".$this->_appUser;
		if ($cache && isset($_SESSION[$session_key])) {
			/*return saved session*/
			return new Simbila_Response($_SESSION[$session_key]);
		}		
		$response = $this->request('/billing/status');
		$_SESSION[$session_key] = $response;
		return new Simbila_Response($response);
	}

	/**
	Create new billing (subscription)
	*/
	public function createBilling($params) {
		return new Simbila_Response(
			$this->request('/billing/create','POST', $params)
		);		
		
	}
	
	/**
	Update current billing (subscription)
	*/
	public function updateBilling($params, $userId = '') {
		return new Simbila_Response(
			$this->request('/billing/update','PUT', $params)
		);		
	}

	/**
	Delete billing (subscription)
	*/
	public function deleteBilling() {
		return new Simbila_Response(
			$this->request('/billing/delete','DELETE')
		);				
	}

	
	/**
	Return all invoices of an account
	*/
	public function billingInvoices() {
		return new Simbila_Response(
			$this->request('/billing/invoices','GET')
		);				
		
	}
	
/****************************************************/
/*	general Simbila API															*/
/****************************************************/

	/**
	Get all invoices of the client
	TH todo: implement filtering
	*/
	public function invoices() {
		return new Simbila_Response(
			$this->request('/invoice/list','GET')
		);					
	}


	
	/**
	Get details of a single invoice
	*/
	public function invoice($id) {
		return new Simbila_Response(
			$this->request('/invoice/' . $id, 'GET')
		);							
	}

	/**
	Create new invoice (single invoice)
	*/
	public function createInvoice($params) {
		return new Simbila_Response(
			$this->request('/invoice/create','POST', $params)
		);		
		
	}

  /**
	Delete invoice
	*/
	public function deleteInvoice($id) {
		return new Simbila_Response(
			$this->request('/invoice/' . $id ,'DELETE')
		);				
	}

	/**
	Update invoice 
	*/
	public function updateInvoice($id, $params) {
		return new Simbila_Response(
			$this->request('/invoice/' . $id,'PUT', $params)
		);		
	}
    
	/**
	 * Invoice action on invoice
	 */
	 public function invokeInvoiceAction($id, $action, $params = array()) {
		return new Simbila_Response(
			$this->request('/invoice/' . $id . '/fire?method=fire&action=' . $action . '&id=' . $id, 'POST', $params)
		);							
	 	
	 }
     
	/**
	 * Send and invoice (issue sending on Simbila service)
	 */
	 public function sendInvoice($id) {
	 	return $this->invokeInvoiceAction($id, 'send');
	 }     
     
	/**
	Returen all clients
	*/
	public function clients() {
		return new Simbila_Response(
			$this->request('/firm/list', 'GET')
		);					
	}

	/**
	Get details of a single client
	*/
	public function client($id) {
		return new Simbila_Response(
			$this->request('/firm/' . $id, 'GET')
		);							
	}

	/**
	Returen all recurring invoices
	*/
	public function rinvoices() {
		return new Simbila_Response(
			$this->request('/rinvoice/list', 'GET')
		);					
	}

	/**
	Get details of a single recurring invoice
	*/
	public function rinvoice($id) {
		return new Simbila_Response(
			$this->request('/rinvoice/' . $id, 'GET')
		);							
	}

    
	/**
	Create new payment
	*/
	public function createPayment($params) {
		return new Simbila_Response(
			$this->request('/payment/create','POST', $params)
		);		
		
	}    

	/**
	 * Execute Simbila API request
	 *
	 * @param string $path Path to the API action
	 * @param array|null $args HTTP post key value pairs
	 * @return string Body of the response from the Simbila API
	 * @throws Simbila_Exception
	 */
	protected function request($path, $method = 'GET', array $args = null) {
		$url = $this->_url . $path;
		if (strpos($url, '?')) $url = $url . "&appId=".$this->getAppId() . "&appUser=" . $this->getAppUser();
		else $url = $url . "?appId=".$this->getAppId() . "&appUser=" . $this->getAppUser();
		return $this->_httpClient->request($url, $method, $this->getUsername(), $this->_getPassword(), $args);
	}

	/**
	 * Set http client
	 *
	 * @param Simbila_AdapterInterface|resource $client curl resource.
	 * @return Simbila
	 * @throws Simbila_Exception
	 */
	public function setHttpClient($client) {
		if ($client instanceof Simbila_AdapterInterface) {
			$this->_httpClient = $client;
			return $this;
		}

		
		if (is_resource($client) && get_resource_type($client) == 'curl') {
			$this->_httpClient = new Simbila_CurlAdapter($client);
			return $this;
		}

		throw new Simbila_Exception("httpClient can only be an instance of Simbila_AdapterInterface or a php curl resource.", Simbila_Exception::USAGE_INVALID);
	}

	/**
	 * Get the current http client
	 *
	 * @return Simbila_AdapterInterface
	 */
	public function getHttpClient() {
		return $this->_httpClient;
	}

	/**
	 * Set request adapter
	 *
	 * @param Simbila_Http_AdapterInterface $requestAdapter
	 */
	static public function setRequestAdapter(Simbila_Http_AdapterInterface $requestAdapter) {
		self::$_requestAdapter = $requestAdapter;
	}

	/**
	 * Gets the request adapter.
	 *
	 * @return Simbila_Http_AdapterInterface
	 */
	static public function getRequestAdapter() {
		if (!self::$_requestAdapter) {			
				self::$_requestAdapter = new Simbila_Http_NativeAdapter();			
		}

		return self::$_requestAdapter;
	}

	/**
		TH: do delete?
	 * Convenience method for requiring an identifier
	 *
	 * @param string $code
	 * @param string $id
	 * @return bool true if $code or $id exists
	 * @throws Simbila_Exception if neither identifier exists
	 */
	private function _requireIdentifier($code, $id) {
		if (!$code && !$id) {
			throw new Simbila_Exception('Either a code or id is required', CheddarGetter_Client_Exception::USAGE_INVALID);
		}
		return true;
	}

	


}
