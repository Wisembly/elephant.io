<?php
/**
 * This file is part of the Elephant.io package
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 *
 * @copyright Wisembly
 * @license   http://www.opensource.org/licenses/MIT-License MIT License
 */

use ElephantIO\Client;
use ElephantIO\Engine\SocketIO\Version1X;

require __DIR__ . '/../../../../vendor/autoload.php';

$handshakeQuery = '/?foo=bar&bar=foo'; //this will be available on io.on('connection', function(socket){ socket.handshake.query.foo / .bar });
$socketOptions  = array('context' =>
	                        array('http' => array(
		                        'header' => "Accept-language: en,\r\n" .
			                        "Origin: https://www.example.com,\r\n" .
			                        "Cookie: foo=bar; \r\n"
	                        )));

$client = new Client(new Version1X('http://localhost:1337' . $handshakeQuery, $socketOptions));

$client->initialize();
$data = waitForEvent($client, 'message', 5);

//example: server->emit('message', 'hello world');
//$data = hello world or false on timeout

$client->close();

/**
 * @param     $socket  , ElephantIO\Client
 * @param     $event   , string
 * @param int $timeout , float comparator
 *
 * @return mixed
 * @since
 */
function waitForEvent($socket, $event, $timeout = 5)
{
	$time_start = microtime(true);
	try
	{
		while (true)
		{
			$res = $socket->read();
			if (!empty($res))
			{
				if (strpos($res, '42') === 0) //starts with 42
				{
					$res = str_replace(substr($res, 0, 2), '', $res);
				}
				$data = json_decode($res);//successful event will start with 42["event","data"]
				if (is_array($data) && $data[0] == $event)
				{
					return $data[1];
				}
				elseif (is_array($data))
				{
					return waitForEvent($socket, $event, $timeout); // we have data, but not as expected
				}
			}
			if ((microtime(true) - $time_start) > $timeout) //server is not responding in time, brake
			{
				break;
			}
		}
	}
	catch (\ElephantIO\Exception\ServerConnectionFailureException $e)
	{
		var_dump($e->getMessage());
	}

	return false;//no data found
}
