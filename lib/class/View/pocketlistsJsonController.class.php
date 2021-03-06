<?php

/**
 * Class pocketlistsViewAction
 */
class pocketlistsJsonController extends waJsonController
{
    use pocketlistsViewTrait;

    /**
     * @param null $params
     */
    public function run($params = null)
    {
        try {
            $this->preExecute();
            $this->execute();
            $this->afterExecute();
        } catch (waException $ex) {
            $this->errors = $ex->getMessage();
        }

        $this->display();
    }
}
