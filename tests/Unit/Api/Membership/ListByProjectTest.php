<?php

namespace Redmine\Tests\Unit\Api\Membership;

use PHPUnit\Framework\TestCase;
use Redmine\Api\Membership;
use Redmine\Client\Client;
use Redmine\Exception\InvalidParameterException;
use Redmine\Exception\UnexpectedResponseException;
use Redmine\Tests\Fixtures\MockClient;
use stdClass;

/**
 * @covers \Redmine\Api\Membership::listByProject
 */
class ListByProjectTest extends TestCase
{
    public function testListByProjectWithoutParametersReturnsResponse()
    {
        // Test values
        $response = '["API Response"]';
        $expectedReturn = ['API Response'];

        // Create the used mock objects
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('requestGet')
            ->with('/projects/5/memberships.json')
            ->willReturn(true);
        $client->expects($this->exactly(1))
            ->method('getLastResponseBody')
            ->willReturn($response);
        $client->expects($this->exactly(1))
            ->method('getLastResponseContentType')
            ->willReturn('application/json');

        // Create the object under test
        $api = new Membership($client);

        // Perform the tests
        $this->assertSame($expectedReturn, $api->listByProject(5));
    }

    public function testListByProjectWithParametersReturnsResponse()
    {
        // Test values
        $parameters = ['not-used'];
        $response = '["API Response"]';
        $expectedReturn = ['API Response'];

        // Create the used mock objects
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('requestGet')
            ->with('/projects/project-slug/memberships.json?limit=25&offset=0&0=not-used')
            ->willReturn(true);
        $client->expects($this->exactly(1))
            ->method('getLastResponseBody')
            ->willReturn($response);
        $client->expects($this->exactly(1))
            ->method('getLastResponseContentType')
            ->willReturn('application/json');

        // Create the object under test
        $api = new Membership($client);

        // Perform the tests
        $this->assertSame($expectedReturn, $api->listByProject('project-slug', $parameters));
    }

    /**
     * @dataProvider getInvalidProjectIdentifiers
     */
    public function testListByProjectWithWrongProjectIdentifierThrowsException($projectIdentifier)
    {
        $api = new Membership(MockClient::create());

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Redmine\Api\Membership::listByProject(): Argument #1 ($projectIdentifier) must be of type int or string');

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
            ->with('/projects/5/memberships.json')
            ->willReturn(true);
        $client->expects($this->exactly(1))
            ->method('getLastResponseBody')
            ->willReturn('');
        $client->expects($this->exactly(1))
            ->method('getLastResponseContentType')
            ->willReturn('application/json');

        // Create the object under test
        $api = new Membership($client);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('The Redmine server replied with an unexpected response.');

        // Perform the tests
        $api->listByProject(5);
    }
}
