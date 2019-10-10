<?php

/**
 * Class pocketlistsProPluginCreateItemAction
 */
class pocketlistsProPluginCreateItemAction implements pocketlistsProPluginAutomationActionInterface
{
    const DUE_PERIOD_MIN  = 'minutes';
    const DUE_PERIOD_HOUR = 'hours';
    const DUE_PERIOD_DAY  = 'days';

    const IDENTIFIER = 'create_item';

    const ORDER_ACTION_PERFORMER_ID = -100500;

    /**
     * @var string
     */
    protected $name = '';

    /**
     * @var string
     */
    protected $note = '';

    /**
     * @var int
     */
    protected $assignedTo = 0;

    /**
     * @var pocketlistsContact
     */
    protected $assignContact;

    /**
     * @var int
     */
    protected $priority = 0;

    /**
     * @var int
     */
    protected $dueIn = 0;

    /**
     * @var string
     */
    protected $duePeriod = self::DUE_PERIOD_DAY;

    /**
     * @var pocketlistsList|null
     */
    protected $list;

    /**
     * pocketlistsProPluginCreateItemAction constructor.
     */
    public function __construct()
    {
        $this->list = new pocketlistsNullList();
    }

    /**
     * @return string
     */
    public function getIdentifier()
    {
        return self::IDENTIFIER;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @link  https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return [
            'identifier'  => $this->getIdentifier(),
            'name'        => $this->name,
            'note'        => $this->note,
            'assigned_to' => (int)$this->assignedTo,
            'priority'    => (int)$this->priority,
            'due_in'      => (int)$this->dueIn,
            'due_period'  => $this->duePeriod,
            'list'        => $this->list ? (int)$this->list->getId() : null,
        ];
    }

    /**
     * @param shopOrder $order
     *
     * @return mixed
     * @throws waException
     */
    public function execute($order)
    {
        /** @var pocketlistsItemFactory $factory */
        $factory = pl2()->getEntityFactory(pocketlistsItem::class);

        $this->name = $this->replaceVars($this->name, $order);

        $currentUserId = wa()->getUser()->getId();
        if ($this->assignedTo == self::ORDER_ACTION_PERFORMER_ID && $currentUserId) {
            $assigned = $currentUserId;
        } elseif ($this->assignedTo > 0) {
            $assigned = $this->assignedTo;
        } else {
            $assigned = null;
        }

        /** @var pocketlistsItem $item */
        $item = $factory->createNew();
        $item
            ->setContactId(pocketlistsBot::PL2BOT_ID)
            ->setName($this->name)
            ->setNote($this->note ?: null)
            ->setPriority($this->priority)
            ->setAssignedContactId($assigned)
            ->setList($this->list)
            ->setCreateDatetime(date('Y-m-d H:i:s'));

        if ($this->dueIn) {
            if ($this->duePeriod === self::DUE_PERIOD_DAY) {
                $due = (new DateTime())
                    ->modify(sprintf('%s %s', $this->dueIn, $this->duePeriod))
                    ->format('Y-m-d 00:00:00');
                $item->setDueDate($due);
            } else {
                $due = (new DateTime())
                    ->modify(sprintf('%s %s', $this->dueIn, $this->duePeriod))
                    ->format('Y-m-d H:i:s');
                $item->setDueDatetime($due);
            }
        }

        if (pl2()->getEntityFactory(pocketlistsItem::class)->insert($item)) {
            pl2()->getEntityFactory(pocketlistsItemLink::class)->createFromDataForItem(
                $item,
                [
                    'app'         => pocketlistsAppLinkShop::APP,
                    'entity_type' => pocketlistsAppLinkShop::TYPE_ORDER,
                    'entity_id'   => $order->getId(),
                ]
            );

            pl2()->getEventDispatcher()->dispatch(
                new pocketlistsEventItemsSave(
                    pocketlistsEventStorage::ITEM_INSERT,
                    $item,
                    ['list' => $this->list, 'assign_contact' => $this->assignContact]
                )
            );

            pocketlistsLogger::debug(
                sprintf('item %d created from automation action %s', $item->getId(), $this->getIdentifier())
            );

            return true;
        }

        pocketlistsLogger::debug(
            sprintf('item %d failed to create from automation action %s', $item->getId(), $this->getIdentifier())
        );

        return false;
    }

