<?php

class SlaTimeTrackingPlugin extends MantisPlugin
{
    var $slaTimeTrackingApi;

    function register()
    {
        $this->name = 'SlaTimeTracking';
        $this->description = 'Plugin to time tracking sla';
        $this->page = '';
        $this->version = '1.0.0';
        $this->requires = array( 'MantisCore' => '2.0.0' );
        $this->author = 'michal@go2ecommerce.pl';
        $this->contact = '';
        $this->url = 'https://agencja-ecommerce.pl';
    }

    function init() {
        plugin_require_api('core/SlaTimeTrackingApi.class.php');
        plugin_require_api('core/ColumnViewIssuePage.class.php');

        $this->slaTimeTrackingApi = new SlaTimeTracking\SlaTimeTrackingApi();
    }

    function hooks() {
        return array(
            'EVENT_MENU_MAIN' => 'menu',
            'EVENT_BUG_DELETED' => 'deleteBug',
            'EVENT_REPORT_BUG' => 'createBug',
            'EVENT_FILTER_COLUMNS' => 'column_add_in_view_all_bug_page',
            'EVENT_VIEW_BUG_DETAILS' => 'viewBug',
            'EVENT_UPDATE_BUG' => 'updateBug'
        );
    }

    function column_add_in_view_all_bug_page( $p_type_event, $p_param ){
        $t_column = new \SlaTimeTracking\ColumnViewIssuePage();

        return array( $t_column );
    }

    /**
     * Show timecard and estimate information when viewing bugs.
     * @param string Event name
     * @param int Bug ID
     */
    function viewBug( $p_event, $p_bug_id ) {
        $table = plugin_table( 'time_tracking', 'SlaTimeTracking');
        $t_query = "SELECT * FROM {$table} WHERE bug_id=" . db_param();
        $t_result = db_query( $t_query, array( $p_bug_id) );
        $slaTime = 0;
        if( db_affected_rows() > 0 ) {
            $t_row = db_fetch_array($t_result);
            $slaTime = $t_row['sla_time'];
            if ($t_row['status'] === 'active') {
                $slaTime += strtotime(date("Y-m-d G:i:s")) - strtotime($t_row['start_date']);
            }
        }
        $slaTimeText = sprintf('%02d:%02d:%02d', ($slaTime/ 3600), ($slaTime/ 60 % 60), $slaTime % 60);

        echo '<tr ', helper_alternate_class(), '>';
        echo '<th class="bug-slatime category">' . plugin_lang_get('slaCounter') . '</th><td>' . $slaTimeText . '</td>';
        echo '</tr>';
    }


    function createBug($p_event, $p_created_bug) {
        $table = plugin_table( 'time_tracking' );
        db_param_push();
        $t_query = " SELECT bug_id 
                     FROM {$table} WHERE bug_id=" . db_param();
        db_query( $t_query, array( $p_created_bug->id ) );

        if( db_affected_rows() == 0 ) {
            $t_category_id = bug_get_field( $p_created_bug->id, 'category_id' );
            //przy tworzeniu sla wpisu sprawdzamy czy status rowna sie nowy i czy kategoria jest inna niz konserwacja lub przeglad
            if ($p_created_bug->status === 10 && !in_array($t_category_id, array(60,80))) {
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
            //jesli pole przyczyna ma wartosc Niezasadne to zawieszamy liczenie sla o ile byl taki wpis
            if ($reasonFieldValue === 'Niezasadne') {
                $fields = [
                    'end_date' => date("Y-m-d G:i:s"),
                    'sla_time' => $t_row['sla_time'] + (strtotime(date("Y-m-d G:i:s")) - strtotime($t_row['start_date'])),
                    'status' => 'suspended'
                ];
            } else {
            $t_row = db_fetch_array( $t_result );

            //status poprzedni inny niz rozwiązany zmieniony na rozwiązany
            //status poprzedni inny niz zamkniety zmieniony na zamkniety
            if (($p_original_bug->status !== 80 && $p_updated_bug->status === 80) || ($p_original_bug->status !== 90 && $p_updated_bug->status === 90)) {
                //jesli status poprzedni jeden z (wstrzymany,rozwiazany, zakmniety)
                if (in_array($p_original_bug->status, array(60,80,90))) {
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
            //do powyzszego: jesli status zmienia sie ze wstrzymanego na jakikolwiek inny niz wstrzymany a byl wczesniej wstrzymany

            //jesli status zmienia sie na wstrzymany
            if ($p_original_bug->status !== 60 && $p_updated_bug->status === 60) {
                $fields = [
                    'end_date' => date("Y-m-d G:i:s"),
                    'sla_time' => $t_row['sla_time'] + (strtotime(date("Y-m-d G:i:s")) - strtotime($t_row['start_date'])),
                    'status' => 'suspended'
                ];
            }

            //jesli status rozwiazany i zamkniety zmienia sie na inny niz (wstrzymany, rozwiazany, zamkniety)
            if (($p_original_bug->status === 80 || $p_original_bug->status === 90) && (!in_array($p_updated_bug->status, array(60,80,90)))) {
                $fields = [
                    'start_date' => date("Y-m-d G:i:s"),
                    'status' => 'active'
                ];
            }
        }

            if (isset($fields)) {
                $this->slaTimeTrackingApi->updateSlaTimeTracking($p_updated_bug->id, $fields);
            }
        } else {
            //jesli nie ma jeszcze sla trackingu a aktualizujemy bug pytanie jakie dac tu warunki bo status nowy pewnie juz istniec nie bedzie
            $t_category_id = bug_get_field( $p_updated_bug->id, 'category_id' );
            if ($p_updated_bug->status === 10 && !in_array($t_category_id, array(60,80))) {
                $this->slaTimeTrackingApi->insertSlaTimeTracking($p_updated_bug->id);
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
