<?php

namespace Infrangible\Task\Model;

use Infrangible\Task\Model\Session\Storage;
use Magento\Framework\Session\SessionManager;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2023 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Session
    extends SessionManager
{
    /**
     * @param string|array $key
     * @param mixed        $value
     */
    public function setData($key, $value = null)
    {
        /** @var Storage $storage */
        $storage = $this->storage;

        $storage->setData($key, $value);
    }

    /**
     * @param null|string|array $key
     *
     * @return void
     */
    public function unsetData($key = null)
    {
        /** @var Storage $storage */
        $storage = $this->storage;

        $storage->unsetData($key);
    }
}
