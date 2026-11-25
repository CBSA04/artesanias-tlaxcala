<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\Database as DB;
echo "Crear administrador (utiliza una terminal privada).\n";
$name=trim(readline('Nombre: '));$email=mb_strtolower(trim(readline('Correo: ')));
if(PHP_OS_FAMILY!=='Windows')system('stty -echo');
try{$password=readline('Contraseña (10–72 bytes): ');}finally{if(PHP_OS_FAMILY!=='Windows')system('stty echo');echo "\n";}
if(mb_strlen($name)<2||mb_strlen($name)>100||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>120||password_error($password)){fwrite(STDERR,"Datos inválidos.\n");exit(1);}
try{DB::run('INSERT INTO users(name,email,password_hash,role,active,created_at) VALUES(?,?,?,\'administrador\',1,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT),date('Y-m-d H:i:s')]);echo "Administrador creado.\n";}catch(Throwable $e){fwrite(STDERR,"No se pudo crear. Verifica la conexión y que el correo sea único.\n");exit(1);}
