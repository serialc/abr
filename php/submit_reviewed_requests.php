<?php
# filename: submit_reviewed_requests.php
# purpose: retrieve a filename of reivewed directions and submit to ABR processing queue

include('abrlib.php');
$abr = new abrlib();

$fn = $_POST['filename'];

# check $fn to make sure its proper
if( ! preg_match("/^[a-zA-Z0-9.]+$/", $fn) ) {
    print($abr->response("FILE NAME ERROR: The name of the temporary file you are referring to doesn't look right.", 400)) and die();
}
# file path
$fp = TEMP_FILE_HOLDER . '/' . VALIDATION_PREFIX . $fn;

# check this file exists
if( file_exists($fp) ) {
    # add data to routing processing queue
    if( ! $abr->submit_file_to_db($fp) ) {
        print($abr->response("PROCESSING ERROR: Cannot add data to processing queue an unspecified error has occurred.", 500)) and die();
    }

    # delete the source file
    if( !unlink($fp) ) { 
        print($abr->response("FILE ERROR: Cannot delete requests file but data may have been added to the processing queue.", 500)) and die();
    }
    
    print($abr->response(["outcome" => "All requests where successfully submitted to queue!"]));
} else {
    print($abr->response("FILE ERROR: Cannot find the submitted file.", 500)) and die();
}

?>
