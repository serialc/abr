<?php
# filename: abrlib.php
# helper functions

include('constants.php');

class abrlib {

    private $conn;

    # constructor
    public function __construct() { }

    public function response($data, $status = 200) {
         header("HTTP/1.1 " . $status . " " . $this->_requestStatus($status));
         header('Content-Type: application/json; charset=utf-8');
         return json_encode($data, JSON_UNESCAPED_SLASHES);
     }

    private function _requestStatus($code) {
        $status = array(
            200 => 'OK',
            201 => 'Created',
            204 => 'No content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            500 => 'Internal Server Error',
        );
        return ($status[$code]) ? $status[$code] : $status[500];
    }

    # source of lat/lng code by arubacao:
    # https://gist.github.com/arubacao/b5683b1dab4e4a47ee18fd55d9efbdd1
    /**
     * Validates a given latitude $lat
     *
     * @param float|int|string $lat Latitude
     * @return bool `true` if $lat is valid, `false` if not
     */
    public function validateLatitude($lat) {
        return preg_match('/^(\+|-)?(?:90(?:(?:\.0{1,6})?)|(?:[0-9]|[1-8][0-9])(?:(?:\.[0-9]{1,6})?))$/', $lat);
    }

    /**
     * Validates a given longitude $long
     *
     * @param float|int|string $long Longitude
     * @return bool `true` if $long is valid, `false` if not
     */
    public function validateLongitude($long) {
        return preg_match('/^(\+|-)?(?:180(?:(?:\.0{1,6})?)|(?:[0-9]|[1-9][0-9]|1[0-7][0-9])(?:(?:\.[0-9]{1,6})?))$/', $long);
    }

    /**
     * Validates a given coordinate
     *
     * @param float|int|string $lat Latitude
     * @param float|int|string $long Longitude
     * @return bool `true` if the coordinate is valid, `false` if not
     */
    public function validateLatLong($lat, $long) {
        return preg_match('/^[-]?((([0-8]?[0-9])(\.(\d+))?)|(90(\.0+)?)),[-]?((((1[0-7][0-9])|([0-9]?[0-9]))(\.(\d+))?)|180(\.0+)?)$/', $lat.','.$long);
    }

    private function _db_connect() {

        # don't reconnect if already connected - this isn't a robust method to check!
        if( isset($this->conn) ) {
            return;
        }

        # create db connection
        try {
            $this->conn = new mysqli(DB_SERVER, DB_USER, DB_PASS, DB_NAME);
        } catch (Exception $e) {
            echo 'ERROR: Failed to connect to database. Please contact your administrator.';
        }

        if( $this->conn->connect_error ) {
            print($this->response(["DB ERROR: Unable to connect to DB."], 500)) and die();
            #die('CONNECTION ERROR: (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());
        }
        
        # Greatly simplify life of funky characters
        $this->conn->set_charset( 'utf8' );
        $this->conn->query('SET NAMES utf8');
    }

    public function get_abr_status() {

        $this->_db_connect();

        $q = 'SELECT state FROM ' . TABLE_STATE;
        $qr = $this->conn->query($q);

        if( ! $qr ) {
            print($this->response(["INTERNAL ERROR: Can't determine whether ABR is running or not."], 500)) and die();
        }

        # contains an associative array ["state" => 0/1/2]
        return $qr->fetch_object();
    }

    public function set_abr_status($state) {

        $this->_db_connect();

        $q = 'UPDATE ' . TABLE_STATE . ' SET state=' . $state . ' WHERE id=1';
        $qr = $this->conn->query($q);

        if( ! $qr ) {
            print($this->response(["INTERNAL ERROR: Can't change running state of ABR."], 500)) and die();
        }

        return True;
    }

    public function get_results_summary() {

        $this->_db_connect();

        $q = 'SELECT case_study, count(case_study) AS total, (count(case_study) - count(rundate)) as queued, count(error) AS errors, count(rundate) as complete FROM ' . TABLE_REQUESTS . ' GROUP BY case_study';
        $qr = $this->conn->query($q);

        if( ! $qr ) {
            print($this->response(["INTERNAL ERROR: Can't retrieve summary of requests."], 500)) and die();
        }

        $rows = [];
        while( $row = $qr->fetch_array(MYSQLI_ASSOC) ) {

            # want info on zip file status as well
            $row['zip_state'] = false;
            $zpath = ZIP_DIRECTORY . '/' . $row['case_study'] . '.zip';
            if( file_exists($zpath) ) {
                $row['zip_state'] = true;
                $row['zip_mod'] = date("F d Y H:i:s",filemtime($zpath));
                $row['zip_size'] = filesize($zpath);
            }
            $rows[] = $row;
        }

        return $rows;
    }

