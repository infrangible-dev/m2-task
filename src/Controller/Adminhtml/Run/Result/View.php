<?php

namespace Infrangible\Task\Controller\Adminhtml\Run\Result;

use Exception;
use Infrangible\BackendWidget\Controller\Backend\Object\Edit;
use Infrangible\Task\Traits\Run;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Result\Page;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2023 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class View
    extends Edit
{
    use Run;

    /**
     * @return string
     */
    protected function getObjectNotFoundMessage(): string
    {
        return __('Could not find run!');
    }

    /**
     * @return Page|void
     * @throws Exception
     */
    public function execute()
    {
        $object = $this->initObject();

        if ( ! $object) {
            $this->_redirect($this->getIndexUrlRoute(), $this->getIndexUrlParams());

            return;
        }

        if ($object->getId() && ! $this->allowEdit() && ! $this->allowView()) {
            $this->_redirect($this->getIndexUrlRoute(), $this->getIndexUrlParams());

            return;
        }

        $this->initAction();

        $blockClass = \Infrangible\Task\Block\Adminhtml\Run\View::class;

        /** @var AbstractBlock $block */
        $block = $this->_view->getLayout()->createBlock($blockClass, '', ['data' => ['run_id' => $object->getId()]]);

        $this->_addContent($block);

        $this->finishAction(__('View Log'));

        return $this->_view->getPage();
    }
}
