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

namespace ElephantIO;

/**
 * Represents an engine used within ElephantIO to send / receive messages from
 * a websocket real time server
 *
 * Loosely based on the work of the following :
 *   - Ludovic Barreca (@ludovicbarreca)
 *   - Mathieu Lallemand (@lalmat)
 *
 * @author Baptiste Clavié <baptiste@wisembly.com>
 */
interface EngineInterface
{
    const OPEN    = 0;
    const CLOSE   = 1;
    const PING    = 2;
    const PONG    = 3;
    const MESSAGE = 4;
    const UPGRADE = 5;
    const NOOP    = 6;

    /** Connect to the targeted server */
    public function connect();

    /** Closes the connection to the websocket */
    public function close();

    /**
     * Read data from the socket
     *
     * @return string Data read from the socket
     */
    public function read();

    /**
     * Emits a message through the websocket
     *
     * @param string $event Event to emit
     * @param array  $args  Arguments to send
     */
    public function emit($event, array $args);

    /** Keeps alive the connection */
    public function keepAlive();

    /** Gets the name of the engine */
    public function getName();
}

