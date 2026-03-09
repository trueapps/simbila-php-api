<?php
/**
 * Simbila API Yii component class file.
 * @author Tomas Hnilica <tomas@tomashnilica.com>
 * @copyright Copyright &copy; Tomas Hnilica 2013
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @version 1.0.0
 */

/**
 * Simbila application component.
 */
class YiiSimbila extends CApplicationComponent
{

	/**
	 * @var string Simbila Bearer API key (obtained from Simbila account settings).
	 */
	public $apiKey;

	/**
	 * @var string|null 3rd-party application identifier (stored as firms.fy_app).
	 *   E.g. 'lunchdrive'. Required for all billing methods.
	 */
	public $appId;

	/**
	 * @var string|int|null 3rd-party application user identifier (stored as firms.fy_app_user).
	 *   E.g. 345. Required for all billing methods.
	 */
	public $appUser;

	/**
	 * @var string Simbila API v1 base URL
	 */
	public $simbilaUrl = 'https://simbila.com/api/v1';

	/**
	 * @var Simbila Simbila client object
	 */
	public $simbila = null;


	/**
	 * Initializes the component.
	 */
	public function init()
	{
		$this->simbila = new Simbila($this->apiKey, $this->appId, $this->appUser);
		$this->simbila->setUrl($this->simbilaUrl);
		parent::init();
	}


}
