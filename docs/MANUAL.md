# Manual de uso y operación

## Visitante y comprador

Abre la colección para buscar productos. Puedes combinar texto, categoría y ordenación. Abre una ficha para revisar material, utilidad, historia, imagen, precio y existencias. El registro pide nombre, correo único y contraseña de 10 a 72 bytes con confirmación. El perfil asignado es comprador.

Después de ingresar, selecciona cantidad y agrega piezas al carrito. Puedes actualizar cantidades o retirar productos. Confirma solo después de revisar el total. La aplicación comprueba precio y stock nuevamente; si algo cambia, vuelve al carrito para revisarlo.

En **Mi cuenta → Mis pedidos** encontrarás el detalle, el importe registrado y el correo de cada taller. Coordina pago y entrega con ellos. Confirmar no genera un cargo bancario ni una guía de envío.

## Vendedor

El administrador crea tu cuenta de vendedor. Ingresa y abre **Mi taller**. Usa **Publicar una pieza** y completa nombre, categoría, municipio, material, utilidad, historia, precio, existencias e imagen. Adjunta una imagen propia/autorizada y confirma que tienes autorización para el contenido. Puedes aportar una referencia del relato.

Las imágenes admitidas son JPG, PNG y WebP, máximo 5 MB y 20 millones de píxeles; el servidor genera una imagen WebP de hasta 1800 píxeles por lado. Una ficha requiere al menos una imagen y campos completos. La categoría Decoración permite productos adicionales de la región.

En **Editar** puedes cambiar la ficha, actualizar existencias, reemplazar la imagen u ocultar la pieza sin borrar pedidos anteriores. Si el inventario cambió mientras editabas, abre de nuevo el formulario y utiliza los valores actuales. No recargues inventario con una pantalla antigua.

En **Pedidos de mi taller** aparecen exclusivamente tus líneas vendidas, la cantidad, el importe y el contacto del comprador. Coordina pago y entrega fuera de la plataforma. Esta versión conserva el estado de pedido pendiente de coordinación; no tiene un flujo de logística o liquidación bancaria.

## Administrador

En **Administración** puedes buscar cuentas por nombre/correo. **Crear cuenta** permite asignar comprador, vendedor o administrador. En **Editar** cambia nombre, correo, rol, estado y, si hace falta, contraseña. Deja la contraseña vacía para conservar la actual.

**Dar de baja** solicita confirmación, desactiva el acceso y conserva el historial. Una cuenta inactiva no inicia sesión y pierde las sesiones vigentes al hacer una nueva solicitud. Reactívala con la casilla **Cuenta activa**. Las piezas de vendedores inactivos no reciben compras nuevas.

El sistema impide dejar la plataforma sin administradores activos y evita que te retires tu propio acceso administrativo. Usa una segunda cuenta autorizada para reasignar el acceso de un administrador. Las operaciones de usuarios quedan en `audit_log`.

## Respaldo y despliegue

- Respaldar la base MySQL junto con `storage/uploads/`; ambos son necesarios para restaurar productos, pedidos e imágenes. Conservar `.env` mediante un gestor seguro de secretos, separado del repositorio.
- Para una copia consistente de MySQL usar una cuenta de respaldo autorizada y `mysqldump --single-transaction`. No colocar contraseñas en comandos que queden en el historial; usar un archivo de opciones protegido o un gestor de credenciales.
- Probar la restauración en una base distinta antes de sustituir una instalación.
- Configurar HTTPS, DocumentRoot `public/`, permisos limitados y `APP_ENV=production`. Publicar la identidad del operador, contacto y políticas comerciales antes de admitir clientes reales.
- Mantener PHP y la base de datos actualizados. Revisar logs sin publicarlos. Evitar almacenar tarjetas o datos sensibles en fichas y notas.
- El carrito se conserva en la sesión del navegador; no se sincroniza entre dispositivos y vence con la sesión.

## Solución de problemas

**“No pudimos conectar con el catálogo”.** Revisa las variables DB, que el servicio esté activo y que hayas ejecutado `bin/install.php`. El detalle técnico se escribe en `storage/logs/php.log`, si la carpeta tiene permisos de escritura.

**“could not find driver”.** Habilita `pdo_mysql` para MySQL o `pdo_sqlite` para demo en el PHP de la terminal/servidor que estás usando.

**No sube la imagen.** Comprueba GD/WebP, `fileinfo`, permisos en `storage/uploads/`, límites de PHP, tamaño y dimensiones. El formulario no conserva el archivo seleccionado después de un error: vuelve a adjuntarlo.

**404 en Apache.** Revisa `mod_rewrite`, `AllowOverride All`, DocumentRoot `public/` y `.htaccess`. Si estás usando una subruta, configura `APP_BASE_PATH`.

**No se mantiene la sesión local.** Usa `APP_ENV=local` con HTTP. `production` exige HTTPS porque las cookies se marcan Secure.

**Puerto 8080 ocupado.** Ejecuta manualmente el servidor con otro puerto y la configuración de base que corresponda. `bin/demo.php` utiliza siempre 8080; no altera servicios existentes.
