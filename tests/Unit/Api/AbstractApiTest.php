<?php

namespace Redmine\Tests\Unit\Api;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Redmine\Api\AbstractApi;
use Redmine\Client\Client;
use Redmine\Exception\SerializerException;
use Redmine\Http\HttpClient;
use Redmine\Http\Response;
use Redmine\Tests\Fixtures\AssertingHttpClient;
use ReflectionMethod;
use SimpleXMLElement;

/**
 * @coversDefaultClass \Redmine\Api\AbstractApi
 */
class AbstractApiTest extends TestCase
{
    public function testCreateWithHttpClientWorks()
    {
        $client = $this->createMock(HttpClient::class);

        $api = new class ($client) extends AbstractApi {};

        $method = new ReflectionMethod($api, 'getHttpClient');
        $method->setAccessible(true);

        $this->assertSame($client, $method->invoke($api));
    }

    public function testCreateWitClientWorks()
    {
        $client = $this->createMock(Client::class);

        $api = new class ($client) extends AbstractApi {};

        $method = new ReflectionMethod($api, 'getHttpClient');
        $method->setAccessible(true);

        $this->assertInstanceOf(HttpClient::class, $method->invoke($api));
    }

    public function testCreateWithoutClitentOrHttpClientThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Redmine\Api\AbstractApi::__construct(): Argument #1 ($client) must be of type Redmine\Client\Client or Redmine\Http\HttpClient, `stdClass` given');

