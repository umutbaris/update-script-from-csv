<?php
/*
 * Http host name is also needed.
 * A sample example to run the php file is : php update.php host excel.csv
 */

try {
	$_SERVER['HTTP_HOST']       = $argv[1];
	$_SERVER['REQUEST_URI']     = '/';
	$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
	$_SERVER['REQUEST_METHOD']  = 'GET';

	$file_path   = './' . $argv[2];

	//checking if the file exists
	if ( !file_exists( $file_path ) ) {
		throw new Exception( 'CSV File not found.' );
	}

	//handle the error if the file can not be opened
	$file_handle = fopen( $file_path, 'r' );
	if ( !$file_handle ) {
		throw new Exception( 'File open failed.' );
	}

	error_log(print_r("BULK UPDATE "  . "PHP process ID : " .  getmypid() . "," . " Host : " . $argv[1] . "," . " CSV File: " . " " . $argv[2], true));

	//reading the csv file and take the values to an array
	$csv_data = [];
	while ( ( $row = fgetcsv( $file_handle, 1024, ';' ) ) !== FALSE ) {
		$csv_data[] = $row;
	}

	if ( empty( $csv_data ) ) {
		throw new Exception( 'File content not found.' );
	}

	global $wpdb;
	$count_not_found_results = 0;
	$count_found_results = 0;
	$count_query_error = 0;
	foreach( $csv_data as $csv_row ) {
		if ( $csv_row[0] == 'GUID' ) {
			continue;
		}
		$guid = preg_match( "/page_id=(.*)/", $csv_row[0], $matches );
		$guid = strpos( $matches[1], '&' ) == false ? $matches[1] :  explode( '&', $matches[1] )[0];

		$table_prefix = 'wp_' . $csv_row[3] . '_';
		$post_table = $table_prefix . 'posts';
		
		$query = "SELECT ID FROM {$post_table} WHERE guid LIKE '%" . $guid ."%'";
		$result = $wpdb->get_row( $query, ARRAY_A );

		if ( empty( $result ) ) {
			$count_not_found_results++;
			error_log( "BULK UPDATE, " . "PHP process ID : " .  getmypid() . " GUID : " . $guid . " is not found on this table : " . 
			$post_table . " query: " . $query );
			continue;
		}
		
		$meta_table = $table_prefix . 'postmeta';
		$query	= $wpdb->query( $wpdb->prepare( "UPDATE {$meta_table} SET meta_value = %s WHERE post_id = %d
		AND meta_key = %s", $csv_row[7], $result['ID'], $csv_row[5]) );

		$updated_status = ' updated.';
		if ( false === $query ) {
			$updated_status = ' not updated. Error: ' . $wpdb->last_error;
			$count_query_error++;
		} else {
			$count_found_results++;
		}

		error_log( "BULK UPDATE, " . "PHP process ID : " .  getmypid() . "," . " Script Name : " . " SEO UPDATE" . "," . " JIRA ID : "  . "," . 
		" post_id : " . $result['ID'] . "," . " post_title : " 
		. $csv_row[2] . "," . " meta_key  : " . $csv_row[5] . "," .  " meta_value  : "  . $csv_row[7] . "," . " guid  : " .  $guid . 
			" Table  : " .  $meta_table . ' Status: ' . $updated_status );
	}
	$total = $count_not_found_results + $count_found_results + $count_query_error;
	error_log( "BULK UPDATE, " . "PHP process ID : " . "count_not_found_results: " . $count_not_found_results . " count_found_results: " . $count_found_results  .
	 " count_query_error: " . $count_query_error . " Total: " . $total );
} catch ( Exception $e ) {
	echo 'Caught exception: ',  $e->getMessage(), "\n";
}