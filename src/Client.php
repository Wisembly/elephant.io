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

use Psr\Log\LoggerInterface;

/**
 * Represents the IO Client which will send and receive the requests to the
 * websocket server
 *
 * Loosely based on the work of Ludovic Barreca
 *
 * @author Baptiste Clavié <baptiste@wisembly.com>
 */
class Client
{
    /** @var EngineInterface */
    private $engine;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(EngineInterface $engine, LoggerInterface $logger = null)
    {
        $this->engine = $engine;
        $this->logger = $logger;
    }

    public function __destruct() {
        try {
            $this->close();
        } catch (\Exception $e) {} // silently fail if we're not connected
    }

    /**
     * Connects to the websocket
     *
     * @param boolean $keepAlive keep alive the connection (not supported yet) ?
     * @return $this
     */
    public function initialize($keepAlive = false)
    {
    }

    /** Reads a message from the socket */
    public function read()
    {
    }

    /**
     * Sends a message through the websocket
     *
     * @return $this
     */
    public function send()
    {
    }

    /**
     * Closes the connection
     *
     * @return $this
     */
    public function close()
    {
    }
}

