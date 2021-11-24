<?php

class SlaTimeTrackingPlugin extends MantisPlugin
{
    var $slaTimeTrackingApi;

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

    function init() {
        plugin_require_api('core/SlaTimeTrackingApi.class.php');

        $this->slaTimeTrackingApi = new SlaTimeTracking\SlaTimeTrackingApi();
    }

    function hooks() {
        return array(
            'EVENT_MENU_MAIN' => 'menu',
            'EVENT_BUG_DELETED' => 'deleteBug',
            'EVENT_REPORT_BUG' => 'createBug',
            'EVENT_UPDATE_BUG' => 'updateBug'
        );
    }

    function createBug($p_event, $p_created_bug) {
        $table = plugin_table( 'time_tracking' );
        db_param_push();
        $t_query = " SELECT bug_id 
                     FROM {$table} WHERE bug_id=" . db_param();
        db_query( $t_query, array( $p_created_bug->id ) );

        if( db_affected_rows() == 0 ) {
            if ($p_created_bug->status === 10) {
                $this->slaTimeTrackingApi->insertSlaTimeTracking($p_created_bug->id);
            }
        }
    }

    function updateBug($p_event, $p_original_bug, $p_updated_bug) {

        $table = plugin_table( 'time_tracking' );
        db_param_push();
        $t_query = " SELECT * 
                     FROM {$table} WHERE bug_id=" . db_param();
        $t_result = db_query( $t_query, array( $p_updated_bug->id ) );

        if( db_affected_rows() > 0 ) {
            //spis statusow
            // 10 -> nowy
            //20 -> zwrocony
            //30 -> uznany
            //40 -> potwierdzony
            //50 -> przypisany
            //60 -> zawieszony
            // 80 -> rozwiazany
            // 90 -> zamkniety

            $t_row = db_fetch_array( $t_result );

            //zmiana na status rozwiazany lub zakonczony
            if (($p_original_bug->status !== 80 && $p_updated_bug->status === 80) || ($p_original_bug->status !== 90 && $p_updated_bug->status === 90)) {
                //jesli zmiana nastapila ze statusu wstrzymany
                if ($p_original_bug->status === 60) {
                    $fields = [
                        'status' => 'closed'
                    ];
                } else {
                    $fields = [
                        'end_date' => date("Y-m-d G:i:s"),
                        'sla_time' => $t_row['sla_time'] + strtotime(date("Y-m-d G:i:s")) - strtotime($t_row['start_date']),
                        'status' => 'closed'
                    ];
                }
            } elseif ($p_original_bug->status === 60 && $p_updated_bug->status !== 60) {
                $fields = [
                    'start_date' => date("Y-m-d G:i:s"),
                    'status' => 'active'
                ];
            }

            //status zadania zmienia sie na wstrzymany = 60 zatrzymujemy liczenie czasu
            if ($p_original_bug->status !== 60 && $p_updated_bug->status === 60) {
                $fields = [
                    'end_date' => date("Y-m-d G:i:s"),
                    'sla_time' => $t_row['sla_time'] + (strtotime(date("Y-m-d G:i:s")) - strtotime($t_row['start_date'])),
                    'status' => 'suspended'
                ];
            }

            //zmiana statusu z zakonczony lub rozwiazany nie na rozwiazany/zakonczony/wstrzymany
            if (($p_original_bug->status === 80 || $p_original_bug->status === 90) && (!in_array($p_updated_bug->status, array(60,80,90)))) {
                $fields = [
                    'start_date' => date("Y-m-d G:i:s"),
                    'status' => 'active'
                ];
            }


            //zmiana z statusow zakonczony/rozwiazany

            if (isset($fields)) {
                $this->slaTimeTrackingApi->updateSlaTimeTracking($p_updated_bug->id, $fields);
            }
        }

    }

    function deleteBug($p_event, $p_bug_id) {

        $t_debug = '/* ' . __METHOD__ . ' */ ';

        $table = plugin_table( 'time_tracking' );
        $t_query = " $t_debug DELETE FROM {$table} 
                     WHERE bug_id=" . db_param();

        $t_sql_param = array($p_bug_id);
        db_query($t_query,$t_sql_param);
    }

    function schema() {
        $t_schema = array();

        $t_table = plugin_table( 'time_tracking' );
        $t_ddl = " id  I   NOTNULL UNSIGNED PRIMARY AUTOINCREMENT,
                 bug_id I DEFAULT NULL UNSIGNED,
                 start_date T DEFAULT NULL,
                 end_date T DEFAULT NULL,
                 sla_time I DEFAULT 0,
                 status C(16) NOTNULL DEFAULT \" 'active' \"";

        $t_schema[] = array( 'CreateTableSQL',
            array($t_table , $t_ddl) );

        $t_schema[] = array( 'CreateIndexSQL',
            array( 'idx_bug_id_sla_time_tracking',
                $t_table,
                'bug_id', array( 'UNIQUE' ) ) );

        return $t_schema;
    }

}