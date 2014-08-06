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

namespace ElephantIO\Engine;

use Psr\Log\LoggerInterface;

use ElephantIO\EngineInterface,
    ElephantIO\Exception\UnsupportedActionException;

abstract class SocketIO implements EngineInterface
{
    const CONNECT      = 0;
    const DISCONNECT   = 1;
    const EVENT        = 2;
    const ACK          = 3;
    const ERROR        = 4;
    const BINARY_EVENT = 5;
    const BINARY_ACK   = 6;

    const TRANSPORT_POLLING   = 'polling';
    const TRANSPORT_WEBSOCKET = 'websocket';

    /** @var string[] Parse url result */
    protected $url;

    /** @var LoggerInterface */
    protected $logger = null;

    /** @var string[] Session information */
    protected $sessions;

    /** {@inheritDoc} */
    public function connect()
    {
        throw new UnsupportedActionException($this, 'connect');
    }

    /** {@inheritDoc} */
    public function keepAlive()
    {
        throw new UnsupportedActionException($this, 'keepAlive');
    }

    /** {@inheritDoc} */
    public function close()
    {
        throw new UnsupportedActionException($this, 'close');
    }

    /** {@inheritDoc} */
    public function send()
    {
        throw new UnsupportedActionException($this, 'send');
    }

    /** {@inheritDoc} */
    public function read()
    {
        throw new UnsupportedActionException($this, 'read');
    }

    /** {@inheritDoc} */
    public function getName()
    {
        return 'SocketIO';
    }

    /**
     * Get the server information from the parsed URL
     *
     * @return string[] information on the given URL
     */
    protected function getServerInformation()
    {
        $server = array_replace($this->url, ['scheme' => 'http',
                                             'host'   => 'localhost',
                                             'path'   => 'socket.io']);

        if (!isset($server['port'])) {
            $server['port'] = 'https' === $server['scheme'] ? 443 : 80;
        }

        if ('https' === $server['scheme']) {
            $server['scheme'] = 'ssl';
        }

        $server['transport'] = $this->transport ?: static::TRANSPORT_POLLING;

        return $server;
    }

    /**
     * Get the defaults options
     *
     * @return array mixed[] Defaults options for this engine
     */
    protected function getDefaultOptions()
    {
        return [['check_ssl' => false,
                 'debug'     => false]];
    }

    /**
     * Build the URL to establish a connection
     *
     * @return string URL built
     */
    abstract protected function buildUrl();
}

