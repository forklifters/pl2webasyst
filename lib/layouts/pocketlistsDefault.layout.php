<?php

class pocketlistsDefaultLayout extends waLayout
{
    public function execute()
    {
        $us = new pocketlistsUserSettings();
        if (!$us->appIcon()) {
            $us->saveDefaults();
        }
    }
}