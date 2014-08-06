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

namespace ElephantIO\Exception;

use BadMethodCallException;

class UnsupportedActionException extends BadMethodCallException
{
    public function __construct($message = null, Exception $previous = null)
    {
        parent::__construct($message ?: 'This action is not supported by this engine', 0, $previous);
    }
}

