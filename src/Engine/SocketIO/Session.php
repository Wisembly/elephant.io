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

    /** @var string[] supported upgrades */
    private $upgrades;

    public function __construct($id, $heartbeat, $timeout, array $upgrades)
    {
        $this->id        = $id;
        $this->timeout   = $timeout;
        $this->upgrades  = $upgrades;
        $this->heartbeat = $heartbeat;
    }

    /** The property should not be modified, hence the private accessibility on them */
    public function __get($prop)
    {
        static $list = ['id', 'heartbeat', 'timeout', 'upgrades'];

        if (!in_array($prop, $list)) {
            throw new InvalidArgumentException(sprintf('Unknown property "%s" for the Session object. Only the following are availables : ["%s"]', $prop, implode('", "', $list)));
        }

        return $this->$prop;
    }
}
