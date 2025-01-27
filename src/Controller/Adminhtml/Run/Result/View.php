<?php

declare(strict_types=1);

namespace Infrangible\Task\Controller\Adminhtml\Run\Result;

use Exception;
use Infrangible\BackendWidget\Controller\Backend\Object\Edit;
use Infrangible\Task\Traits\Run;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Result\Page;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2025 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class View extends Edit
{
    use Run;

    protected function getObjectNotFoundMessage(): string
    {
        return __('Could not find run!')->render();
    }

    /**
     * @return Page|void
     * @throws Exception
     */
    public function execute()
    {
        $object = $this->initObject();

        if (! $object) {
            $this->_redirect(
                $this->getIndexUrlRoute(),
                $this->getIndexUrlParams()
            );

            return;
        }

        if ($object->getId() && ! $this->allowEdit() && ! $this->allowView()) {
            $this->_redirect(
                $this->getIndexUrlRoute(),
                $this->getIndexUrlParams()
            );

            return;
        }

        $this->initAction();

        $blockClass = \Infrangible\Task\Block\Adminhtml\Run\View::class;

        $blockData = ['run_id' => $object->getId()];

        if ($this->getRequest()->getParam('back_route')) {
            $blockData[ 'back_route' ] = $this->getRequest()->getParam('back_route');
        }

        /** @var AbstractBlock $block */
        $block = $this->_view->getLayout()->createBlock(
            $blockClass,
            '',
            ['data' => $blockData]
        );

        $this->_addContent($block);

        $this->finishAction(__('View Log')->render());

        return $this->_view->getPage();
    }
}
