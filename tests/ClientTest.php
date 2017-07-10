<?php
namespace Akkroo\Tests;

use PHPUnit\Framework\TestCase;

use Akkroo\Client;
use Akkroo\Result;

use Http\Mock\Client as MockClient;
use Http\Discovery;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class ClientTest extends TestCase
{
    public function setUp()
    {
        $this->apiKey = 'DummyAPIKey';
        $this->logger = new Logger('TestSDK');
        $this->logger->pushHandler(new StreamHandler(__DIR__.'/../build/logs/tests.log', Logger::DEBUG));
        $this->httpClient = new MockClient;
        $this->defaultResponseContentType = 'application/vnd.akkroo-v1.1.5+json';
        $this->responseFactory = Discovery\MessageFactoryDiscovery::find();

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
        $this->assertAttributeEquals('ValidToken', 'authToken', $client);
        $this->assertAttributeEquals(time() + 86400, 'authTokenExpiration', $client);
    }

    public function testAuthenticationAfterAValidLogin()
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
}
