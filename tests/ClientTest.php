<?php
namespace Akkroo\Tests;

use PHPUnit\Framework\TestCase;

use Akkroo\Client;
use Akkroo\Result;
use Akkroo\Resource;
use Akkroo\Collection;
use Akkroo\Company;
use Akkroo\Event;
use Akkroo\Registration;

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
        $this->logger = new Logger('TestSDK');
        $this->logger->pushHandler(new StreamHandler(self::$logFile, Logger::DEBUG));
        $this->httpClient = new MockClient;
        $this->defaultResponseContentType = 'application/vnd.akkroo-v1.1.5+json';
        $this->responseFactory = Discovery\MessageFactoryDiscovery::find();
        $this->dataDir = dirname(__FILE__) . '/_files';

        $this->client = new Client($this->httpClient, $this->apiKey);
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
        $this->assertNotEmpty($result->requestID);
    }

    public function testAuthenticationWithNoToken()
    {
        $response = $this->responseFactory->createResponse(
            400,
            'Bad Request',
            ['Content-Type' => $this->defaultResponseContentType],
            json_encode([
                'error' => 'invalid_request',
                'error_description' => 'Access token not found',
                'detail_error' => 'authAccessTokenNotFound'
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
                'error' => 'invalid_grant',
                'error_description' => 'The access token has expired',
                'detail_error' => 'authAccessTokenExpired'
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
                'error' => 'invalid_grant',
                'error_description' => 'Access invalid',
                'detail_error' => 'authAccessTokenInvalid'
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
                'token_type' => 'bearer',
                'scope' => 'PublicAPI'
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
    }

   /**
     * @expectedException Akkroo\Error\Generic
     * @expectedExceptionCode 400
     * @expectedExceptionMessage Bad Request (invalid_client): Client id was not found in the headers or body
     */
    public function testGenericLoginError()
    {
        $response = $this->responseFactory->createResponse(
            400,
            'Bad Request',
            ['Content-Type' => $this->defaultResponseContentType],
            json_encode([
                'error' => 'invalid_client',
                'error_description' => 'Client id was not found in the headers or body',
                'detail_error' => 'authClientIDNotFound'
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
            'Request-ID' => $customRequestID
        ]);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($result->success);
        $this->assertNotEmpty($result->requestID);
        $this->assertEquals($customRequestID, $result->requestID);
    }

    public function testCompanyResource()
    {
        $response = $this->responseFactory->createResponse(
            200,
            'OK',
            ['Content-Type' => $this->defaultResponseContentType],
            json_encode([
              'lastModified' => 'Tue, 16 May 2017 13:45:55 GMT',
              'name' => 'Foobar Inc',
              'username' => 'fubar',
              'accountExpires' => null,
              'created' => 'Tue, 22 Oct 2013 10:04:36 GMT',
              'numDevices' => 10
            ])
        );
        $this->httpClient->addResponse($response);
        $company = $this->client->get('company');
        $this->assertInstanceOf(Company::class, $company);
        $this->assertEquals('Foobar Inc', $company->name);
        $this->assertEquals('fubar', $company->username);
        $this->assertEquals(10, $company->numDevices);
    }

    public function testEventsCollection()
    {
        $response = $this->responseFactory->createResponse(
            200,
            'No Error',
            ['Content-Type' => $this->defaultResponseContentType],
            file_get_contents($this->dataDir . '/events.json')
        );
        $this->httpClient->addResponse($response);
        $events = $this->client->get('events');
        $this->assertInstanceOf(Collection::class, $events);
        $this->assertInstanceOf(Event::class, $events[0]);
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
        $registrations = $this->client->get('registrations', ['event_id' => 147]);
        $this->assertInstanceOf(Collection::class, $registrations);
        $this->assertInstanceOf(Registration::class, $registrations[0]);
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
        $registrations = $this->client->get('registrations', [
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
        $this->assertInstanceOf(Registration::class, $registrations[0]);
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
        $registration = $this->client->get('registrations', [
            'id' => '58f73032279871a5058b4567',
            'event_id' => 147
        ]);
        $this->assertInstanceOf(Registration::class, $registration);
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
        $registration = $this->client->get('registrations', [
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
        $result = $this->client->test();
    }

    public function testValidResourceCount()
    {
        $response = $this->responseFactory->createResponse(
            206,
            'Partial Content',
            [
                'Content-Type' => $this->defaultResponseContentType,
                'Content-Range' => 'resources 0-0/4'
            ]
        );
        $this->httpClient->addResponse($response);
        $result = $this->client->count('registrations', ['event_id' => 157]);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertNotEmpty($result->count);
        $this->assertEquals(4, $result->count);
    }

    /**
     * @expectedException Akkroo\Error\Generic
     * @expectedExceptionCode 400
     * @expectedExceptionMessage Missing range headers
     */
    public function testResourceCountErrorOnMissingContentRange()
    {
        $response = $this->responseFactory->createResponse(
            206,
            'Partial Content',
            ['Content-Type' => $this->defaultResponseContentType]
        );
        $this->httpClient->addResponse($response);
        $result = $this->client->count('registrations', ['event_id' => 157]);
    }

    /**
     * @expectedException Akkroo\Error\Generic
     * @expectedExceptionCode 400
     * @expectedExceptionMessage Invalid content range
     */
    public function testResourceCountErrorOnInvalidContentRange()
    {
        $response = $this->responseFactory->createResponse(
            206,
            'Partial Content',
            [
                'Content-Type' => $this->defaultResponseContentType,
                'Content-Range' => 'resources 0-0'
            ]
        );
        $this->httpClient->addResponse($response);
        $result = $this->client->count('registrations', ['event_id' => 157]);
    }

    public function testSuccessfulNewEventCreation()
    {
        $data = json_decode(file_get_contents($this->dataDir . '/event_134.json'), true);
        $data['date'] = date('Y-m-d', strtotime('tomorrow'));
        $data['preRegEndDate'] = date('Y-m-d', strtotime('today'));

        $response = $this->responseFactory->createResponse(
            201,
            'Created',
            ['Content-Type' => $this->defaultResponseContentType],
            json_encode([
                'id' => 134
            ])
        );
        $this->httpClient->addResponse($response);

        $response = $this->responseFactory->createResponse(
            200,
            'No Error',
            ['Content-Type' => $this->defaultResponseContentType],
            json_encode(array_merge(['id' => 134], $data))
        );
        $this->httpClient->addResponse($response);

        $event = $this->client->post('events', $data);

        $this->assertInstanceOf(Event::class, $event);
        $this->assertEquals(134, $event->id);
        $this->assertEquals($data['name'], $event->name);
    }

    public function testEventValidationErrors()
    {
        $data = json_decode(file_get_contents($this->dataDir . '/event_134.json'), true);
        $response = $this->responseFactory->createResponse(
            400,
            'Bad Request',
            ['Content-Type' => $this->defaultResponseContentType],
            file_get_contents($this->dataDir . '/event_validation_errors.json')
        );
        $this->httpClient->addResponse($response);
        try {
            $event = $this->client->post('events', $data);
        } catch (\Exception $e) {
            $this->assertInstanceOf(\Akkroo\Error\Validation::class, $e);
            $this->assertEquals(400, $e->getCode());
            $this->assertEquals('One or more validation errors occured', $e->getMessage());
            $errorDetails = $e->getDetails();
            $this->assertInternalType('array', $errorDetails);
            $this->assertEquals('date', $errorDetails[0]['attribute']);
        }
    }
}
