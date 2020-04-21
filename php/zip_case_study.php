<?php
# filename: zip_case_study.php
# purpose: called by cron, looks if case study needs to be zipped and does so

include('abrlib.php');
$abr = new abrlib();

# Get case studies from DB (NOTE - this only gets 1 case study)
foreach($abr->get_case_study_zip_requests() as $case_study) {
    # testing
    #print $case_study . "\n";

    # we don't want to wait until completion to remove from queue as this may be called again before this task is completed
    $abr->delete_case_study_zip_request( $case_study );

    # 1. exports all the records for this case study to its respective case_study dir
    # 2. zips the contents of the case_study folder and save it in (ZIP_DIRECTORY . '/' . $case_study.zip)
    # - See: https://stackoverflow.com/questions/4914750/how-to-zip-a-whole-folder-using-php
    $case_study_data_path = RESULTS_FILE_HOLDER . '/' . $case_study . '/';
    $db_export_file = $case_study_data_path . $case_study . '_data.tsv';
    $public_zip_file_name = $case_study . '_' . date("Y-m-d") . '.zip';
    $internal_zip_file_name = $case_study . '.zip';
    $zip_file_path = ZIP_DIRECTORY . '/' . $internal_zip_file_name;

    # check if zip file already exists - zip files are automatically deleted if data source is updated
    if( file_exists($zip_file_path) ) {
        continue;
    }
    
    # 1 - Get all case study records
    # ##############################
    $saved_data = $abr->save_case_study_data( $case_study, $db_export_file );

    # Check data export worked or die
    if( ! $saved_data ) {
        $abr->response("Failed to save data.", 500) and die();
    }

    # 2 - zip everything in the case_study directory/folder
    # ##############################
    $zip = new ZipArchive();
    $zip->open( $zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $files = array_diff(scandir($case_study_data_path), array('..', '.'));

    # this may take a while so prevent timout (up to 5 minutes)
    set_time_limit(300);

    # add each file to zip archive
    foreach ($files as $filename) {
        # if it is a json data file, add it to the 'json' folder
        if( preg_match('/\.json$/', $filename) ) {
            $zip->addFile($case_study_data_path . $filename, $case_study . '/json_data/' . $filename);
        } else {
            $zip->addFile($case_study_data_path . $filename, $case_study . '/' . $filename);
        }
    }

    # Check zipping worked or die
    if( ! $zip->close() ) {
        print($abr->response("Failed to zip data.", 500)) and die();
    }

    # try deleting this case_study from the queue again in case someone was impatient
    $abr->delete_case_study_zip_request( $case_study );
}
