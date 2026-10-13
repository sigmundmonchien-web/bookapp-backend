<?php
echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . '<br>';
echo 'post_max_size: ' . ini_get('post_max_size') . '<br>';
echo 'php.ini utilisé: ' . php_ini_loaded_file();