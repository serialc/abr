# ABR - Advanced Batch Routing
Advanced batch routing - a more comprehensive tool than [FBR](https://github.com/serialc/FBR) (Friendly Batch Routing)

## Installation
It's recommended you do not run this publicly as submitted Google API keys can be exposed.

1. Create DB tables (see sql).
2. Configure php/constants.php file and rename as php/constants\_custom.php
  * You will need to define DB config.
  * Update the main path of the applicatioin.
  * Customize the directories to store various data - also create the directories.
3. Have all the necessary interfaces for MySQL, PHP, and Apache to communicate and operate.
  * For example: libapache2-mod-php, php7.3-mysql, php-zip, php-curl
  * Restart apache if necessary.
4. Configure CRON to perform tasks:  
\# Process requests  
&ast; &ast; &ast; &ast; &ast; /usr/local/bin/php ~/www/abr/php/main.php  
\# Request zipping of bundle of queries  
&ast; &ast; &ast; &ast; &ast; /usr/local/bin/php ~/www/abr/php/zip\_case\_study.php  
\# Log drive space usage  
10,40 &ast; &ast; &ast; &ast; /usr/local/bin/php ~/www/abr/php/analytics\_data\_collector.php
5. Test the above tasks in command line to check for any errors.
6. Add a small amount of data to test. Test using live and 'anytime' data.

