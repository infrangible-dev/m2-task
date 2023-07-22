<?php

namespace Infrangible\Task\Traits;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2023 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
trait Run
{
    /**
     * @return string
     */
    protected function getModuleKey(): string
    {
        return 'Infrangible_Task';
    }

    /**
     * @return string
     */
    protected function getResourceKey(): string
    {
        return 'task_run';
    }

    /**
     * @return string
     */
    protected function getMenuKey(): string
    {
        return 'task_run';
    }

    /**
     * @return string
     */
    protected function getObjectName(): string
    {
        return 'Run';
    }

    /**
     * @return string|null
     */
    protected function getObjectField(): ?string
    {
        return 'run_id';
    }

    /**
     * @return string
     */
    protected function getTitle(): string
    {
        return __('Tasks');
    }

    /**
     * @return bool
     */
    protected function allowAdd(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    protected function allowEdit(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    protected function allowView(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    protected function allowDelete(): bool
    {
        return false;
    }

    /**
     * @return string
     */
    protected function getEditUrlRoute(): string
    {
        return '*/*/view';
    }
}
