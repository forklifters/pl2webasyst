<?php

/**
 * Class pocketlistsUser
 */
class pocketlistsUser extends pocketlistsContact
{
    /**
     * @var pocketlistsUserSettings
     */
    private $settings;

    /**
     * @return pocketlistsUserSettings
     */
    public function getSettings()
    {
        if ($this->settings === null) {
            $this->settings = new pocketlistsUserSettings();
        }

        return $this->settings;
    }

    /**
     * @param pocketlistsUserSettings $settings
     *
     * @return pocketlistsUser
     */
    public function setSettings(pocketlistsUserSettings $settings)
    {
        $this->settings = $settings;

        return $this;
    }

    /**
     * @return int
     * @throws waException
     */
    public function hasLinkedApps()
    {
        /** @var pocketlistsAppLinkInterface[] $apps */
        $apps = pl2()->getLinkedApp();

        if (!$apps) {
            return 0;
        }

        /** @var pocketlistsAppLinkInterface $app */
        foreach ($apps as $app) {
            if ($app->userCanAccess($this)) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * @return int|null
     * @throws waException
     */
    public function getAppCount()
    {
        $icon = $this->getSettings()->appIcon();

        /** @var pocketlistsItemModel $itemModel */
        $itemModel = pl2()->getModel(pocketlistsItem::class);

        $count = 0;
        switch ($icon) {
            case pocketlistsUserSettings::ICON_OVERDUE: // overdue
                $count = $itemModel->countTodo(
                    $this->getContact()->getId(),
                    [],
                    [
                        pocketlistsItem::PRIORITY_RED,
                        pocketlistsItem::PRIORITY_BLACK,
                        pocketlistsItem::PRIORITY_BURNINHELL,
                    ],
                    pocketlistsItem::STATUS_UNDONE
                );

                break;

            case pocketlistsUserSettings::ICON_OVERDUE_TODAY: // overdue + today
                $count = $itemModel->countTodo(
                    $this->getContact()->getId(),
                    [],
                    [
                        pocketlistsItem::PRIORITY_YELLOW,
                        pocketlistsItem::PRIORITY_RED,
                        pocketlistsItem::PRIORITY_BLACK,
                        pocketlistsItem::PRIORITY_BURNINHELL,
                    ],
                    pocketlistsItem::STATUS_UNDONE
                );

                break;

            case pocketlistsUserSettings::ICON_OVERDUE_TODAY_AND_TOMORROW: // overdue + today + tomorrow
                $count = $itemModel->countTodo(
                    $this->getContact()->getId(),
                    [],
                    [
                        pocketlistsItem::PRIORITY_GREEN,
                        pocketlistsItem::PRIORITY_YELLOW,
                        pocketlistsItem::PRIORITY_RED,
                        pocketlistsItem::PRIORITY_BLACK,
                        pocketlistsItem::PRIORITY_BURNINHELL,
                    ],
                    pocketlistsItem::STATUS_UNDONE
                );

                break;
            case pocketlistsUserSettings::ICON_ALL:
                $count = $itemModel->countTodo(
                    $this->getContact()->getId(),
                    [],
                    [
                        pocketlistsItem::PRIORITY_NORM,
                        pocketlistsItem::PRIORITY_GREEN,
                        pocketlistsItem::PRIORITY_YELLOW,
                        pocketlistsItem::PRIORITY_RED,
                        pocketlistsItem::PRIORITY_BLACK,
                        pocketlistsItem::PRIORITY_BURNINHELL,
                    ],
                    pocketlistsItem::STATUS_UNDONE
                );

                break;
        }

        return $count ?: null;
    }
}
