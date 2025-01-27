<?php

declare(strict_types=1);

namespace Infrangible\Task\Controller\Adminhtml;

use Exception;
use Infrangible\Core\Helper\Instances;
use Infrangible\Core\Helper\Stores;
use Infrangible\Task\Helper\Data;
use Infrangible\Task\Model\Session;
use Infrangible\Task\Task\Base;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Block\Template;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Area;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\TranslateInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\App\EmulationFactory;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
abstract class Run extends Action
{
    /** @var Stores */
    protected $storeHelper;

    /** @var Instances */
    protected $instanceHelper;

    /** @var Data */
    protected $taskHelper;

    /** @var Session */
    protected $taskSession;

    /** @var \Magento\Backend\Model\Auth\Session */
    protected $authSession;

    /** @var Emulation */
    protected $appEmulation;

    /** @var ResolverInterface */
    protected $localeResolver;

    /** @var TranslateInterface */
    protected $translate;

    /** @var Base */
    private $task;

    public function __construct(
        Context $context,
        Stores $storeHelper,
        Instances $instanceHelper,
        Data $taskHelper,
        Session $taskSession,
        \Magento\Backend\Model\Auth\Session $authSession,
        EmulationFactory $appEmulationFactory,
        ResolverInterface $localeResolver,
        TranslateInterface $translate
    ) {
        parent::__construct($context);

        $this->storeHelper = $storeHelper;
        $this->instanceHelper = $instanceHelper;
        $this->taskHelper = $taskHelper;

        $this->taskSession = $taskSession;
        $this->authSession = $authSession;
        $this->appEmulation = $appEmulationFactory->create();
        $this->localeResolver = $localeResolver;
        $this->translate = $translate;
    }

    abstract protected function getTaskName(): string;

    abstract protected function getClassName(): string;

    /**
     * @throws Exception
     */
    public function getTask(): Base
    {
        if ($this->task === null) {
            $this->task = $this->instanceHelper->getInstance($this->getClassName());

            if (! ($this->task instanceof Base)) {
                throw new Exception(
                    sprintf(
                        'Task must extend %s',
                        Base::class
                    )
                );
            }
        }

        return $this->task;
    }

    /**
     * @return Redirect|void
     * @throws Exception
     */
    public function execute()
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $taskName = $this->getTaskName();

        $this->taskSession->setData(
            'task_name',
            $taskName
        );

        if (empty($taskName)) {
            $this->taskSession->setData(
                'task_error_reason',
                __('Please specify a task name!')
            );

            $resultRedirect->setPath('infrangible_task/run/error');

            return $resultRedirect;
        }

        $isAllowed = $this->_authorization->isAllowed('Infrangible_Task::infrangible_task') &&
            $this->_authorization->isAllowed($this->getTaskResourceId());

        if (! $isAllowed) {
            $this->taskSession->setData(
                'task_error_reason',
                __('No right to execute task!')
            );

            $resultRedirect->setPath('infrangible_task/run/error');

            return $resultRedirect;
        }

        $task = $this->getTask();

        $testMode = $this->getRequest()->getParam(
            'test',
            false
        );

        try {
            $storeCode = $this->getRequest()->getParam('store_code');

            if (empty($storeCode)) {
                $storeCode = 'admin';
            }

            $storeId = $this->storeHelper->getStore($storeCode)->getId();

            $this->appEmulation->startEnvironmentEmulation(
                $storeId,
                Area::AREA_ADMINHTML
            );

            $locale = null;

            if ($this->getRequest()->getParam('backend_user')) {
                $backendUser = $this->authSession->getUser();

                $locale = $backendUser->getInterfaceLocale();
            }

            if ($this->getRequest()->getParam('locale')) {
                $locale = $this->getRequest()->getParam('locale');
            }

            if ($locale) {
                $this->localeResolver->setLocale($locale);
                $this->translate->setLocale($locale);
                $this->translate->loadData(Area::AREA_ADMINHTML);
            }

            $taskResult = $task->launchFromAdmin(
                $storeCode,
                $taskName,
                $testMode !== false
            );

            $this->appEmulation->stopEnvironmentEmulation();

            $redirectPath = $this->getRedirectPath();

            if ($redirectPath) {
                $runId = $task->getRun()->getId();

                if ($this->isAddResultMessage()) {
                    $this->addResultMessage(
                        $taskName,
                        $taskResult,
                        $runId
                    );
                }

                $resultRedirect->setPath($redirectPath);

                return $resultRedirect;
            } else {
                $this->_view->loadLayout(['default', 'infrangible_task_run_result']);

                /** @var Template|bool $resultBlock */
                $resultBlock = $this->_view->getLayout()->getBlock('task_result');

                if ($resultBlock === false) {
                    throw new LocalizedException(__('Result block not found in layout'));
                }

                $taskTitle = $this->taskHelper->getTaskConfigValue(
                    $taskName,
                    'data',
                    'title',
                    null,
                    false,
                    true
                );

                $resultBlock->setData(
                    'title',
                    __($taskTitle)
                );

                $taskMessages = $task->getSummary(
                    Base::SUMMARY_TYPE_ALL,
                    false,
                    true
                );

                $resultBlock->setData(
                    'result',
                    $taskMessages
                );

                $this->_view->renderLayout();
            }
        } catch (Exception $exception) {
            $this->taskSession->setData(
                'task_error_reason',
                $exception->__toString()
            );

            $resultRedirect->setPath('infrangible_task/run/error');

            return $resultRedirect;
        }
    }

    abstract protected function getTaskResourceId(): string;

    protected function getRedirectPath(): ?string
    {
        return null;
    }

    protected function isAddResultMessage(): bool
    {
        return false;
    }

    protected function addResultMessage(string $taskName, bool $taskResult, $runId): void
    {
        $messageData = [
            'task_name' => $taskName,
            'run_url'   => $this->_url->getUrl(
                'infrangible_task/run_result/view',
                ['run_id' => $runId, 'back_route' => base64_encode('exutec_check24productexport/export')]
            )
        ];

        if ($taskResult) {
            $this->messageManager->addComplexSuccessMessage(
                'taskSuccessMessage',
                $messageData
            );
        } else {
            $this->messageManager->addComplexErrorMessage(
                'taskErrorMessage',
                $messageData
            );
        }
    }
}
