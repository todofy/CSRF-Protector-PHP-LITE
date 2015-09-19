<?php
/**
 * PHP library to authenticate CSRF Tokens sent from client
 * and take action based on config.
 *
 * AUTHOR: Minhaz A V <minhazav@gmail.com>
 */


if (!defined('__CSRF_PROTECTOR__')) {
	define('__CSRF_PROTECTOR__', true); 	// to avoid multiple declaration errors

	// name of HTTP POST variable for authentication
	define("CSRFP_TOKEN","csrfp_token");

	class csrfProtector
	{
		/*
		 * Variable: $cookieExpiryTime
		 * expiry time for cookie
		 * @var int
		 */
		public static $cookieExpiryTime = 1800;	//30 minutes

		/*
		 * Variable: $isSameOrigin
		 * flag for cross origin/same origin request
		 * @var bool
		 */
		private static $isSameOrigin = true;

		/*
		 * Variable: $isValidHTML
		 * flag to check if output file is a valid HTML or not
		 * @var bool
		 */
		private static $isValidHTML = false;

		/*
		 * Variable: $requestType
		 * Varaible to store weather request type is post or get
		 * @var string
		 */
		protected static $requestType = "GET";

		/*
		 * Variable: $config
		 * config file for CSRFProtector
		 * @var int Array, length = 6
		 * Property: #1: failedAuthAction (int) => action to be taken in case autherisation fails
		 * Property: #2: tokenLength (int) => default length of hash
		 */
		public static $config = array(
				"failedAuthAction" => array(
						"GET" => 0,
						"POST" => 0
				),
		    "tokenLength" => 10,
				"verifyGetFor" => array()
		);


		/*
		 *	Function: init
	 	 *
		 *	function to initialise the csrfProtector work flow
		 *
		 *	Parameters:
		 *	$length - length of CSRF_AUTH_TOKEN to be generated
		 *	$action - int array, for different actions to be taken in case of failed validation
		 *
		 *	Returns:
		 *		void
		 *
		 *	Throws:
		 *		configFileNotFoundException - when configuration file is not found
		 * 		incompleteConfigurationException - when all required fields in config
		 *											file are not available
		 *
		 */
		public static function init($length = null, $action = null)
		{
			/*
			 * if mod_csrfp already enabled, no verification, no filtering
			 * Already done by mod_csrfp
			 */
			if (getenv('mod_csrfp_enabled'))
				return;

			//start session in case its not
			if (session_id() == '')
			    session_start();

			//overriding length property if passed in parameters
			if ($length != null)
				self::$config['tokenLength'] = intval($length);

			//action that is needed to be taken in case of failed authorisation
			if ($action != null)
				self::$config['failedAuthAction'] = $action;

			// Authorise the incoming request
			self::authorizePost();

			if (!isset($_COOKIE[self::$config['CSRFP_TOKEN']])
				|| !isset($_SESSION[self::$config['CSRFP_TOKEN']])
				|| !is_array($_SESSION[self::$config['CSRFP_TOKEN']])
				|| !in_array($_COOKIE[self::$config['CSRFP_TOKEN']],
					$_SESSION[self::$config['CSRFP_TOKEN']]))
				self::refreshToken();

			// Set protected by CSRF Protector header
			header('X-CSRF-Protection: OWASP CSRFP 1.0.0');
		}

		/*
		 * Function: authorizePost
		 * function to authorise incoming post requests
		 *
		 * Parameters:
		 * void
		 *
		 * Returns:
		 * void
		 *
		 * Throws:
		 * logDirectoryNotFoundException - if log directory is not found
		 */
		public static function authorizePost()
		{
			//#todo this method is valid for same origin request only,
			//enable it for cross origin also sometime
			//for cross origin the functionality is different
			if ($_SERVER['REQUEST_METHOD'] === 'POST') {

				//set request type to POST
				self::$requestType = "POST";

				//currently for same origin only
				if (!(isset($_POST[self::$config['CSRFP_TOKEN']])
					&& isset($_SESSION[self::$config['CSRFP_TOKEN']])
					&& (self::isValidToken($_POST[self::$config['CSRFP_TOKEN']]))
					)) {

					//action in case of failed validation
					self::failedValidationAction();
				} else {
					self::refreshToken();	//refresh token for successfull validation
				}
			} else if (!static::isURLallowed()) {

				//currently for same origin only
				if (!(isset($_GET[self::$config['CSRFP_TOKEN']])
					&& isset($_SESSION[self::$config['CSRFP_TOKEN']])
					&& (self::isValidToken($_GET[self::$config['CSRFP_TOKEN']]))
					)) {

					//action in case of failed validation
					self::failedValidationAction();
				} else {
					self::refreshToken();	//refresh token for successfull validation
				}
			}
		}

		/*
		 * Function: isValidToken
		 * function to check the validity of token in session array
		 * Function also clears all tokens older than latest one
		 *
		 * Parameters:
		 * $token - the token sent with GET or POST payload
		 *
		 * Returns:
		 * bool - true if its valid else false
		 */

		private static function isValidToken($token)
		{
			if (!isset($_SESSION[self::$config['CSRFP_TOKEN']])) return false;
			if (!is_array($_SESSION[self::$config['CSRFP_TOKEN']])) return false;
			foreach ($_SESSION[self::$config['CSRFP_TOKEN']] as $key => $value) {
				if ($value == $token) {

					// Clear all older tokens assuming they have been consumed
					foreach ($_SESSION[self::$config['CSRFP_TOKEN']] as $_key => $_value) {
						if ($_value == $token) break;
						array_shift($_SESSION[self::$config['CSRFP_TOKEN']]);
					}
					return true;
				}
			}

			return false;
		}

		/*
		 * Function: failedValidationAction
		 * function to be called in case of failed validation
		 * performs logging and take appropriate action
		 *
		 * Parameters:
		 * void
		 *
		 * Returns:
		 * void
		 */
		private static function failedValidationAction()
		{
			if (!file_exists(__DIR__ ."/../" .self::$config['logDirectory']))
				throw new logDirectoryNotFoundException("OWASP CSRFProtector: Log Directory Not Found!");

			//call the logging function
			static::logCSRFattack();

			//#todo: ask mentors if $failedAuthAction is better as an int or string
			//default case is case 0
			switch (self::$config['failedAuthAction'][self::$requestType]) {
				case 0:
					//send 403 header
					header('HTTP/1.0 403 Forbidden');
					exit("<h2>403 Access Forbidden by CSRFProtector!</h2>");
					break;
				case 1:
					//unset the query parameters and forward
					if (self::$requestType === 'GET') {
						$_GET = array();
					} else {
						$_POST = array();
					}
					break;
				case 2:
					//redirect to custom error page
					$location  = self::$config['errorRedirectionPage'];
					header("location: $location");
				case 3:
					//send custom error message
					exit(self::$config['customErrorMessage']);
					break;
				case 4:
					//send 500 header -- internal server error
					header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
					exit("<h2>500 Internal Server Error!</h2>");
					break;
				default:
					//unset the query parameters and forward
					if (self::$requestType === 'GET') {
						$_GET = array();
					} else {
						$_POST = array();
					}
					break;
			}
		}

		/*
		 * Function: refreshToken
		 * Function to set auth cookie
		 *
		 * Parameters:
		 * void
		 *
		 * Returns:
		 * void
		 */
		public static function refreshToken()
		{
			$token = self::generateAuthToken();

			if (!isset($_SESSION[self::$config['CSRFP_TOKEN']])
				|| !is_array($_SESSION[self::$config['CSRFP_TOKEN']]))
				$_SESSION[self::$config['CSRFP_TOKEN']] = array();

			//set token to session for server side validation
			array_push($_SESSION[self::$config['CSRFP_TOKEN']], $token);

			//set token to cookie for client side processing
			setcookie(self::$config['CSRFP_TOKEN'],
				$token,
				time() + self::$cookieExpiryTime);
		}

		/*
		 * Function: generateAuthToken
		 * function to generate random hash of length as given in parameter
		 * max length = 128
		 *
		 * Parameters:
		 * length to hash required, int
		 *
		 * Returns:
		 * string, token
		 */
		public static function generateAuthToken()
		{
			//if config tokenLength value is 0 or some non int
			if (intval(self::$config['tokenLength']) == 0) {
				self::$config['tokenLength'] = 32;	//set as default
			}

			//#todo - if $length > 128 throw exception

			if (function_exists("hash_algos") && in_array("sha512", hash_algos())) {
				$token = hash("sha512", mt_rand(0, mt_getrandmax()));
			} else {
				$token = '';
				for ($i = 0; $i < 128; ++$i) {
					$r = mt_rand(0, 35);
					if ($r < 26) {
						$c = chr(ord('a') + $r);
					} else {
						$c = chr(ord('0') + $r - 26);
					}
					$token .= $c;
				}
			}
			return substr($token, 0, self::$config['tokenLength']);
		}

		/*
		 * Function: logCSRFattack
		 * Functio to log CSRF Attack
		 *
		 * Parameters:
		 * void
		 *
		 * Retruns:
		 * void
		 *
		 * Throws:
		 * logFileWriteError - if unable to log an attack
		 */
		private static function logCSRFattack()
		{
			//miniature version of the log
			$log = array();
			$log['timestamp'] = time();
			$log['HOST'] = $_SERVER['HTTP_HOST'];
			$log['REQUEST_URI'] = $_SERVER['REQUEST_URI'];
			$log['requestType'] = self::$requestType;

			if (self::$requestType === "GET")
				$log['query'] = $_GET;
			else
				$log['query'] = $_POST;

			$log['cookie'] = $_COOKIE;

			error_log(json_encode($log));
		}

		/*
		 * Function: getCurrentUrl
		 * Function to return current url of executing page
		 *
		 * Parameters:
		 * void
		 *
		 * Returns:
		 * string - current url
		 */
		private static function getCurrentUrl()
		{
			$request_scheme = 'https';

			if (isset($_SERVER['REQUEST_SCHEME'])) {
				$request_scheme = $_SERVER['REQUEST_SCHEME'];
			} else {
				if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
					$request_scheme = 'https';
				} else {
					$request_scheme = 'http';
				}
			}

			return $request_scheme . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
		}

		/*
		 * Function: isURLallowed
		 * Function to check if a url mataches for any urls
		 * Listed in config file
		 *
		 * Parameters:
		 * void
		 *
		 * Returns:
		 * boolean - true is url need no validation, false if validation needed
		 */
		public static function isURLallowed() {
			foreach (self::$config['verifyGetFor'] as $key => $value) {
				$value = str_replace(array('/','*'), array('\/','(.*)'), $value);
				preg_match('/' .$value .'/', self::getCurrentUrl(), $output);
				if (count($output) > 0)
					return false;
			}
			return true;
		}
	};
}
