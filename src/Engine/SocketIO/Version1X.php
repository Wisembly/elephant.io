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

namespace ElephantIO\Engine\SocketIO;

use Psr\Log\LoggerInterface;

use GuzzleHttp\Stream\Stream;

use ElephantIO\Engine\SocketIO;

/**
 * Implements the dialog with Socket.IO version 1.x
 *
 * Based on the work of Mathieu Lallemand (@lalmat)
 *
 * @author Baptiste Clavié <baptiste@wisembly.com>
 */
class Version1X extends AbstractSocketIO
{
    const TRANSPORT_POLLING   = 'polling';
    const TRANSPORT_WEBSOCKET = 'websocket';

    /** @var Stream */
    protected $stream;

    /** {@inheritDoc} */
    public function connect()
    {
        $this->handshake();
    }

    /** {@inheritDoc} */
    public function getName()
    {
        return 'SocketIO Version 1.X';
    }

    /** {@inheritDoc} */
    protected function getDefaultOptions()
    {
        $defaults = parent::getDefaultOptions();

        $defaults['version']   = 2;
        $defaults['use_b64']   = false;
        $defaults['transport'] = static::TRANSPORT_POLLING;

        return $defaults;
    }

    /** {@inheritDoc} */
    protected function buildUrl()
    {
        $url = $this->getServerInformation();

        $query = ['use_b64'   => $this->options['use_b64'],
                  'EIO'       => $this->options['version'],
                  'transport' => $this->options['transport']];

        if (isset($url['query'])) {
            $query = array_replace($query, $url['query']);
        }

        return sprintf('%s://%s:%d/%s/?%s', $url['scheme'], $url['host'], $url['port'], $url['path'], http_build_query($query));
    }

    /** Does the handshake with the Socket.io server and populates the `session` value object */
    protected function handshake()
    {
        if (null !== $this->sessions) {
            return;
        }

        $result  = file_get_contents($this->buildUrl());
        $decoded = json_decode(substr($result, strpos('{', $result), strrpos('}', $result)), true);

        $this->session = new Session($decoded['sid'], $decoded['pingInterval'], $decoded['pingTimeout'], $decoded['upgrades']);
    }
}

