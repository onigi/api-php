<?php
/**
 * Onigi
 *
 * @package		Onigi
 * @author		Onigi Dev Team
 * @copyright	Copyright (c) 2011, Onigi.
 * @license		http://onigi.com/license.html
 * @link		http://onigi.com
 * @since		Version 1.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * Onigi API Client Class
 *
 * Handle request to Onigi API
 *
 * @package		Onigi
 * @subpackage	Libraries
 * @author		Onigi Dev Team
 * @category	API
 */

if (!function_exists('curl_init')) {
  throw new Exception('Onigi needs the CURL PHP extension.');
}

if (!function_exists('json_decode')) {
  throw new Exception('Onigi needs the JSON PHP extension.');
}

/**
 * Thrown when an API call returns an exception.
 *
 * @author Haveiss <haveiss@onigi.com>
 */
class OnigiApiException extends Exception
{
  /**
   * The result from the API server that represents the exception information.
   */
  protected $result;

  /**
   * Make a new API Exception with the given result.
   *
   * @param Array $result the result from the API server
   */
  public function __construct($result)
  {
    $this->result = $result;
    
    $code = isset($result['error_code']) ? $result['error_code'] : 0;
    
    if (isset($result['error_description']))
    {
      $msg = $result['error_description'];
    }
    else
    {
      $msg = 'Unknown Error. Check getResult()';
    }
    
    parent::__construct($msg, $code);
  }

  /**
   * Return the associated result object returned by the API server.
   *
   * @returns Array the result from the API server
   */
  public function getResult()
  {
    return $this->result;
  }

  /**
   * Returns the associated type for the error. This will default to
   * 'Exception' when a type is not available.
   *
   * @return String
   */
  public function getType()
  {
    if (isset($this->result['error']))
    {
      return $this->result['error'];
    }

    return 'Exception';
  }

  /**
   * To make debugging easier.
   *
   * @returns String the string representation of the error
   */
  public function __toString()
  {
    $str = $this->getType() . ': ';
    if ($this->code != 0)
    {
      $str .= $this->code . ': ';
    }
    
    return $str . $this->message;
  }
}

class Onigi
{
  /**
   * Version.
   */
  const VERSION = '1.0.0';
  
  /**
   * Default options for curl.
   */
  public static $CURL_OPTS = array(
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_SSL_VERIFYPEER => FALSE,
    CURLOPT_USERAGENT      => 'onigi-php-1.0'
  );
  
  /**
   * List of query parameters that get automatically dropped when rebuilding
   * the current URL.
   */
  protected static $DROP_QUERY_PARAMS = array(
    'code',
    'state',
    'signed_request',
    'error_code',
    'error_description'
  );
  
  /**
   * Maps aliases to Onigi domains.
   */
  public static $DOMAIN_MAP = array(
    'api'       => 'https://market.onigi.com/api/',
    'www'       => 'https://market.onigi.com/'
  );
  
  /**
   * The Application ID.
   */
  protected $appId;

  /**
   * The Application API Secret.
   */
  protected $apiSecret;

  /**
   * The ID of the Onigi user, or 0 if the user is logged out.
   */
  protected $user;

  /**
   * The data from the signed_request token.
   */
  protected $signedRequest;

  /**
   * A CSRF state variable to assist in the defense against
   * CSRF attacks.
   */
  protected $state;

  /**
   * The OAuth access token received in exchange for a valid authorization
   * code.  null means the access token has yet to be determined.
   */
  protected $accessToken = NULL;
  
  /**
   * The OAuth refresh token received in exchange for a valid authorization
   * code.  null means the refresh token has yet to be determined.
   */
  protected $refreshToken = NULL;

	/**
   * Provides the implementations of the inherited abstract
   * methods.  The implementation uses PHP sessions to maintain
   * a store for user ids and access tokens.
   */
  protected static $kSupportedKeys = array('code', 'access_token', 'refresh_token', 'user_id');
	
