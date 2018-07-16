<?php
# filename: upload_processing.php
# purpose: handles validation of uploaded requests

include('abrlib.php');
$abr = new abrlib();

# get the passed variables
$fn = $_POST['filename'];
$total_lines = $_POST['total_lines'];
$current_line = $_POST['current_line'];

# Check the file inputs for formatting / headings
$fhandle = fopen(TEMP_FILE_HOLDER . '/' . $fn, "r");

# log start time, want to stop in VALIDATION_BATCH_PROCESSING_SECONDS
$start_time = time();

# if we're able to open the file
if ($fhandle) {

    $line_num = 0;
    $keys = explode(';', INPUT_REQUEST_HEADERS);
    $errors = [];
    $requests = 0;
    $origins = [];
    $destinations = [];

    # go through each line
    while (($line = fgets($fhandle)) !== false) {

        # only process the requested lines
        if( $line_num < $current_line ) {
            # jump to next line, not there yet
            $line_num += 1;
            continue;
        } elseif ( $start_time + VALIDATION_BATCH_PROCESSING_SECONDS < time() ) {
            # done for this batch, send response and exit

            # if $errors is not empty there are errors
            if( sizeof($errors) > 0 ) {
                print($abr->response($errors, 400)) and die();
            }

            # send the temp file code and other info
            print($abr->response([ 
                "outcome" => "progressing",
                "filename" => $fn,
                "next_line" => $line_num,
                "total_lines" => $total_lines,
                "requests" => $requests,
                "origins" => $origins,
                "destinations" => $destinations
            ])) and exit();
        }

        // clean end of line
        $line = rtrim($line);

        // Check the header
        if( $line_num === 0 ) {
            if( strcmp($line, INPUT_REQUEST_HEADERS) !== 0 ) {
                $errors[] = "SYNTAX ERROR: Header does not match requirement. Header must be:<br><strong>" . INPUT_REQUEST_HEADERS . "</strong>";
            }

            // ok, header is correct
            $line_num += 1;
            continue;
        }

        // Begin parsing data
        $parts = explode(';', $line);

        # Check that each row has X proper number of elements
        if( sizeof($parts) !== sizeof($keys) ) {
            $errors[] = "SYNTAX ERROR: Line " . $line_num . " does not have the required number of fields (" . sizeof($keys) . ").";
            continue;
        }

        $p = array_combine($keys, explode(';', $line));

        # check formatting of each segment of p
        # case_study, id, origin, destination, departure_datetime, mode, instructions, priority, apikey

        # case_study: ^[a-z0-9_]{4,16}$
        if( ! preg_match('/^[a-z0-9_]{4,16}$/', $p['case_study']) ) {
            $errors[] = "VALUE ERROR: Line $line_num's case study name '" . $p['case_study'] . "' is invalid. Must be 4-16 characters of length containing only: a-z 0-9 . _";
        }

        # id: ^[a-z0-9._]{4,16}$
        if( ! preg_match('/^[a-z0-9._]{1,16}$/', $p['case_study']) ) {
            $errors[] = "VALUE ERROR: Line $line_num's id value '" . $p['case_study'] . "' is invalid. Must be 1-16 characters of length containing only: a-z 0-9 . _";
        }

        # origin: validateLatLong
        # check the validity of origin/destinations
        $oll = explode(',', $p['origin']);
        if( ! $abr->validateLatLong($oll[0], $oll[1]) ) {
            $errors[] = "VALUE ERROR: Line $line_num has an invalid lat/long origin formatting/value of <strong>" . $p['origin'] . "</strong>.";
        }
        # Add to origins lists
        if( $total_lines < MAP_PREVIEW_REQUESTS_LIMIT && !in_array($oll, $origins ) ) {
            $origins[] = $oll;
        }

        # destination: validateLatLong
        $dll = explode(',', $p['destination']);
        if( ! $abr->validateLatLong($dll[0], $dll[1]) ) {
            $errors[] = "VALUE ERROR: Line $line_num has an invalid lat/long destination formatting/value of <strong>" . $p['destination'] . "</strong>.";
        }
        # Add to destinations lists
        if( $total_lines < MAP_PREVIEW_REQUESTS_LIMIT && !in_array($dll, $destinations) ) {
            $destinations[] = $dll;
        }

        # departure_datetime: Linux epoch time (seconds since Jan. 1, 1970), integer.
        # departure must be 'now' or linux epoch time in future. An "INVALID_REQUEST" is thrown if request is in past. Compare against now.
        # check that request date is in the future
        
        # convert to int
        $p['departure_datetime'] = (int)$p['departure_datetime'];

        if( $p['departure_datetime'] === 0 ) {
            # did not provide departure time
            # no traffic will be requested
            # check that live is not requested as this makes no sense
            if( strpos($p['instructions'], 'L') !== false ) {
                $errors[] = "VALUE ERROR: Line $line_num wants 'live' requests but doesn't provide a datetime to do so.";
            }
        } else {
            if( $p['departure_datetime'] < time() ) {
                $errors[] = "VALUE ERROR: Line $line_num has a departure_datetime that is in the past.";
            }
        }

        # mode: Must be one of driving, walking, bicycling, transit
        if( ! preg_match('/^(driving|walking|bicycling|transit)$/', $p['mode']) ) {
            $errors[] = "VALUE ERROR: Line $line_num has a mode value '" . $p['mode'] . "' that is in invalid.";
        }

        # instructions: Can be R, H, L ^[RHL]{0,3}$ Check if each letter is present at least once to create new requests. This will be translated to a suffix of format RL-HH (e.g., R-1, LW-10, RL-0, RL-23)
        if( ! preg_match('/^[RHL]{0,3}$/', $p['instructions']) ) {
            $errors[] = "VALUE ERROR: Line $line_num has an instructions value '" . $p['instructions'] . "' that is in invalid.";
        }

        # priority: ^[1-9]$
        if( ! preg_match('/^[1-9]$/', $p['priority']) ) {
            $errors[] = "VALUE ERROR: Line $line_num has a priority '" . $p['priority'] . "' that is not in the range of 1-9.";
        }

        # apikey: ^[a-zA-Z0-9-_]{39}$
        if( ! preg_match('/^[a-zA-Z0-9-_]{39}$/', $p['apikey']) ) {
            $errors[] = "VALUE ERROR: Line $line_num has an apikey '" . $p['apikey'] . "' that is invalid. Should be of length of 39 and contain only characters: a-z A-Z 0-9";
        }

        # check if there are existing or duplicate requests with the same case_study and id
        $duplicate = $abr->check_id_already_exists($p['case_study'], $p['id']);
        if( $duplicate > 0 ) {
            $errors[] = "DUPLICATE ERROR: Line $line_num in case study '<strong>" . $p['case_study'] . "</strong>' already has a request with id '<strong>" . $p['id'] . "</strong>'.";
        }

        # calculate how many requests are being submitted
        $req_mult = 1;
        if( strpos($p['instructions'], 'H') !== false ) {
            $req_mult *= 24;
        }
        if( strpos($p['instructions'], 'R') !== false ) {
            $req_mult *= 2;
        }
        $requests += $req_mult;

        # count the line number for error messages and batch size
        $line_num += 1;
        # end of looping through file lines
    }

    fclose($fhandle);

} else {
    // error opening the file.
    print($abr->response(["FILE ERROR: Unable to read file."], 500)) and die();
} 

# complete all processing of file

# if $errors is not empty there are errors
if( sizeof($errors) > 0 ) {
    print($abr->response($errors, 400)) and die();
}

# rename file to have validation prefix
rename(TEMP_FILE_HOLDER . '/' . $fn , TEMP_FILE_HOLDER . '/' . VALIDATION_PREFIX . $fn);

# send the temp file code and other info
print($abr->response([ 
    "outcome" => "complete",
    "filename" => $fn,
    "lines" => $line_num,
    "requests" => $requests,
    "origins" => $origins,
    "destinations" => $destinations,
    "display_map" => ($line_num < MAP_PREVIEW_REQUESTS_LIMIT ? true : false)
]));

?>
