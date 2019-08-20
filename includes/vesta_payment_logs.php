<?php 
/**
 * Create a grid in admin and show all logs.
 *
 * @package VestaPaymentLog
 */

if (!class_exists('WP_List_Table')) {
    include_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
class VestaPaymentLog extends WP_List_Table
{

    /**
     * [REQUIRED] You must declare constructor and give some basic params
     */
    function __construct()
    {
        global $status, $page;
        parent::__construct(
            array(
            'singular' => 'log',
            'plural' => 'logs',
            )
        );
    }

    /**
     * [REQUIRED] this is a default column renderer
     *
     * @param  $item - row (key, value array)
     * @param  $column_name - string (key)
     * @return HTML
     */
    function column_default($item, $column_name)
    {
        return $item[$column_name];
    }

    /**
     * [OPTIONAL] this is example, how to render column with actions,
     * when you hover row "Edit | Delete" links showed
     *
     * @param  $item - row (key, value array)
     * @return HTML
     */
    function column_responsecode($item)
    {	$get_variables = filter_input_array(INPUT_GET);
        $actions = array(
            'delete' => sprintf('<a href="?page=%s&action=delete&id=%s">%s</a>', $get_variables['page'], $item['id'], __('Delete', 'wc_vesta_payment')),
        );

        return sprintf(
            '%s %s',
            $item['responsecode'],
            $this->row_actions($actions)
        );
    }

    /**
     * [REQUIRED] this is how checkbox column renders
     *
     * @param  $item - row (key, value array)
     * @return HTML
     */
    function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="id[]" value="%s" />',
            $item['id']
        );
    }

    /**
     * [REQUIRED] This method return columns to display in table
     * you can skip columns that you do not want to show
     * like content, or description
     *
     * @return array
     */
    function get_columns()
    {
        $columns = array(
            'cb'            => '<input type="checkbox" />', //Render a checkbox instead of text
            'order_id' => 'Order ID',
            'response_code'  => 'Response Code',
            'response_text'       => 'Vesta Log Message',
            'created_on'       => 'Created on',
        );
        return $columns;
    }

    /**
     * [OPTIONAL] This method return columns that may be used to sort table
     * all strings in array - is column names
     * notice that true on name column means that its default sort
     *
     * @return array
     */
    function get_sortable_columns()
    {
        $sortable_columns = array(
            'created_on'   => array('created_on',false)
        );
        return $sortable_columns;
    }

    /**
     * [OPTIONAL] Return array of bult actions if has any
     *
     * @return array
     */
    function get_bulk_actions()
    {
        $actions = array(
            'delete' => 'Delete'
        );
        return $actions;
    }

    /**
     * [OPTIONAL] This method processes bulk actions
     * it can be outside of class
     * it can not use wp_redirect coz there is output already
     * in this example we are processing delete action
     * message about successful deletion will be shown on page in next part
     */
    function process_bulk_action()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'vesta_payment_logs'; // do not forget about tables prefix
		$get_id = filter_input_array(INPUT_GET);
        if ('delete' === $this->current_action()) {
            $ids = isset($get_id['id']) ? $get_id['id'] : array();
            if (is_array($ids)) { $ids = implode(',', $ids);
            }
            if (!empty($ids)) {
                $wpdb->query("DELETE FROM $table_name WHERE id IN($ids)");
            }
        }
    }

    function usort_reorder( $a, $b )
    {
        // If no sort, default to title
        $get_get_var = filter_input_array(INPUT_GET);
        $orderby = ( ! empty($get_get_var['orderby']) ) ? $get_get_var['orderby'] : 'created_on';
        // If no order, default to asc
        $order = ( ! empty($get_get_var['order']) ) ? $get_get_var['order'] : 'desc';
        // Determine sort order
        $result = strcmp($a[$orderby], $b[$orderby]);
        // Send final sort direction to usort
        return ( $order === 'asc' ) ? $result : -$result;
    }
    /**
     * [REQUIRED] This is the most important method
     *
     * It will get rows from database and prepare them to be showed in table
     */
    function prepare_items()
    {
        global $wpdb;
        $per_page = 10;

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);

        $this->process_bulk_action();

        $data = $this->fetch_table_data();
        usort($data, array( &$this, 'usort_reorder' ));

        $current_page = $this->get_pagenum();
        $total_items = count($data);
        $data = array_slice($data, (($current_page - 1) * $per_page), $per_page);
        $this->items = $data;


        /**
         * REQUIRED. We also have to register our pagination options & calculations.
         */
        $this->set_pagination_args(
            array(
            'total_items' => $total_items,                  //WE have to calculate the total number of items
            'per_page'    => $per_page,                     //WE have to determine how many items to show on a page
            'total_pages' => ceil($total_items / $per_page)   //WE have to calculate the total number of pages
            )
        );
    }
    /**
     * Get the dynamic data from database
     **/
    public function fetch_table_data()
    {
        global $wpdb;
        $wpdb_table = $wpdb->prefix . 'vesta_payment_logs';
        $get_get_var = filter_input_array(INPUT_GET);
        $orderby = ( isset($get_get_var['orderby']) ) ? esc_sql($get_get_var['orderby']) : 'id';
        $order = ( isset($get_get_var['order']) ) ? esc_sql($get_get_var['order']) : 'DESC';
        $user_query = "SELECT id, order_id, response_code, response_text, created_on FROM $wpdb_table ORDER BY $orderby $order";
        $query_results = $wpdb->get_results($user_query, ARRAY_A);
        return $query_results;
    }
}
