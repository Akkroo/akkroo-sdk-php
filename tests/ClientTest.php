<?php
namespace Akkroo\Tests;

use PHPUnit\Framework\TestCase;

use Akkroo\Client;
use Akkroo\Result;

use Http\Mock\Client as MockClient;

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

        $this->client = new Client($this->httpClient, $this->apiKey);
        $this->client->setLogger($this->logger);
    }

    public function testThatAClientIsCreated()
    {
        $this->assertInstanceOf(Client::class, $this->client);
    }

    public function testAPITest()
    {
        $responseFactory = \Http\Discovery\MessageFactoryDiscovery::find();
        $response = $responseFactory->createResponse(
            200,
            'OK',
            ['Content-Type' => $this->defaultResponseContentType],
            '{"success" : true}'
        );
        $this->httpClient->addResponse($response);
        $result = $this->client->test();
        $this->assertInstanceOf(Result::class, $result);
    }
}
