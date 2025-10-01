<?php

class SlaTimeTrackingPlugin extends MantisPlugin
{
    var $slaTimeTrackingApi;

    function register()
    {
        $this->name = 'SlaTimeTracking';
        $this->description = 'Plugin to time tracking sla';
        $this->page = '';
        $this->version = '2.0.0';
        $this->requires = array('MantisCore' => '2.0.0');
        $this->author = 'michal@go2ecommerce.pl';
        $this->contact = '';
        $this->url = 'https://agencja-ecommerce.pl';
    }

    function init()
    {
        plugin_require_api('core/SlaTimeTrackingApi.class.php');
        plugin_require_api('core/ColumnViewIssuePage.class.php');

        $this->slaTimeTrackingApi = new SlaTimeTracking\SlaTimeTrackingApi();
    }

    function hooks()
    {
        return array(
            'EVENT_MENU_MAIN' => 'menu',
            'EVENT_BUG_DELETED' => 'deleteBug',
            'EVENT_REPORT_BUG' => 'createBug',
            'EVENT_FILTER_COLUMNS' => 'column_add_in_view_all_bug_page',
            'EVENT_VIEW_BUG_DETAILS' => 'viewBug',
            'EVENT_UPDATE_BUG' => 'updateBug',
            'EVENT_BUGNOTE_ADD_FORM' => 'addSuspendReasonField',
        );
    }

    function column_add_in_view_all_bug_page($p_type_event, $p_param)
    {
        $t_column = new \SlaTimeTracking\ColumnViewIssuePage();

        return array($t_column);
    }

    /**
     * Show timecard and estimate information when viewing bugs.
     * @param string Event name
     * @param int Bug ID
     */
    function viewBug($p_event, $p_bug_id)
    {
        $table = plugin_table('time_tracking', 'SlaTimeTracking');
        $t_query = "SELECT * FROM {$table} WHERE bug_id=" . db_param();
        $t_result = db_query($t_query, array($p_bug_id));
        $slaTime = 0;
        if (db_affected_rows() > 0) {
            $t_row = db_fetch_array($t_result);
            $slaTime = $t_row['sla_time'];
            if ($t_row['status'] === 'active') {
                date_default_timezone_set('Europe/Warsaw');
                $slaTime += strtotime(date("Y-m-d G:i:s")) - strtotime($t_row['start_date']);
            }
        }
        $slaTimeText = sprintf('%02d:%02d:%02d', ($slaTime / 3600), ($slaTime / 60 % 60), $slaTime % 60);

        echo '<tr ', helper_alternate_class(), '>';
        echo '<th class="bug-slatime category">' . plugin_lang_get('slaCounter') . '</th><td>' . $slaTimeText . '</td>';
        echo '</tr>';
    }


    function createBug($p_event, $p_created_bug)
    {
        $table = plugin_table('time_tracking');
        db_param_push();
        $t_query = " SELECT bug_id 
                     FROM {$table} WHERE bug_id=" . db_param();
        db_query($t_query, array($p_created_bug->id));

        if (db_affected_rows() == 0) {
            $t_category_name = category_get_field( $p_created_bug->category_id, 'name' );

            //przy tworzeniu sla wpisu sprawdzamy czy status rowna sie nowy i czy kategoria jest inna niz konserwacja lub przeglad
            if ($p_created_bug->status === 10 && !in_array($t_category_name, array('Przegląd', 'Konserwacja', 'Przegląd/Konserwacja'))) {
                $this->slaTimeTrackingApi->insertSlaTimeTracking($p_created_bug->id);
            }
        }
    }

