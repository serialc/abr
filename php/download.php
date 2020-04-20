<?php
# filename: download.php
# purpose: return zipped data for a case study requested. Also can request the zipping of a dataset.

include('abrlib.php');
$abr = new abrlib();

if( isset($_GET['case_study']) ) {
    $case_study = $_GET['case_study'];
}
$instruction = $_GET['instruction'];

switch($instruction) {

case 'zip':
    # add case study to zip queue
    if( !$abr->add_case_study_zip_request($case_study) ) {
        print($abr->response("INTERNAL ERROR: Unable to add case study to zip queue.", 500)) and die();
    }
    break;

case 'zip_all_finished':
    if( $abr->zip_all_finished_case_studies() ) {
        print($abr->response('Smart Zip successful'));
    } else {
        print($abr->response("Failed to request zipping of all completed case studies.", 500));
    }
    break;

case 'download':
    # Servers file to the user
    # The below method is good but doesn't work for large files:
    # - See: https://stackoverflow.com/questions/10997516/how-to-hide-the-actual-download-folder-location
    # This method is better:
    # https://stackoverflow.com/questions/6914912/streaming-a-large-file-using-php
    
    # define the path and name of the zip file
    $internal_zip_file_name = $case_study . '.zip';
    $zip_file_path = ZIP_DIRECTORY . '/' . $internal_zip_file_name;
    $public_zip_file_name = $case_study . '_' . date("Y-m-d_H-i-s", filemtime($zip_file_path)) . '.zip';

    # send a byte stream
    $fh = fopen($zip_file_path, 'rb');
    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=$public_zip_file_name");
    header("Content-Length: " . filesize($zip_file_path));

    while (!feof($fh)) {
        $buffer = fread($fh, 1024*1024);
        print $buffer;
        ob_flush();
        flush();
    }
    #$status = fclose($fh) # causes problems, page redirection
    #fpassthru($fh);
    break;

default:
    # Error
    print($abr->response("BAD REQUEST: You asked for a download operation type that is unexpected.", 400)) and die();
}

?>
