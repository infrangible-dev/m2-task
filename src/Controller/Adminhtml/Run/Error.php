<?php

namespace Infrangible\Task\Controller\Adminhtml\Run;

use Infrangible\Task\Model\Session;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Block\Template;
use Magento\Framework\Exception\LocalizedException;
use Tofex\Help\Variables;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2023 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Error
    extends Action
{
    /** @var Variables */
    protected $variableHelper;

    /** @var Session */
    protected $taskSession;

    /**
     * @param Context   $context
     * @param Variables $variableHelper
     * @param Session   $taskSession
     */
    public function __construct(Context $context, Variables $variableHelper, Session $taskSession)
    {
        parent::__construct($context);

        $this->variableHelper = $variableHelper;

        $this->taskSession = $taskSession;
    }

    /**
     * Execute action based on request and return result
     *
     * Note: Request will be added as operation argument in future
     *
     * @return void
     * @throws LocalizedException
     */
    public function execute()
    {
        $reason = $this->taskSession->getData('task_error_reason');

        if ( ! $this->variableHelper->isEmpty($reason)) {
            $this->_view->loadLayout(['default', 'infrangible_task_run_error']);

            /** @var Template|bool $errorBlock */
            $errorBlock = $this->_view->getLayout()->getBlock('task_error');

            if ($errorBlock === false) {
                throw new LocalizedException(__('Result block not found in layout'));
            }

            $errorBlock->setData('reason', $reason);

            $this->taskSession->unsetData('task_error_reason');

            $this->_view->renderLayout();
        } else {
            $this->_redirect('/');
        }
    }

    /**
     * @return bool
     */
    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Infrangible_Task::infrangible_task_task_' .
            $this->taskSession->getData('task_name'));
    }
}