  /**
   * Initialize a Onigi Application.
   *
   * The configuration:
   * - appId: the application ID
   * - secret: the application secret
   *
   * @param Array $config the application configuration
   */
  public function __construct($configs)
  {
    $this->setAppId($configs['appId']);
    $this->setApiSecret($configs['secret']);
    
    if (isset($_COOKIE[$this->getCSRFTokenCookieName()]))
    {
      $this->state = $_COOKIE[$this->getCSRFTokenCookieName()];
    }
    
    if (!isset($_SESSION))
    {
      session_start();
    }
  }
  
  protected function setPersistentData($key, $value)
  {
    if (!in_array($key, self::$kSupportedKeys))
    {
      self::errorLog('Unsupported key passed to setPersistentData.');
      return;
    }

    $session_var_name = $this->constructSessionVariableName($key);
    
    $_SESSION[$session_var_name] = $value;
  }

  protected function getPersistentData($key, $default = false)
  {
    if (!in_array($key, self::$kSupportedKeys))
    {
      self::errorLog('Unsupported key passed to getPersistentData.');
      return $default;
    }

    $session_var_name = $this->constructSessionVariableName($key);
    
    return isset($_SESSION[$session_var_name]) ? $_SESSION[$session_var_name] : $default;
  }

  protected function clearAllPersistentData()
  {
    foreach (self::$kSupportedKeys as $key)
    {
      $session_var_name = $this->constructSessionVariableName($key);
      unset($_SESSION[$session_var_name]);
    }
  }

  protected function constructSessionVariableName($key)
  {
    return implode('_', array('onigi', $this->getAppId(), $key));
  }
  
  /**
   * Set the Application ID.
   *
   * @param String $appId the Application ID
   */
  public function setAppId($appId)
  {
    $this->appId = $appId;
    return $this;
  }

  /**
   * Get the Application ID.
   *
   * @return String the Application ID
   */
  public function getAppId()
  {
    return $this->appId;
  }

  /**
   * Set the API Secret.
   *
   * @param String $appId the API Secret
   */
  public function setApiSecret($apiSecret)
  {
    $this->apiSecret = $apiSecret;
    return $this;
  }

  /**
   * Get the API Secret.
   *
   * @return String the API Secret
   */
  public function getApiSecret()
  {
    return $this->apiSecret;
  }
	
	/**
   * Sets the access token for api calls.  Use this if you get
   * your access token by other means and just want the SDK
   * to use it.
   *
   * @param String $access_token an access token.
   */
  public function setAccessToken($access_token)
  {
    $this->accessToken = $access_token;
    return $this;
  }
  
  /**
   * Sets the refresh token for api calls.  Use this if you get
   * your refresh token by other means and just want the SDK
   * to use it.
   *
   * @param String $refresh_token an access token.
   */
  public function setRefreshToken($refresh_token)
  {
    $this->refreshToken = $refresh_token;
    return $this;
  }
  
  /**
   * Determines the access token that should be used for API calls.
   * The first time this is called, $this->accessToken is set equal
   * to either a valid user access token, or it's set to the application
   * access token if a valid user access token wasn't available.  Subsequent
   * calls return whatever the first call returned.
   *
   * @return String the access token
   */
  public function getAccessToken()
  {
    if ($this->accessToken !== null)
    {
      // we've done this already and cached it.  Just return.
      return $this->accessToken;
    }

    // first establish access token to be the application
    // access token, in case we navigate to the /auth/access
    // endpoint, where SOME access token is required.
    if ($user_access_token = $this->getUserAccessToken())
    {
      $this->setAccessToken($user_access_token);
    }

    return $this->accessToken;
  }
  
