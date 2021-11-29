<?php

namespace SlaTimeTracking;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ColumnViewIssuePage
 *
 * @author ermolaev
 */
class ColumnViewIssuePage extends \MantisColumn {

    public function __construct() {

        $this->sortable = TRUE;

        $this->title = plugin_lang_get('slaCounter');

        $this->column = 'sla_time';
    }

    public function display( \BugData $p_bug, $p_columns_target ) {
        plugin_push_current( 'SlaTimeTracking' );
        $table = plugin_table( 'time_tracking', 'SlaTimeTracking');

        $t_query = "SELECT * FROM {$table} WHERE bug_id=" . db_param();
        $t_result = db_query( $t_query, array( $p_bug->id ) );

        if( db_affected_rows() > 0 ) {
            $t_row = db_fetch_array( $t_result );
            $slaTime = $t_row['sla_time'];
            if ($t_row['status'] === 'active') {
                $slaTime += strtotime(date("Y-m-d G:i:s")) - strtotime($t_row['start_date']);
            }

            echo sprintf('%02d:%02d:%02d', ($slaTime/ 3600),($slaTime/ 60 % 60), $slaTime% 60);
        } else {
            echo 0;
        }
        plugin_pop_current();
    }

    public function sortquery( $p_direction ) {
        plugin_push_current( 'tracking_t' );

        $t_bug_table    = db_get_table( 'mantis_bug_table' );
        $t_relationship_table = plugin_table( 'tracking_time' );

        return array(
            'join'  => "LEFT JOIN $t_relationship_table relationship ON $t_bug_table.id=relationship.bug_id",
            'order' => "relationship.event_id $p_direction",
        );
    }

}