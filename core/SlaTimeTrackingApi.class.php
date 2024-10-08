<?php
namespace SlaTimeTracking;

class SlaTimeTrackingApi {

    function insertSlaTimeTracking($bug_id) {
        $table = plugin_table( 'time_tracking' );

        $t_db_param = array(db_param());
        $t_sql_param = array($bug_id);
        $t_db_param[] = db_param();
        $t_sql_param[] = date("Y-m-d G:i:s");

        $t_db_param = implode(',',$t_db_param);


        $t_query = " INSERT INTO {$table}
                     (bug_id, start_date)
                     VALUES( {$t_db_param} )";
        db_query($t_query, $t_sql_param);
    }

    function updateSlaTimeTracking($bug_id, $fields) {
        $table = plugin_table( 'time_tracking' );

        foreach($fields as $key => $value) {
            $t_db_param[] = $key . '=' . db_param();
            $t_sql_param[] = $value;
        }
        $t_sql_param[] = $bug_id;

        $t_db_param = implode(',',$t_db_param);

        $t_query = " UPDATE {$table}
                     SET {$t_db_param} 
                     WHERE bug_id = " . db_param();

        db_query($t_query, $t_sql_param);
    }

    function removeSlaTimeTracking($bug_id) {
        $t_debug = '/* ' . __METHOD__ . ' */ ';

        $table = plugin_table('time_tracking');
        $t_query = " $t_debug DELETE FROM {$table} 
                     WHERE bug_id=" . db_param();

        $t_sql_param = array($bug_id);
        db_query($t_query, $t_sql_param);
    }
}