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

    function updateBug() {

    }

    function schema() {
        $t_schema = array();

        $t_table = plugin_table( 'time_tracking' );
        $t_ddl = " id  I   NOTNULL UNSIGNED PRIMARY AUTOINCREMENT,
                 bug_id I DEFAULT NULL UNSIGNED,
                 start_date T DEFAULT NULL,
                 end_date T DEFAULT NULL,
                 sla_time I DEFAULT 0,
                 suspended_start_date T DEFAULT NULL,
                 suspended_end_date T DEFAULT NULL,
                 suspended_time I DEFAULT 0";

        $t_schema[] = array( 'CreateTableSQL',
            array($t_table , $t_ddl) );

        $t_schema[] = array( 'CreateIndexSQL',
            array( 'idx_bug_id_sla_time_tracking',
                $t_table,
                'bug_id', array( 'UNIQUE' ) ) );

        return $t_schema;
    }

}