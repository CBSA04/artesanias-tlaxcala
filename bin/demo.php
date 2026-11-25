<?php
/** Local-only demo; no .env or MySQL needed. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);
foreach(['DB_CONNECTION'=>'sqlite','DB_DATABASE'=>$root.'/storage/demo.sqlite','APP_ENV'=>'local','APP_BASE_PATH'=>'','SHOW_DEMO_ACCOUNTS'=>'1'] as $k=>$v)putenv($k.'='.$v);
$argv=['install.php','--demo'];require $root.'/bin/install.php';
echo "\nAbre http://127.0.0.1:8080 en tu navegador.\nDetén el servidor con Ctrl+C.\n";
passthru(escapeshellarg(PHP_BINARY).' -S 127.0.0.1:8080 -t '.escapeshellarg($root.'/public').' '.escapeshellarg($root.'/public/router.php'));