  /**
   * Determines and returns the user access token, first using
   * the signed request if present, and then falling back on
   * the authorization code if present.  The intent is to
   * return a valid user access token, or false if one is determined
   * to not be available.
   *
   * @return String a valid user access token, or false if one
   *         could not be determined.
   */
  protected function getUserAccessToken()
  {
    // first, consider a signed request if it's supplied.
    // if there is a signed request, then it alone determines
    // the access token.
    $signed_request = $this->getSignedRequest();
    if ($signed_request)
    {
      if (array_key_exists('refresh_token', $signed_request))
      {
        $this->setPersistentData('refresh_token', $signed_request['refresh_token']);
      }
      
      if (array_key_exists('oauth_token', $signed_request))
      {
        $access_token = $signed_request['oauth_token'];
        $this->setPersistentData('access_token', $access_token);
        return $access_token;
      }
      
      // signed request states there's no access token, so anything
      // stored should be cleared.
      $this->clearAllPersistentData();
      
      return FALSE; // respect the signed request's data, even
                    // if there's an authorization code or something else
    }
    
    $code = $this->getCode();
    if ($code && $code != $this->getPersistentData('code'))
    {
      $tokens = $this->getAccessTokenFromCode($code);
      if ($tokens)
      {
        $this->setPersistentData('code', $code);
        $this->setPersistentData('access_token', $tokens['access_token']);
        $this->setPersistentData('refresh_token', $tokens['refresh_token']);
        
        return $tokens['access_token'];
      }

      // code was bogus, so everything based on it should be invalidated.
      $this->clearAllPersistentData();
      
      return FALSE;
    }
    
    if($this->getPersistentData('access_token')){
      return $this->getPersistentData('access_token');
    }

    // as a fallback, just return whatever is in the persistent
    // store, knowing nothing explicit (signed request, authorization
    // code, etc.) was present to shadow it (or we saw a code in $_REQUEST,
    // but it's the same as what's in the persistent store)
    return $this->appId.'|'.$this->apiSecret;
  }
  
  /**
   * Get the data from a signed_request token.
   *
   * @return String the base domain
   */
  public function getSignedRequest()
  {
    if (!$this->signedRequest && isset($_REQUEST['signed_request']))
    {
    	$this->signedRequest = $this->parseSignedRequest($_REQUEST['signed_request']);
    }
    
    return $this->signedRequest;
  }
  
  /**
   * Get the UID of the connected user, or 0
   * if the Onigi user is not connected.
   *
   * @return String the UID if available.
   */
  public function getUser()
  {
    if ($this->user !== null)
    {
      // we've already determined this and cached the value.
      return $this->user;
    }

    return $this->user = $this->getUserFromAvailableData();
  }
  
  /**
   * Determines the connected user by first examining any signed
   * requests, then considering an authorization code, and then
   * falling back to any persistent store storing the user.
   *
   * @return Integer the id of the connected Onigi user, or 0
   * if no such user exists.
   */
  protected function getUserFromAvailableData()
  {
    // if a signed request is supplied, then it solely determines
    // who the user is.
    $signed_request = $this->getSignedRequest();
    if ($signed_request)
    {
      if (array_key_exists('user_id', $signed_request))
      {
        $user = $signed_request['user_id'];
        $this->setPersistentData('user_id', $signed_request['user_id']);
        
        return $user;
      }

      // if the signed request didn't present a user id, then invalidate
      // all entries in any persistent store.
      $this->clearAllPersistentData();
      
      return 0;
    }

    $user = $this->getPersistentData('user_id', $default = 0);
    $persisted_access_token = $this->getPersistentData('access_token');
    $persisted_refresh_token = $this->getPersistentData('refresh_token');

    // use access_token to fetch user id if we have a user access_token, or if
    // the cached access token has changed.
    $access_token = $this->getAccessToken();
    if ($access_token &&
        !($user && $persisted_access_token == $access_token))
    {
      $user = $this->getUserFromAccessToken();
      if ($user)
      {
        $this->setPersistentData('user_id', $user);
      }
      else
      {
        $this->clearAllPersistentData();
      }
    }

    return $user;
  }
  
