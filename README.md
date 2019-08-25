# ABR - Advanced Batch Routing
Advanced batch routing - a more comprehensive tool than [FBR](https://github.com/serialc/FBR) (Friendly Batch Routing)

## Installation
It's recommended you do not run this publicly as submitted Google API keys can be exposed.

1. Create DB tables.
2. Configure php/constants.php file.
3. Configure CRON to perform tasks:  
\# Process requests  
&ast; &ast; &ast; &ast; &ast; /usr/local/bin/php ~/www/abr/php/main.php  
\# Request zipping of bundle of queries  
&ast; &ast; &ast; &ast; &ast; /usr/local/bin/php ~/www/abr/php/zip_case_study.php  
\# Log drive space usage  
10,40 &ast; &ast; &ast; &ast; /usr/local/bin/php ~/www/abr/php/analytics_data_collector.php

