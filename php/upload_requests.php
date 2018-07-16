<?php
# filename: upload_requests.php
# purpose: handles requests file upload 

include('abrlib.php');
$abr = new abrlib();

# get the passed variables/uploaded file details
$tmp_file_path = $_FILES['requests_text_file']['tmp_name'];
$fn = $_FILES['requests_text_file']['name'];
$size = $_FILES['requests_text_file']['size'];
$type = $_FILES['requests_text_file']['type'];

# check file size
if( $size > MAX_FILE_UPLOAD_SIZE ) {
    print($abr->response(["FILE SIZE ERROR: File is too large."], 400)) and die();
}
# check file type, should be 'text/plain'
if( $type !== 'text/plain' ) {
    print($abr->response(["FILE TYPE ERROR: File not a text file."], 400)) and die();
}

# temporarily save the file
$fn = basename($tmp_file_path);
$nfn = ltrim($fn, 'php') . '.txt';
$upload_dir = TEMP_FILE_HOLDER . '/';

# make sure moving the file is possible
if( is_dir($upload_dir) && is_writable($upload_dir) ) {
    move_uploaded_file($tmp_file_path, $upload_dir . $nfn);
} else {
    print($abr->response(["FILE ERROR: Unable to write file."], 500)) and die();
}

# count number of lines in the file
$handle = fopen($upload_dir . $nfn, "r");
$linecount = 0;
while( !feof($handle) ) {
    $line = fgets($handle);
    $linecount++;
}

fclose($handle);

# add to DB the temp file name, number of lines and processed lines (0)
#if( !$abr->add_upload_file($nfn, $linecount, 0) ) {
#    print($abr->response(["FILE UPLOAD ERROR: Unable to add file to DB for validation."], 500)) and die();
#}
# NOT NECESSARY, WE JUST PING PONG THE INFO BACK AND FORTH BETWEEN PHP AND AJAX

# send the temp file code and other info
print($abr->response([ 
    "outcome" => "uploaded",
    "filename" => $nfn,
    "lines" => $linecount
]));

?>