  /**
   * Get a Login URL for use with redirects. By default, full page redirect is
   * assumed. If you are using the generated URL with a window.open() call in
   * JavaScript, you can pass in display=popup as part of the $params.
   *
   * The parameters:
   * - redirect_uri: the url to go to after a successful login
   * - scope: comma separated list of requested extended perms
   *
   * @param Array $params provide custom parameters
   * @return String the URL for the login flow
   */
  public function getLoginUrl($params = array())
  {
    $this->establishCSRFTokenState();
    $currentUrl = $this->getCurrentUrl();
    
    return $this->getUrl(
      'www',
      'auth/authorize',
      array_merge(array(
        'client_id' => $this->getAppId(),
        'redirect_uri' => $currentUrl, // possibly overwritten
        'state' => $this->state,
        't' => time()
      ),
      $params)
    );
  }
  
  /**
   * Make an API call.
   *
   * @param Array $params the API call parameters
   * @return the decoded response
   */
  public function api($path, $method = 'GET', $params = array())
  {
    if (is_array($method) && empty($params))
    {
      $params = $method;
      $method = 'GET';
    }
    $params['method'] = strtoupper($method); // method override as we always do a POST

    $result = $this->_oauthRequest(
      $this->getUrl('api', $path.'.json', array(
        't' => time()
      )),
      $params
    );
    
    $result = json_decode($result, TRUE);
    
    // results are returned, errors are thrown
    if (is_array($result) && isset($result['error_code']))
    {
      $this->throwAPIException($result);
    }

    return $result;
  }
  
  /**
   * Get the authorization code from the query parameters, if it exists,
   * and otherwise return false to signal no authorization code was
   * discoverable.
   *
   * @return mixed the authorization code, or false if the authorization
   * code could not be determined.
   */
  protected function getCode()
  {
    if (isset($_REQUEST['code']))
    {
      if ($this->state !== null &&
          isset($_REQUEST['state']) &&
          $this->state === $_REQUEST['state'])
      {
        // CSRF state has done its job, so clear it
        $this->state = null;
        unset($_COOKIE[$this->getCSRFTokenCookieName()]);
        
        return $_REQUEST['code'];
      }
      else
      {
        self::errorLog('CSRF state token does not match one provided.');
        
        return FALSE;
      }
    }

    return FALSE;
  }
  
  /**
   * Retrieves the UID with the understanding that
   * $this->accessToken has already been set and is
   * seemingly legitimate.  It relies on Onigi API
   * to retreive user information and then extract
   * the user ID.
   *
   * @return Integer returns the UID of the Onigi user, or
   * 0 if the Onigi user could not be determined.
   */
  protected function getUserFromAccessToken()
  {
    try {
      $user_info = $this->api('/me');
      return $user_info['store_id'];
    } catch (OnigiApiException $e) {
      return 0;
    }
  }
  
  /**
   * Lays down a CSRF state token for this process.  We
   * only generate a new CSRF token if one isn't currently
   * circulating in the domain's cookie.
   *
   * @return void
   */
  protected function establishCSRFTokenState()
  {
    if ($this->state === null)
    {
      $this->state = md5(uniqid(mt_rand(), true));
      setcookie($name = $this->getCSRFTokenCookieName(),
                $value = $this->state,
                $expires = time() + 3600); // sticks for an hour
    }
  }
  
  /**
   * Retreives an access token for the given authorization code
   * (previously generated from market.onigi.com on behalf of
   * a specific user).  A legitimate access token is generated provided the access token
   * and the user for which it was generated all match, and the user is
   * either logged in to Facebook or has granted an offline access permission.
   *
   * @param String $code an authorization code.
   * @return mixed an access token exchanged for the authorization code, or
   * false if an access token could not be generated.
   */
  public function getAccessTokenFromCode($code)
  {
    if (empty($code))
    {
      return FALSE;
    }
    
    try {
      $access_token_response =
        $this->_oauthRequest(
          $this->getUrl('www', 'auth/access'),
          $params = array(
            'client_id' => $this->getAppId(),
            'client_secret' => $this->getApiSecret(),
            'redirect_uri' => $this->getCurrentUrl(),
            'code' => $code,
            't' => time()
          )
        );
    } catch (OnigiApiException $e) {
      // most likely that user very recently revoked authorization.
      // In any event, we don't have an access token, so say so.
      return FALSE;
    }
    
    if (empty($access_token_response))
    {
      return FALSE;
    }
    
    $access_token_response = json_decode($access_token_response, TRUE);
    
    if (!isset($access_token_response['access_token']))
    {
      return FALSE;
    }
    
    return array(
      'access_token' => $access_token_response['access_token'],
      'refresh_token' => $access_token_response['refresh_token']
    );
  }
  
