<?php

namespace Vectorface\Gearman\tests;

use Vectorface\Gearman\Exception;
use PHPUnit\Framework\TestCase;
use Vectorface\Gearman\Connection;

/**
 * @category   Testing
 *
 * @author     Till Klampaeckel <till@php.net>
 * @license    http://www.opensource.org/licenses/bsd-license.php New BSD License
 *
 * @version    CVS: $Id$
 *
 * @link       http://pear.php.net/package/Net_Gearman
 * @since      0.2.4
 */
class ConnectionTest extends TestCase
{
    /**
     * When no server is supplied, it should connect to localhost:4730.
     */
    public function testDefaultConnect()
    {
        try {
            $connection = Connection::connect();
        } catch (Exception) {
            $this->markTestSkipped('Skipped. You can try this test on your machine with gearman running.');
        }

        $this->assertIsResource($connection);
        $this->assertEquals('socket', strtolower(get_resource_type($connection)));

        $this->assertTrue(Connection::isConnected($connection));

        Connection::close($connection);
    }

    /**
     * @throws Exception
     */
    public function testSend()
    {
        try {
            $connection = Connection::connect();
        } catch (Exception) {
            $this->markTestSkipped('Skipped. You can try this test on your machine with gearman running.');
        }

        Connection::send($connection, 'echo_req', ['text' => 'foobar']);

        do {
            $ret = Connection::read($connection);
        } while (!count($ret));

        Connection::close($connection);

        $this->assertIsArray($ret);
        $this->assertEquals('echo_res', $ret['function']);
        $this->assertEquals(17, $ret['type']);

        $this->assertIsArray($ret['data']);
        $this->assertEquals('foobar', $ret['data']['text']);
    }
}
