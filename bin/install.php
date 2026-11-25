<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\Database as DB;
try {
 $driver=DB::connection()->getAttribute(PDO::ATTR_DRIVER_NAME);
 DB::connection()->exec(file_get_contents(ROOT.'/database/'.($driver==='sqlite'?'sqlite':'mysql').'.sql'));
 echo "Estructura instalada ($driver).\n";
 if(in_array('--demo',$argv,true)){require ROOT.'/database/seed.php';echo "Catálogo y tres cuentas de demostración disponibles.\n";}
 echo "Instalación terminada.\n";
} catch(Throwable $e){fwrite(STDERR,"No se pudo instalar: ".$e->getMessage()."\n");exit(1);}
