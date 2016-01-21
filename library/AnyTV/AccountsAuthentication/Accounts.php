<?php

class AnyTV_AccountsAuthentication_Accounts
{
	protected $schema 	= 'https';
	//protected $api 		= 'api.accounts.freedom.tm/v2';
	protected $api 		= 'accounts.freedom.tm/api/v2';

	/**
	 * @var string
	 */
	protected $clientId;

	/**
	 * @var string
	 */
	protected $clientSecret;

	public function __construct()
	{
		$options = XenForo_Application::getOptions();

		$this->clientId = $options->accountsFreedomClientId;
		$this->clientSecret = $options->accountsFreedomClientSecret;

	}

	public function getAccessToken($url, $code)
	{
		try
		{
			$client = XenForo_Helper_Http::getClient(sprintf('%s://%s/oauth/token', $this->schema, $this->api));
			$client->setParameterPost(array(
				'client_id' => $this->clientId,
				'redirect_uri' => $url,
				'client_secret' => $this->clientSecret,
				'code' => $code,
				'grant_type' => 'authorization_code'
			));

			$response = $client->request('POST');

			$body = $response->getBody();
			if (preg_match('#^[{\[]#', $body))
			{
				$parts = json_decode($body, true);
			}
			else
			{
				$parts = XenForo_Application::parseQueryString($body);
			}

			return $parts;
		}
		catch (Zend_Http_Client_Exception $e)
		{
			return false;
		}
	}

	public function getAccessTokenFromCode($code, $redirectUri = false)
	{
		if (!$redirectUri)
		{
			$requestPaths = XenForo_Application::get('requestPaths');
			$redirectUri = preg_replace('#(&|\?)code=[^&]*#', '', $requestPaths['fullUri']);
		}
		else
		{
			// FB does this strange thing with slashes after a ? for some reason
			$parts = explode('?', $redirectUri, 2);
			if (isset($parts[1]))
			{
				$redirectUri = $parts[0] . '?' . str_replace('/', '%2F', $parts[1]);
			}
		}

		return self::getAccessToken($redirectUri, $code);
	}

	public function getAccountsRequestUrl($redirectUri, $state = null)
	{
		if ( is_null($state))
		{
			$state = md5(uniqid('xf', true));
		}

		$session = XenForo_Application::getSession();
		$session->set('accountsCsrfState', $state);

		return $this->schema . '://' . $this->api . '/oauth/?client_id=' . $this->clientId
			. '&redirect_uri=' . urlencode($redirectUri)
			. '&state=' . $state
			. '&roles=profile,email'
			. '&access_type=offline'
			. '&response_type=code';
	}

	public function getUserInfo($userId, $accessToken)
	{
		try
		{
			$client = XenForo_Helper_Http::getClient(sprintf('%s://%s/user', $this->schema, $this->api));
			$client->setHeaders('Authorization', 'Bearer ' . $accessToken);
			$response = $client->request('GET');

			$body = $response->getBody();

			$body = json_decode($body, true);

			return $body;
		}
		catch(Zend_Http_Client_Exception $e) {
			return false;
		}
	}

	public function isConnectable()
	{
		return (empty($this->clientId) OR empty($this->clientSecret)) ? false : true;
	}

}