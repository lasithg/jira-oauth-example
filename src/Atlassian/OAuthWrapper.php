<?php
namespace Atlassian;

use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use GuzzleHttp\Subscriber\Log\LogSubscriber;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use GuzzleHttp\Subscriber\Log\Formatter;

class OAuthWrapper {

	protected $baseUrl;
	protected $sandbox;
	protected $consumerKey;
	protected $consumerSecret;
	protected $callbackUrl;
	protected $requestTokenUrl = 'oauth';
	protected $accessTokenUrl = 'oauth';
	protected $authorizationUrl = 'OAuth.action?oauth_token=%s';

	protected $tokens;
	protected $client;
	protected $oauthPlugin;

	public function __construct($baseUrl) {
		$this->baseUrl = $baseUrl;
	}

	public function requestTempCredentials() {
		return $this->requestCredentials(
			$this->requestTokenUrl . '?oauth_callback=' . $this->callbackUrl	
		);
	}
	
	public function requestAuthCredentials($token, $tokenSecret, $verifier) {
		return $this->requestCredentials(
			$this->accessTokenUrl . '?oauth_callback=' . $this->callbackUrl . '&oauth_verifier=' . $verifier,
			$token,
			$tokenSecret
		);
	}

	protected function requestCredentials($url, $token = false, $tokenSecret = false) {
		$client = $this->getClient($token, $tokenSecret);
		$response = $client->post($url);

		return $this->makeTokens($response);
	}

	protected function makeTokens($response) {
		$body = (string) $response->getBody();

		$tokens = array();
		parse_str($body, $tokens);

		if (empty($tokens)) {
			throw new Exception("An error occurred while requesting oauth token credentials");
		}

		$this->tokens = $tokens;
		return $this->tokens;
	}

	public function getClient($token = false, $tokenSecret = false) {
		if (!is_null($this->client)) {
			return $this->client;
		} else {

			$log = new Logger('guzzle');
			$log->pushHandler(new StreamHandler('guzzle.log'));

			$this->client = new Client([
				'base_url' => $this->baseUrl,
				'defaults' => ['auth' => 'oauth']
			]);

			$subscriber = new LogSubscriber($log, Formatter::SHORT);
			$this->client->getEmitter()->attach($subscriber);

			$oauth  = new Oauth1([
				'consumer_key' 		=> $this->consumerKey,
				'consumer_secret' 	=> $this->consumerSecret,
				'token' 			=> !$token ? $this->tokens['oauth_token'] : $token,
				'token_secret' 		=> !$token ? $this->tokens['oauth_token_secret'] : $tokenSecret,
				'signature_method' => Oauth1::SIGNATURE_METHOD_RSA
			]);

			$this->client->getEmitter()->attach($oauth);

			return $this->client;
			
		}
	}

	public function makeAuthUrl() {
		return $this->baseUrl . sprintf($this->authorizationUrl, urlencode($this->tokens['oauth_token']));
	}

	public function setConsumerKey($consumerKey) {
		$this->consumerKey = $consumerKey;
		return $this;
	}

	public function setConsumerSecret($consumerSecret) {
		$this->consumerSecret = $consumerSecret;
		return $this;
	}

	public function setCallbackUrl($callbackUrl) {
		$this->callbackUrl = $callbackUrl;
		return $this;
	}

	public function setRequestTokenUrl($requestTokenUrl) {
		$this->requestTokenUrl = $this->baseUrl . $requestTokenUrl;
		return $this;
	}

	public function setAccessTokenUrl($accessTokenUrl) {
		$this->accessTokenUrl = $this->baseUrl . $accessTokenUrl;
		return $this;
	}

	public function setAuthorizationUrl($authorizationUrl) {
		$this->authorizationUrl = $authorizationUrl;
		return $this;
	}
	
	public function setPrivateKey($privateKey) {
		$this->privateKey = $privateKey;
		return $this;
	}
}
