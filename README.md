# Simbila.com PHP API 

##### 1. Include this package in your project:

```bash
composer require trueapps/simbila-php-api
```

##### 2. Basic usage

Please see example/example.php for usage.

##### 3. Usage with Yii

There is a YiiSimbila component interface. To install:
1. Copy file to protected/extensions/simbila
2. Include Simbila classes

	'import'=>array(
		'application.extensions.simbila.*',
		'application.extensions.simbila.lib.*',
			...
	 )    
    

3. Set the component

	'components'=>array(
		'simbila' => array(
			'class'=> 'ext.simbila.components.YiiSimbila',
			'username'=>'tomas@tomashnilica.com',
			'password'=>'THAPI',
			'appId'=>'lunchdrive',
			'appUserExpr'=>'account()->id',
			'simbilaUrl'=>'http://www.simbila.com/api',
		),
		...
	)
		

	

4. Access the Simbila object by Yii::app()->simbila->simbila;
