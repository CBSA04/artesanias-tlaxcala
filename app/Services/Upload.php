<?php
declare(strict_types=1);
namespace App\Services;
use DomainException;

final class Upload {
    public static function image(array $file): string {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new DomainException('Selecciona una imagen válida de hasta 5 MB.');
        if (($file['size'] ?? 0) < 1 || $file['size'] > 5 * 1024 * 1024 || !is_uploaded_file($file['tmp_name'])) throw new DomainException('La imagen no es válida o supera los 5 MB.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) throw new DomainException('Solo se admiten imágenes JPG, PNG o WebP.');
        $info = @getimagesize($file['tmp_name']);
        if (!$info || $info[0] * $info[1] > 20000000 || $info[0] > 8000 || $info[1] > 8000) throw new DomainException('La imagen debe ser menor a 20 megapíxeles y 8000 píxeles por lado.');
        $image = @imagecreatefromstring(file_get_contents($file['tmp_name']));
        if (!$image) throw new DomainException('No se pudo procesar la imagen.');
        $max = 1800; $ratio = min(1, $max / max(imagesx($image), imagesy($image)));
        if ($ratio < 1) { $small = imagescale($image, max(1, (int) round(imagesx($image) * $ratio))); imagedestroy($image); $image = $small; }
        $name = bin2hex(random_bytes(16)) . '.webp';
        $path = ROOT . '/storage/uploads/' . $name;
        try {
            if (!imagewebp($image, $path, 85)) throw new \RuntimeException('No se pudo guardar la imagen.');
            chmod($path, 0640);
        } finally { imagedestroy($image); }
        return '/media/' . $name;
    }
    public static function remove(string $path): void {
        if (preg_match('#^/media/([a-f0-9]{32}\.webp)$#D', $path, $m)) @unlink(ROOT . '/storage/uploads/' . $m[1]);
    }
}
