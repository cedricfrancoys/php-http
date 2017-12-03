<?php
/* 
    This file is part of the qinoa framework <http://www.github.com/cedricfrancoys/qinoa>
    Some Right Reserved, Cedric Francoys, 2017, Yegen
    Licensed under GNU GPL 3 license <http://www.gnu.org/licenses/>
*/
namespace qinoa\http;

use qinoa\http\HttpUri;
use qinoa\http\HttpHeaders;


/*
    # What is a HTTP message ?


    ## HTTP request

    @link http://www.ietf.org/rfc/rfc2616.txt

    * method    string
    * URI       object
    * protocol  object

    example: GET http://www.example.com/path/to/file/index.html HTTP/1.0

    getters : method, URI, protocol


    ### URI structure:
    @link http://www.ietf.org/rfc/rfc3986.txt

    generic form: scheme:[//[user[:password]@]host[:port]][/path][?query][#fragment]
    example: http://www.example.com/index.html


    URI __toString 

    getters :   scheme, user, password, host, port, path, query, fragment


    ### protocol structure:
        name    always 'HTTP'
        version HTTP version (1.0 or 1.1)

    generic form: name/version    
    example: HTTP/1.0

    getters: name (string), version (string) 

    ## HTTP response

    protocol
        name    always 'HTTP'
        version HTTP version (1.0 or 1.1)
    status
        code    HTTP status code
        reason  human readable HTTP status

*/


class HttpMessage {
   
    protected static $HTTP_METHODS = ['GET', 'POST', 'HEAD', 'PUT', 'PATCH', 'DELETE', 'PURGE', 'OPTIONS', 'TRACE', 'CONNECT'];

    /**
     *  List from Wikipedia article "List of HTTP status codes"
     *  @link https://en.wikipedia.org/wiki/List_of_HTTP_status_codes
     */
    protected static $HTTP_STATUS_CODES = [
        '100' => 'Continue',
        '101' => 'Switching Protocols',
        '102' => 'Processing',
        '200' => 'OK',
        '201' => 'Created',
        '202' => 'Accepted',
        '203' => 'Non-Authoritative Information',
        '204' => 'No Content',
        '205' => 'Reset Content',
        '206' => 'Partial Content',
        '207' => 'Multi-Status',
        '208' => 'Already Reported',
        '210' => 'Content Different',
        '226' => 'IM Used',
        '300' => 'Multiple Choices',
        '301' => 'Moved Permanently',
        '302' => 'Found',
        '303' => 'See Other',
        '304' => 'Not Modified',
        '305' => 'Use Proxy',
        '306' => '(aucun)',
        '307' => 'Temporary Redirect',
        '308' => 'Permanent Redirect',
        '310' => 'Too many Redirects',
        '400' => 'Bad Request',
        '401' => 'Unauthorized',
        '402' => 'Payment Required',
        '403' => 'Forbidden',
        '404' => 'Not Found',
        '405' => 'Method Not Allowed',
        '406' => 'Not Acceptable',
        '407' => 'Proxy Authentication Required',
        '408' => 'Request Time-out',
        '409' => 'Conflict',
        '410' => 'Gone',
        '411' => 'Length Required',
        '412' => 'Precondition Failed',
        '413' => 'Request Entity Too Large',
        '414' => 'Request-URI Too Long',
        '415' => 'Unsupported Media Type',
        '416' => 'Requested range unsatisfiable',
        '417' => 'Expectation failed',
        '418' => 'I’m a teapot',
        '421' => 'Bad mapping / Misdirected Request',
        '422' => 'Unprocessable entity',
        '423' => 'Locked',
        '424' => 'Method failure',
        '425' => 'Unordered Collection',
        '426' => 'Upgrade Required',
        '428' => 'Precondition Required',
        '429' => 'Too Many Requests',
        '431' => 'Request Header Fields Too Large',
        '449' => 'Retry With',
        '450' => 'Blocked by Windows Parental Controls',
        '451' => 'Unavailable For Legal Reasons',
        '456' => 'Unrecoverable Error',
        '444' => 'No Response',
        '495' => 'SSL Certificate Error',
        '496' => 'SSL Certificate Required',
        '497' => 'HTTP Request Sent to HTTPS Port',
        '499' => 'Client Closed Request',
        '500' => 'Internal Server Error',
        '501' => 'Not Implemented',
        '502' => 'Bad Gateway ou Proxy Error',
        '503' => 'Service Unavailable',
        '504' => 'Gateway Time-out',
        '505' => 'HTTP Version not supported',
        '506' => 'Variant Also Negotiates',
        '507' => 'Insufficient storage',
        '508' => 'Loop detected',
        '509' => 'Bandwidth Limit Exceeded',
        '510' => 'Not extended',
        '511' => 'Network authentication required',
        '520' => 'Unknown Error',
        '521' => 'Web Server Is Down',
        '522' => 'Connection Timed Out',
        '523' => 'Origin Is Unreachable',
        '524' => 'A Timeout Occurred',
        '525' => 'SSL Handshake Failed',
        '526' => 'Invalid SSL Certificate',
        '527' => 'Railgun Error'
    ];
    
    // @var string      (ex.: POST)
    private $method;
    
    // @var string      (ex.: HTTP/1.1)
    private $protocol;
    
    // @var mixed (string | array)
    private $body;
    
    // @var string
    private $status;

    // @var HttpUri
    private $uri;     
    
