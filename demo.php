<?php
spl_autoload_register(
    function ($class) {
        if (!class_exists($class)) {
            include $class . '.php';
        }
    }
);

if ('POST' == $_SERVER['REQUEST_METHOD']) {
    $url = $_POST['url'];
    $cookies = $_POST['cookies'];

    if (empty($url)) {
        return false;
    }

    $http = new Http($url);

    if (empty($cookies)) {
        $http->getCookies()->send();
    } else {
        $http->setCookies($cookies)->send();
    }

    $body = $http->response();

    echo $body;
} else {
    header('HTTP/1.1 400 Bad Request');
}