    function updateBug($p_event, $p_original_bug, $p_updated_bug)
    {
        $custom_field_id_suspend = custom_field_get_id_from_name('Zawieszone ze względu na:');
        $custom_field_id_comment = custom_field_get_id_from_name('Komentarz dla PLK');

        if ($p_original_bug->status !== 60 && $p_updated_bug->status === 60) {
            if (custom_field_is_linked($custom_field_id_suspend, $p_updated_bug->project_id)) {
                $suspend_reason = gpc_get_string('suspend_reason', '');

                if (is_blank($suspend_reason)) {
                    form_security_purge('bug_update');
                    $t_url = 'bug_change_status_page.php?id=' . $p_updated_bug->id . '&new_status=60';
                    session_set('suspend_reason_error', true);
                    bug_set_field($p_original_bug->id, 'status', $p_original_bug->status);
                    print_header_redirect($t_url);
                    return;
                } else {
                    custom_field_set_value($custom_field_id_suspend, $p_updated_bug->id, $suspend_reason);
                }
            }
        } else {
            custom_field_set_value($custom_field_id_suspend, $p_updated_bug->id, null);
        }

        $table = plugin_table('time_tracking');
        db_param_push();
        $t_query = " SELECT * 
                     FROM {$table} WHERE bug_id=" . db_param();
        $t_result = db_query($t_query, array($p_updated_bug->id));
        $t_row = db_fetch_array($t_result);
        $u_category_name = category_get_field( $p_updated_bug->category_id, 'name' );

        date_default_timezone_set('Europe/Warsaw');

        if ($t_row) {
            //Logika zatrzymania SLA na podstawie pola "Komentarz dla PLK" – tylko gdy projekt ma pole "Zawieszone ze względu na:"
            if (custom_field_is_linked($custom_field_id_suspend, $p_updated_bug->project_id)) {
                $commentValue = custom_field_get_value($custom_field_id_comment, $p_updated_bug->id);
                if (!is_blank($commentValue)) {
                    if ($t_row['status'] !== 'suspended') {
                        $fields = [
                            'end_date' => date("Y-m-d G:i:s"),
                            'sla_time' => $t_row['sla_time'] + (strtotime(date("Y-m-d G:i:s")) - strtotime($t_row['start_date'])),
                            'status' => 'suspended'
                        ];
                    }
                }
            }

            $reasonFieldValue = custom_field_get_value(21, $p_updated_bug->id);
            //jesli pole przyczyna ma wartosc Niezasadne to zawieszamy liczenie sla o ile byl taki wpis
            if ($reasonFieldValue === 'Niezasadne') {
                if ($t_row['status'] !== 'suspended') {
                    $fields = [
                        'end_date' => date("Y-m-d G:i:s"),
                        'sla_time' => $t_row['sla_time'] + (strtotime(date("Y-m-d G:i:s")) - strtotime($t_row['start_date'])),
                        'status' => 'suspended'
                    ];
                }
            } else {
                //status poprzedni inny niz rozwiązany zmieniony na rozwiązany
                //status poprzedni inny niz zamkniety zmieniony na zamkniety
                if (($p_original_bug->status !== 80 && $p_updated_bug->status === 80) || ($p_original_bug->status !== 90 && $p_updated_bug->status === 90)) {
                    //jesli status poprzedni jeden z (wstrzymany,rozwiazany, zakmniety)
                    if (in_array($p_original_bug->status, array(60, 80, 90))) {
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

                //jesli status zmienia sie na wstrzymany a licznik nie byl wstrzymany
                if ($t_row['status'] !== 'suspended' && $p_original_bug->status !== 60 && $p_updated_bug->status === 60) {
                    $fields = [
                        'end_date' => date("Y-m-d G:i:s"),
                        'sla_time' => $t_row['sla_time'] + (strtotime(date("Y-m-d G:i:s")) - strtotime($t_row['start_date'])),
                        'status' => 'suspended'
                    ];
                }

                //jesli status rozwiazany i zamkniety zmienia sie na inny niz (wstrzymany, rozwiazany, zamkniety)
                if (($p_original_bug->status === 80 || $p_original_bug->status === 90) && (!in_array($p_updated_bug->status, array(60, 80, 90)))) {
                    $fields = [
                        'start_date' => date("Y-m-d G:i:s"),
                        'status' => 'active'
                    ];
                }
            }

            //jesli zmienione na ktoras z tych kategorii to zerujemy licznik
            if (in_array($u_category_name, array('Przegląd', 'Konserwacja', 'Przegląd/Konserwacja'))) {
                $this->slaTimeTrackingApi->removeSlaTimeTracking($p_updated_bug->id);
            }

            if (isset($fields)) {
                $this->slaTimeTrackingApi->updateSlaTimeTracking($p_updated_bug->id, $fields);
            }
        } else {
            $o_category_name = category_get_field( $p_original_bug->category_id, 'name' );
            //jesli nie ma jeszcze sla trackingu a kategoria zmieniana jest z przeglad/konserwacja na cos innego to uruchamiamy go
            if (in_array($o_category_name, array('Przegląd', 'Konserwacja', 'Przegląd/Konserwacja')) && !in_array($u_category_name, array('Przegląd', 'Konserwacja', 'Przegląd/Konserwacja'))) {
                $this->slaTimeTrackingApi->insertSlaTimeTracking($p_updated_bug->id);
            }
        }
        bug_clear_cache_all( $p_updated_bug->id );
    }

    function deleteBug($p_event, $p_bug_id)
    {

        $t_debug = '/* ' . __METHOD__ . ' */ ';

        $table = plugin_table('time_tracking');
        $t_query = " $t_debug DELETE FROM {$table} 
                     WHERE bug_id=" . db_param();

        $t_sql_param = array($p_bug_id);
        db_query($t_query, $t_sql_param);
    }

    function schema()
    {
        $t_schema = array();

        $t_table = plugin_table('time_tracking');
        $t_ddl = " id  I   NOTNULL UNSIGNED PRIMARY AUTOINCREMENT,
                 bug_id I DEFAULT NULL UNSIGNED,
                 start_date T DEFAULT NULL,
                 end_date T DEFAULT NULL,
                 sla_time I DEFAULT 0,
                 status C(16) NOTNULL DEFAULT \" 'active' \"";

        $t_schema[] = array('CreateTableSQL',
            array($t_table, $t_ddl));

        $t_schema[] = array('CreateIndexSQL',
            array('idx_bug_id_sla_time_tracking',
                $t_table,
                'bug_id', array('UNIQUE')));

        return $t_schema;
    }

    function addSuspendReasonField($p_event, $p_bug_id) {
        $custom_field_id = custom_field_get_id_from_name('Zawieszone ze względu na:');
        $t_project_id = bug_get_field($p_bug_id, 'project_id');
        $t_new_status = gpc_get_int('new_status', null);

        if (!custom_field_is_linked($custom_field_id, $t_project_id) || $t_new_status != 60) {
            return;
        }

        $t_field_def = custom_field_get_definition($custom_field_id);
        $t_enum_values = explode('|', $t_field_def['possible_values']);

        $has_error = session_get('suspend_reason_error', false);
        // 🟡 Pobierz wcześniej zapisaną wartość z pola niestandardowego
        $selected_value = custom_field_get_value($custom_field_id, $p_bug_id);

        // 🔁 Jeśli formularz wrócił z błędem, użyj wartości z POST
        if (isset($_POST['suspend_reason'])) {
            $selected_value = gpc_get_string('suspend_reason', '');
        }

        echo '<tr>';
        echo '<td class="category">Zawieszone ze względu na:</td>';
        echo '<td>';

        if ($has_error) {
            echo '<div class="error-msg" style="color: red; margin-bottom: 5px;">Pole jest wymagane.</div>';
        }

        echo '<select name="suspend_reason"' . ($has_error ? ' style="border: 1px solid red;"' : '') . '>';
        echo '<option value="">-- wybierz --</option>';
        foreach ($t_enum_values as $value) {
            $value = trim($value);
            $selected = ($value === $selected_value) ? ' selected="selected"' : '';
            echo '<option value="' . string_attribute($value) . '"' . $selected . '>' . string_display_line($value) . '</option>';
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';
    }
}
