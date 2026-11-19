<article class="product-card">
  <a class="product-image image-<?= e($item['category']) ?>" href="<?= url('/producto/' . $item['id']) ?>"><img src="<?= image_url($item['image']) ?>" alt="<?= e($item['name']) ?>" loading="lazy" width="600" height="660"><span class="product-tag"><?= e(\App\Models\Product::CATEGORIES[$item['category']] ?? 'Artesanía') ?></span><?php if (!(int) $item['stock']): ?><span class="stock-tag">Agotado</span><?php endif; ?><span class="discover-link">Conocer la pieza</span></a>
  <div class="product-meta"><span><?= e($item['municipality']) ?></span><span><?= (int) $item['stock'] ?> disponibles</span></div>
  <div class="product-title-row"><h3><a href="<?= url('/producto/' . $item['id']) ?>"><?= e($item['name']) ?></a></h3><span class="product-price"><?= money(cents((string) $item['price'])) ?></span></div>
  <p class="product-material"><?= e($item['material']) ?></p>
</article>
