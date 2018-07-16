<?php

# DB connection parameters
define('DB_SERVER', 'localhost');
define('DB_USER', 'test');                      # customize
define('DB_PASS', 'super_secret_db_passwd');    # customize 
define('DB_NAME', 'abr_project_db');            # customize

# DB Table names
define('TABLE_REQUESTS', 'requests');
define('TABLE_APIKEY_BLOCK', 'keyblock');
define('TABLE_ZIP_QUEUE', 'zip_queue');
define('TABLE_STATE', 'process');
define('TABLE_UPLOADING', 'upload_processing');

# paths to files/folders
define("ABR_PATH", "/Users/cyrille/Sites/AdvancedBatchRouting/abr/php");        # customize
define("TEMP_FILE_HOLDER", ABR_PATH . "upload_temp");
define("RESULTS_FILE_HOLDER", ABR_PATH . "results");
define("ZIP_DIRECTORY", ABR_PATH . "zip");
define("LOG_FILE_PATH", ABR_PATH . "log_files/");
define("ABR_LOG_FILE", LOG_FILE_PATH . "abr_log_file.txt");
define("ABR_REQUESTS_COUNT_LOG_FILE", LOG_FILE_PATH . "abr_requests_count_log_file.txt");
define("ABR_DISK_USAGE_FILE", LOG_FILE_PATH . "disk_usage.txt");
define("ABR_DISK_USAGE_LOG_FILE", LOG_FILE_PATH . "disk_usage_log_file.txt");

# configuration options for ABR
define("INPUT_REQUEST_HEADERS", "case_study;id;origin;destination;departure_datetime;mode;instructions;priority;apikey");
define("MAX_FILE_UPLOAD_SIZE", 1024 * 1024 * 45);   # X MB
define("API_KEY_DAILY_REQUEST_QUOTA_LIMIT", 1290);  # In month with 31 days this means 40,000 requests - the maximum number of free requests
define("REQUEST_DELAY_MICROSECONDS", 100000);       # millionth of a second - 500000 is 1/2 second
define("REQUESTS_PER_MINUTE", 250);                 # the maximum number of requests that will be attempted per minute
define("RUNNING_TIME_PER_MINUTE", 55);              # seconds that ABR should run per minute
define("REQUEST_TIMEOUT_SECONDS", 5);               # This can delay things too much... careful as it may cause the same problem as the line above.
define("QUOTA_BYPASS_PRIORITY_LIMIT", 5);           # The priority level that will ignore whether you have bypassed your free 24 quota
define("OUTPUT_FIELD_SEPARATOR", "\t");
define("VALIDATION_BATCH_PROCESSING_SECONDS", 2);
define("VALIDATION_PREFIX", 'validated_');
define("MAP_PREVIEW_REQUESTS_LIMIT", 10000);        # ABR will try and display your requests as long as there are fewer than this
define("DISK_QUOTA_IN_GB", 8);                      # How much space will you allocate to ABR - stops processing if disk usage reaches this
define("PROCESS_LOCKING_ENABLED", false);           # Buggy and disabled

# routing variables
define("GMD_URL", "https://maps.googleapis.com/maps/api/directions/json?");
define("GMD_REGION", "");                   # See: https://developers.google.com/maps/documentation/directions/intro#RegionBiasing
define("GMD_UNITS", "metric");              # metric or imperial, see link below
define("GMD_TRAFFIC_MODEL", "best_guess");  # See: https://developers.google.com/maps/documentation/directions/intro#optional-parameters
define("GMD_LANGUAGE", "en");               # See: https://developers.google.com/maps/faq#languagesupport

# Server side on/off control for ABR
# This and the mysql (web controlled) controls must be enabled for ABR to run
define("ABR_PROCESS_CONTROL", true); # should be set to true, set to false to force stop
define("DEBUG_LOGGING", false);
?>
