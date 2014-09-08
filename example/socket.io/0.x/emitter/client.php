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

use ElephantIO\Client,
    ElephantIO\Engine\SocketIO\Version0X;

require __DIR__ . '/../../../../vendor/autoload.php';

$client = new Client(new Version0X('http://localhost:1337'));

$client->initialize();
$client->emit('action', ['foo' => 'bar']);
$client->close();

