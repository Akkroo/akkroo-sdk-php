<?php
namespace Akkroo\Tests;

use PHPUnit\Framework\TestCase;

use Akkroo\Client;
use Akkroo\Result;
use Akkroo\Resource;
use Akkroo\Collection;
use Akkroo\Company;
use Akkroo\Event;
use Akkroo\Record;

use Http\Mock\Client as MockClient;
use Http\Discovery;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class ClientTest extends TestCase
{
    protected static $logFile;

    public static function setUpBeforeClass()
    {
        self::$logFile = __DIR__.'/../build/logs/tests.log';
        if (is_readable(self::$logFile)) {
            unlink(self::$logFile);
        }
    }

    public function setUp()
    {
        $this->apiKey = 'DummyAPIKey';
        $this->apiUsername = 'dummy';
        $this->logger = new Logger('TestSDK');
        $this->logger->pushHandler(new StreamHandler(self::$logFile, Logger::DEBUG));
        $this->httpClient = new MockClient;
        $this->defaultResponseContentType = 'application/json';
        $this->responseFactory = Discovery\MessageFactoryDiscovery::find();
        $this->dataDir = dirname(__FILE__) . '/_files';

        $this->client = new Client($this->httpClient, $this->apiUsername, $this->apiKey);
        $this->client->setLogger($this->logger);
    }

    public function testThatAClientIsCreated()
    {
        $this->assertInstanceOf(Client::class, $this->client);
    }

    public function testAPITest()
    {
        $response = $this->responseFactory->createResponse(
            200,
            'OK',
            ['Content-Type' => $this->defaultResponseContentType],
            json_encode([
                'success' => true
            ])
        );
        $this->httpClient->addResponse($response);
        $result = $this->client->test();
        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($result->success);
    }

    public function testAuthenticationWithNoToken()
    {
        $response = $this->responseFactory->createResponse(
            400,
            'Bad Request',
            ['Content-Type' => $this->defaultResponseContentType],
            json_encode([
                'error' => [
                    'message' => 'The data from the request failed schema validation'
                ]
            ])
        );
        $this->httpClient->addResponse($response);
        $result = $this->client->authTest();
        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->requestID);
    }

    public function testAuthenticationWithExpiredToken()
    {
        $response = $this->responseFactory->createResponse(
            401,
            'Unauthorized',
            ['Content-Type' => $this->defaultResponseContentType],
            json_encode([
                'error' => [
                    'message' => 'Token has expired'
                ]
            ])
        );
        $this->httpClient->addResponse($response);
        $result = $this->client->authTest('ExpiredToken');
        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->requestID);
    }

    public function testAuthenticationWithInvalidToken()
    {
        $response = $this->responseFactory->createResponse(
            401,
            'Unauthorized',
            ['Content-Type' => $this->defaultResponseContentType],
            json_encode([
                'error' => [
                    'message' => 'Invalid credentials'
                ]
            ])
        );
        $this->httpClient->addResponse($response);
        $result = $this->client->authTest('InvalidToken');
        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->requestID);
    }

    public function testAuthenticationWithValidToken()
    {
        $response = $this->responseFactory->createResponse(
            200,
            'No Error',
            ['Content-Type' => $this->defaultResponseContentType],
            json_encode([
                'success' => true
            ])
        );
        $this->httpClient->addResponse($response);
        $result = $this->client->authTest('ValidToken');
        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($result->success);
        $this->assertNotEmpty($result->requestID);

        $credentials = $this->client->getCredentials();
        $this->assertInternalType('array', $credentials);
        $this->assertEquals('ValidToken', $credentials['authToken']);
    }

    public function testSuccessfulLogin()
    {
        $response = $this->responseFactory->createResponse(
            200,
            'No Error',
            ['Content-Type' => $this->defaultResponseContentType],
            json_encode([
                'access_token' => 'ValidToken',
                'expires_in' => 86400,
                'refresh_token' => 'RefreshToken'
            ])
        );
        $this->httpClient->addResponse($response);
        $client = $this->client->login();
        $this->assertInstanceOf(Client::class, $client);
        $this->assertAttributeEquals('ValidToken', 'authToken', $this->client);
        $this->assertAttributeEquals(time() + 86400, 'authTokenExpiration', $client);
        $response = $this->responseFactory->createResponse(
            200,
            'No Error',
            ['Content-Type' => $this->defaultResponseContentType],
            json_encode([
                'success' => true
            ])
        );
        $this->httpClient->addResponse($response);
        $result = $this->client->authTest();
        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($result->success);
        $this->assertNotEmpty($result->requestID);

        $credentials = $this->client->getCredentials();
        $this->assertInternalType('array', $credentials);
        $this->assertEquals('ValidToken', $credentials['authToken']);
        $this->assertEquals(time() + 86400, $credentials['authTokenExpiration']);
    }

   /**
     * @expectedException Akkroo\Error\Generic
     * @expectedExceptionCode 400
     * @expectedExceptionMessage Unable to login, no access token returned
     */
    public function testGenericLoginError()
    {
        $response = $this->responseFactory->createResponse(
            200,
            'No Error',
            ['Content-Type' => $this->defaultResponseContentType],
            json_encode([
                'access_token' => '',
                'expires_in' => 86400,
                'token_type' => 'bearer',
                'scope' => 'PublicAPI'
            ])
        );
        // Works with both empty token and empty content
        $this->httpClient->addResponse($response);
        $client = $this->client->login();
    }

   /**
     * @expectedException Akkroo\Error\Generic
     * @expectedExceptionCode 400
     * @expectedExceptionMessage Bad Request (The data from the request failed schema validation)
     */
    public function testMissingTokenLoginError()
    {
        $response = $this->responseFactory->createResponse(
            400,
            'Bad Request',
            ['Content-Type' => $this->defaultResponseContentType],
            json_encode([
                'error' => [
                    'message' => 'The data from the request failed schema validation'
                ]
            ])
        );
        $this->httpClient->addResponse($response);
        $client = $this->client->login();
    }

    public function testOverridableRequestID()
    {
        $customRequestID = 'SomeCustomUniqueValue';
        $response = $this->responseFactory->createResponse(
            200,
            'OK',
            ['Content-Type' => $this->defaultResponseContentType],
            json_encode([
                'success' => true,
                'requestID' => $customRequestID
            ])
        );
        $this->httpClient->addResponse($response);
        $result = $this->client->test([
            'X-Request-ID' => $customRequestID
        ]);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($result->success);
        $this->assertNotEmpty($result->requestID);
        $this->assertEquals($customRequestID, $result->requestID);
    }

    public function testEventsCollection()
    {
        $response = $this->responseFactory->createResponse(
            200,
            'No Error',
            [
                'Content-Type' => $this->defaultResponseContentType,
                'X-Total-Count' => '5',
                'Link' => '</v2/events?per_page=2&page=1>; rel="self",</v2/events?per_page=2&page=2>;'
                    .' rel="next",</v2/events?per_page=2&page=3>; rel="last"'
            ],
            file_get_contents($this->dataDir . '/events.json')
        );
        $this->httpClient->addResponse($response);
        $events = $this->client->get('events');
        $this->assertInstanceOf(Collection::class, $events);
        $this->assertInstanceOf(Event::class, $events[0]);

        // Testing pagination metadata
        $eventsMeta = $events->getMeta();
        $contentRange = $events->getMeta('contentRange');
        $this->assertInternalType('array', $eventsMeta);
        $this->assertInternalType('array', $contentRange);
        $this->assertEquals(1, $contentRange['from']);
        $this->assertEquals(2, $contentRange['to']);
        $this->assertEquals(5, $contentRange['total']);
        $this->assertNull($events->getMeta('foo'));
    }

    public function testEventsCollectionWithParameters()
    {
        $response = $this->responseFactory->createResponse(
            200,
            'No Error',
            ['Content-Type' => $this->defaultResponseContentType],
            file_get_contents($this->dataDir . '/events.json')
        );
        $this->httpClient->addResponse($response);
        $events = $this->client->get('events', [
            'lastModifiedFrom' => time(),
            'lastModifiedTo' => time(),
            'registrationsLastModifiedFrom' => time(),
            'registrationsLastModifiedTo' => time(),
            'fields' => ['id', 'name']
        ]);
        $this->assertInstanceOf(Collection::class, $events);
        $this->assertInstanceOf(Event::class, $events[0]);
    }

    public function testSingleEvent()
    {
        $response = $this->responseFactory->createResponse(
            200,
            'No Error',
            ['Content-Type' => $this->defaultResponseContentType],
            file_get_contents($this->dataDir . '/event_147.json')
        );
        $this->httpClient->addResponse($response);
        $event = $this->client->get('events', ['id' => 147]);
        $this->assertInstanceOf(Event::class, $event);
    }

    /**
     * @expectedException Akkroo\Error\NotFound
     * @expectedExceptionCode 404
     */
    public function testUnknownEvent()
    {
        $response = $this->responseFactory->createResponse(
            404,
            'Not Found',
            ['Content-Type' => $this->defaultResponseContentType],
            json_encode([
              'error' => 'notFound',
              'details' => [],
              'message' => 'Model does not exist'
            ])
        );
        $this->httpClient->addResponse($response);
        $event = $this->client->get('events', ['id' => 147]);
    }

    public function testEventRegistrations()
    {
        $response = $this->responseFactory->createResponse(
            200,
            'No Error',
            ['Content-Type' => $this->defaultResponseContentType],
            file_get_contents($this->dataDir . '/event_147_registrations.json')
        );
        $this->httpClient->addResponse($response);
        $registrations = $this->client->get('records', ['event_id' => 147]);
        $this->assertInstanceOf(Collection::class, $registrations);
        $this->assertInstanceOf(Record::class, $registrations[0]);
    }

    public function testEventRegistrationsWithParameters()
    {
        $response = $this->responseFactory->createResponse(
            200,
            'No Error',
            [
                'Content-Type' => $this->defaultResponseContentType,
                'Content-Range' => 'resources 0-2/2'
            ],
            file_get_contents($this->dataDir . '/event_147_registrations.json')
        );
        $this->httpClient->addResponse($response);
        $registrations = $this->client->get('records', [
            'event_id' => 147,
            'isCheckIn' => true,
            'hasArrived' => false,
            'createdFrom' => time(),
            'createdTo' => time(),
            'lastModifiedFrom' => time(),
            'lastModifiedTo' => time(),
            'range' => [0, 2]
        ]);
        $this->assertInstanceOf(Collection::class, $registrations);
        $this->assertInstanceOf(Record::class, $registrations[0]);
    }

    public function testSingleRegistration()
    {
        $response = $this->responseFactory->createResponse(
            200,
            'No Error',
            ['Content-Type' => $this->defaultResponseContentType],
            file_get_contents($this->dataDir . '/event_147_registration_58f73032279871a5058b4567.json')
        );
        $this->httpClient->addResponse($response);
        $registration = $this->client->get('records', [
            'id' => '58f73032279871a5058b4567',
            'event_id' => 147
        ]);
        $this->assertInstanceOf(Record::class, $registration);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSingleRegistrationWithNoEventID()
    {
        $response = $this->responseFactory->createResponse(
            404,
            'Not Found',
            ['Content-Type' => $this->defaultResponseContentType],
            json_encode([
                'error' => 'notFound',
                'details' => [],
                'message' => 'Endpoint does not exist'
            ])
        );
        $this->httpClient->addResponse($response);
        $registration = $this->client->get('records', [
            'id' => '58f73032279871a5058b4567'
        ]);
    }

    /**
     * @expectedException Akkroo\Error\Generic
     * @expectedExceptionCode 400
     * @expectedExceptionMessage Invalid response content type: text/plain
     */
    public function testErrorOnInvalidResponseContentType()
    {
        $response = $this->responseFactory->createResponse(
            200,
            'OK',
            ['Content-Type' => 'text/plain'],
            json_encode([
              'success' => true
            ])
        );
        $this->httpClient->addResponse($response);
        $result = $this->client->get('some-path');
    }

    public function testValidResourceCount()
    {
        $response = $this->responseFactory->createResponse(
            206,
            'Partial Content',
            [
                'Content-Type' => $this->defaultResponseContentType,
                'X-Total-Count' => '4'
            ]
        );
        $this->httpClient->addResponse($response);
        $result = $this->client->count('records', ['event_id' => 157]);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertNotEmpty($result->count);
        $this->assertEquals(4, $result->count);
    }

    // Feature not yet implemented on API
    // public function testSuccessfulNewEventCreation()
    // {
    //     $data = json_decode(file_get_contents($this->dataDir . '/event_134.json'), true);
    //     $data['date'] = date('Y-m-d', strtotime('tomorrow'));
    //     $data['preRegEndDate'] = date('Y-m-d', strtotime('today'));

    //     $response = $this->responseFactory->createResponse(
    //         201,
    //         'Created',
    //         ['Content-Type' => $this->defaultResponseContentType],
    //         json_encode([
    //             'id' => 134
    //         ])
    //     );
    //     $this->httpClient->addResponse($response);

    //     $response = $this->responseFactory->createResponse(
    //         200,
    //         'No Error',
    //         ['Content-Type' => $this->defaultResponseContentType],
    //         json_encode(array_merge(['id' => 134], $data))
    //     );
    //     $this->httpClient->addResponse($response);

    //     $event = $this->client->post('events', $data);

    //     $this->assertInstanceOf(Event::class, $event);
    //     $this->assertEquals(134, $event->id);
    //     $this->assertEquals($data['name'], $event->name);
    // }

    // Feature not yet implemented on API
    // public function testEventValidationErrors()
    // {
    //     $data = json_decode(file_get_contents($this->dataDir . '/event_134.json'), true);
    //     $response = $this->responseFactory->createResponse(
    //         400,
    //         'Bad Request',
    //         ['Content-Type' => $this->defaultResponseContentType],
    //         file_get_contents($this->dataDir . '/event_validation_errors.json')
    //     );
    //     $this->httpClient->addResponse($response);
    //     try {
    //         $event = $this->client->post('events', $data);
    //     } catch (\Exception $e) {
    //         $this->assertInstanceOf(\Akkroo\Error\Validation::class, $e);
    //         $this->assertEquals(400, $e->getCode());
    //         $this->assertEquals('One or more validation errors occured', $e->getMessage());
    //         $errorDetails = $e->getDetails();
    //         $this->assertInternalType('array', $errorDetails);
    //         $this->assertEquals('date', $errorDetails[0]['attribute']);
    //         $this->assertEquals('tooSmall', $errorDetails[0]['type']);
    //     }
    // }

    // Feature not yet implemented on API
    // public function testEventUniqueConflict()
    // {
    //     $data = json_decode(file_get_contents($this->dataDir . '/event_134.json'), true);
    //     $response = $this->responseFactory->createResponse(
    //         400,
    //         'Bad Request',
    //         ['Content-Type' => $this->defaultResponseContentType],
    //         file_get_contents($this->dataDir . '/event_unique_conflict.json')
    //     );
    //     $this->httpClient->addResponse($response);
    //     try {
    //         $event = $this->client->post('events', $data);
    //     } catch (\Exception $e) {
    //         $this->assertInstanceOf(\Akkroo\Error\UniqueConflict::class, $e);
    //         $this->assertEquals(400, $e->getCode());
    //         $this->assertEquals('The record already exists', $e->getMessage());
    //         $errorDetails = $e->getDetails();
    //         $this->assertInternalType('array', $errorDetails);
    //         $this->assertEquals('values.email.value', $errorDetails[0]['attribute']);
    //         $this->assertEquals('134', $errorDetails[0]['existingID']);
    //     }
    // }

    public function testSuccessfulNewRegistrationCreation()
    {
        $data = json_decode(file_get_contents($this->dataDir . '/event_134_new_registration.json'), true);
        $data['timeArrived'] = date('c'); // RFC timestamp

        $response = $this->responseFactory->createResponse(
            201,
            'Created',
            ['Content-Type' => $this->defaultResponseContentType],
            json_encode([
                'id' => '598b2b50279871ce058b4567'
            ])
        );
        $this->httpClient->addResponse($response);

        $response = $this->responseFactory->createResponse(
            200,
            'No Error',
            ['Content-Type' => $this->defaultResponseContentType],
            file_get_contents($this->dataDir . '/event_134_registration_598b2b50279871ce058b4567.json')
        );
        $this->httpClient->addResponse($response);

        $registration = $this->client->post('records', $data, ['event_id' => 134]);

        $this->assertInstanceOf(Record::class, $registration);
        $this->assertEquals('598b2b50279871ce058b4567', $registration->id);
        $this->assertEquals(134, $registration->eventID);
        $this->assertEquals('API', $registration->source);
    }

    public function testRegistrationValidationErrors()
    {
        $data = json_decode(file_get_contents($this->dataDir . '/event_134_new_registration.json'), true);
        $data['timeArrived'] = 123456; // Purposefully invalid timestamp
        unset($data['isCheckIn']); // Deleting a required field
        $response = $this->responseFactory->createResponse(
            400,
            'Bad Request',
            ['Content-Type' => $this->defaultResponseContentType],
            file_get_contents($this->dataDir . '/registration_validation_errors.json')
        );
        $this->httpClient->addResponse($response);
        try {
            $registration = $this->client->post('records', $data, ['event_id' => 134]);
        } catch (\Exception $e) {
            $this->assertInstanceOf(\Akkroo\Error\Validation::class, $e);
            $this->assertEquals(400, $e->getCode());
            // Error message may vary so we're not testing it
            $errorDetails = $e->getDetails();
            $this->assertInternalType('array', $errorDetails);
            $this->assertCount(2, $errorDetails);
            $this->assertNotEmpty($errorDetails['timeArrived']);
        }
    }

    public function testSuccessfulEventDeletion()
    {
        $response = $this->responseFactory->createResponse(
            204,
            'No Content',
            ['Content-Type' => $this->defaultResponseContentType]
        );
        $this->httpClient->addResponse($response);
        $result = $this->client->delete('events', ['id' => 134]);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($result->success);
        $this->assertNotEmpty($result->requestID);
    }

    /**
     * @expectedException Akkroo\Error\NotFound
     * @expectedExceptionCode 404
     */
    public function testErrorOnEventDeletion()
    {
        $response = $this->responseFactory->createResponse(
            404,
            'Not Found',
            ['Content-Type' => $this->defaultResponseContentType]
        );
        $this->httpClient->addResponse($response);
        $result = $this->client->delete('events', ['id' => 134]);
    }

    public function testSuccessfulBulkRegistrationDeletion()
    {
        $response = $this->responseFactory->createResponse(
            204,
            'No Content',
            ['Content-Type' => $this->defaultResponseContentType]
        );
        $this->httpClient->addResponse($response);
        $result = $this->client->delete('records', ['event_id' => 134]);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($result->success);
        $this->assertNotEmpty($result->requestID);
    }

    public function testOptionsMethod()
    {
        $response = $this->responseFactory->createResponse(
            204,
            'No Content',
            [
                'Content-Type' => $this->defaultResponseContentType,
                'Allow' => 'OPTIONS, PUT, PATCH, DELETE, HEAD, GET'
            ]
        );
        $this->httpClient->addResponse($response);
        $result = $this->client->options('events', ['id' => 134]);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($result->success);
        $this->assertNotEmpty($result->requestID);
        $this->assertInternalType('array', $result->allow);
        $this->assertEquals(['OPTIONS', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'GET'], $result->allow);
    }

    /**
     * @expectedException Akkroo\Error\Generic
     * @expectedExceptionCode 400
     * @expectedExceptionMessage Missing allow header
     */
    public function testOptionsMethodWithInvalidResponse()
    {
        $response = $this->responseFactory->createResponse(
            204,
            'No Content',
            ['Content-Type' => $this->defaultResponseContentType]
        );
        $this->httpClient->addResponse($response);
        $result = $this->client->options('events', ['id' => 134]);
    }

    public function testSuccessfulPUTEvent()
    {
        $data = json_decode(file_get_contents($this->dataDir . '/event_134.json'), true);
        $data['date'] = date('Y-m-d', strtotime('tomorrow'));
        $data['preRegEndDate'] = date('Y-m-d', strtotime('today'));
        $data['lastModified'] = date('D, j M Y H:i:s e', strtotime('yesterday'));

        $response = $this->responseFactory->createResponse(
            204,
            'No Content',
            ['Content-Type' => $this->defaultResponseContentType]
        );
        $this->httpClient->addResponse($response);

        $response = $this->responseFactory->createResponse(
            200,
            'No Error',
            ['Content-Type' => $this->defaultResponseContentType],
            json_encode(array_merge(['id' => 134], $data))
        );
        $this->httpClient->addResponse($response);

        $event = $this->client->put('events', ['id' => 134], $data);

        $this->assertInstanceOf(Event::class, $event);
        $this->assertEquals(134, $event->id);
        $this->assertEquals($data['name'], $event->name);
    }

    public function testSuccessfulPATCHEvent()
    {
        $event = json_decode(file_get_contents($this->dataDir . '/event_134.json'), true);
        $event['lastModified'] = date('D, j M Y H:i:s e', strtotime('yesterday'));

        $data = ['name' => 'New Event Name'];

        $response = $this->responseFactory->createResponse(
            204,
            'No Content',
            ['Content-Type' => $this->defaultResponseContentType]
        );
        $this->httpClient->addResponse($response);

        $response = $this->responseFactory->createResponse(
            200,
            'No Error',
            ['Content-Type' => $this->defaultResponseContentType],
            json_encode(array_merge(['id' => 134], $event, $data))
        );
        $this->httpClient->addResponse($response);

        $event = $this->client->patch(
            'events',
            ['id' => 134],
            $data,
            ['If-Unmodified-Since' => $event['lastModified']]
        );

        $this->assertInstanceOf(Event::class, $event);
        $this->assertEquals(134, $event->id);
        $this->assertEquals($data['name'], $event->name);
    }
}
