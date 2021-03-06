<?php

/**
 * Class pocketlistsRecapCli
 */
class pocketlistsRecapCli extends waCliController
{
    /**
     * @param null $params
     *
     * @throws waDbException
     * @throws waException
     */
    public function run($params = null)
    {
        $test = waRequest::param('test', false, waRequest::TYPE_INT);

        $time = time();
        $asp = new waAppSettingsModel();
        $asp->set('pocketlists', 'last_recap_cron_time', $time);

        pl2()->getModel(pocketlistsItem::class)->updateCalcPriority();

        (new pocketlistsNotificationDailyRecap())->notify(array(), $test);
    }
}
