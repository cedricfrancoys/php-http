<?php
/* 
    This file is part of the qinoa framework <http://www.github.com/cedricfrancoys/qinoa>
    Some Right Reserved, Cedric Francoys, 2017, Yegen
    Licensed under GNU GPL 3 license <http://www.gnu.org/licenses/>
*/
namespace qinoa\http;

use qinoa\http\HttpMessage;

class HttpResponse extends HttpMessage {
    
    public function __construct($headline, $headers=[], $body='') {
        parent::__construct($headline, $headers, $body);
        
        // parse headline
        $parts = explode(' ', $headline, 2);

        // retrieve status and/or protocol
        if(isset($parts[1])) {
            $this->setStatus($parts[1]);
            $this->setProtocol($parts[0]);
        }
        else {
            if(isset($parts[0])) {
                if(is_numeric($parts[0])) {
                    $this->setStatusCode($parts[0]);
                }
                else {
                    $this->setProtocol($parts[0]);
                }
            }
        }
                
    }
    
    public function send() {   
        header_remove();
        
        // set status-line
        header($this->getProtocol().' '.$this->getStatus());
        // set headers
        $headers = $this->getHeaders(true);
        foreach($headers as $header => $value) {
            if($header == 'Content-Length') continue;
            header($header.': '.$value);
        }
        // output body
        $body = $this->getBody();

        if(is_array($body)) {
            switch($this->getHeaders()->getContentType()) {
            case 'application/json':
            case 'text/javascript':
                $body = json_encode($body, JSON_PRETTY_PRINT);
                break;
            case 'text/xml':
            case 'application/xml':
            case 'text/xml, application/xml':
                $xml = new SimpleXMLElement('<root/>');
                array_walk_recursive($body, array ($xml, 'addChild'));
                $body = $xml->asXML();
            default:
                $body = http_build_query($body);
            }
        }
        header('Content-Length: '.strlen($body));
        echo $body;
        // no output should be emitted after this point
        return $this;        
    }
    
}