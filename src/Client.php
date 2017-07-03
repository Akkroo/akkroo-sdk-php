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
        return $this->request('GET', '/selftest');
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
     * Send a request to the API endpoint
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
        // TODO: improve this by managing intermediate statuses (eg. 201, 206, etc...)
        $status = $response->getStatusCode();
        if ($status !== 200) {
            throw new Error\Generic("Error Processing Request", $status);
        }

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

        // TODO: use a factory to determine the best result object to return
        // (eg. Akkroo\Collection, Akkroo\Event, Registration, Company, etc)
        // OR just return the result and let the caller decide which object return
        return new Result(json_decode($body, true));
    }
}
