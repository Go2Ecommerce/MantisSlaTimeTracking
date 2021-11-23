<?php

class SlaTimeTracking extends MantisPlugin
{
    function register()
    {
        $this->name = 'SlaTimeTracking';
        $this->description = 'Plugin to time tracking sla';
        $this->page = 'config_page';
        $this->version = '1.0.0';
        $this->requires = array( 'MantisCore' => '2.0.0' );
        $this->author = 'michal@go2ecommerce.pl';
        $this->contact = '';
        $this->url = 'https://agencja-ecommerce.pl';
    }

    function hooks() {
        return array(
            'EVENT_MENU_MAIN' => 'menu',

            'EVENT_UPDATE_BUG' => 'updateBug'
        );
    }



}