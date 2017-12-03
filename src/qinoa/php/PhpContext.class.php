<?php
/* 
    This file is part of the qinoa framework <http://www.github.com/cedricfrancoys/qinoa>
    Some Right Reserved, Cedric Francoys, 2017, Yegen
    Licensed under GNU GPL 3 license <http://www.gnu.org/licenses/>
*/
namespace qinoa\php;

use qinoa\http\HttpRequest;
use qinoa\http\HttpResponse;

class PhpContext {
        
    private static $instance;
    
    private $httpRequest;
    private $httpResponse;
    
    private function __construct() {
        // init session
        if(!strlen($this->getSessionId())) session_start();

        // retrieve current request 
        $this->httpRequest = new HttpRequest($this->getHttpMethod().' '.$this->getHttpUri().' '.$this->getHttpProtocol(), $this->getHttpRequestHeaders(), $this->getHttpBody());
        
        // build response (retrieive default headers set by PHP)
        $this->httpResponse = new HttpResponse('HTTP/1.1 200 OK', $this->getHttpResponseHeaders());        
    }
    
    public static function &getInstance() {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }
        return self::$instance;        
    }
    
    public function getHttpRequest() {
        return $this->httpRequest;
    }

    public function getHttpResponse() {
        return $this->httpResponse;
    }
    
    public function getSessionId() {
        return session_id();
    }
    
    private function getHttpResponseHeaders() {
        $res = [];
        $headers = headers_list();
        foreach($headers as $header) {
            list($name, $value) = explode(':', $header, 2);
            $res[$name] = trim($value);
        }
        return $res;
    }
    
    private function getHttpProtocol() {
        return $_SERVER['SERVER_PROTOCOL'];
    }

    /** 
     * Retrieve the request method.
     *
     * If the X-HTTP-Method-Override header is set, and if the method is a POST,
     * then it is used to determine the "real" intended HTTP method.
     *
     */    
    private function getHttpMethod() {
        static $method = null;        
        if(!$method) {
            $method = $_SERVER['REQUEST_METHOD'];
            if (strcasecmp($method, 'POST') === 0) {
                if (isset($_SERVER['X-HTTP-METHOD-OVERRIDE'])) {
                    $method = $_SERVER['X-HTTP-METHOD-OVERRIDE'];
                } 

            }
            // normalize to upper case
            $method = strtoupper($method);
        }
        return $method;        
    }
    
    /**
     * Get all HTTP header key/values as an associative array for the current request.
     *
     */    
    private function getHttpRequestHeaders() {
        static $headers = null;
        
        if(!$headers) {
            // 1) retrieve headers
            if (function_exists('getallheaders')) {
                $headers = (array) getallheaders();                  
            }
            else {
                // Polyfill from https://github.com/ralouphie/getallheaders
                // Copyright (c) 2014 Ralph Khattar - License: The MIT License (MIT)                            
                $headers = array();
                $copy_server = array(
                    'CONTENT_TYPE'   => 'Content-Type',
                    'CONTENT_LENGTH' => 'Content-Length',
                    'CONTENT_MD5'    => 'Content-MD5',
                );
                foreach ($_SERVER as $key => $value) {
                    if (substr($key, 0, 5) === 'HTTP_') {
                        $key = substr($key, 5);
                        if (!isset($copy_server[$key]) || !isset($_SERVER[$key])) {
                            $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
                            $headers[$key] = $value;
                        }
                    } elseif (isset($copy_server[$key])) {
                        $headers[$copy_server[$key]] = $value;
                    }
                }              
            }
            // 2) normalize headers            
            if (!isset($headers['Authorization'])) {
                if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                    $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
                } elseif (isset($_SERVER['PHP_AUTH_USER'])) {
                    $basic_pass = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';
                    $headers['Authorization'] = 'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $basic_pass);
                } elseif (isset($_SERVER['PHP_AUTH_DIGEST'])) {
                    $headers['Authorization'] = $_SERVER['PHP_AUTH_DIGEST'];
                }
            }              
            // handle ETags
            if(!isset($headers['ETag'])) {
                if(isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
                    $headers['ETag'] = $_SERVER['HTTP_IF_NONE_MATCH'];
                }
                else {
                    $headers['ETag'] = '';
                }
            }
            // handle client's IP address
            $client_ip = $_SERVER['REMOTE_ADDR'];            
            if(!isset($headers['X-Forwarded-For']) || strpos($headers['X-Forwarded-For'], $client_ip) === false ) {
                if(!isset($headers['X-Forwarded-For'])) {
                    $headers['X-Forwarded-For'] = $_SERVER['REMOTE_ADDR'];
                } 
                else {
                    $headers['X-Forwarded-For'] = $_SERVER['REMOTE_ADDR'].','.$headers['X-Forwarded-For'];
                }
            }
            
        }
        return $headers;
    }
    
    private function getHttpUri() {
        $scheme = isset($_SERVER['HTTPS']) ? "https" : "http";
        $auth = '';
        if(isset($_SERVER['PHP_AUTH_USER']) && strlen($_SERVER['PHP_AUTH_USER']) > 0) {
            $auth = $_SERVER['PHP_AUTH_USER'];
            if(isset($_SERVER['PHP_AUTH_PW']) && strlen($_SERVER['PHP_AUTH_PW']) > 0) {
                $auth .= ':'.$_SERVER['PHP_AUTH_PW'];
            }
            $auth .= '@';
        }
        $host = isset($_SERVER['HTTP_HOST'])?$_SERVER['HTTP_HOST']:'localhost';
        $port = isset($_SERVER['SERVER_PORT'])?$_SERVER['SERVER_PORT']:80;
        return  $scheme. "://".$auth."$host:$port{$_SERVER['REQUEST_URI']}";
    }
    
    private function getHttpBody() {
        $body = '';
        
        // retrieve current method
        $method = $this->getHttpMethod();
        
        // append parameters from request URI if not already in (for internal requests and redirections)
        if($method == 'GET') {            
            if(false !== strpos($_SERVER['REQUEST_URI'], '?')) {
                $params = [];            
                parse_str(explode('?', $_SERVER['REQUEST_URI'])[1], $params);  
                $_REQUEST = array_merge($_REQUEST, $params);            
            }                    
        }        
        // use PHP native HTTP request parser for supported methods 
        if( in_array($method, ['GET', 'POST']) && !empty($_REQUEST) ){            
            $body = $_REQUEST;
        }
        // otherwise load raw content from input stream (HttpMessage class will be able to deal with it)
        else {            
            $body = @file_get_contents('php://input');            
        }
        
        return $body;
    }
}