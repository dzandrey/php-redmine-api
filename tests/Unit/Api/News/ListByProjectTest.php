<?php

namespace Redmine\Tests\Unit\Api\News;

use PHPUnit\Framework\TestCase;
use Redmine\Api\News;
use Redmine\Client\Client;
use Redmine\Exception\InvalidParameterException;
use Redmine\Exception\UnexpectedResponseException;
use Redmine\Tests\Fixtures\MockClient;
use stdClass;

/**
 * @covers \Redmine\Api\News::listByProject
 */
class ListByProjectTest extends TestCase
{
    public function testListByProjectWithoutParametersReturnsResponse()
    {
        // Test values
        $projectId = 5;
        $response = '["API Response"]';
        $expectedReturn = ['API Response'];

        // Create the used mock objects
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('requestGet')
            ->with('/projects/5/news.json')
            ->willReturn(true);
        $client->expects($this->exactly(1))
            ->method('getLastResponseBody')
            ->willReturn($response);
        $client->expects($this->exactly(1))
            ->method('getLastResponseContentType')
            ->willReturn('application/json');

        // Create the object under test
        $api = new News($client);

        // Perform the tests
        $this->assertSame($expectedReturn, $api->listByProject($projectId));
    }

    public function testListByProjectWithParametersReturnsResponse()
    {
        // Test values
        $projectId = 5;
        $parameters = ['not-used'];
        $response = '["API Response"]';
        $expectedReturn = ['API Response'];

        // Create the used mock objects
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('requestGet')
            ->with('/projects/5/news.json?limit=25&offset=0&0=not-used')
            ->willReturn(true);
        $client->expects($this->exactly(1))
            ->method('getLastResponseBody')
            ->willReturn($response);
        $client->expects($this->exactly(1))
            ->method('getLastResponseContentType')
            ->willReturn('application/json');

        // Create the object under test
        $api = new News($client);

        // Perform the tests
        $this->assertSame($expectedReturn, $api->listByProject($projectId, $parameters));
    }

    /**
     * @dataProvider getInvalidProjectIdentifiers
     */
    public function testListByProjectWithWrongProjectIdentifierThrowsException($projectIdentifier)
    {
        $api = new News(MockClient::create());

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Redmine\Api\News::listByProject(): Argument #1 ($projectIdentifier) must be of type int or string');

        $api->listByProject($projectIdentifier);
    }

    public static function getInvalidProjectIdentifiers(): array
    {
        return [
            'null' => [null],
            'true' => [true],
            'false' => [false],
            'float' => [0.0],
            'array' => [[]],
            'object' => [new stdClass()],
        ];
    }

    public function testListByProjectThrowsException()
    {
        // Create the used mock objects
        $client = $this->createMock(Client::class);
        $client->expects($this->exactly(1))
            ->method('requestGet')
            ->with('/projects/5/news.json')
            ->willReturn(true);
        $client->expects($this->exactly(1))
            ->method('getLastResponseBody')
            ->willReturn('');
        $client->expects($this->exactly(1))
            ->method('getLastResponseContentType')
            ->willReturn('application/json');

        // Create the object under test
        $api = new News($client);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('The Redmine server replied with an unexpected response.');

        // Perform the tests
        $api->listByProject(5);
    }
}