    // @var HttpHeader
    private $headers;    

    
    /**
     *
     * @param $headline string
     * @param $headers  array   associative array with headers names as keys and headers content as values 
     * @param $body mixed (array, string)   either associative array (of key-value pairs) or raw text for unknown content-type
     */
    public function __construct($headline, $headers=[], $body='') {        
        // $headline is handled in HttpResponse and HttpRequest classes
        $this->setStatus(null); 
        $this->setHeaders($headers);
        $this->setBody($body);
        // default protocol
        $this->setProtocol('HTTP/1.1');
        // default method
        $this->setMethod('GET');
        // init URI (this is an invalid URI, so all members will be set to null)
        $this->setUri('');
    }
    
    public function setMethod($method) {
        $method = strtoupper($method);
        if(in_array($method, self::$HTTP_METHODS) ) {
            $this->method = $method;
        }
        return $this;
    }
    
    /**
     *
     * @param $headers  array
     */
    public function setHeaders($headers) {
        $this->headers = new HttpHeaders($headers);
        return $this;        
    }
    
    public function setHeader($header, $value) {
        $this->headers->set($header, $value);
        // maintain URI consitency
        if(strcasecmp($header, 'Host') === 0) {
            $this->uri->setHost($value);
        }
        return $this;
    }
    
    /**
     *
     * This method is invoked byt HttpRequest and HttpResponse constructors.
     *
     * @param $uri  string
     */
    public function setUri($uri) {        
        $this->uri = new HttpUri($uri);
        // maintain headers consistency
        if($host = $this->uri->getHost()) {
            if($port = $this->uri->getPort()) {
                $host = $host.':'.$port;
            }
            $this->headers->set('Host', $host);            
        }        
        // maintain body consistency
        if($query = $this->uri->getQuery()) {
            $body = [];
            parse_str($query, $body);
            $this->setBody($body);
        }
        return $this;
    }    
    
    /**
     * Tries to force conversion to an associative array based on content-type.
     * Falls back to a raw string if conversion is not possible.
     * 
     *
     * @param $body mixed (string | array)
     */
    public function setBody($body) {
        if(!is_array($body)) {
            switch($this->getHeaders()->getContentType()) {
            case 'application/x-www-form-urlencoded':
                $params = [];
                parse_str($body, $params);
                $body = (array) $params;
                break;
            case 'application/json':
            case 'text/javascript':
                $body = json_decode($body, true);
                break;
            case 'text/xml':
            case 'application/xml':
            case 'text/xml, application/xml':
                $xml = simplexml_load_string($body, "SimpleXMLElement", LIBXML_NOCDATA);
                $json = json_encode($xml);
                $body = json_decode($json, true);                
            }
        }        
        $this->body = $body;
        return $this;
    }

    public function setProtocol($protocol) {
        $this->protocol = $protocol;
        return $this;
    }

    /**
     * @param $status   mixed (string | integer)  
     *
     * @return HttpMessage
     */
    public function setStatus($status) {
        // handle single status code argument
        if(is_numeric($status)) {
            // retrieve the 'reason' part
            $reason = isset(self::$HTTP_STATUS_CODES[$status])?' '.$status_codes[$status]:'';
            $status = $status.$reason;
        }
        $this->status = $status;
        return $this;
    }
        


    /**
     * 
     * can be forced to cast to string using $as_string boolean
     *
     * @return HttpUri
     *
     */    
    public function getUri($as_string=false) {
        if($as_string) {
            return (string) $this->uri;
        }
        return $this->uri;
    }    

    /**
     * 
     * can be forced to cast to array using $as_array boolean
     *
     * @return HttpHeaders
     *
     */    
    public function getHeaders($as_array=false) {
        if($as_array) {
            return $this->headers->getHeaders();
        }
        return $this->headers;
    }

    
    public function getHeader($header, $default=null) {
        return $this->headers->get($header, $default);
    }

    public function getMethod() {
        return $this->method;
    }    
    
    /**
     *
     * @return mixed    array or string associative array or raw data
     */
    public function getBody() {
        return $this->body;
    }    

    public function getProtocol() {
        return $this->protocol;
    }
    
    public function getProtocolVersion() {
        return (float) explode('/', $this->getProtocol())[1];
    }

    public function getStatus() {
        return $this->status;
    }
    
    public function getStatusCode() {
        list($code, $reason) = explode(' ', $this->status, 2);
        return $code;
    }
    
    /**
     * tries to retrieve a value from message body based on a given param name
     *
     */
    public function get($param, $default=null) {
        $res = $default;
        if(isset($this->body) && is_array($this->body)) {
            if(isset($this->body[$param])) {
                $res = $this->body[$param];
            }
        }
        return $res;
    }
    
    public function set($param, $value) {
        if(isset($this->body) && is_array($this->body)) {
            $this->body[$param] = $value;
        }
    }
    

    /**
     * send method is defined in HttpResponse and HttpRequest classes
     *
     */
    public function send() {}


    /**
     * Returns true if the request is a XMLHttpRequest.
     *
     * Checks HTTP header for an X-Requested-With entry set to 'XMLHttpRequest'.
     *
     * @link http://en.wikipedia.org/wiki/List_of_Ajax_frameworks#JavaScript
     *
     * @return bool true if the request is an XMLHttpRequest, false otherwise
     */
    public function isXHR() {
        return ('XMLHttpRequest' == $this->headers->get('X-Requested-With'));
    }      
}