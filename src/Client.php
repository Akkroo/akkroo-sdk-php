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
use InvalidArgumentException;

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
        $result = $this->request('GET', '/auth', $headers, [], $body);
        $login = (new Result($result['data']))->withRequestID($result['requestID']);
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
     * @param array  $headers Additional headers
     *
     * @return Akkroo\Collection | Akkroo\Resource
     *
     * @throws Error\Authentication
     * @throws Error\NotFound
     * @throws Error\Generic
     */
    public function get($resource, array $params = [], array $headers = [])
    {
        $path = $this->buildPath($resource, $params);
        $result = $this->request('GET', $path, $headers, $params);
        return Resource::create($resource, $result['data'])->withRequestID($result['requestID']);
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
        $result = $this->request('GET', '/selftest', $headers);
        return (new Result($result['data']))->withRequestID($result['requestID']);
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
            $result = $this->request('GET', '/authTest', $headers);
            return (new Result($result['data']))->withRequestID($result['requestID']);
        } catch (Error\Generic $e) {
            $this->logger->error(
                'Auth Test failed',
                ['code' => $e->getCode(), 'message' => $e->getMessage(), 'requestID' => $e->getRequestID()]
            );
            return (new Result(['success' => false]))->withRequestID($e->getRequestID());
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
        $body = [
            'data' => $this->parseResponseBody($response)
        ];
        if (!empty($requestID)) {
            $body['requestID'] = $requestID;
        }
        $this->logger->debug('Parsed response', ['status' => $status, 'reason' => $reason, 'body' => $body]);
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
     * @param string $resource Main resource path
     * @param array  $params   URL and querystring parameters
     * @return string
     */
    protected function buildPath($resource, array $params = [])
    {
        $path = '/' . $resource;
        switch ($resource) {
            case 'events':
                if (!empty($params['id'])) {
                    $path .= '/' . $params['id'];
                }
                break;
            case 'registrations':
                if (empty($params['event_id'])) {
                    throw new InvalidArgumentException('An event ID is required for registrations');
                }
                $path = '/events/' . $params['event_id'] . $resource;
                if (!empty($params['id'])) {
                    $path .= '/' . $params['id'];
                }
                break;
        }
        return $path;
    }

    /**
     * @param array  $params   URL and querystring parameters
     * @return string
     */
    protected function buildQuery(array $params)
    {
        // Add querystring parameters
        $query = [];
        foreach ($params as $key => $value) {
            // Exclude URL and range values
            if (in_array($key, ['id', 'event_id', 'range'])) {
                continue;
            }
            if ($key === 'fields' && is_array($value)) {
                $query[$key] = implode(',', $value);
                continue;
            }
            if ($value === true) {
                $query[$key] = 'true';
                continue;
            }
            if ($value === false) {
                $query[$key] = 'false';
                continue;
            }
            $query[$key] = $value;
        }
        // Decode unreserved characters adding ',' to the list
        // see https://github.com/guzzle/psr7/blob/master/README.md#guzzlehttppsr7urinormalizernormalize
        return preg_replace_callback(
            '/%(?:2C|2D|2E|5F|7E|3[0-9]|[46][1-9A-F]|[57][0-9A])/i',
            function (array $match) {
                return rawurldecode($match[0]);
            },
            http_build_query($query)
        );
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
            ->withPath($path)->withQuery($this->buildQuery($params));

        // Create and send a request
        $this->logger->debug('Sending request', [
            'method' => $method,
            'uri' => (string) $uri,
            'headers' => $requestHeaders
        ]);
        $request = $this->requestFactory->createRequest($method, $uri, $requestHeaders);
        $response = $this->httpClient->sendRequest($request);
        // Check response content type match
        $contentType = $response->getHeaderLine('Content-Type');
        if ($contentType !== $acceptContentType) {
            throw new Error\Generic(sprintf("Invalid response content type: %s", $contentType));
        }

        // Return the decoded JSON and let the caller create the appropriate result format
        return $this->parseResponse($response, $requestHeaders['Request-ID']);
    }
}
