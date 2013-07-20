<?php

namespace OAuth2\Zend;

use OAuth2\RequestInterface;
use Zend\Http\PhpEnvironment\Request;
use Zend\Stdlib\Parameters;

/**
 * OAuth2 Request decorator
 * 
 * Wrapper for Zends request object
 */
class RequestDecorator implements RequestInterface
{
    const ENC_URLENCODED = 'application/x-www-form-urlencoded';
    const PHP_AUTH_USER = 'PHP_AUTH_USER';
    const PHP_AUTH_PW = 'PHP_AUTH_PW';
    
    /**
     * Zend Request object to manage
     * 
     * @var Zend\Http\PhpEnvironment\Request
     */
    protected $request;
    
    /**
     * PHP request params (POST/PUT/DELETE/PATCH)
     *
     * @var ParametersInterface
     */
    protected $requestParams = null;
    
    /**
     * Contructor
     * 
     * Accepts the request object to decorate within the OAuth process
     * 
     * @param Request $request
     * @return void
     */
    public function __construct(Request $request = null)
    {
        if (is_null($request)) {
            $this->setRequest($request);
        }
    }
    
    /**
     * Set the request object and set the request params by processing the raw
     * body content or using the post data
     * 
     * If the content-type indicates a FORM payload, in a POST, PUT, PATCH or
     * DELETE the payload is passed to parse_str() and added to a Parameters bag 
     * 
     * @param Zend\Http\PhpEnvironment\Request $request
     * @return \OAuth2\RequestDecorator
     */
    public function setRequest(Request $request)
    {
        if ($request->isPost()) {
            $params = $request->getPost();
            $this->requestParams = $params;
        } elseif ($this->requestHasContentType(self::ENC_URLENCODED)
            && ($request->isPut() || $request->isDelete() || $request->isPatch())
        ) {
            $content = $request->getContent();
            $parsedParams = array();
            parse_str($content, $parsedParams);
            $params = new Parameters($parsedParams);
            $this->requestParams = $params;
        }
        
        $this->request = $request;
        return $this;
    }
    
    /**
     * Get the response object
     * 
     * @return \Zend\Http\Response
     */
    public function getRequest()
    {
        if (!$this->request) {
            $request = new Request;
            $this->setRequest($request);
        }
        
        return $this->request;
    }
    
    /**
     * Get all the query string parameters to satisfy the RequestInterface
     * 
     * @note From what I can see, this method is deprecated within the OAuth library
     * @return array
     */
    public function getAllQueryParameters()
    {
        return $this->getRequest()->getQuery()->getArrayCopy();
    }
    
    /**
     * Get the header values
     * 
     * As in the guts of the OAuth process the basic auth credentials are fished
     * out from the header instead of the server, we need to add a check for it
     * to proxy to Request::getServer() otherwise, the default behavious is to
     * proxy to Request::getHeaders() and return the field value
     * 
     * @param string $name
     * @param mixed $default
     * @return string
     */
    public function headers($name, $default = null)
    {
        switch ($name) {
            case self::PHP_AUTH_USER:
            case self::PHP_AUTH_PW:
                return $this->getRequest()->getServer($name, $default);
            break;
            default :
                if (($header = $this->getRequest()->getHeaders($name, $default))) {
                    return $header->getFieldValue();
                }
            break;
        }
    }
    
    /**
     * Get the query string parameters
     * 
     * Proxies to Request::getQuery()
     * 
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function query($name, $default = null)
    {
        return $this->getRequest()->getQuery($name, $default);
    }

    /**
     * Get the server vars from the request object
     * 
     * Proxies to Request::getServer()
     * 
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function server($name, $default = null)
    {
        return $this->getRequest()->getServer($name, $default);
    }
    
    /**
     * Return the parameter container responsible for request parameters or a single parameter value.
     *
     * @param string|null $name Parameter name to retrieve, or null to get the whole container.
     * @param mixed|null $default Default value to use when the parameter is missing.
     * @return \Zend\Stdlib\ParametersInterface|mixed
     */
    public function request($name, $default = null)
    {
        if ($this->requestParams === null) {
            $this->requestParams = new Parameters();
        }

        if ($name === null) {
            return $this->requestParams;
        }

        return $this->requestParams->get($name, $default);
    }
    
    /**
     * Check if request has certain content type
     *
     * @param  Request $request
     * @param  string|null $contentType
     * @return bool
     */
    protected function requestHasContentType($contentType = '')
    {
        /** @var $headerContentType \Zend\Http\Header\ContentType */
        $headerContentType = $this->getHeaders()->get('content-type');
        if (!$headerContentType) {
            return false;
        }

        $requestedContentType = $headerContentType->getFieldValue();
        if (stripos($requestedContentType, $contentType) === 0) {
            return true;
        }

        return false;
    }
}