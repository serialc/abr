<?php
# filename: server_settings.php
# purpose: provide a summary of ABR settings

include('abrlib.php');
$abr = new abrlib();

$class = 'col-md-2 col-sm-4 col-6 text-center setting';
print $abr->response('<div class="' . $class . '"><p>Maximum file size</p><p>' . MAX_FILE_UPLOAD_SIZE/1024/1024 . ' MB</div>' .
    '<div class="' . $class . '"><p>Daily free quota</p><p>' . API_KEY_DAILY_REQUEST_QUOTA_LIMIT . '</div>' .
    '<div class="' . $class . '"><p>Requests per minute</p><p>' . REQUESTS_PER_MINUTE . '</div>' .
    '<div class="' . $class . '"><p>Requests delay</p><p>' . REQUEST_DELAY_MICROSECONDS/1000000 . 's</div>' .
    '<div class="' . $class . '"><p>Quota bypass priority</p><p>' . (QUOTA_BYPASS_PRIORITY_LIMIT + 1) . '</div>' .
    #'<div class="' . $class . '"><p>Directions units</p><p>' . GMD_UNITS . '</div>' .
    '<div class="' . $class . '"><p>Traffic model</p><p>' . GMD_TRAFFIC_MODEL . '</div>');

?>
