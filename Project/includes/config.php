<?php
// Media root for course content (videos, subtitles).
// Lives outside the project so multi-GB media never enters the repo.
// Used by stream.php, subtitle.php, course.php and import-course.php.
if (!defined('MEDIA_ROOT')) {
    define('MEDIA_ROOT', 'C:/xampp/htdocs/PHP-ing3');
}
