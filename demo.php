<?php
spl_autoload_register(function($class) {
    if (!class_exists($class)) {
        require $class . '.php';
    }
});

if ('POST' == $_SERVER['REQUEST_METHOD']) {
	$url = $_POST['url'];
	$cookies = $_POST['cookies'];

	if (empty($url)) {
		return false;
	}

	if (empty($cookies)) {
		return false;
	}

	$http = new Http($url);
	$http->setCookies($cookies)->send();
	$body = $http->response();

	echo $body;
} else {
	header('HTTP/1.1 400 Bad Request');
}
