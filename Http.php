<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/27
 * Time: 11:30
 */

class Http
{
    // 目标网站无法打开时返回的错误代码
    protected $error_code = 600;

    // 默认安卓UA
    protected $user_agent = 'Mozilla/5.0 (Linux; U; Android 4.1.2; zh-cn; Chitanda/Akari) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0 Mobile Safari/534.30 MicroMessenger/6.0.0.58_r884092.501 NetType/WIFI';

    // 请求地址
    protected $url;

    // 请求方法，默认GET
    protected $method;

    // 超时时间
    protected $timeout;

    protected $scheme;

    protected $host;

    protected $port;

    protected $path;

    protected $query;

    protected $referer;

    // 请求头部
    protected $header;

    // 响应内容
    protected $response;

    // 请求Cookies
    protected $cookies = null;

    // 本地Cookie保存路径
    protected $cookie_path;

    /**
     * 兼容PHP5模式
     *
     * Http constructor.
     *
     * @param null   $url
     * @param string $method
     * @param int    $timeout
     */
    public function __construct($url = null, $method = 'GET', $timeout = 60)
    {
        @set_time_limit(0);
        if (!empty($url)) {
            $this->connect($url, $method, $timeout);
        }
        $this->cookie_path = dirname(__FILE__) . "/pic.cookie";
        return $this;
    }

    /**
     * 初始化对象
     *
     * @param  string $url
     * @param  string $method
     * @param  int    $timeout
     * @return object
     */
    public function Httplib($url = null, $method = 'GET', $timeout = 60)
    {
        return $this->__construct($url, $method, $timeout);
    }

    /**
     * 改变连接url
     *
     * @param  string $url
     * @param  string $method
     * @param  int    $timeout
     * @return object
     */
    public function connect($url = null, $method = 'GET', $timeout = 60)
    {
        $this->header = null;
        $this->response = null;
        $this->url = $url;
        $this->method = strtoupper(empty($method) ? 'GET' : $method);
        $this->timeout = empty($timeout) ? 30 : $timeout;
        if (!empty($url)) {
            $this->parseURL($url);
        }
        return $this;
    }

    /**
     * 发送请求
     *
     * @param  array $params
     * @return bool
     */
    public function send($params = array())
    {
        $header = null;
        $response = null;
        $queryStr = null;
        $params && $this->method = 'POST';
        if (function_exists('curl_exec')) {
            $ch = curl_init($this->url);
            curl_setopt_array(
                $ch, array(
                    CURLOPT_TIMEOUT => $this->timeout,
                    CURLOPT_HEADER => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_USERAGENT => $this->user_agent,
                    CURLOPT_REFERER => $this->referer,
                )
            );

            if (!is_null($this->cookies)) {
                curl_setopt($ch, CURLOPT_COOKIE, $this->cookies);
            } else {
                curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_path);
            }

            if ($this->method == 'GET') {
                curl_setopt($ch, CURLOPT_HTTPGET, true);
            } else {
                if (is_array($params)) {
                    $queryStr = http_build_query($params);
                } else {
                    $queryStr = $params;
                }
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $queryStr);
            }
            $fp = curl_exec($ch);
            curl_close($ch);

            if (!$fp) {
                return false;
            }

            // 读取 header
            $i = 0;
            $length = strlen($fp);
            do {
                $header .= substr($fp, $i, 1);
                $i++;
            } while (!preg_match("/\r\n\r\n$/", $header));

            // 遇到跳转，执行跟踪跳转
            if ($this->redirect($header)) {
                return true;
            }

            // 读取内容
            do {
                $response .= substr($fp, $i, 4096);
                $i = $i + 4096;
            } while ($length >= $i);

            unset($fp, $length, $i);
        }

        $this->header = $header;
        $this->response = $this->handleResponse($response);
        return true;
    }

    /**
     * 跟踪跳转
     *
     * @param  string $header
     * @return bool
     */
    public function redirect($header)
    {
        if (in_array($this->status($header), array(301, 302))) {
            if (preg_match("/Location\:(.+)\r\n/i", $header, $regs)) {
                $regs[1] = trim($regs[1]);
                $url = parse_url($regs[1]);
                !isset($url['host']) && $regs[1] = $this->referer . $regs[1];
                $this->getCookies()->connect($regs[1], $this->method, $this->timeout)->send();
//                $this->connect($regs[1], $this->method, $this->timeout)->send();
                return true;
            }
        }
        return false;
    }

    /**
     * 取得请求的header
     *
     * @return string
     */
    public function header()
    {
        return $this->header;
    }

    /**
     * 处理返回响应内容
     *
     * @param  $response
     * @return mixed
     */
    public function handleResponse($response)
    {
        if (preg_match_all('/(src="(.*?)")|(href="(.*?)")/is', $response, $match)) {
            $mergeData = array_merge($match[2], $match[4]);
            foreach ($mergeData as $value) {
                if (false === strpos($value, 'http') && 0 == strpos($value, '/')) {
                    // 处理Js Image css的全路径
                    $response = str_replace($value, $this->scheme . '://' . $this->host . $value, $response);
                }
            }
        }
        return $response;
    }

    /**
     * 请求返回的html
     *
     * @return string
     */
    public function response()
    {
        return $this->response;
    }

    /**
     * 返回状态
     *
     * @param  string $header
     * @return int
     */
    public function status($header = null)
    {
        if (empty($header)) {
            $header = $this->header;
        }
        if (preg_match("/(.+) (\d+) (.+)\r\n/i", $header, $status)) {
            return $status[2];
        } else {
            return $this->error_code;
        }
    }

    /**
     * 解析url
     *
     * @param string $url
     */
    public function parseURL($url)
    {
        $aUrl = parse_url($url);
        $aUrl['query'] = isset($aUrl['query']) ? $aUrl['query'] : null;
        $scheme = isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : null;
        $this->scheme = ($scheme == 'off' || empty($scheme)) ? 'http' : 'https';
        $this->host = isset($aUrl['host']) ? $aUrl['host'] : null;
        $this->port = empty($aUrl['port']) ? 80 : (int) $aUrl['host'];
        $this->path = empty($aUrl['path']) ? '/' : (string) $aUrl['path'];
        $this->query = strlen($aUrl['query']) > 0 ? '?' . $aUrl['query'] : null;
        $this->referer = $this->scheme . '://' . $aUrl['host'];
    }

    /**
     * 获取Cookies
     *
     * @return $this
     */
    public function getCookies()
    {
        if (file_exists($this->cookie_path)) {
            unlink($this->cookie_path);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_path);
        curl_exec($ch);
        curl_close($ch);

        return $this;
    }

    /**
     * 设置Cookies
     *
     * @param  $cookies
     * @return $this
     */
    public function setCookies($cookies)
    {
        $this->cookies = $cookies;
        return $this;
    }

}
