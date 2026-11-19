<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;
use PDO;
use DomainException;

final class Checkout {
    public function __construct(private PDO $db) {}
    private function query(string $sql, array $args): \PDOStatement { $q = $this->db->prepare($sql); $q->execute($args); return $q; }
    public function place(int $userId, array $cart, string $key): int {
        if (!preg_match('/^[a-f0-9]{64}$/D', $key)) throw new DomainException('La confirmación no es válida. Revisa tu carrito.');
        // Idempotencia: repetir la misma operación devuelve su pedido, incluso si ya se vació el carrito.
        $existing = $this->query('SELECT id FROM orders WHERE operation_key=? AND user_id=?', [$key, $userId])->fetchColumn();
        if ($existing) return (int) $existing;
        if (!$cart || count($cart) > 100) throw new DomainException('Agrega productos a tu carrito antes de confirmar.');
        ksort($cart, SORT_NUMERIC);
        try {
            $this->db->beginTransaction();
            $lock = Database::lock($this->db);
            $user = $this->query('SELECT role, active FROM users WHERE id=?' . $lock, [$userId])->fetch();
            if (!$user || !$user['active'] || $user['role'] !== 'cliente') throw new DomainException('Solo una cuenta compradora activa puede confirmar pedidos.');
            $total = 0; $lines = [];
            foreach ($cart as $id => $item) {
                if (!ctype_digit((string) $id) || (int) $id < 1 || !is_array($item)) throw new DomainException('El carrito contiene información no válida.');
                $quantity = $item['quantity'] ?? 0;
                if (!is_int($quantity) || $quantity < 1 || $quantity > 99) throw new DomainException('Las cantidades deben estar entre 1 y 99.');
                $product = $this->query('SELECT * FROM products WHERE id=?' . $lock, [(int) $id])->fetch();
                if (!$product || !$product['published']) throw new DomainException('Uno de los productos dejó de estar disponible. Revisa tu carrito.');
                if (!$this->query("SELECT id FROM users WHERE id=? AND active=1 AND role='vendedor'", [$product['seller_id']])->fetchColumn()) throw new DomainException('El taller de esta pieza ya no recibe pedidos.');
                if ((int) $product['stock'] < $quantity) throw new DomainException('No hay existencias suficientes de «' . $product['name'] . '». Ajusta la cantidad.');
                $price = \cents((string) $product['price']);
                if ($price !== ($item['price_cents'] ?? null)) throw new DomainException('Cambió el precio de «' . $product['name'] . '». Revisa el nuevo total antes de confirmar.');
                $subtotal = $price * $quantity; $total += $subtotal;
                if ($total > 9999999999) throw new DomainException('El pedido supera el importe permitido. Divide tu compra.');
                $lines[] = ['product' => $product, 'quantity' => $quantity, 'price' => $price, 'subtotal' => $subtotal];
            }
            $this->query('INSERT INTO orders (user_id, total, status, operation_key, created_at) VALUES (?, ?, ?, ?, ?)', [$userId, \decimal($total), 'pendiente', $key, date('Y-m-d H:i:s')]);
            $orderId = (int) $this->db->lastInsertId();
            foreach ($lines as $line) {
                $p = $line['product'];
                $updated = $this->query('UPDATE products SET stock=stock-?, version=version+1 WHERE id=? AND stock>=? AND published=1', [$line['quantity'], $p['id'], $line['quantity']]);
                if ($updated->rowCount() !== 1) throw new DomainException('Las existencias cambiaron. Actualiza el carrito e inténtalo otra vez.');
                $this->query('INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)', [$orderId, $p['id'], $p['name'], $line['quantity'], \decimal($line['price']), \decimal($line['subtotal'])]);
            }
            $this->db->commit();
            return $orderId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            // Dos solicitudes simultáneas con la misma clave nunca crean dos pedidos.
            $existing = $this->query('SELECT id FROM orders WHERE operation_key=? AND user_id=?', [$key, $userId])->fetchColumn();
            if ($existing) return (int) $existing;
            throw $e;
        }
    }
}