    public function save_case_study_data( $case_study, $file_path_name ) {

        $this->_db_connect();

        $headings = ['case_study', 'id', 'idsuffix', 'live', 'origin', 'destination', 'departure_datetime', 'mode', 'priority', 'rundate', 'error'];
        $q = "SELECT " . implode(", ", $headings) . " FROM " . TABLE_REQUESTS . " WHERE case_study='" . $case_study . "' ORDER BY rundate";
        $qr = $this->conn->query($q);

        if( ! $qr ) {
            print($this->response(["DATABASE ERROR: Can't retrieve and save data."], 500)) and die();
        }

        $fh = fopen( $file_path_name, 'w' );

        # write headings
        fwrite( $fh, implode(OUTPUT_FIELD_SEPARATOR, $headings) . PHP_EOL);
        while( $row = $qr->fetch_array(MYSQLI_ASSOC) ) {
            fwrite( $fh, implode(OUTPUT_FIELD_SEPARATOR, $row) . PHP_EOL);
        }
        fclose($fh);

        return true;
    }

    public function get_queue_summary() {

        $this->_db_connect();

        $q = 'SELECT case_study, mode, live, priority, count(*) AS pending FROM ' . TABLE_REQUESTS . ' WHERE rundate IS NULL GROUP BY case_study, mode, live, priority ORDER BY case_study';
        $qr = $this->conn->query($q);

        if( ! $qr ) {
            print($this->response(["INTERNAL ERROR: Can't retrieve summary of requests."], 500)) and die();
        }

        $rows = [];
        while( $row = $qr->fetch_array(MYSQLI_ASSOC) ) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function submit_file_to_db($fp) {

        $this->_db_connect();

        # declare the variables we'll need for the loop
        $header = true;
        $keys = [];
        $lines = 0;

        # Go through each line of the file
        $fhandle = fopen($fp, "r");
        if ($fhandle) {
            while (($line = fgets($fhandle)) !== false) {
                // clean end of line
                $line = rtrim($line);

                // skip the header, we have already validated it in the upload process
                if( $header ) {
                    $header = false;
                    $keys = explode(';', $line);
                    continue;
                }

                // start processing a line of data
                $lines += 1;
                $parts = explode(';', $line);
                # makes column headings keys of associative array to ease referencing
                $p = array_combine($keys, explode(';', $line));

                # keys are: case_study, id, origin, destination, departure_datetime, mode, instructions, priority, apikey

                # build the requests and idsuffix field based on instructions
                $requests = [];
                $requests[0] = $p;
                $requests[0]['idsuffix'] = '';
                $requests[0]['live'] = 0; // boolean true/false

                # Live request
                if( preg_match('/L/', $p['instructions']) ) {
                    $requests[0]['live'] = 1;
                    $requests[0]['idsuffix'] = 'L';
                }

                # Waypoints W or V
                if( preg_match('/W/', $p['instructions']) ) {
                    $requests[0]['idsuffix'] .= 'W';
                }
                if( preg_match('/V/', $p['instructions']) ) {
                    $requests[0]['idsuffix'] .= 'V';
                }

                # Dual/reversed requests
                if( preg_match('/R/', $p['instructions']) ) {
                    # create a second COPY of the request, but invert direction
                    $requests[1] = $requests[0];

                    # swap OD to DO
                    $origin = $requests[1]['origin'];
                    $requests[1]['origin'] = $requests[1]['destination'];
                    $requests[1]['destination'] = $origin;
                    $requests[1]['idsuffix'] .= 'R';
                }

                # Hourly requests
                if( preg_match('/H/', $p['instructions']) ) {
                    # for each request in $requests we need to add 23 more and modify the existing idsuffix
                    $new_requests = []; 
                    foreach($requests as $req) {
                        $idsuffix = $req['idsuffix'];

                        foreach(range(0,23) as $hour) {
                            $new_requests[$idsuffix.$hour] = $req;

                            $new_requests[$idsuffix.$hour]['idsuffix'] .= '_' . $hour;
                            # update the datetime for an additional hour
                            $new_requests[$idsuffix.$hour]['departure_datetime'] += $hour * 3600;

                        }
                    }

                    # overwrite the array we iterated through
                    $requests = $new_requests;
                }

                # Insert the requests for this line
                # validating is done in upload code - no escape characters are present
                # build super insert
                $insert_q = "INSERT INTO " . TABLE_REQUESTS . " (case_study, id, idsuffix, live, origin, destination, departure_datetime, mode, priority, apikey) VALUES ";
                $values = '';

                # paste together values of query
                foreach($requests as $req) {
                    $values .= "('" . 
                        $req['case_study'] . "', '" . 
                        $req['id'] . "', '" . 
                        $req['idsuffix'] . "', " . 
                        $req['live'] . ", '" . 
                        $req['origin'] . "', '" . 
                        $req['destination'] . "', '" .
                        $req['departure_datetime'] . "', '" .
                        $req['mode'] . "', " .
                        $req['priority'] . ", '" .
                        $req['apikey'] . "'), ";
                }

                # build full query, remove trailing comma and paste with INSERT portion
                $insert_q = rtrim($insert_q . $values, ', ');

                # INSERT new data
                $qr = $this->conn->query($insert_q);

                # check for error
                if( ! $qr ) {
                    # for debugging - turn off
                    #print("DEBUGGING - Must be removed eventually.");
                    #print $bssid . " INSERT query failed: " . $this->conn->error . "\n";
                    #print $insert_q;

                    print($this->response(["DB ERROR: Unable to add data from line '" . $lines . "' to requests database. Quitting. Requests up to this line have been added."], 500)) and die();
                }
            }

            # close the filehandle
            fclose($fhandle);
        } else {
            // error opening the file.
            print($this->response(["FILE ERROR: Unable to read file."], 500)) and die();
        } 

        return True;
    }

    # Handles the log file
    public function append_log($msg) {
        $fh = fopen(ABR_LOG_FILE, "a") or $this->response("SERVER ERROR: Failed to open log file.", 500) and die();
        $date = new DateTime;
        $date = $date->format('Y-m-d H:i:s');
        fwrite($fh, '[' . $date . '] ' . $msg . "\n");
        fclose($fh);
    }

    public function clear_log() {
        $fh = fopen(ABR_LOG_FILE, "w") or $this->response("SERVER ERROR: Failed to open log file.", 500) and die();
        # write empty string to clear the file
        fwrite($fh, ''); 
        fclose($fh);
        return True;
    }

    public function get_requests_batch($type, $quantity) {

        $this->_db_connect();

        # get a batch of queries that are
        # - sorted by those with highest priority first
        # - are under their quota for the last 25 hours
        # - are the closest to the present
        # - are not run yet
        # - are not block apikeys
        if( strcmp($type, "regular") === 0 ) {

            $q = "SELECT * FROM " . TABLE_REQUESTS . " WHERE rundate IS NULL " .
                "AND apikey NOT IN (SELECT apikey FROM " . TABLE_APIKEY_BLOCK . ") " .
                "AND (apikey NOT IN " .
                    "(SELECT apikey FROM " .
                        "(SELECT apikey, count(apikey) AS count FROM " . TABLE_REQUESTS . " WHERE rundate > DATE_SUB(NOW(), INTERVAL 25 HOUR) GROUP BY apikey ORDER BY rundate) " .
                    "AS keycount WHERE count >= " . API_KEY_DAILY_REQUEST_QUOTA_LIMIT . ") " .
                " OR priority > " . QUOTA_BYPASS_PRIORITY_LIMIT . ") ORDER BY priority DESC, departure_datetime DESC LIMIT " . $quantity;

        }

        # Live requests are not bound by quota limit - it is up to the user to be careful and potentially get ERRORS or incur costs.
        if( strcmp($type, "live") === 0 ) {
            $q = "SELECT * FROM " . TABLE_REQUESTS . " WHERE rundate IS NULL AND live = 1 AND departure_datetime < DATE_ADD(NOW(), INTERVAL 1 minute) ORDER BY departure_datetime DESC";
        }

        # execute query and check for errors
        $qr = $this->conn->query($q);
        if( ! $qr ) {
            print($q);
            die(mysqli_error($this->conn));
        }

        # Logging for debugging
        if( DEBUG_LOGGING ) {
            $this->append_log("Sent <b>" . $this->conn->affected_rows . " $type requests</b> to be processed.");
        }

        $rows = [];
        while( $row = $qr->fetch_array(MYSQLI_ASSOC) ) {
            $rows[] = $row;
        }

        # debugging
        #print "In mode " . $type . " we retrieved " . sizeof($rows) . " rows using query:\n" . $q . "\n";
        return $rows;
    }

    public function check_id_already_exists( $case_study, $id ) {

        $this->_db_connect();

        # check if this key exists in table
        $q = "SELECT count(*) AS count FROM " . TABLE_REQUESTS . " WHERE case_study='$case_study' AND id='$id'";
        $qr = $this->conn->query($q);

        # Check if it executed correctly
        if( $qr === false ) {
            print(mysqli_error($this->conn));
            return false;
        }

        return $qr->fetch_object()->count;
    }

    public function check_apikey_is_below_quota( $apikey ) {

        $this->_db_connect();

        # check if this key exists in table
        $q = "SELECT apikey, count(apikey) AS requests FROM " . TABLE_REQUESTS . " WHERE apikey='" . $apikey . "' AND rundate > DATE_SUB(NOW(), INTERVAL 25 HOUR)";
        $qr = $this->conn->query($q);

        # Check if it executed correctly
        if( $qr === false ) {
            print(mysqli_error($this->conn));
            return false;
        }

        # check if this apikey even exists/has run anything yet
        if( $qr->num_rows === 0 ) {
            # nope, okay then it's fine, way under it's quota
            return true;
        } 

        # this key has been used before, is it under it's daily/24h quota?
        if( $qr->fetch_object()->requests < API_KEY_DAILY_REQUEST_QUOTA_LIMIT ) {
            return true;
        }

        # it's over it's 24h quota!
        return false;
    }

    public function update_error_request( $request, $error ) {

        $this->_db_connect();

        # update request with date of failure and cause
        $q = "UPDATE " . TABLE_REQUESTS . " SET rundate=NOW(), error='" . $error . "' WHERE rid=" . $request['rid'];
        $this->conn->query($q) or die(mysqli_error($this->conn));
        return true;
    }

    public function update_successful_request( $request ) {

        $this->_db_connect();

        # update request with date of successfull retrieval
        $q = "UPDATE " . TABLE_REQUESTS . " SET rundate=NOW() WHERE rid=" . $request['rid'];
        $this->conn->query($q) or die(mysqli_error($this->conn));
        return true;
    }

    public function get_case_study_zip_requests () {
        $this->_db_connect();

        # we limit this to one in case it takes too long to process and there are multiple, this could cause multiple calls
        $q = 'SELECT case_study FROM ' . TABLE_ZIP_QUEUE . ' LIMIT 1';
        $qr = $this->conn->query($q) or die(mysqli_error($this->conn));

        $rows = [];
        while( $row = $qr->fetch_array(MYSQLI_ASSOC) ) {
            $rows[] = $row['case_study'];
        }
        return $rows;
    }
    public function add_case_study_zip_request ( $case_study ) {
        $this->_db_connect();

        $insert_q = 'INSERT IGNORE INTO ' . TABLE_ZIP_QUEUE . " (case_study) VALUES ('$case_study')";
        $qr = $this->conn->query($insert_q);
        
        # check for error
        if( ! $qr ) { return false; }
        return true;
    }
    public function delete_case_study_zip_request ( $case_study ) {
        $this->_db_connect();

        $delete_q = 'DELETE FROM ' . TABLE_ZIP_QUEUE . " WHERE case_study='$case_study'";
        $qr = $this->conn->query($delete_q);
        
        # check for error
        if( ! $qr ) { return false; }
        return true;
    }
    public function delete_queued_requests ( $case_study ) {
        $this->_db_connect();

        $delete_q = 'DELETE FROM ' . TABLE_REQUESTS . " WHERE case_study='$case_study' AND rundate IS NULL";
        $qr = $this->conn->query($delete_q);
        
        # check for error
        if( ! $qr ) { return false; }
        return true;
    }

    public function delete_case_study ( $case_study ) {
        $this->_db_connect();

        # delete all DB entries
        $delete_q = 'DELETE FROM ' . TABLE_REQUESTS . " WHERE case_study='$case_study'";
        $qr = $this->conn->query($delete_q);
        
        # check for error
        if( ! $qr ) { 
            print($abr->response("DB DELETION ERROR: Failed to delete the data from the server.", 400)) and die();
            return false;
        }

        # delete all json results data for the case study
        $case_study_dir = RESULTS_FILE_HOLDER . '/' . $case_study;
        array_map('unlink', glob($case_study_dir . "/*"));
        if( file_exists($case_study_dir) ) {
            rmdir($case_study_dir);
        }

        # delete zip file for case study
        if( file_exists(ZIP_DIRECTORY . '/' . $case_study . '.zip') ) {
            unlink(ZIP_DIRECTORY . '/' . $case_study . '.zip');
        }

        return true;
    }

    public function add_upload_file ( $filename, $linecount, $processed ) {
        $this->_db_connect();

        $insert_q = 'INSERT INTO ' . TABLE_UPLOADING . " (filename, linecount, processed) VALUES ('$filename', $linecount, $processed)";
        $qr = $this->conn->query($insert_q);

        #  check for errors
        if( ! $qr ) { return false; }
        return true;
    }

    public function block_apikey ( $apikey, $block_type ) {
        $this->_db_connect();

        if( strcmp($block_type, "temporary" ) === 0) {
            # 1 hour block
            $insert_q = 'INSERT INTO ' . TABLE_APIKEY_BLOCK . " (apikey, block_type, unblock_datetime) VALUES ('$apikey', '$block_type', DATE_ADD(NOW(), INTERVAL 1 HOUR))";
        } else {
            # banned permenantly
            $insert_q = 'INSERT INTO ' . TABLE_APIKEY_BLOCK . " (apikey, block_type, unblock_datetime) VALUES ('$apikey', 'banned', NULL)";
        }
        
        $qr = $this->conn->query($insert_q);

        #  check for errors
        if( ! $qr ) { return false; }
        return true;
    }

    public function check_apikey_blocked ( $apikey ) {
        $this->_db_connect();

        $select_q = 'SELECT apikey FROM ' . TABLE_APIKEY_BLOCK . " WHERE apikey = '$apikey'";
        $sqr = $this->conn->query($select_q);

        if( $sqr->num_rows > 0 ) {
            # blocked
            return true;
        }
        # not blocked
        return false;
    }

    # checks if keys are still banned or not based on datetime, returns how many keys have been enabled
    public function check_apikey_validities () {
        $this->_db_connect();

        $delete_q = 'DELETE FROM ' . TABLE_APIKEY_BLOCK . " WHERE block_type = 'temporary' AND unblock_datetime < NOW()";

        $qr = $this->conn->query($delete_q);

        #  check for errors
        return($this->conn->affected_rows);
    }

    public function log_completed_requests( $date, $num ) {

        $fh = fopen(ABR_REQUESTS_COUNT_LOG_FILE, "a") or $this->response("SERVER ERROR: Failed to open requests count log file.", 500) and die();
        fwrite($fh, $date . ',' . $num . "\n");
        fclose($fh);
    }

    public function get_directory_size($path) {
        $bytestotal = 0;
        $path = realpath($path);
        if($path!==false && $path!='' && file_exists($path)){
            foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $object){
                $bytestotal += $object->getSize();
            }
        }
        return $bytestotal;
    }



    # INSERT
    #$q = 'INSERT IGNORE INTO ' . BS_DATA_TABLE . ' (stnid, snapshot, bikes, spaces, docks) VALUES ' . $data;
    #$this->conn->query($q) or die(mysqli_error($this->conn));
    
    # UPDATE
    # $q = 'UPDATE ' . BS_STATION_TABLE . ' SET bikes=' . $b . ', spaces=' . $s . ' WHERE stnid=' . $stn;
    # $this->conn->query($q) or die(mysqli_error($this->conn));
    
    # SELECT
    # $q = 'SELECT stnid FROM ' . BS_STATION_TABLE;
    # $qr = $this->conn->query($q) or die(mysqli_error($this->conn));
    # $qr->fetch_object()

}

?>
