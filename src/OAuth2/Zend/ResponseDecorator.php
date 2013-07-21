<?php

namespace OAuth2\Zend;

use OAuth2\ResponseInterface;
use Zend\Http\Response;

/**
 * Class to handle OAuth2 Responses in a graceful way.
 * 
 * This response object is a replacement to the original object to allow for
 * simple integration of the Zend Framework 2 response object
 * 
 * Use this interface to output the proper OAuth2 responses.
 *
 * @see OAuth2_ResponseInterface
 */
class ResponseDecorator implements ResponseInterface
{
    /**
     * Zend Response object to manage
     * 
     * @var Zend\Http\Response
     */
    protected $response;
    
    /**
     * Contructor
     * 
     * Accepts the response object to decorate within the OAuth process
     * 
     * @param Response $response
     * @return void
     */
    public function __construct(Response $response = null)
    {
        if (is_null($response)) {
            $this->setResponse($response);
        }
    }
    
    /**
     * Set the response object
     * 
     * @param \Zend\Http\Response $response
     * @return \OAuth2\Zend\ResponseDecorator
     */
    public function setResponse(Response $response)
    {
        $this->response = $response;
        return $this;
    }
    
    /**
     * Get the response object
     * 
     * @return \Zend\Http\Response
     */
    public function getResponse()
    {
        if (!$this->response) {
            $response = new Response;
            $this->setResponse($response);
        }
        
        return $this->response;
    }
    
    /**
     * Wrapper to input an error into the response object
     * 
     * @param int $statusCode
     * @param string $error
     * @param string $errorDescription
     * @param string $errorUri
     * @throws \InvalidArgumentException
     */
    public function setError($statusCode, $error, $errorDescription = null, $errorUri = null)
    {
        $parameters = array(
            'error' => $error,
            'error_description' => $errorDescription,
        );

        if (!is_null($errorUri)) {
            if (strlen($errorUri) > 0 && $errorUri[0] == '#') {
                // we are referencing an oauth bookmark (for brevity)
                $errorUri = 'http://tools.ietf.org/html/rfc6749' . $errorUri;
            }
            $parameters['error_uri'] = $errorUri;
        }

        $httpHeaders = array(
            'Cache-Control' => 'no-store'
        );
        
        $this->setStatusCode($statusCode);
        $this->addParameters($parameters);
        $this->addHttpHeaders($httpHeaders);

        $response = $this->getResponse();
        if (!$response->isClientError() && !$response->isServerError()) {
            throw new \InvalidArgumentException(sprintf('The HTTP status code is not an error ("%s" given).', $statusCode));
        }
    }

    /**
     * Add an array of headers to the response object
     * 
     * The array is an associative array where the key is the header name
     * and the value is the field value
     * 
     * @param array $httpHeaders
     * @return \OAuth2\Zend\ResponseDecorator
     */
    public function addHttpHeaders(array $httpHeaders)
    {
        $headers = $this->getResponse()->getHeaders();
        foreach ($httpHeaders as $header => $field) {
            $headers->addHeaderLine($header . ':' . $field);
        }
        
        return $this;
    }

    public function addParameters(array $parameters)
    {
        
    }

    public function getParameter($name)
    {
        
    }

    /**
     * 
     * 
     * @param int $statusCode
     * @param string $url
     * @param string $state
     * @param string $error
     * @param string $errorDescription
     * @param string $errorUri
     */
    public function setRedirect($statusCode = 302, $url, $state = null, $error = null, $errorDescription = null, $errorUri = null)
    {
        if (empty($url)) {
            throw new \InvalidArgumentException('Cannot redirect to an empty URL.');
        }

        $parameters = array();

        if (!is_null($state)) {
            $parameters['state'] = $state;
        }

        if (!is_null($error)) {
            $this->setError(400, $error, $errorDescription, $errorUri);
        }
        $this->setStatusCode($statusCode);
        $this->addParameters($parameters);

        if (count($this->parameters) > 0) {
            // add parameters to URL redirection
            $parts = parse_url($url);
            $sep = isset($parts['query']) && count($parts['query']) > 0 ? '&' : '?';
            $url .= $sep . http_build_query($this->parameters);
        }

        $this->addHttpHeaders(array('Location' =>  $url));

        if (!$this->getResponse()->isRedirect()) {
            throw new \InvalidArgumentException(sprintf('The HTTP status code is not a redirect ("%s" given).', $statusCode));
        }
    }

    /**
     * Set the response status code
     * 
     * Proxies to Response::setStatusCode()
     * 
     * @param int $statusCode
     * @return \OAuth2\Zend\ResponseDecorator
     */
    public function setStatusCode($statusCode)
    {
        $this->getResponse()->setStatusCode($statusCode);
        return $this;
    }
}