<?php
# filename: main.php
# purpse: the main brain of ABR that:
# 1. retrieves requests from DB (in order of importance),
# 2. sends them to Google (without exceeding quotas)
# 3. stores result,
# 4. updates database

include('abrlib.php');

class main {
    private $abr;

    # constructor
    public function __construct() {
        # initialize helper library
        $this->abr = new abrlib();

        # check if ABR should/can run (0=off, 1=ready, 2=already running)
        if( (int)$this->abr->get_abr_status()->state !== 1 ) {
            # quit, already running (if double process blocking is enabled) or turned off
            return;
        }

        # check if developer switch is 'on'
        if( !ABR_PROCESS_CONTROL ) {
            $this->abr->append_log("ABR did not run as it is in development mode");
            return;
        }

        # log start time, want to stop in 55 seconds
        $start_time = time();

        # lock status so we don't get two instances running
        if( PROCESS_LOCKING_ENABLED ) {
            $this->abr->set_abr_status(2);
        }

        # log every hour even though it runs every minute
        $date = new DateTime;
        if( $date->format('i') === '00' ) {
            $this->abr->append_log("Running");
        }

        # check state of blocked apikeys, may want to unblock some
        $enabled_apikeys = $this->abr->check_apikey_validities();
        if( $enabled_apikeys > 0 ) {
            $this->abr->append_log("$enabled_apikeys apikey(s) has/have been unblocked.");
        }

        # Get a batch of live requests to fulfill, get as many live (LIVE meaning real-time congestion requests see documentation) requests as needed - ignore quotas
        $live_requests = $this->abr->get_requests_batch('live', NULL);

        # determine how much of your quota is available from requests used in the last 24 hours minus those in $live_requests
        # fill the rest (or potentially all) of our minute request quota (REQUESTS_PER_MINUTE) with normal (timeless/future) requests
        $regular_requests = $this->abr->get_requests_batch('regular', REQUESTS_PER_MINUTE - sizeof($live_requests));

        # merge both sets of arrays
        $requests = array_merge($live_requests, $regular_requests);

        # Setup curl requests
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, REQUEST_TIMEOUT_SECONDS);

