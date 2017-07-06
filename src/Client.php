<?php
namespace Akkroo;

use Http\Client\HttpClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Http\Message\RequestFactory;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Discovery\UriFactoryDiscovery;
use Http\Message\UriFactory;

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
     * @throws Error\Authentication
     */
    public function login()
    {
        return $this;
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
     * @throws Error\Generic
     */
    public function test()
    {
        return new Result($this->request('GET', '/selftest'));
    }

    /**
     * Send a test authentication API request
     *
     * @throws Error\Authentication
     * @throws Error\Generic
     */
    public function authTest()
    {
    }

    /**
     * Process response status
     *
     * @param string $method  HTTP method
     * @param string $path    Relative URL path (without query string)
     *
     * @return boolean
     *
     * @throws Error\Authentication
     * @throws Error\NotFound
     * @throws Error\Generic
     */
    protected function processResponseStatus($status, $reason = 'unknown')
    {
        switch ($status) {
            case 401:
            case 403:
                throw new Error\Authentication($reason, $status);
                break;

            case 404:
                throw new Error\NotFound('Resource Not Found', $status);
                break;

            default:
                // 3xx redirect status must be managed by the HTTP Client
                // Statuses other that what we define success are automatic errors
                if (!in_array($status, [200, 201, 202, 203, 204, 205, 206])) {
                    throw new Error\Generic("Error Processing Request: " . $reason, $status);
                }
                break;
        }
        return true;
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

        // Adding custom headers
        $requestHeaders = array_merge_recursive(['Accept' => $acceptContentType], $headers);

        // Creating URI: URI params must be already provided by the calling method
        $uri = $this->uriFactory->createUri($this->options['endpoint'])
            ->withPath($path);

        // Create and send a request
        $request = $this->requestFactory->createRequest($method, $uri, $requestHeaders);
        $response = $this->httpClient->sendRequest($request);

        // Process status
        $this->processResponseStatus($response->getStatusCode(), $response->getReasonPhrase());

        // Check response content type match
        // TODO: improve this by managing multiple accept
        $contentType = $response->getHeaderLine('Content-Type');
        $this->logger->debug('Received a response with content type', [
            'contentType' => $contentType,
            'acceptContentType' => $acceptContentType
        ]);
        if ($contentType !== $acceptContentType) {
            throw new Error\Generic("Invalid response data");
        }

        // Parse response body into an appropriate Akkroo object
        $body = (string) $response->getBody();

        // Return the decoded JSON and let the caller create the appropriate result format
        return json_decode($body, true);
    }
}
