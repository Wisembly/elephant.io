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

use DomainException;

use Psr\Log\LoggerInterface;

use ElephantIO\EngineInterface,
    ElephantIO\Payload\Decoder,
    ElephantIO\Exception\UnsupportedActionException;

abstract class AbstractSocketIO implements EngineInterface
{
    const CONNECT      = 0;
    const DISCONNECT   = 1;
    const EVENT        = 2;
    const ACK          = 3;
    const ERROR        = 4;
    const BINARY_EVENT = 5;
    const BINARY_ACK   = 6;

    /** @var string[] Parse url result */
    protected $url;

    /** @var string[] Session information */
    protected $session;

    /** @var mixed[] Array of options for the engine */
    protected $options;

    /** @var resource Resource to the connected stream */
    protected $stream;

    public function __construct($url, array $options = array())
    {
        $this->url     = $this->parseUrl($url);
        $this->options = array_replace($this->getDefaultOptions(), $options);
    }

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

    /**
     * Write the message to the socket
     *
     * @param integer $code    type of message (one of EngineInterface constants)
     * @param string  $message Message to send, correctly formatted
     */
    abstract public function write($code, $message = null);

    /** {@inheritDoc} */
    public function emit($event, array $args)
    {
        throw new UnsupportedActionException($this, 'emit');
    }

    /**
     * {@inheritDoc}
     *
     * Be careful, this method may hang your script, as we're not in a non
     * blocking mode.
     */
    public function read()
    {
        if (!is_resource($this->stream)) {
            return;
        }

        /*
         * The first byte contains the FIN bit, the reserved bits, and the
         * opcode... We're not interested in them. Yet.
         */
        $data = fread($this->stream, 1);

        // the second byte contains the mask bit and the payload's length
        $data  .= $part = fread($this->stream, 1);
        $length = (int)  (bin2hex($part) & ~0x80); // removing the mask bit
        $mask   = (bool) (bin2hex($part) &  0x80);

        /*
         * Here is where it is getting tricky :
         *
         * - If the length <= 125, then we do not need to do anything ;
         * - if the length is 126, it means that it is coded over the next 2 bytes ;
         * - if the length is 127, it means that it is coded over the next 8 bytes.
         *
         * But,here's the trick : we cannot interpret a length over 127 if the
         * system does not support 64bits integers (such as Windows, or 32bits
         * processors architectures).
         */
        switch ($length) {
            case 0x7D: // 125
            break;

            case 0x7E: // 126
                $length = unpack('n', fread($this->stream, 2));
            break;

            case 0x7F: // 127
                // are (at least) 64 bits not supported by the architecture ?
                if (8 > PHP_INT_SIZE) {
                    throw new DomainException('64 bits unsigned integer are not supported on this architecture');
                }

                /*
                 * As (un)pack does not support unpacking 64bits unsigned
                 * integer, we need to split the data
                 *
                 * {@link http://stackoverflow.com/questions/14405751/pack-and-unpack-64-bit-integer}
                 */
                list($left, $right) = array_values(unpack('N2', fread($this->stream, 8)));
                $length = $left << 32 | $right;
            break;
        }

        // incorporate the mask key if the mask bit is 1
        if (true === $mask) {
            $data .= fread($this->stream, 4);
        }

        // Split the packet in case of the length > 16kb
        while ($length > 0 && $buffer = fread($this->stream, $length)) {
            $data   .= $buffer;
            $length -= strlen($buffer);
        }

        // decode the payload
        return (string) new Decoder($data);
    }

    /** {@inheritDoc} */
    public function getName()
    {
        return 'SocketIO';
    }

    /**
     * Parse an url into parts we may expect
     *
     * @return string[] information on the given URL
     */
    protected function parseUrl($url)
    {
        $parsed = parse_url($url);

        if (false === $parsed) {
            throw new MalformedUrlException($url);
        }

        $server = array_replace(array('scheme' => 'http',
                                 'host'   => 'localhost',
                                 'query'  => array(),
                                 'path'   => 'socket.io'), $parsed);

        if (!isset($server['port'])) {
            $server['port'] = 'https' === $server['scheme'] ? 443 : 80;
        }

        if (!is_array($server['query'])) {
            parse_str($server['query'], $query);
            $server['query'] = $query;
        }

        $server['secured'] = 'https' === $server['scheme'];

        return $server;
    }

    /**
     * Get the defaults options
     *
     * @return array mixed[] Defaults options for this engine
     */
    protected function getDefaultOptions()
    {
        return array('context' => array(),
                'debug'   => false,
                'wait'    => 100*1000,
                'timeout' => ini_get("default_socket_timeout"));
    }
}

