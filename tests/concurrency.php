<?php
/** DB_CONNECTION=mysql DB_DATABASE=..._test php tests/concurrency.php --confirm-test */
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\Database as DB;
use App\Services\Checkout;
if(($argv[1]??'')==='--worker'){
 $start=(float)$argv[4];while(microtime(true)<$start)usleep(1000);
 try{$id=(new Checkout(DB::connection()))->place((int)$argv[2],[(int)$argv[3]=>['quantity'=>1,'price_cents'=>1250]],bin2hex(random_bytes(32)));echo 'BOUGHT '.$id;}
 catch(DomainException $e){echo 'REJECTED';}exit;
}
if(!in_array('--confirm-test',$argv,true)||!str_ends_with(env('DB_DATABASE'),'_test')||DB::connection()->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'){fwrite(STDERR,"Solo en una base MySQL desechable terminada en _test, con --confirm-test.\n");exit(1);}
$tag=bin2hex(random_bytes(6));$users=[];$pid=null;$db=DB::connection();
try{
 foreach(['vendedor','cliente','cliente'] as $index=>$role){DB::run('INSERT INTO users(name,email,password_hash,role,active,created_at) VALUES(?,?,?,?,1,?)',['Concurrency '.$tag,$tag.$index.'@demo.test',password_hash('TestPass2026!',PASSWORD_DEFAULT),$role,date('Y-m-d H:i:s')]);$users[]=(int)$db->lastInsertId();}
 DB::run('INSERT INTO products(seller_id,name,material,utility,history,municipality,category,price,stock,source,published,image,illustrative,version,created_at) VALUES(?,?,?,?,?,?,?,12.50,1,?,1,?,1,1,?)',[$users[0],'Última unidad '.$tag,'Madera','Prueba de compra','Prueba de concurrencia','Tlaxcala','madera','Prueba','/assets/products/mascara.svg',date('Y-m-d H:i:s')]);$pid=(int)$db->lastInsertId();
 $jobs=[];$start=(string)(microtime(true)+2);
 foreach([$users[1],$users[2]] as $buyer){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'--worker',(string)$buyer,(string)$pid,$start],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$jobs[]=[$proc,$pipes];}
 $results=[];
 foreach($jobs as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);if($exit!==0||$err)throw new RuntimeException($err);$results[]=$out;}
 $wins=count(array_filter($results,fn($v)=>str_starts_with($v,'BOUGHT ')));$fails=count(array_filter($results,fn($v)=>$v==='REJECTED'));
 $stock=(int)DB::one('SELECT stock FROM products WHERE id=?',[$pid])['stock'];$orders=(int)DB::one('SELECT COUNT(*) n FROM order_items WHERE product_id=?',[$pid])['n'];
 if($wins!==1||$fails!==1||$stock!==0||$orders!==1)throw new RuntimeException('Sobreventa o resultados inesperados: '.json_encode($results));
 echo "OK Dos procesos compiten por una unidad: un pedido, un rechazo, stock cero.\n";
}finally{
 if($pid){$orders=DB::all('SELECT order_id FROM order_items WHERE product_id=?',[$pid]);DB::run('DELETE FROM order_items WHERE product_id=?',[$pid]);foreach($orders as $o)DB::run('DELETE FROM orders WHERE id=?',[$o['order_id']]);DB::run('DELETE FROM products WHERE id=?',[$pid]);}
 foreach(array_reverse($users) as $u)DB::run('DELETE FROM users WHERE id=?',[$u]);
}