  /**
   * Make a OAuth Request.
   *
   * @param String $path the path (required)
   * @param Array $params the query/post data
   * @return the decoded response object
   * @throws OnigiApiException
   */
  protected function _oauthRequest($url, $params)
  {
    if (!isset($params['access_token']))
    {
      $params['access_token'] = $this->getAccessToken();
    }

    // json_encode all params values that are not strings
    foreach ($params as $key => $value)
    {
      if (!is_string($value))
      {
        $params[$key] = json_encode($value);
      }
    }
    
    return $this->makeRequest($url, $params);
  }
  
  /**
   * Makes an HTTP request. This method can be overriden by subclasses if
   * developers want to do fancier things or use something other than curl to
   * make the request.
   *
   * @param String $url the URL to make the request to
   * @param Array $params the parameters to use for the POST body
   * @param CurlHandler $ch optional initialized curl handle
   * @return String the response text
   */
  protected function makeRequest($url, $params, $ch = NULL)
  {
    if (!$ch)
    {
      $ch = curl_init();
    }
    
    $allowedMethods = array('GET', 'POST');
    
    $opts = self::$CURL_OPTS;
    
	  if (!isset($params['method']) || !in_array($params['method'], $allowedMethods))
    {
      $params['method'] = 'GET';
    }
    
    $opts[CURLOPT_CUSTOMREQUEST] = $params['method'];
    
    if ($params['method'] != 'GET')
    {
      $opts[CURLOPT_POSTFIELDS] = $params;
    }
    else
    {
      $url = $url . ((strpos($url, '?') > 0) ? '&' : '?') . http_build_query($params, null, '&');
    }
    $opts[CURLOPT_URL] = $url;
		
    // disable the 'Expect: 100-continue' behaviour. This causes CURL to wait
    // for 2 seconds if the server does not support this header.
    if (isset($opts[CURLOPT_HTTPHEADER]))
    {
      $existing_headers = $opts[CURLOPT_HTTPHEADER];
      $existing_headers[] = 'Expect:';
      $opts[CURLOPT_HTTPHEADER] = $existing_headers;
    }
    else
    {
      $opts[CURLOPT_HTTPHEADER] = array('Expect:');
    }
    
    if ($params['method'] == 'POST')
  	{
  		$opts[CURLOPT_HTTPHEADER][] = 'Content-type: multipart/form-data; charset=utf-8';
  	}
    
    curl_setopt_array($ch, $opts);
    $result = curl_exec($ch);
    
    // CURLE_SSL_CACERT
    if (curl_errno($ch) == 60)
    {
      self::errorLog('Invalid or no certificate authority found, '.
                     'using bundled information');
      curl_setopt($ch, CURLOPT_CAINFO,
                  dirname(__FILE__) . '/dev_onigi_ca_chain_bundle.crt');
      
      $result = curl_exec($ch);
      echo curl_errno($ch);
    } 
    

    if ($result === FALSE)
    {
      $e = new OnigiApiException(array(
        'error_code' => curl_errno($ch),
        'error' => array(
          'message' => curl_error($ch),
          'type' => 'CurlException'
        ),
      ));
      
      curl_close($ch);
      throw $e;
    }
    
    curl_close($ch);
    return $result;
  }
  
  /**
   * The name of the cookie housing the CSRF protection value.
   *
   * @return String the cookie name
   */
  protected function getCSRFTokenCookieName()
  {
    return 'onigicsrf_'.$this->getAppId();
  }
  
