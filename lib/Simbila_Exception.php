<?php
/**
 * @package Simbila
 * @author Tomas Hnilica <tomas@tomashnilica.com>
 */
/**
 * Client exception object 
 * @package Simbila
 * @author Tomas Hnilica <tomas@tomashnilica.com>
 */
 
class Simbila_Exception extends Exception {
	
	const REQUEST_INVALID = 400;
	const NOT_FOUND = 404;
	const DATA_INVALID = 500;
	const USAGE_INVALID = 500;
	const UNKNOWN = 500;
	
}
