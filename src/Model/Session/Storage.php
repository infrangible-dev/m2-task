<?php

declare(strict_types=1);

namespace Infrangible\Task\Model\Session;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Storage
    extends \Magento\Framework\Session\Storage
{
    /**
     * Constructor
     *
     * @param string $namespace
     * @param array  $data
     */
    public function __construct($namespace = 'task', array $data = [])
    {
        parent::__construct($namespace, $data);
    }
}