  /**
   * Parses a signed_request and validates the signature.
   *
   * @param String A signed token
   * @return Array the payload inside it or null if the sig is wrong
   */
  protected function parseSignedRequest($signed_request)
  {
    list($encoded_sig, $payload) = explode('.', $signed_request, 2);
    
    // decode the data
    $sig = self::base64UrlDecode($encoded_sig);
    $data = json_decode(self::base64UrlDecode($payload), TRUE);
    
    if (strtoupper($data['algorithm']) !== 'HMAC-SHA256')
    {
      self::errorLog('Unknown algorithm. Expected HMAC-SHA256');
      return NULL;
    }
    
    // check sig
    $expected_sig = hash_hmac('sha256', $payload, $this->getApiSecret(), TRUE);
    if ($sig !== $expected_sig)
    {
      self::errorLog('Bad Signed JSON signature!');
      return NULL;
    }
    
    return $data;
  }
  
  /**
   * Build the URL for given domain alias, path and parameters.
   *
   * @param $name String the name of the domain
   * @param $path String optional path (without a leading slash)
   * @param $params Array optional query parameters
   * @return String the URL for the given parameters
   */
  protected function getUrl($name, $path = '', $params = array())
  {
    $url = self::$DOMAIN_MAP[$name];
    if ($path)
    {
      if ($path[0] === '/')
      {
        $path = substr($path, 1);
      }
      
      $url .= $path;
    }
    
    if ($params)
    {
      $url .= '?' . http_build_query($params, null, '&');
    }

    return $url;
  }
  
  /**
   * Returns the Current URL, stripping it of known Onigi parameters that should
   * not persist.
   *
   * @return String the current URL
   */
  protected function getCurrentUrl()
  {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on'
      ? 'https://'
      : 'http://';
    $currentUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $parts = parse_url($currentUrl);

    // drop known onigi params
    $query = '';
    if (!empty($parts['query']))
    {
      $params = array();
      parse_str($parts['query'], $params);
      foreach(self::$DROP_QUERY_PARAMS as $key)
      {
        unset($params[$key]);
      }
      
      if (!empty($params))
      {
        $query = '?' . http_build_query($params, null, '&');
      }
    }

    // use port if non default
    $port =
      isset($parts['port']) &&
      (($protocol === 'http://' && $parts['port'] !== 80) ||
       ($protocol === 'https://' && $parts['port'] !== 443))
      ? ':' . $parts['port'] : '';

    // rebuild
    return $protocol . $parts['host'] . $port . $parts['path'] . $query;
  }
  
  /**
   * Analyzes the supplied result to see if it was thrown
   * because the access token is no longer valid.  If that is
   * the case, then the persistent store is cleared.
   *
   * @param $result a record storing the error message returned
   *        by a failed API call.
   */
  protected function throwAPIException($result)
  {
    $e = new OnigiApiException($result);
    
    $message = $e->getMessage();
    if ((strpos($message, 'Error validating access token') !== false) ||
          (strpos($message, 'Invalid OAuth access token') !== false))
    {
      $this->setAccessToken(null);
    }
    
    $this->user = 0;
    $this->clearAllPersistentData();

    throw $e;
  }
  
  /**
   * Prints to the error log if you aren't in command line mode.
   *
   * @param String log message
   */
  protected static function errorLog($msg)
  {
    // disable error log if we are running in a CLI environment
    // @codeCoverageIgnoreStart
    if (php_sapi_name() != 'cli')
    {
      error_log($msg);
    }
    // uncomment this if you want to see the errors on the page
    // print 'error_log: '.$msg."\n";
    // @codeCoverageIgnoreEnd
  }
  
  /**
   * Base64 encoding that doesn't need to be urlencode()ed.
   * Exactly the same as base64_encode except it uses
   *   - instead of +
   *   _ instead of /
   *
   * @param String base64UrlEncodeded string
   */
  protected static function base64UrlDecode($input)
  {
    return base64_decode(strtr($input, '-_', '+/'));
  }
}