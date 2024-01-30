<?php

namespace Redmine\Tests\Unit\Api\Project;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Redmine\Api\Project;
use Redmine\Exception\UnexpectedResponseException;
use Redmine\Http\HttpClient;
use Redmine\Http\Response;
use Redmine\Tests\Fixtures\AssertingHttpClient;

/**
 * @covers \Redmine\Api\Project::close
 */
class CloseTest extends TestCase
{
    public function testCloseReturnsTrue()
    {
        $client = AssertingHttpClient::create(
            $this,
            [
                'PUT',
                '/projects/5/close.xml',
                'application/xml',
                '',
                204
            ]
        );

        $api = new Project($client);

        $this->assertTrue($api->close(5));
    }

    public function testCloseThrowsUnexpectedResponseException()
    {
        $client = AssertingHttpClient::create(
            $this,
            [
                'PUT',
                '/projects/5/close.xml',
                'application/xml',
                '',
                403
            ]
        );

        $api = new Project($client);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('The Redmine server replied with the status code 403');

        $api->close(5);
    }

    public function testCloseWithoutIntOrStringThrowsInvalidArgumentException()
    {
        $client = $this->createMock(HttpClient::class);

        $api = new Project($client);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Redmine\Api\Project::close(): Argument #1 ($projectIdentifier) must be of type int or string');

        // provide a wrong project identifier
        $api->close(true);
    }
}