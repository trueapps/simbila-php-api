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
	 * @var string Simbila username.
	 */
	public $username;

	/**
	 * @var string Simbila password.
	 */
	public $password;

	/**
	 * @var string Simbila application ID.
	 */
	public $appId;

	/**
	 * @var string Simbila application user ID.
	 * This expression will be evaluated and INT is expected
	 */
	public $appUserExpr = 'account()->id';


	/**
	 * @var string Simbila API URL
	 */
	public $simbilaUrl = 'https://www.simbila.com/api';

	/**
	 * @var Simbila Simbila object
	 */
	public $simbila = null;



	/**
	 * Initializes the component.
	 */
	public function init()
	{
		$userid = $this->evaluateExpression($this->appUserExpr);
		$this->simbila = new Simbila($this->username, $this->password, $this->appId , $userid);
		$this->simbila->setUrl($this->simbilaUrl);
		parent::init();
	}


}
