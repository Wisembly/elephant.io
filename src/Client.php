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

use ElephantIO\Exception\SocketException;

/**
 * Represents the IO Client which will send and receive the requests to the
 * websocket server
 *
 * @author Baptiste Clavié <baptiste@wisembly.com>
 */
class Client
{
    /** @var EngineInterface */
    private $engine;

    /** @var LoggerInterface */
    private $logger;

    private $isConnected = false;

    public function __construct(EngineInterface $engine, LoggerInterface $logger = null)
    {
        $this->engine = $engine;
        $this->logger = $logger;
    }

    public function __destruct()
    {
        if (!$this->isConnected) {
            return;
        }

        $this->close();
    }

    /**
     * Connects to the websocket
     *
     * @param boolean $keepAlive keep alive the connection (not supported yet) ?
     * @return $this
     */
    public function initialize($keepAlive = false)
    {
        try {
            null !== $this->logger && $this->logger->debug('Connecting to the websocket');
            $this->engine->connect();
            null !== $this->logger && $this->logger->debug('Connected to the server');

            $this->isConnected = true;

            if (true === $keepAlive) {
                null !== $this->logger && $this->logger->debug('Keeping alive the connection to the websocket');
                $this->engine->keepAlive();
            }
        } catch (SocketException $e) {
            null !== $this->logger && $this->logger->error('Could not connect to the server', ['exception' => $e]);

            throw $e;
        }

        return $this;
    }

    /**
     * Reads a message from the socket
     *
     * @return MessageInterface Message read from the socket
     */
    public function read()
    {
        null !== $this->logger && $this->logger->debug('Reading a new message from the socket');
        return $this->engine->read();
    }

    /**
     * Emits a message through the engine
     *
     * @return $this
     */
    public function emit($event, array $args)
    {
        null !== $this->logger && $this->logger->debug('Sending a new message', ['event' => $event, 'args' => $args]);
        $this->engine->emit($event, $args);

        return $this;
    }

    /**
     * Closes the connection
     *
     * @return $this
     */
    public function close()
    {
        null !== $this->logger && $this->logger->debug('Closing the connection to the websocket');
        $this->engine->close();

        $this->isConnected = false;

        return $this;
    }

    /**
     * Gets the engine used, for more advanced functions
     *
     * @return EngineInterface
     */
    public function getEngine()
    {
        return $this->engine;
    }
}

