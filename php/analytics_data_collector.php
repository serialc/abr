<?php
# filename: calculate_disk_usage.php
# purpose: called by cron, calculates du usage for ABR

include('abrlib.php');
$abr = new abrlib();

$bytes = $abr->get_directory_size(ABR_PATH);

# save the size in Gibibyte 
$gbytes = round($bytes/1000000000, 2);
file_put_contents(ABR_DISK_USAGE_FILE, $gbytes);

# log as well
$fh = fopen(ABR_DISK_USAGE_LOG_FILE, "a");
fwrite($fh, time() . ',' . $gbytes . "\n");
fclose($fh);

# if too big turn off ABR
if( $gbytes > DISK_QUOTA_IN_GB ) {
    $abr->set_abr_status(0);
}

?>
