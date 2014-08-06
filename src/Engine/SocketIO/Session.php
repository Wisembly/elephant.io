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

namespace ElephantIO\SocketIO;

/**
 * Represents the data for a Session
 *
 * @author Baptiste Clavié <baptiste@wisembly.com>
 */
class Session
{
    /** @var integer session's id */
    private $id;

    /** @var integer session's last heartbeat */
    private $heartbeat;

    /** @var integer session's timeout */
    private $timeout;

    /** @var string[] supported transports */
    private $transports;

    public function __construct($id, $heartbeat, $timeout, array $transports)
    {
        $this->id         = $id;
        $this->timeout    = $timeout;
        $this->heartbeat  = $heartbeat;
        $this->transports = $transports;
    }

    /** The property should not be modified, hence the private accessibility on them */
    public function __get($prop)
    {
        if (!in_array($prop, ['id', 'heartbeat', 'timeout', 'transports'])) {
            throw new InvalidArgumentException(sprintf('Unknown property "%s" for the Session object', $prop));
        }

        return $this->$prop;
    }
}
