<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Database as DB;
use App\Models\Product;
use App\Services\Checkout;

final class CartController {
    public function index(): void {
        \require_role('cliente');
        $lines = []; $total = 0; $ready = true;
        foreach ($_SESSION['cart'] ?? [] as $id => $item) {
            $product = Product::find((int) $id, false);
            $available = $product && $product['seller_active'] && $product['seller_role'] === 'vendedor' && $product['published'] && (int) $product['stock'] >= $item['quantity'];
            if ($product) $_SESSION['cart'][$id]['price_cents'] = \cents((string) $product['price']);
            $price = $product ? \cents((string) $product['price']) : $item['price_cents'];
            $subtotal = $price * $item['quantity']; $total += $subtotal;
            $lines[] = ['id' => $id, 'product' => $product, 'quantity' => $item['quantity'], 'price' => $price, 'subtotal' => $subtotal, 'available' => $available];
            $ready = $ready && $available;
        }
        $key = bin2hex(random_bytes(32));
        $_SESSION['checkout_keys'][$key] = hash('sha256', json_encode($_SESSION['cart'] ?? []));
        $_SESSION['checkout_keys'] = array_slice($_SESSION['checkout_keys'], -10, null, true);
        \view('cart', compact('lines', 'total', 'ready', 'key') + ['title' => 'Tu selección']);
    }
    private function quantity(): int {
        $raw = \input('quantity');
        if (!ctype_digit($raw) || (int) $raw < 1 || (int) $raw > 99) throw new \DomainException('Elige una cantidad entera entre 1 y 99.');
        return (int) $raw;
    }
    public function add(): void {
        \require_role('cliente');
        try {
            $id = \valid_id(\input('product_id')); $q = $this->quantity(); $product = Product::find($id);
            if (!$product) throw new \DomainException('El producto ya no está disponible.');
            $q += $_SESSION['cart'][$id]['quantity'] ?? 0;
            if ($q > 99 || $q > (int) $product['stock']) throw new \DomainException('La cantidad supera las existencias o el máximo de 99 unidades.');
            $_SESSION['cart'][$id] = ['quantity' => $q, 'price_cents' => \cents((string) $product['price'])];
            \flash('Agregaste «' . $product['name'] . '» a tu selección.');
        } catch (\DomainException $e) { \flash($e->getMessage(), 'error'); }
        \redirect('/carrito');
    }
    public function update(): void {
        \require_role('cliente');
        try {
            $id = \valid_id(\input('product_id')); $q = $this->quantity(); $p = Product::find($id);
            if (!isset($_SESSION['cart'][$id]) || !$p) throw new \DomainException('El producto ya no está disponible.');
            if ($q > (int) $p['stock']) throw new \DomainException('La cantidad supera las existencias actuales.');
            $_SESSION['cart'][$id] = ['quantity' => $q, 'price_cents' => \cents((string) $p['price'])];
            \flash('Actualizamos tu selección.');
        } catch (\DomainException $e) { \flash($e->getMessage(), 'error'); }
        \redirect('/carrito');
    }
    public function remove(): void {
        \require_role('cliente');
        try { unset($_SESSION['cart'][\valid_id(\input('product_id'))]); \flash('Quitamos el artículo de tu selección.', 'info'); }
        catch (\DomainException $e) { \flash($e->getMessage(), 'error'); }
        \redirect('/carrito');
    }
    public function checkout(): void {
        $user = \require_role('cliente'); $key = \input('operation_key');
        try {
            $prior = DB::one('SELECT id FROM orders WHERE operation_key=? AND user_id=?', [$key, $user['id']]);
            if ($prior) \redirect('/pedidos/' . $prior['id']);
            $cart = $_SESSION['cart'] ?? [];
            if (!isset($_SESSION['checkout_keys'][$key]) || !hash_equals($_SESSION['checkout_keys'][$key], hash('sha256', json_encode($cart)))) throw new \DomainException('Tu selección cambió. Revisa el carrito antes de confirmar.');
            $id = (new Checkout(DB::connection()))->place((int) $user['id'], $cart, $key);
            unset($_SESSION['cart']);
            \flash('Tu pedido quedó registrado. El pago y la entrega se coordinarán con los vendedores.');
            \redirect('/pedidos/' . $id);
        } catch (\DomainException $e) { \flash($e->getMessage(), 'error'); }
        catch (\PDOException $e) { error_log($e->getMessage()); \flash('No se pudo confirmar el pedido. No se realizó un cargo. Revisa tu selección e inténtalo de nuevo.', 'error'); }
        \redirect('/carrito');
    }
    public function orders(): void {
        $user = \require_role('cliente');
        $orders = DB::all('SELECT o.*, (SELECT COUNT(*) FROM order_items i WHERE i.order_id=o.id) AS items FROM orders o WHERE user_id=? ORDER BY id DESC', [$user['id']]);
        \view('orders', ['orders' => $orders, 'title' => 'Mis pedidos']);
    }
    public function order(int $id): void {
        $user = \require_role('cliente');
        $order = DB::one('SELECT * FROM orders WHERE id=? AND user_id=?', [$id, $user['id']]);
        if (!$order) \abort_page(404, 'No encontramos este pedido en tu cuenta.');
        $items = DB::all('SELECT i.*, p.image, p.illustrative, u.name AS seller_name, u.email AS seller_email FROM order_items i JOIN products p ON p.id=i.product_id JOIN users u ON u.id=p.seller_id WHERE i.order_id=? ORDER BY i.id', [$id]);
        \view('order', compact('order', 'items') + ['title' => 'Pedido #' . str_pad((string) $id, 5, '0', STR_PAD_LEFT)]);
    }
}
