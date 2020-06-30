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
     * Public API scope
     */
    const SCOPE_PUBLIC = 'public';

    /**
     * Widget scope
     */
    const SCOPE_WIDGET = 'widget';

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
        'endpoint' => 'https://api.integrate-events.com/v2',
        'version' => '2.0.0',
        'scope' => self::SCOPE_PUBLIC
    ];

    /**
     * @var LoggerInterface
     */
    protected $logger = null;

    /**
     * @var string
     */
    protected $username = '';

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
     * @var string
     */
    protected $refreshToken = '';

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
     * @param  string $username Your Akkroo API username (i.e. company username)
     * @param  string $apiKey Your Akkroo API key
     * @return void
     */
    public function __construct(HttpClient $httpClient, string $username, string $apiKey, array $options = [])
    {
        $this->httpClient = $httpClient;
        $this->options = array_merge($this->defaults, $options);
        $this->username = $username;
        $this->apiKey = $apiKey;
        $this->logger = new NullLogger;
        $this->requestFactory = MessageFactoryDiscovery::find();
        $this->uriFactory = UriFactoryDiscovery::find();
    }

    /**
     * Inject a logger object
     *
     * @param  LoggerInterface $logger A PSR-3 compatible logger
     * @return Client
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Authenticate with Akkroo API and get a token
     *
     * Note: current API version returns HTTP 400 when wrong credentials are supplied
     *
     * @param string|null $username
     *
     * @return Client
     *
     * @throws Error\Authentication
     * @throws Error\Generic
     * @throws Error\NotFound
     */
    public function login(string $username = null)
    {
        $headers = [
            'Content-Type' => 'application/json'
        ];

        if ($username !== null) {
            $headers = array_merge(['X-Auth-Username' => $username], $headers);
        }

        $body = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->username,
            'client_secret' => $this->apiKey,
            'scope' => $this->options['scope']
        ];
        $result = $this->request('POST', '/auth', $headers, [], $body);
        $login = (new Result($result['data']))->withRequestID($result['requestID']);
        if ($login->access_token) {
            $this->authToken = $login->access_token;
            $this->authTokenExpiration = time() + (int) $login->expires_in;
            $this->refreshToken = $login->refresh_token;
            return $this;
        }
        throw new Error\Generic('Unable to login, no access token returned');
    }

    /**
     * Returns credentials from last authentication
     *
     * @return array
     */
    public function getCredentials()
    {
        return [
            'authToken' => $this->authToken,
            'authTokenExpiration' => $this->authTokenExpiration,
            'refreshToken' => $this->refreshToken,
        ];
    }

    /**
     * Fetch one or more resources
     *
     * @param  string $resource Resource name (i.e. events, registrations)
     * @param  array  $params   Search parameters (i.e. id, event_id, search query, range, fields, sort)
     * @param  array  $headers Additional headers
     *
     * @return Collection | Resource
     *
     * @throws Error\Authentication
     * @throws Error\NotFound
     * @throws Error\Generic
     */
    public function get($resource, array $params = [], array $headers = [])
    {
        $path = $this->buildPath($resource, $params);
        $result = $this->request('GET', $path, $headers, $params);
        $resourceMeta = [];
        if (!empty($result['headers']['X-Total-Count'])) {
            $contentRange = $this->parseContentRange($result['headers']);
            $resourceMeta['contentRange'] = $contentRange;
        }
        return Resource::create($resource, $result['data'], $params, $resourceMeta)
            ->withRequestID($result['requestID']);
    }

    /**
     * Create a new resource
     *
     * @param  string $resource Resource name (i.e. events, registrations)
     * @param  array  $data     Resource data
     * @param  array  $params   Search parameters (i.e. id, event_id, search query, range, fields, sort)
     * @param  array  $headers  Additional headers
     *
     * @return Resource
     *
     * @throws Error\Authentication
     * @throws Error\NotFound
     * @throws Error\Generic
     */
    public function post($resource, array $data, array $params = [], array $headers = [])
    {
        $path = $this->buildPath($resource, $params);
        $result = $this->request('POST', $path, $headers, $params, $data);
        // Store temporary resource containing only ID
        $tmp = Resource::create($resource, $result['data'], $params)->withRequestID($result['requestID']);
        // Return minimal object if called by Webforms, avoiding errors
        if ($this->options['scope'] === self::SCOPE_WIDGET) {
            return $tmp;
        }
        // Fetch data for inserted resource: use same request ID, so the server could avoid
        // inserting a duplicate
        return $this->get($resource, array_merge($params, ['id' => $tmp->id]), ['Request-ID' => $tmp->requestID]);
    }

    /**
     * Update a resource fully or partially
     *
     * If using PUT method, the $data parameter must be the full resource data
     *
     * @param  string $method   Must be PUT or PATCH
     * @param  string $resource Resource name (i.e. events, registrations)
     * @param  array  $params   URL parameters (i.e. id, event_id)
     * @param  array  $data     Resource data
     * @param  array  $headers  Additional headers
     *
     * @return Resource
     *
     * @throws Error\Authentication
     * @throws Error\NotFound
     * @throws Error\Generic
     */
    protected function update($method, $resource, array $params, array $data, array $headers = [])
    {
        $path = $this->buildPath($resource, $params);
        // Take care of modified header, but let it overridable
        if (empty($headers['If-Unmodified-Since']) && !empty($data['lastModified'])) {
            $headers['If-Unmodified-Since'] = $data['lastModified'];
        }
        $result = $this->request($method, $path, $headers, $params, $data);
        // If we don't have an exception here it's all right, we can fetch the updated resource
        // using the original Request-ID
        $headers['Request-ID'] = $result['requestID'];
        return $this->get($resource, $params, $headers);
    }

    /**
     * Update a resource
     *
     * The $data parameter must be the full resource data
     *
     * @param  string $resource Resource name (i.e. events, registrations)
     * @param  array  $params   URL parameters (i.e. id, event_id)
     * @param  array  $data     Resource data
     * @param  array  $headers  Additional headers
     *
     * @return Resource
     *
     * @throws Error\Authentication
     * @throws Error\NotFound
     * @throws Error\Generic
     */
    public function put($resource, array $params, array $data, array $headers = [])
    {
        return $this->update('PUT', $resource, $params, $data, $headers);
    }

    /**
     * Partially update a resource
     *
     * @param  string $resource Resource name (i.e. events, registrations)
     * @param  array  $params   URL parameters (i.e. id, event_id)
     * @param  array  $data     Resource data
     * @param  array  $headers  Additional headers
     *
     * @return Resource
     * @throws Error\Authentication
     * @throws Error\NotFound
     * @throws Error\Generic
     */
    public function patch($resource, array $params, array $data, array $headers = [])
    {
        return $this->update('PATCH', $resource, $params, $data, $headers);
    }

    /**
     * Delete a resource
     *
     * @param  string $resource Resource name (i.e. events, registrations)
     * @param  array  $params   URL parameters (i.e. id, event_id)
     *
     * @return Result
     *
     * @throws Error\Authentication
     * @throws Error\NotFound
     * @throws Error\Generic
     */
    public function delete($resource, array $params = [])
    {
        $path = $this->buildPath($resource, $params);
        $result = $this->request('DELETE', $path);
        return (new Result(['success' => true]))->withRequestID($result['requestID']);
    }

    /**
     * Count resources that satisfy the query
     *
     * @param  string $resource Resource name (i.e. events, registrations)
     * @param  array  $params   URL parameters (i.e. id, event_id)
     *
     * @return Result
     *
     * @throws Error\Authentication
     * @throws Error\NotFound
     * @throws Error\Generic
     */
    public function count($resource, $params = [])
    {
        $path = $this->buildPath($resource, $params);
        $result = $this->request('HEAD', $path, [], $params);
        $contentRange = $this->parseContentRange($result['headers']);
        return (new Result(['count' => $contentRange['total']]))->withRequestID($result['requestID']);
    }

    /**
     * Get allowed HTTP methods for a resource
     *
     * @param  string $resource Resource name (i.e. events, registrations)
     * @param  array  $params   URL parameters (i.e. id, event_id)
     *
     * @return Result
     *
     * @throws Error\Authentication
     * @throws Error\NotFound
     * @throws Error\Generic
     */
    public function options($resource, $params = [])
    {
        $path = $this->buildPath($resource, $params);
        $result = $this->request('OPTIONS', $path);
        if (empty($result['headers']['Allow'])) {
            throw new Error\Generic('Missing allow header');
        }
        $allow = array_map(function ($item) {
            return trim($item);
        }, explode(',', $result['headers']['Allow'][0]));
        return (new Result(['success' => true, 'allow' => $allow]))->withRequestID($result['requestID']);
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
        $headers = [
            'Content-Type' => 'application/json'
        ];
        if (!empty($token)) {
            $headers['Authorization'] = 'Bearer ' . $token;
            $this->authToken = $token;
        } elseif (!empty($this->authToken)) {
            $headers['Authorization'] = 'Bearer ' . $this->authToken;
        }
        try {
            $result = $this->request('GET', '/auth/test', $headers);
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
            'data' => $this->parseResponseBody($response),
            'headers' => $response->getHeaders()
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
                    if (isset($body['data']['error']['data'])) {
                        throw new Error\Validation('Validation Error', $status, $body);
                    }
                    if ($body['data']['error'] === 'uniqueConflict') {
                        throw new Error\UniqueConflict('Unique Conflict', $status, $body);
                    }
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
     * Parse the Content-Range header to readable data
     *
     * @param  array $headers
     * @return array
     */
    protected function parseContentRange(array $headers)
    {
        $totalCount = (int) $headers['X-Total-Count'][0];
        $contentRange = ['from' => 1, 'to' => $totalCount, 'total' => $totalCount];
        if (!empty($headers['Link'])) {
            preg_match_all('/\<.+?>\;\srel\=\".+?\"/', $headers['Link'][0], $links);

            foreach ($links[0] as $link) {
                $link = explode(' ', $link);
                $matches = [];
                $linkRel = preg_match('/rel="(.*)"/', $link[1], $matches) ? $matches[1] : 'unknown';
                $matches = [];
                $linkURI = preg_match('/<\/v2(.*)>/', $link[0], $matches) ? $matches[1] : 'unknown';
                $linkURIParts = explode('?', $linkURI);
                $linkResource = trim($linkURIParts[0], '/');
                parse_str($linkURIParts[1], $linkURIParams);
                $contentRange['links'][$linkRel] = [
                    'uri' => $linkURI,
                    'resource' => $linkResource,
                    'params' => $linkURIParams
                ];
            }
            $contentRange['page'] = $contentRange['links']['self']['params']['page'];
            $contentRange['pages'] = $contentRange['links']['last']['params']['page'] ?? 1;
            $contentRange['per_page'] = $contentRange['links']['self']['params']['per_page'];
            $contentRange['from'] = $contentRange['per_page'] * ($contentRange['page'] -1) + 1;
            $contentRange['to'] = $contentRange['per_page'] * $contentRange['page'];
        }

        return $contentRange;
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
            case 'records':
                if (empty($params['event_id'])) {
                    throw new InvalidArgumentException('An event ID is required for records');
                }
                $path = '/events/' . $params['event_id'] . '/' . $resource;
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
        $acceptContentType = 'application/json';

        // Unique request ID
        $requestID = uniqid('', true);

        // Adding custom headers
        $requestHeaders = array_merge([
            'Accept' => $acceptContentType,
            'Request-ID' => $requestID
        ], $headers);

        // Add credentials
        if (!empty($this->authToken) && empty($requestHeaders['Authorization'])) {
            $requestHeaders['Authorization'] = 'Bearer ' . $this->authToken;
        }

        // Add content-type header (currently required GET requests)
        if (empty($requestHeaders['Content-Type'])) {
            $requestHeaders['Content-Type'] = $acceptContentType;
        }

        // Creating URI: URI params must be already provided by the calling method
        $endpoint = $this->uriFactory->createUri($this->options['endpoint']);
        $uri = $endpoint->withPath($endpoint->getPath() . $path)
            ->withQuery($this->buildQuery($params));

        // Create body, if provided
        $body = (!empty($data)) ? json_encode($data) : null;

        // Create and send a request
        $this->logger->debug('Sending request', [
            'method' => $method,
            'uri' => (string) $uri,
            'headers' => $requestHeaders,
            'body' => $body
        ]);
        $request = $this->requestFactory->createRequest($method, $uri, $requestHeaders, $body);
        $response = $this->httpClient->sendRequest($request);
        $this->logger->debug('Received response', [
            'status' => $response->getStatusCode(),
            'reason' => $response->getReasonPhrase(),
            'headers' => $response->getHeaders(),
            'body' => (string) $response->getBody()
        ]);
        // Check response content type match
        $contentType = $response->getHeaderLine('Content-Type');
        if (204 !== $response->getStatusCode() && $contentType !== $acceptContentType) {
            throw new Error\Generic(sprintf("Invalid response content type: %s", $contentType));
        }

        // Return the decoded JSON and let the caller create the appropriate result format
        return $this->parseResponse($response, $requestHeaders['Request-ID']);
    }
}
