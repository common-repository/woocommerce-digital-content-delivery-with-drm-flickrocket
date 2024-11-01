<?php
	/*
	Configuration file
	*/

	$current = '';
	if (array_key_exists("HTTP_HOST", $_SERVER))
	{
		$current = hash('sha512', $_SERVER["HTTP_HOST"] . 'development');
	}

	if ($current == '1d50bdf28b4cf32bd855d7d42f81bbabfb48c8f7ac5413513b60da2dee1e3cdba5aaed03d52a5d9a484b311cafcd52051ce29bec6ce4ecbc1a14964bf5f807f0' || 
		$current == 'd7b1463e590a8b1fc7f5e31e28d5adad6903609a3bcec29d8d2edfcf7c72c8789ed8fe6750d57a7c15656f22bbd59c7f2691495082d5acf79677d492d4cef9c7')
	{
		define( 'FR_REST_URL', 'https://dev2api.flickrocket.com' );
		define( 'FR_OAUTH_URL', 'https://dev2oauth.flickrocket.com'); 
		define( 'FR_EXTADMIN_URL', 'https://dev2exapp.flickrocket.com/ExtAdmin/' );
		define( 'FR_UPLOADER_URL', 'https://dev2uploader.flickrocket.com/' );
		define( 'FR_CLIENT_ID', 'DEV:WooCommerceFree.2120384246.apps.flickrocket.com' );
		define( 'FR_MARKETPLACE_URL', 'https://dev.creators-marketplace.com' );
		define( 'FR_MYCONTENTJS_URL', 'https://dev2exapp.flickrocket.com/MyContentJs' );
		define( 'FR_DEBUG', true );
	}
	else
	{
		define( 'FR_REST_URL', 'https://api.flickrocket.com' );
		define( 'FR_OAUTH_URL', 'https://oauth.flickrocket.com' ); 
		define( 'FR_EXTADMIN_URL', 'https://exapp.flickrocket.com/ExtAdmin/' );
		define( 'FR_UPLOADER_URL', 'https://uploader.flickrocket.com/' );
		define( 'FR_CLIENT_ID', 'WooCommerceFree.2033545684.apps.flickrocket.com' );
		define( 'FR_MARKETPLACE_URL', 'https://www.creators-marketplace.com' );
		define( 'FR_MYCONTENTJS_URL', 'https://exapp.flickrocket.com/MyContentJs' );
		define( 'FR_DEBUG', false );
	}

