<?php
# filename: download.php
# purpose: return zipped data for a case study requested. Also can request the zipping of a dataset.

include('abrlib.php');
$abr = new abrlib();

if( isset($_GET['case_study']) ) {
    $case_study = $_GET['case_study'];
    if( ! preg_match('/^[a-z0-9_]{4,16}$/', $case_study) ) {
        print($abr->response("CASE STUDY NAME ERROR: The case study name isn't valid. [$case_study]", 400)) and die();
    }
}

if( isset($_GET['case_studies']) ) {
    $case_studies = json_decode($_GET['case_studies']);
    foreach( $case_studies as $cs ) {
        # check cs: ^[a-z0-9_]{4,16}$
        if( ! preg_match('/^[a-z0-9_]{4,16}$/', $cs) ) {
          print($abr->response("CASE STUDY NAME ERROR-2: The case study name isn't valid. [$cs]", 400)) and die();
        }
    }
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
    # The below method is fine but doesn't work for large files:
    # - See: https://stackoverflow.com/questions/10997516/how-to-hide-the-actual-download-folder-location
    # This method is better:
    # https://stackoverflow.com/questions/6914912/streaming-a-large-file-using-php
    
    # define the path and name of the zip file
    $zip_file_path = ZIP_DIRECTORY . '/' . $case_study . '.zip';
    $public_zip_file_name = $case_study . '_' . date("Y-m-d_H-i-s", filemtime($zip_file_path)) . '.zip';

    # send a byte stream
    $fh = fopen($zip_file_path, 'rb');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename=' . $public_zip_file_name);
    header('Content-Length: ' . filesize($zip_file_path));
    readfile($zip_file_path);

    break;

case 'download_selection':
    // prep the package
    $zipname = 'ABR_mutliple_case_studies_' . date("Y-m-d_H-i-s", time()) . '.zip';
    $zippath = ZIP_DIRECTORY . '/' . $zipname;

    $zip = new ZipArchive();

    if( ! $zip->open($zippath, ZipArchive::CREATE | ZipArchive::OVERWRITE) ) {
        die("Failed to open zip archive.");
    }

    foreach ($case_studies as $filename) {
        $filepath = ZIP_DIRECTORY . '/' . $filename . '.zip';
        if( file_exists($filepath) ) {
            if( ! $zip->addFile($filepath, $filename . '.zip') ) {
                die("Adding file [$filename] to zip failed!");
            }
        } else {
            die("Requested file [$filename] does not exist!");
        }
    }
    if( ! $zip->close() ) {
        print("Failed to zip data.") and die();
    }

    // send the package
    header('Content-Type: application/zip');
    header('Content-disposition: attachment; filename=' . $zippath);
    header('Content-Length: ' . filesize($zippath));
    readfile($zippath);

    # delete it
    unlink($zippath);

    break;

default:
    # Error
    print($abr->response("BAD REQUEST: You asked for a download operation type that is unexpected.", 400)) and die();
}

?>
