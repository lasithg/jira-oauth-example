<?php
require_once __DIR__ . '/../vendor/autoload.php';

$app = new Silex\Application();
$app['debug'] = true;
$app->register(new Silex\Provider\TwigServiceProvider(), array(
	'twig.path' => __DIR__ . '/../views',
));
$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

$app['oauth'] = $app->share(function() use($app){
	$oauth = new Atlassian\OAuthWrapper('https://example.atlassian.net/'); // replace your jira url
	$oauth->setConsumerKey('1234567890') // replace your jira consumer key
	      ->setConsumerSecret(__DIR__ . '/jira.pem') // privet key
	      ->setRequestTokenUrl('plugins/servlet/oauth/request-token')
	      ->setAuthorizationUrl('plugins/servlet/oauth/authorize?oauth_token=%s')
	      ->setAccessTokenUrl('plugins/servlet/oauth/access-token')
	      ->setCallbackUrl(
              $app['url_generator']->generate('callback', array(), true)
          );
	;
	return $oauth;
});

$app->get('/', function() use($app){
	$oauth = $app['session']->get('oauth');

	if (empty($oauth)) {
		$priorities = null;
	} else {
		$priorities = $app['oauth']->getClient(
			$oauth['oauth_token'], 
			$oauth['oauth_token_secret']
		)->get('rest/api/2/priority')->json();
		
		// Test
		/* $dd = $app['oauth']->getClient(
			$oauth['oauth_token'],
			$oauth['oauth_token_secret']
		)->post('rest/api/2/issue/LRRA-153/comment',
		[
				'json' => ['body' => 'test comment 1']
		]
		); */
	}

	return $app['twig']->render('layout.twig', array(
		'oauth' => $oauth,
		'priorities' => $priorities,
	)); 
	
	// Test
	/* if (empty($oauth)) {
		$priorities = null;
	} else {
		$priorities = $app['oauth']->getClient(
			$oauth['oauth_token'], 
			$oauth['oauth_token_secret']
		)->post('rest/api/2/issue/LRRA-153/comment', 
		[
				'headers' => ['Content-Type' => 'application/json']
		], 
		json_encode(['body' => 'test comment 1'])
		)->send();
	}
	echo $priorities; */
})->bind('home');

$app->get('/post-test', function() use($app){
	$oauth = $app['session']->get('oauth');

	if (empty($oauth)) {
		$priorities = null;
	} else {
		$priorities = $app['oauth']->getClient(
			$oauth['oauth_token'], 
			$oauth['oauth_token_secret']
		)->post('rest/api/2/issue/LRRA-153/comment', 
		[
				'headers' => ['Content-Type' => 'application/json']
		], 
		json_encode(['body' => 'test comment 1'])
		);
	}
	echo $priorities;
})->bind('post-test');

$app->get('/connect', function() use($app){
	$token = $app['oauth']->requestTempCredentials();
	
	$app['session']->set('oauth', $token);

	return $app->redirect(
		$app['oauth']->makeAuthUrl()
	);
})->bind('connect');

$app->get('/callback', function() use($app){
	$verifier = $app['request']->get('oauth_verifier');

	if (empty($verifier)) {
		throw new \InvalidArgumentException("There was no oauth verifier in the request");
	}
	
	$tempToken = $app['session']->get('oauth');

	$token = $app['oauth']->requestAuthCredentials(
		$tempToken['oauth_token'],
		$tempToken['oauth_token_secret'],
		$verifier
	);

	$app['session']->set('oauth', $token);

    return $app->redirect(
		$app['url_generator']->generate('home')
	);
})->bind('callback');

$app->get('/reset', function() use($app){
	$app['session']->set('oauth', null);

    return $app->redirect(
		$app['url_generator']->generate('home')
	);
})->bind('reset');

$app->run();