    /**
     * @return string
     * @throws waException
     */
    public function viewHtml()
    {
        $view = wa()->getView();

        $assign = 0;
        if ($this->assignedTo > 0) {
            $assign = pl2()->getEntityFactory(pocketlistsContact::class)->createNewWithId($this->assignedTo)->getName();
        } elseif ($this->assignedTo == self::ORDER_ACTION_PERFORMER_ID) {
            $assign = _wp('Order action performer');
        }

        $view->assign([
            'assign' => $assign,
            'action' => $this,
            'due' => !empty($this->dueIn) ? sprintf_wp('%d %s', $this->dueIn, $this->duePeriod) : 0,
        ]);

        return $view->fetch(
            wa()->getAppPath(
                '/plugins/pro/templates/actions/automation/actions/createItemView.html',
                pocketlistsHelper::APP_ID
            )
        );
    }

    /**
     * @return string
     * @throws waException
     */
    public function editHtml()
    {
        $view = wa()->getView();

        /** @var pocketlistsListFactory $factory */
        $factory = pl2()->getEntityFactory(pocketlistsList::class);
        $users = [];
        $app = pl2()->getLinkedApp('shop');
        foreach (pocketlistsRBAC::getAccessContacts() as $userId) {
            $user = pl2()->getEntityFactory(pocketlistsContact::class)->createNewWithId($userId);
            if ($app->userCanAccess($user)) {
                $users[$userId] = $user;
            }
        }

        $view->assign(
            [
                'action'     => $this,
                'lists'      => $factory->findLists(),
                'users'      => $users,
                'performer'  => pl2()->getUser(),
                'duePeriods' => [
                    self::DUE_PERIOD_MIN  => _wp('Minutes'),
                    self::DUE_PERIOD_HOUR => _wp('Hours'),
                    self::DUE_PERIOD_DAY  => _wp('Days'),
                ],
            ]
        );

        return $view->fetch(
            wa()->getAppPath(
                '/plugins/pro/templates/actions/automation/actions/createItemEdit.html',
                pocketlistsHelper::APP_ID
            )
        );
    }

    /**
     * @param array $json
     *
     * @return pocketlistsProPluginCreateItemAction
     * @throws waException
     */
    public function load(array $json)
    {
        $this->name = ifset($json['name'], '');
        $this->note = ifset($json['note'], '');
        $this->assignedTo = (int)ifset($json['assigned_to'], 0);
        $this->list = !empty($json['list'])
            ? pl2()->getEntityFactory(pocketlistsList::class)->findById($json['list'])
            : null;
        $this->dueIn = (int)ifset($json['due_in'], 0);
        $this->duePeriod = ifset($json['due_period'], self::DUE_PERIOD_MIN);
        $this->priority = (int)ifset($json['priority'], pocketlistsItem::PRIORITY_NORM);

        if (!$this->list instanceof pocketlistsList) {
            $this->list = new pocketlistsNullList();
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getAssignedTo()
    {
        return $this->assignedTo;
    }

    /**
     * @return pocketlistsContact
     */
    public function getAssignContact()
    {
        return $this->assignContact;
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @return int
     */
    public function getDueIn()
    {
        return $this->dueIn;
    }

    /**
     * @return string
     */
    public function getDuePeriod()
    {
        return $this->duePeriod;
    }

    /**
     * @return pocketlistsList|null
     */
    public function getList()
    {
        return $this->list;
    }

    /**
     * @return string
     */
    public function getNote()
    {
        return $this->note;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $json = json_encode($this, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return '';
        }

        return (string)$json;
    }

    /**
     * @param string    $str
     * @param shopOrder $order
     *
     * @return mixed
     */
    private function replaceVars($str, shopOrder $order)
    {
        $orderParams = $order->params;

        return str_replace(
            ['{$customer_name}', '{$tracking_number}'],
            [$order->contact->getName(), ifset($orderParams, 'tracking_number', _wp('No tracking number'))],
            $str
        );
    }
}