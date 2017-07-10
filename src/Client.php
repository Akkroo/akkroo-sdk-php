<?php
namespace Akkroo;

use Http\Client\HttpClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Http\Message\RequestFactory;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Discovery\UriFactoryDiscovery;
use Http\Message\UriFactory;
use Psr\Http\Message\ResponseInterface;

class Client
{
    /**
     * @var HttpClient
     */
    protected $httpClient;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var array
     */
    protected $defaults = [
        'endpoint' => 'https://akkroo.com/api',
        'version' => '1.1.5'
    ];

    /**
     * @var LoggerInterface
     */
    protected $logger = null;

    /**
     * @var string
     */
    protected $apiKey = '';

    /**
     * @var string
     */
    protected $authToken = '';

    /**
     * @var int
     */
    protected $authTokenExpiration = 0;

    /**
     * Creates PSR-7 HTTP Requests
     *
     * @var RequestFactory
     */
    protected $requestFactory = null;

    /**
     * Creates PSR-7 URIS
     *
     * @var UriFactory
     */
    protected $uriFactory = null;

    /**
     * Create a new Akkroo client
     *
     * Currently supported options are 'version' and 'endpoint'.
     *
     * @param  HttpClient $httpClient Client to do HTTP requests
     * @param  string $apiKey Your Akkroo API key
     * @return void
     */
    public function __construct(HttpClient $httpClient, $apiKey, $options = [])
    {
        $this->httpClient = $httpClient;
        $this->options = array_merge_recursive($this->defaults, $options);
        $this->apiKey = $apiKey;
        $this->logger = new NullLogger;
        $this->requestFactory = MessageFactoryDiscovery::find();
        $this->uriFactory = UriFactoryDiscovery::find();
    }

    /**
     * Inject a logger object
     *
     * @param  LoggerInterface $logger A PSR-3 compatible logger
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Authenticate with Akkroo API and get a token
     *
     * Note: current API version returns HTTP 400 when wrong credentials are supplied
     *
     * @throws Error\Authentication
     * @throws Error\generic
     */
    public function login()
    {
        $headers = [
            'Content-Type' => sprintf('application/vnd.akkroo-v%s+json', $this->options['version']),
            'Authorization' => 'Basic ' . $this->apiKey
        ];
        $body = json_encode([
            'grant_type' => 'client_credentials',
            'scope' => 'PublicAPI'
        ]);
        $login = new Result($this->request('GET', '/auth', $headers, [], $body));
        if ($login->access_token) {
            $this->authToken = $login->access_token;
            $this->authTokenExpiration = time() + (int) $login->expires_in;
            return $this;
        }
        throw new Error\Generic("Unable to login, no access token returned");
    }

    /**
     * Fetch one or more resources
     *
     * @param  string $resource Resource name (i.e. events, registrations)
     * @param  array  $params   Search parameters (i.e. id, event_id, search query, range, fields, sort)
     * @return Akkroo\Collection | Akkroo\Resource
     * @throws Error\Authentication
     * @throws Error\NotFound
     * @throws Error\Generic
     */
    public function get($resource, array $params = [])
    {
    }

    /**
     * Create a new resource
     *
     * @param  string $resource Resource name (i.e. events, registrations)
     * @param  array  $data     Resource data
     * @return Akkroo\Resource
     * @throws Error\Authentication
     * @throws Error\NotFound
     * @throws Error\Generic
     */
    public function post($resource, array $data)
    {
    }

    /**
     * Update a resource
     *
     * The $data parameter must be the full resource data
     *
     * @param  string $resource Resource name (i.e. events, registrations)
     * @param  array  $params   URL parameters (i.e. id, event_id)
     * @param  array  $data     Resource data
     * @return Akkroo\Resource
     * @throws Error\Authentication
     * @throws Error\NotFound
     * @throws Error\Generic
     */
    public function put($resource, array $params, array $data)
    {
    }

    /**
     * Partially update a resource
     *
     * @param  string $resource Resource name (i.e. events, registrations)
     * @param  array  $params   URL parameters (i.e. id, event_id)
     * @param  array  $data     Resource data
     * @return Akkroo\Resource
     * @throws Error\Authentication
     * @throws Error\NotFound
     * @throws Error\Generic
     */
    public function patch($resource, array $params, array $data)
    {
    }

