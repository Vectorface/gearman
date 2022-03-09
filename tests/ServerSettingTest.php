<?php

namespace Vectorface\Gearman\tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vectorface\Gearman\Client;
use Vectorface\Gearman\ServerSetting;
use Vectorface\Gearman\Worker;

class ServerSettingTest extends TestCase
{
    /**
     * @param ServerSetting $serverSetting
     * @dataProvider serverSettingImplementationDataProvider
     */
    public function testAddServer(ServerSetting $serverSetting)
    {
        $serverSetting->addServer('192.168.1.1');

        $servers = $serverSetting->getServers();

        $this->assertCount(1, $servers);
        $this->assertEquals('192.168.1.1:4730', $servers[0]);

        $serverSetting->addServer('192.168.1.1', 1234);

        $servers = $serverSetting->getServers();

        $this->assertCount(2, $servers);
        $this->assertEquals('192.168.1.1:1234', $servers[1]);

        $serverSetting->addServer('example.com');

        $servers = $serverSetting->getServers();

        $this->assertCount(3, $servers);
        $this->assertEquals('example.com:4730', $servers[2]);
    }

    /**
     * @param ServerSetting $serverSetting
     * @dataProvider serverSettingImplementationDataProvider
     */
    public function testAddServerHostIsAutomaticallyTrimmed(ServerSetting $serverSetting)
    {
        $serverSetting->addServer('  localhost   ');
        $servers = $serverSetting->getServers();

        $this->assertEquals('localhost:4730', $servers[0]);
    }

    /**
     * @param ServerSetting $serverSetting
     * @dataProvider serverSettingImplementationDataProvider
     */
    public function testAddServerCallWithNoArgumentsAddsLocalhost(ServerSetting $serverSetting)
    {
        $serverSetting->addServer();

        $servers = $serverSetting->getServers();

        $this->assertCount(1, $servers);
        $this->assertEquals('localhost:4730', $servers[0]);
    }

    /**
     * @param ServerSetting $serverSetting
     * @dataProvider serverSettingImplementationDataProvider
     *
     */
    public function testAddServerThrowsExceptionIfServerAlreadyExists(ServerSetting $serverSetting)
    {
        $this->expectException(InvalidArgumentException::class);
        $serverSetting->addServer();
        $serverSetting->addServer();
    }

    /**
     * @param ServerSetting $serverSetting
     * @dataProvider serverSettingImplementationDataProvider
     *
     */
    public function testAddServerThrowsExceptionIfEmptyHostIsGiven(ServerSetting $serverSetting)
    {
        $this->expectException(InvalidArgumentException::class);
        $serverSetting->addServer('');
    }

    /**
     * @param ServerSetting $serverSetting
     * @dataProvider serverSettingImplementationDataProvider
     *
     */
    public function testAddServerThrowsExceptionIfSpacesAreGivenAsHost(ServerSetting $serverSetting)
    {
        $this->expectException(InvalidArgumentException::class);
        $serverSetting->addServer('      ');
    }

    /**
     * @param ServerSetting $serverSetting
     * @dataProvider serverSettingImplementationDataProvider
     *
     */
    public function testAddServerThrowsExceptionIfEmptyPortIsGiven(ServerSetting $serverSetting)
    {
        $this->expectException(InvalidArgumentException::class);
        $serverSetting->addServer('localhost', 0);
    }

    /**
     * @param ServerSetting $serverSetting
     * @dataProvider serverSettingImplementationDataProvider
     */
    public function testAddServers(ServerSetting $serverSetting)
    {
        $servers = [
            'localhost',
            'localhost:1234',
            'example.com:4730',
        ];

        $serverSetting->addServers($servers);

        $servers[0] = 'localhost:4730';

        $this->assertEquals($servers, $serverSetting->getServers());
    }

    /**
     * @param ServerSetting $serverSetting
     * @dataProvider serverSettingImplementationDataProvider
     */
    public function testAddServersAsString(ServerSetting $serverSetting)
    {
        $servers = 'localhost,localhost:1234,  example.com:4730';
        $serverSetting->addServers($servers);

        $servers = [
            'localhost:4730',
            'localhost:1234',
            'example.com:4730',
        ];
        $this->assertEquals($servers, $serverSetting->getServers());
    }

    /**
     * @param ServerSetting $serverSetting
     * @dataProvider serverSettingImplementationDataProvider
     *
     */
    public function testAddServersThrowsExceptionIfServerAlreadyExists(ServerSetting $serverSetting)
    {
        $this->expectException(InvalidArgumentException::class);
        $servers = [
            'localhost:4730',
        ];

        $serverSetting
            ->addServer('localhost')
            ->addServers($servers);
    }

    public function serverSettingImplementationDataProvider()
    {
        return [
            [new Worker()],
            [new Client()],
        ];
    }
}