        # Go through list of requests
        $num_requests = 0;
        foreach( $requests as $r ) {

            # check if ABR should continue to run
            $abr_status = (int)$this->abr->get_abr_status()->state;
            if( (PROCESS_LOCKING_ENABLED && $abr_status !== 2) || (!PROCESS_LOCKING_ENABLED && $abr_status !== 1) ) {
                break;
            }

            # double check this apikey is not blocked
            if( $this->abr->check_apikey_blocked( $r['apikey'] ) ) {
                # apikey blocked
                continue;
            }

            # check that this apikey hasn't exceeded it's quota and isn't blocked
            # - if it's not high priority
            # - not live ((int)live === 0) NEED TO CAST AS A STRING IS RETURNED FROM DB and '0' != 0
            if( $r['priority'] <= QUOTA_BYPASS_PRIORITY_LIMIT && (int)$r['live'] === 0 && ! $this->abr->check_apikey_is_below_quota( $r['apikey'] ) ) {
                # Ban this key for an hour
                $this->abr->block_apikey( $r['apikey'], 'temporary');
                $this->abr->append_log('Apikey ending with <b>' . substr($r['apikey'], -6) . '</b> has been <b>temporarily blocked</b> due to exceeding internal quoata.');
                # oops, try next request
                continue;
            }

            # delete zip file for this case study if it exists
            $zpath = ZIP_DIRECTORY . '/' . $r['case_study'] . '.zip';
            if( file_exists($zpath) ) { unlink($zpath); }

            # slight modification if departure_datetime === 0, means we don't want traffic
            $traffic = true;
            if( $r['departure_datetime'] === '0' ) {
                $traffic = false;
            }

            # slight modification if 'live'
            if( $r['live'] === 1 ) {
                # this makes it so if the request is a few seconds/minutes/days/months/years... behind, it will run now
                $r['departure_datetime'] = 'now';
            }

            $url = GMD_URL .
                "&language=" . GMD_LANGUAGE .
                "&reqion=" . GMD_REGION .
                "&units=" . GMD_UNITS .
                ($traffic ? "&traffic_model=" . GMD_TRAFFIC_MODEL : '') .
                "&mode=" . $r['mode'] .
                "&origin=" . $r['origin'] .
                "&destination=" . $r['destination'] .
                ($traffic ? "&departure_time=" . $r['departure_datetime'] : '') .
                "&key=" . $r['apikey'];

            # add url to curl 
            curl_setopt($ch, CURLOPT_URL, $url);
            # get URL content/directions
            $results = curl_exec($ch);

            $results_json = json_decode($results , true);
            $status = $results_json['status'];

            switch($status) {
            case "OK":

                # save results
                # create the dir if needed
                $save_dir = RESULTS_FILE_HOLDER . '/' . $r['case_study'];
                if( file_exists($save_dir) === false ) {
                    if( mkdir($save_dir) === false ) {
                        $this->abr->append_log("DIRECTORY ERROR: Retrieved data for request case study <strong>" . $r['case_study'] . " " . $r['id'] . " " . $r['idsuffix'] . "</strong> but couldn't create the directory to save the file.");
                        break;
                    }
                }

                # save the routing results to file
                $save_file = $save_dir . '/' . $r['case_study'] . '-' . $r['id'] . '-' . $r['idsuffix'] . '.json';

                # make sure we aren't overwriting a file/data
                $duplicate_num = 1;
                while( file_exists($save_file) ) {
                    $save_file = $save_dir . '/' . $r['case_study'] . '-' . $r['id'] . '-' . $r['idsuffix'] . '_duplicate' . $duplicate_num . '.json';
                    $duplicate_num += 1;
                    # this can't get out of hand - limit to 10
                    if( $duplicate_num > 10 ) {
                        $this->abr->append_log("SAVING ERROR: It looks like case study request strong>" . $r['case_study'] . " " . $r['id'] . " " . $r['idsuffix'] . "</strong> has many duplicate requests of the same exact id specification. This is causing problems. Overwriting past data - sorry.");
                        break;
                    }
                }

                # ok, save and check results
                $written = file_put_contents($save_file, $results);
                if( $written === FALSE ) {
                    # this is bad - got the data but unable to save it!
                    # Log this, don't update mysql and continue to next request (with probably the same result)
                    $this->abr->append_log("SAVING ERROR: Retrieved data for request case study <strong>" . $r['case_study'] . " " . $r['id'] . " " . $r['idsuffix'] . "</strong> but didn't manage to save the data.");
                    break;
                }
                
                # mysql update
                $this->abr->update_successful_request($r, $status);

                break;
            case "NOT_FOUND":
            case "ZERO_RESULTS":
            case "MAX_WAYPOINTS_EXCEEDED":
            case "MAX_ROUTE_LENGTH_EXCEEDED":
            case "INVALID_REQUEST":
                # update mysql date processed and add error message
                $this->abr->append_log($status . " error for request case study <strong>" . $r['case_study'] . " " . $r['id'] . " " . $r['idsuffix'] . "</strong>");
                if( isset($results_json['error_message']) ) {
                    $this->abr->append_log("Error detail: " . $results_json['error_message']);
                }
                # mysql update
                $this->abr->update_error_request($r, $status);
                break;
            case "OVER_QUERY_LIMIT":
                # this shouldn't happen!
                # log it and continue to next request - do not update as finished/failed. 
                $this->abr->append_log($status . " error for request case study <strong>" . $r['case_study'] . " " . $r['id'] . " " . $r['idsuffix'] . "</strong>");
                if( isset($results_json['error_message']) ) {
                    $this->abr->append_log("Error detail: " . $results_json['error_message']);
                }

                # Ban this key for an hour
                $this->abr->block_apikey( $r['apikey'], 'temporary');
                $this->abr->append_log('Apikey ending with ' . substr($r['apikey'], -6) . ' has been <b>temporarily blocked</b> due to exceeding Google API quota.');

                break;
            case "REQUEST_DENIED":
            case "UNKNOWN_ERROR":
                # log it, mark this request as failed/finished and continue to next request
                $this->abr->append_log($status . " error for request case study <strong>" . $r['case_study'] . " " . $r['id'] . " " . $r['idsuffix'] . "</strong>");
                if( isset($results_json['error_message']) ) {
                    $this->abr->append_log("Error detail: " . $results_json['error_message']);
                }
                $this->abr->update_error_request($r, $status);
                break;
            default:
                # shouldn't happen so log it
                $this->abr->append_log($status . " error for request case study <strong>" . $r['case_study'] . " " . $r['id'] . " " . $r['idsuffix'] . "</strong>");
                $this->abr->append_log("UNKNOWN error! Google Maps Directions returned the error type <strong>" . $status . "</strong> but we weren't expecting it. Notify the developer please! ABR will try this request again. If no further errors are reported the issue is resolved.");
                if( isset($results_json['error_message']) ) {
                    $this->abr->append_log("Error detail: " . $results_json['error_message']);
                }
                break;
            }

            # count the request
            $num_requests += 1;

            # between requests wait the alloted time
            usleep(REQUEST_DELAY_MICROSECONDS);

            if( $start_time + RUNNING_TIME_PER_MINUTE < time() ) {
                break;
            }
        }
        // close handle to release resources
        curl_close($ch);

        # Logging
        $this->abr->log_completed_requests( $start_time, $num_requests );

        # Logging for debugging
        if( DEBUG_LOGGING ) {
            $this->abr->append_log('Completed processing of <b>' . $num_requests . ' requests</b>.');
        }
        # allow another process to run ABR
        if( PROCESS_LOCKING_ENABLED ) {
            $this->abr->set_abr_status(1);
        }
    }

}

# create instance
$control = new main();

?>