    /**
     * Delete a resource
     *
     * @param  string $resource Resource name (i.e. events, registrations)
     * @param  array  $params   URL parameters (i.e. id, event_id)
     * @return Akkroo\Result
     * @throws Error\Authentication
     * @throws Error\NotFound
     * @throws Error\Generic
     */
    public function delete($resource, array $params = [])
    {
    }

    /**
     * Count resources that satisfy the query
     *
     * @param  string $resource Resource name (i.e. events, registrations)
     * @param  array  $params   URL parameters (i.e. id, event_id)
     * @return Akkroo\Result
     * @throws Error\Authentication
     * @throws Error\NotFound
     * @throws Error\Generic
     */
    public function count($resource, $params = [])
    {
    }

    /**
     * Send a test API request
     *
     * @param array  $headers Additional headers
     *
     * @throws Error\Generic
     */
    public function test($headers = [])
    {
        return new Result($this->request('GET', '/selftest', $headers));
    }

    /**
     * Send an /authTest API request
     *
     * If the token is not supplied. it will try with the current internal token.
     *
     * This method does not throw exceptions, it logs server errors with the
     * internal logger, if provided
     *
     * @param  string $token An auth token to test for
     *
     * @return Result
     */
    public function authTest($token = null)
    {
        $headers = [];
        if (!empty($token)) {
            $headers['Authorization'] = 'Bearer ' . $token;
        } elseif (!empty($this->authToken)) {
            $headers['Authorization'] = 'Bearer ' . $this->authToken;
        }
        try {
            return new Result($this->request('GET', '/authTest', $headers));
        } catch (Error\Generic $e) {
            $this->logger->error(
                'Auth Test failed',
                ['code' => $e->getCode(), 'message' => $e->getMessage(), 'requestID' => $e->getRequestID()]
            );
            return new Result(['success' => false, 'requestID' => $e->getRequestID()]);
        }
    }

    /**
     * Process response status
     *
     * @param  ResponseInterface $response
     * @param  string            $requestID Unique linked request
     *
     * @return array The parsed JSON body
     *
     * @throws Error\Authentication
     * @throws Error\NotFound
     * @throws Error\Generic
     */
    protected function parseResponse($response, $requestID = null)
    {
        $status = $response->getStatusCode();
        $reason = $response->getReasonPhrase();
        $body = $this->parseResponseBody($response);
        if (!empty($requestID)) {
            $body['requestID'] = $requestID;
        }
        switch ($status) {
            case 401:
            case 403:
                throw new Error\Authentication($reason, $status, $body);
                break;

            case 404:
                throw new Error\NotFound('Resource Not Found', $status);
                break;

            default:
                // 3xx redirect status must be managed by the HTTP Client
                // Statuses other that what we define success are automatic errors
                if (!in_array($status, [200, 201, 202, 203, 204, 205, 206])) {
                    throw new Error\Generic($reason, $status, $body);
                }
                break;
        }
        return $body;
    }

    /**
     * Parse response body to JSON
     *
     * @param  ResponseInterface $response
     * @return array
     */
    protected function parseResponseBody($response)
    {
        $body = (string) $response->getBody();
        return json_decode($body, true);
    }

    /**
     * Send a request to the API endpoint
     *
     * @param string $method  HTTP method
     * @param string $path    Relative URL path (without query string)
     * @param array  $headers Additional headers
     * @param array  $params  Query string parameters
     * @param array  $data    Request body
     *
     * @return array JSON-decoded associative array from server response
     *
     * @throws Error\Authentication
     * @throws Error\NotFound
     * @throws Error\Generic
     */
    protected function request($method, $path, $headers = [], $params = [], $data = [])
    {
        // Minimal default header
        $acceptContentType = sprintf('application/vnd.akkroo-v%s+json', $this->options['version']);

        // Unique request ID
        $requestID = uniqid('', true);

        // Adding custom headers
        $requestHeaders = array_merge([
            'Accept' => $acceptContentType,
            'Request-ID' => $requestID
        ], $headers);

        // Creating URI: URI params must be already provided by the calling method
        $uri = $this->uriFactory->createUri($this->options['endpoint'])
            ->withPath($path);

        // Create and send a request
        $request = $this->requestFactory->createRequest($method, $uri, $requestHeaders);
        $response = $this->httpClient->sendRequest($request);

        // Check response content type match
        $contentType = $response->getHeaderLine('Content-Type');
        if ($contentType !== $acceptContentType) {
            throw new Error\Generic("Invalid response data");
        }

        // Return the decoded JSON and let the caller create the appropriate result format
        return $this->parseResponse($response, $requestHeaders['Request-ID']);
    }
}
