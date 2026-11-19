<?php
/** Runs against a disposable SQLite file created and removed by this script. */
declare(strict_types=1);
$testDb=sys_get_temp_dir().'/artesanias-checkout-'.bin2hex(random_bytes(8)).'.sqlite';
putenv('DB_CONNECTION=sqlite');putenv('DB_DATABASE='.$testDb);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\Database as DB;
use App\Services\Checkout;
function check(bool $ok,string $name):void{if(!$ok)throw new RuntimeException('FALLÓ: '.$name);echo "OK $name\n";}
function rejects(callable $f,string $name):void{try{$f();}catch(DomainException $e){check(true,$name);return;}throw new RuntimeException('No se rechazó: '.$name);}
try{
 $db=DB::connection();$db->exec(file_get_contents(ROOT.'/database/sqlite.sql'));require ROOT.'/database/seed.php';
 $client=(int)DB::one("SELECT id FROM users WHERE role='cliente'")['id'];$seller=(int)DB::one("SELECT id FROM users WHERE role='vendedor'")['id'];
 $p=DB::one('SELECT * FROM products WHERE stock>2 LIMIT 1');$id=(int)$p['id'];$price=cents((string)$p['price']);$before=(int)$p['stock'];$s=new Checkout($db);$key=fn()=>bin2hex(random_bytes(32));
 rejects(fn()=>$s->place($client,[],$key()),'carrito vacío');
 rejects(fn()=>$s->place($seller,[$id=>['quantity'=>1,'price_cents'=>$price]],$key()),'vendedor no compra');
 foreach([0,-1,100,'1'] as $q)rejects(fn()=>$s->place($client,[$id=>['quantity'=>$q,'price_cents'=>$price]],$key()),'cantidad inválida '.json_encode($q));
 rejects(fn()=>$s->place($client,[$id=>['quantity'=>$before+1,'price_cents'=>$price]],$key()),'sin sobreventa');
 rejects(fn()=>$s->place($client,[$id=>['quantity'=>1,'price_cents'=>$price+1]],$key()),'precio modificado');
 DB::run('UPDATE products SET published=0 WHERE id=?',[$id]);rejects(fn()=>$s->place($client,[$id=>['quantity'=>1,'price_cents'=>$price]],$key()),'producto oculto');DB::run('UPDATE products SET published=1 WHERE id=?',[$id]);
 $op=$key();$order=$s->place($client,[$id=>['quantity'=>2,'price_cents'=>$price]],$op);
 check((int)DB::one('SELECT stock FROM products WHERE id=?',[$id])['stock']===$before-2,'descuento exacto de inventario');
 check(cents((string)DB::one('SELECT total FROM orders WHERE id=?',[$order])['total'])===$price*2,'importe calculado en servidor');
 check($s->place($client,[],$op)===$order,'confirmación idempotente');
 check((int)DB::one('SELECT COUNT(*) n FROM orders')['n']===1,'un solo pedido');
 $db->exec("CREATE TRIGGER force_failure BEFORE INSERT ON order_items BEGIN SELECT RAISE(ABORT,'simulated write failure'); END");
 try{$s->place($client,[$id=>['quantity'=>1,'price_cents'=>$price]],$key());throw new RuntimeException('No falló la escritura');}catch(PDOException $e){}
 check((int)DB::one('SELECT stock FROM products WHERE id=?',[$id])['stock']===$before-2,'rollback de inventario tras fallo de escritura');
 check((int)DB::one('SELECT COUNT(*) n FROM orders')['n']===1,'rollback de cabecera tras fallo de escritura');
 check(!password_verify('incorrecta',DB::one('SELECT password_hash FROM users WHERE id=?',[$client])['password_hash']),'contraseña protegida');
 echo "Todas las pruebas de transacción pasaron.\n";
}finally{unset($db);foreach([$testDb,$testDb.'-wal',$testDb.'-shm'] as $f)if(is_file($f))unlink($f);}
