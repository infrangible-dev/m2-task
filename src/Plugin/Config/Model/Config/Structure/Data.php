<?php

declare(strict_types=1);

namespace Infrangible\Task\Plugin\Config\Model\Config\Structure;

use FeWeDev\Base\Arrays;
use FeWeDev\Base\Variables;
use Infrangible\Task\Model\Config\Source\LogLevel;
use Magento\Config\Model\Config\Source\Email\Identity;
use Magento\Config\Model\Config\Source\Yesno;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Data
{
    /** @var Arrays */
    protected $arrays;

    /** @var Variables */
    protected $variables;

    public function __construct(Arrays $arrays, Variables $variables)
    {
        $this->arrays = $arrays;
        $this->variables = $variables;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function beforeMerge(\Magento\Config\Model\Config\Structure\Data $object, array $config): array
    {
        $sections = $this->arrays->getValue($config, 'config:system:sections');

        if (!$sections) {
            return [$config];
        }

        foreach ($sections as $sectionName => $sectionData) {
            $tab = $this->arrays->getValue($sectionData, 'tab');

            if ($tab === 'tasks') {
                $sectionData = array_replace_recursive(
                    $sectionData,
                    $this->getTaskSection($sectionName, $sectionName === 'task_general')
                );

                $groupsData = $this->arrays->getValue($sectionData, 'children', []);

                foreach ($groupsData as $groupsKey => $groupData) {
                    $fieldsData = $this->arrays->getValue($groupData, 'children', []);

                    usort($fieldsData, function (array $fieldData1, $fieldData2) {
                        $sortOrder1 = $this->arrays->getValue($fieldData1, 'sortOrder', 0);
                        $sortOrder2 = $this->arrays->getValue($fieldData2, 'sortOrder', 0);

                        return $sortOrder1 > $sortOrder2 ? 1 : ($sortOrder1 < $sortOrder2 ? -1 : 0);
                    });

                    $groupsData[$groupsKey]['children'] = $fieldsData;
                }

                $sectionData['children'] = $groupsData;

                $config = $this->arrays->addDeepValue(
                    $config,
                    ['config', 'system', 'sections', $sectionName],
                    $sectionData,
                    true,
                    true
                );
            }
        }

        return [$config];
    }

    public function getTaskSection(string $taskName, bool $isGeneralTask): array
    {
        $groupsData = [
            'settings'        => [
                'label'  => 'Settings',
                'fields' => [
                    'max_memory'           => ['label' => 'Max Memory', 'comment' => 'In MB', 'sortOrder' => 10],
                    'wait_for_predecessor' => [
                        'type'      => 'select',
                        'label'     => 'Wait for Predecessor',
                        'source'    => Yesno::class,
                        'sortOrder' => 21
                    ],
                    'suppress_empty_mails' => [
                        'type'      => 'select',
                        'label'     => 'Suppress Empty Mails',
                        'source'    => Yesno::class,
                        'sortOrder' => 30
                    ]
                ]
            ],
            'logging'         => [
                'label'  => 'Logging',
                'fields' => [
                    'log_level'         => [
                        'type'   => 'select',
                        'label'  => 'Log Level',
                        'source' => LogLevel::class
                    ],
                    'log_warn_as_error' => [
                        'type'   => 'select',
                        'label'  => 'Log Warning as Error',
                        'source' => Yesno::class
                    ]
                ]
            ],
            'summary_success' => [
                'label'  => 'Success Summary',
                'fields' => [
                    'send'                  => [
                        'type'   => 'select',
                        'label'  => 'Send',
                        'source' => Yesno::class
                    ],
                    'sender'                => [
                        'type'    => 'select',
                        'label'   => 'Sender',
                        'source'  => Identity::class,
                        'depends' => ['send' => '1']
                    ],
                    'recipients'            => [
                        'label'   => 'Recipients',
                        'comment' => 'Divided by semicolon',
                        'depends' => ['send' => '1']
                    ],
                    'copy_recipients'       => [
                        'label'   => 'Copy Recipients',
                        'comment' => 'Divided by semicolon',
                        'depends' => ['send' => '1']
                    ],
                    'blind_copy_recipients' => [
                        'label'   => 'Blind Copy Recipients',
                        'comment' => 'Divided by semicolon',
                        'depends' => ['send' => '1']
                    ],
                    'subject'               => [
                        'label'   => 'Subject',
                        'depends' => ['send' => '1']
                    ]
                ]
            ],
            'summary_error'   => [
                'label'  => 'Error Summary',
                'fields' => [
                    'send'                  => [
                        'type'   => 'select',
                        'label'  => 'Send',
                        'source' => Yesno::class
                    ],
                    'sender'                => [
                        'type'    => 'select',
                        'label'   => 'Sender',
                        'source'  => Identity::class,
                        'depends' => ['send' => '1']
                    ],
                    'recipients'            => [
                        'label'   => 'Recipients',
                        'comment' => 'Divided by semicolon',
                        'depends' => ['send' => '1']
                    ],
                    'copy_recipients'       => [
                        'label'   => 'Copy Recipients',
                        'comment' => 'Divided by semicolon',
                        'depends' => ['send' => '1']
                    ],
                    'blind_copy_recipients' => [
                        'label'   => 'Blind Copy Recipients',
                        'comment' => 'Divided by semicolon',
                        'depends' => ['send' => '1']
                    ],
                    'subject'               => [
                        'label'   => 'Subject',
                        'depends' => ['send' => '1']
                    ]
                ]
            ]
        ];

        if (!$isGeneralTask) {
            $groupsData['settings']['fields']['depends_on'] = [
                'label'     => 'Depends On',
                'comment'   => 'Other tasks divided by semicolon',
                'sortOrder' => 20
            ];

            foreach (['settings', 'logging', 'summary_success', 'summary_error'] as $groupName) {
                $groupsData[$groupName]['fields']['overwrite_task_general'] = [
                    'type'      => 'select',
                    'label'     => 'Overwrite Task General',
                    'source'    => Yesno::class,
                    'sortOrder' => 5
                ];

                if ($groupName === 'settings') {
                    foreach (['max_memory', 'wait_for_predecessor', 'suppress_empty_mails'] as $fieldName) {
                        $groupsData[$groupName]['fields'][$fieldName]['depends'] = ['overwrite_task_general' => '1'];
                    }
                }

                if ($groupName === 'logging') {
                    foreach (['log_level', 'log_warn_as_error'] as $fieldName) {
                        $groupsData[$groupName]['fields'][$fieldName]['depends'] = ['overwrite_task_general' => '1'];
                    }
                }

                if ($groupName === 'summary_success' || $groupName === 'summary_error') {
                    foreach (['send', 'sender', 'recipients', 'copy_recipients', 'blind_copy_recipients', 'subject'] as
                        $fieldName) {
                        $groupsData[$groupName]['fields'][$fieldName]['depends'] = ['overwrite_task_general' => '1'];
                    }
                }
            }
        }

        $groups = [];

        foreach ($groupsData as $groupName => $groupData) {
            $groupLabel = $this->arrays->getValue($groupData, 'label', '');
            $groupFields = $this->arrays->getValue($groupData, 'fields');

            $groups[$groupName] =
                $this->getTaskSectionGroup($taskName, $groupName, $groupLabel, count($groups) + 10, $groupFields);
        }

        return [
            'resource'      => 'Infrangible_Task::config_infrangible_task',
            'translate'     => 'label',
            'showInDefault' => 1,
            'showInWebsite' => 1,
            'showInStore'   => 1,
            'children'      => $groups
        ];
    }

    public function getTaskSectionGroup(
        string $taskName,
        string $groupName,
        string $label,
        int $sortOrder,
        array $fieldsData
    ): array {
        $fields = [];

        foreach ($fieldsData as $fieldName => $fieldData) {
            $fieldType = $this->arrays->getValue($fieldData, 'type', 'text');
            $fieldLabel = $this->arrays->getValue($fieldData, 'label', '');
            $fieldComment = $this->arrays->getValue($fieldData, 'comment', '');
            $fieldSourceModel = $this->arrays->getValue($fieldData, 'source', '');
            $fieldDepends = $this->arrays->getValue($fieldData, 'depends', []);
            $fieldSortOrder = $this->arrays->getValue($fieldData, 'sortOrder', count($fields) + 10);

            $fields[$fieldName] = $this->getTaskSectionField(
                $taskName,
                $groupName,
                $fieldName,
                $fieldType,
                $fieldLabel,
                $fieldComment,
                $fieldSourceModel,
                $fieldDepends,
                $fieldSortOrder
            );
        }

        return [
            '_elementType'  => 'group',
            'type'          => 'text',
            'id'            => $groupName,
            'path'          => $taskName,
            'translate'     => 'label',
            'label'         => $label,
            'sortOrder'     => $sortOrder,
            'showInDefault' => 1,
            'showInWebsite' => 1,
            'showInStore'   => 1,
            'children'      => $fields
        ];
    }

    public function getTaskSectionField(
        string $taskName,
        string $groupName,
        string $fieldName,
        string $type,
        string $label,
        string $comment,
        string $sourceClass,
        array $depends,
        int $sortOrder
    ): array {
        $fieldData = [
            '_elementType'  => 'field',
            'id'            => $fieldName,
            'path'          => sprintf('%s/%s', $taskName, $groupName),
            'type'          => $type,
            'translate'     => 'label comment',
            'label'         => $label,
            'sortOrder'     => $sortOrder,
            'showInDefault' => 1,
            'showInWebsite' => 1,
            'showInStore'   => 1
        ];

        if (!$this->variables->isEmpty($comment)) {
            $fieldData['comment'] = $comment;
        }

        if (!$this->variables->isEmpty($sourceClass)) {
            $fieldData['source_model'] = $sourceClass;
        }

        if (!$this->variables->isEmpty($depends)) {
            foreach ($depends as $dependFieldName => $dependFieldValue) {
                $fieldData['depends']['fields'][$dependFieldName] = [
                    '_elementType' => 'field',
                    'id'           => sprintf('%s/%s/%s', $taskName, $groupName, $dependFieldName),
                    'value'        => $dependFieldValue,
                    'dependPath'   => [
                        $taskName,
                        $groupName,
                        $dependFieldName
                    ]
                ];
            }
        }

        return $fieldData;
    }
}