        /** @phpstan-ignore-next-line We are providing an invalid parameter to test the exception */
        new class (new \stdClass()) extends AbstractApi {};
    }

    /**
     * @covers ::getLastResponse
     */
    public function testGetLastResponseWithHttpClientWorks()
    {
        $client = $this->createMock(HttpClient::class);

        $api = new class ($client) extends AbstractApi {};

        $method = new ReflectionMethod($api, 'getLastResponse');
        $method->setAccessible(true);

        $this->assertInstanceOf(Response::class, $method->invoke($api));
    }

    /**
     * @test
     * @dataProvider getIsNotNullReturnsCorrectBooleanData
     */
    public function testIsNotNullReturnsCorrectBoolean(bool $expected, $value)
    {
        $client = $this->createMock(Client::class);

        $api = new class ($client) extends AbstractApi {};

        $method = new ReflectionMethod($api, 'isNotNull');
        $method->setAccessible(true);

        $this->assertSame($expected, $method->invoke($api, $value));
    }

    public static function getIsNotNullReturnsCorrectBooleanData(): array
    {
        return [
            [false, null],
            [false, false],
            [false, ''],
            [false, []],
            [true, true],
            [true, 0],
            [true, 1],
            [true, 0.0],
            [true, -0.0],
            [true, 0.5],
            [true, '0'],
            [true, 'string'],
            [true, [0]],
            [true, ['0']],
            [true, ['']],
            [true, new \stdClass()],
        ];
    }

    /**
     * @covers ::lastCallFailed
     */
    public function testLastCallFailedPreventsRaceCondition()
    {
        $client = AssertingHttpClient::create(
            $this,
            [
                'GET',
                '200.json',
                'application/json',
                '',
                200
            ],
            [
                'GET',
                '500.json',
                'application/json',
                '',
                500
            ]
        );

        $api1 = new class ($client) extends AbstractApi {
            public function __construct($client)
            {
                parent::__construct($client);
                parent::get('200.json', false);
            }
        };

        $api2 = new class ($client) extends AbstractApi {
            public function __construct($client)
            {
                parent::__construct($client);
                parent::get('500.json', false);
            }
        };

        $api3 = new class ($client) extends AbstractApi {};

        $this->assertSame(false, $api1->lastCallFailed());
        $this->assertSame(true, $api2->lastCallFailed());
        $this->assertSame(true, $api3->lastCallFailed());
    }

    /**
     * @covers ::lastCallFailed
     * @test
     * @dataProvider getLastCallFailedData
     */
    public function testLastCallFailedWithClientReturnsCorrectBoolean($statusCode, $expectedBoolean)
    {
        $client = $this->createMock(Client::class);
        $client->method('getLastResponseStatusCode')->willReturn($statusCode);

        $api = new class ($client) extends AbstractApi {};

        $this->assertSame($expectedBoolean, $api->lastCallFailed());
    }

    /**
     * @covers ::lastCallFailed
     * @test
     * @dataProvider getLastCallFailedData
     */
    public function testLastCallFailedWithHttpClientReturnsCorrectBoolean($statusCode, $expectedBoolean)
    {
        $response = $this->createMock(Response::class);
        $response->method('getStatusCode')->willReturn($statusCode);

        $client = $this->createMock(HttpClient::class);
        $client->method('request')->willReturn($response);

        $api = new class ($client) extends AbstractApi {
            public function __construct($client)
            {
                parent::__construct($client);
                $this->get('', false);
            }
        };

        $this->assertSame($expectedBoolean, $api->lastCallFailed());
    }

    public static function getLastCallFailedData(): array
    {
        return [
            [0, true],
            [100, true],
            [101, true],
            [102, true],
            [103, true],
            [103, true],
            [200, false],
            [201, false],
            [202, true],
            [203, true],
            [204, true],
            [205, true],
            [206, true],
            [207, true],
            [208, true],
            [226, true],
            [300, true],
            [301, true],
            [302, true],
            [303, true],
            [304, true],
            [305, true],
            [306, true],
            [307, true],
            [308, true],
            [400, true],
            [401, true],
            [402, true],
            [403, true],
            [404, true],
            [405, true],
            [406, true],
            [407, true],
            [408, true],
            [409, true],
            [410, true],
            [411, true],
            [412, true],
            [413, true],
            [414, true],
            [415, true],
            [416, true],
            [417, true],
            [421, true],
            [422, true],
            [423, true],
            [424, true],
            [425, true],
            [426, true],
            [428, true],
            [429, true],
            [431, true],
            [451, true],
            [500, true],
            [501, true],
            [502, true],
            [503, true],
            [504, true],
            [505, true],
            [506, true],
            [507, true],
            [508, true],
            [509, true],
            [510, true],
            [511, true],
        ];
    }

    /**
     * @covers ::retrieveData
     *
     * @dataProvider retrieveDataData
     */
    public function testRetrieveData($response, $contentType, $expected)
    {
        $client = $this->createMock(Client::class);
        $client->method('requestGet')->willReturn(true);
        $client->method('getLastResponseBody')->willReturn($response);
        $client->method('getLastResponseContentType')->willReturn($contentType);

        $api = new class ($client) extends AbstractApi {};

        $method = new ReflectionMethod($api, 'retrieveData');
        $method->setAccessible(true);

        $this->assertSame($expected, $method->invoke($api, '/issues.json'));
    }

    public static function retrieveDataData(): array
    {
        return [
            'test decode by default' => ['{"foo_bar": 12345}', 'application/json', ['foo_bar' => 12345]],
        ];
    }

    /**
     * @covers ::retrieveData
     *
     * @dataProvider getRetrieveDataToExceptionData
     */
    public function testRetrieveDataThrowsException($response, $contentType, $expectedException, $expectedMessage)
    {
        $client = $this->createMock(Client::class);
        $client->method('requestGet')->willReturn(true);
        $client->method('getLastResponseBody')->willReturn($response);
        $client->method('getLastResponseContentType')->willReturn($contentType);

        $api = new class ($client) extends AbstractApi {};

        $method = new ReflectionMethod($api, 'retrieveData');
        $method->setAccessible(true);

        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedMessage);

        $method->invoke($api, '/issues.json');
    }

    public static function getRetrieveDataToExceptionData(): array
    {
        return [
            'Empty body' => ['', 'application/json', SerializerException::class, 'Syntax error" while decoding JSON: '],
        ];
    }

    /**
     * @covers ::retrieveAll
     *
     * @dataProvider getRetrieveAllData
     */
    public function testDeprecatedRetrieveAll($content, $contentType, $expected)
    {
        $client = $this->createMock(Client::class);
        $client->method('requestGet')->willReturn(true);
        $client->method('getLastResponseBody')->willReturn($content);
        $client->method('getLastResponseContentType')->willReturn($contentType);

        $api = new class ($client) extends AbstractApi {};

        $method = new ReflectionMethod($api, 'retrieveAll');
        $method->setAccessible(true);

        $this->assertSame($expected, $method->invoke($api, ''));
    }

    public static function getRetrieveAllData(): array
    {
        return [
            'array response' => ['{"foo_bar": 12345}', 'application/json', ['foo_bar' => 12345]],
            'string response' => ['"string"', 'application/json', 'Could not convert response body into array: "string"'],
            'false response' => ['', 'application/json', false],
        ];
    }

    /**
     * @covers ::attachCustomFieldXML
     */
    public function testDeprecatedAttachCustomFieldXML()
    {
        $client = $this->createMock(Client::class);

        $api = new class ($client) extends AbstractApi {};

        $method = new ReflectionMethod($api, 'attachCustomFieldXML');
        $method->setAccessible(true);

        $xml = new SimpleXMLElement('<?xml version="1.0"?><issue/>');

        $this->assertInstanceOf(SimpleXMLElement::class, $method->invoke($api, $xml, []));
    }
}
