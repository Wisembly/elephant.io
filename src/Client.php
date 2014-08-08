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
        null !== $this->logger && $this->logger->info('Connecting to the websocket');
        $this->engine->connect();

        if (true === $keepAlive) {
            null !== $this->logger && $this->logger->info('Keeping alive the connection to the websocket');
            $this->engine->keepAlive();
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
        null !== $this->logger && $this->logger->info('Reading a new message from the socket');
        return $this->engine->read();
    }

    /**
     * Emits a message through the engine
     *
     * @return $this
     */
    public function emit($event, array $args)
    {
        null !== $this->logger && $this->logger->info('Sending a new message', ['event' => $event, 'args' => $args]);
        $this->engine->emit($event, $args);
    }

    /**
     * Closes the connection
     *
     * @return $this
     */
    public function close()
    {
        null !== $this->logger && $this->logger->info('Closing the connection to the websocket');
        $this->engine->close();

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

