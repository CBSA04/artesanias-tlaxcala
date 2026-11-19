<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Database as DB;
use App\Models\Product;
use App\Services\Upload;

final class SellerController {
    public function index(): void {
        $u = \require_role('vendedor');
        $products = DB::all('SELECT * FROM products WHERE seller_id=? ORDER BY id DESC', [$u['id']]);
        $stats = DB::one('SELECT COUNT(*) AS products, COALESCE(SUM(stock),0) AS stock, COALESCE(SUM(CASE WHEN published=1 THEN 1 ELSE 0 END),0) AS published FROM products WHERE seller_id=?', [$u['id']]);
        $sales = DB::all('SELECT o.id, o.created_at, i.product_name, i.quantity, i.subtotal, u.name AS buyer_name, u.email AS buyer_email FROM order_items i JOIN products p ON p.id=i.product_id JOIN orders o ON o.id=i.order_id JOIN users u ON u.id=o.user_id WHERE p.seller_id=? ORDER BY o.id DESC, i.id DESC', [$u['id']]);
        \view('seller', compact('products', 'stats', 'sales') + ['title' => 'Mi taller']);
    }
    public function form(?int $id = null): void {
        $u = \require_role('vendedor'); $product = null;
        if ($id) { $product = DB::one('SELECT * FROM products WHERE id=? AND seller_id=?', [$id, $u['id']]); if (!$product) \abort_page(404, 'Este producto no pertenece a tu taller.'); }
        \view('product-form', ['product' => $product, 'categories' => Product::CATEGORIES, 'title' => $id ? 'Editar producto' : 'Una nueva pieza']);
    }
    public function save(?int $id = null): void {
        $u = \require_role('vendedor'); $product = $id ? DB::one('SELECT * FROM products WHERE id=? AND seller_id=?', [$id, $u['id']]) : null;
        if ($id && !$product) \abort_page(404, 'Este producto no pertenece a tu taller.');
        $old = [];
        foreach (['name', 'material', 'utility', 'history', 'municipality', 'category', 'price', 'stock', 'source', 'version'] as $field) $old[$field] = \input($field);
        $old['published'] = \input('published') === '1' ? 1 : 0;
        $newImage = null;
        try {
            foreach (['name' => 150, 'material' => 1000, 'utility' => 2000, 'history' => 6000, 'municipality' => 100] as $field => $max) {
                if (mb_strlen($old[$field]) < 2 || mb_strlen($old[$field]) > $max) throw new \DomainException('Completa todos los campos de la ficha y respeta las longitudes indicadas.');
            }
            if (!isset(Product::CATEGORIES[$old['category']])) throw new \DomainException('Selecciona una categoría válida.');
            $price = \cents($old['price']);
            if ($price < 1 || $price > 99999999) throw new \DomainException('El precio debe estar entre $0.01 y $999,999.99.');
            if (!ctype_digit($old['stock']) || (int) $old['stock'] > 999999) throw new \DomainException('Las existencias deben ser un entero entre 0 y 999999.');
            if (mb_strlen($old['source']) > 2000) throw new \DomainException('La referencia del contenido debe tener hasta 2000 caracteres.');
            if (\input('rights') !== '1') throw new \DomainException('Confirma que cuentas con autorización para publicar la imagen y la información.');
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) $newImage = Upload::image($_FILES['image']);
            $image = $newImage ?? $product['image'] ?? null;
            if (!$image) throw new \DomainException('Adjunta al menos una imagen del producto.');
            $illustrative = $newImage ? 0 : (int) ($product['illustrative'] ?? 0);
            $values = [$old['name'], $old['material'], $old['utility'], $old['history'], $old['municipality'], $old['category'], \decimal($price), (int) $old['stock'], $old['source'], $old['published'], $image, $illustrative];
            if ($id) {
                $version = \valid_id($old['version']);
                $q = DB::run('UPDATE products SET name=?, material=?, utility=?, history=?, municipality=?, category=?, price=?, stock=?, source=?, published=?, image=?, illustrative=?, version=version+1 WHERE id=? AND seller_id=? AND version=?', [...$values, $id, $u['id'], $version]);
                if ($q->rowCount() !== 1) throw new \DomainException('Este producto cambió mientras lo editabas. Vuelve a abrirlo para revisar el inventario actual.');
            } else {
                DB::run('INSERT INTO products (name, material, utility, history, municipality, category, price, stock, source, published, image, illustrative, seller_id, version, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)', [...$values, $u['id'], date('Y-m-d H:i:s')]);
            }
            if ($newImage && $product) Upload::remove($product['image']);
            \flash($id ? 'Guardamos los cambios de tu producto.' : 'Tu nueva pieza ya forma parte de tu taller.'); \redirect('/vendedor');
        } catch (\DomainException $e) {
            if ($newImage) Upload::remove($newImage);
            \view('product-form', ['product' => $product, 'old' => $old, 'categories' => Product::CATEGORIES, 'title' => $id ? 'Editar producto' : 'Una nueva pieza', 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) { if ($newImage) Upload::remove($newImage); throw $e; }
    }
}
