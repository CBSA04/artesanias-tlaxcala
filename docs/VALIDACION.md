# Validación de la entrega

Fecha de comprobación: 18 de noviembre de 2026. Estas pruebas corresponden al código entregado, sin atribuirle una publicación o entrega comercial anterior.

## Entorno utilizado

- PHP 8.4.12 con PDO MySQL/SQLite, GD, fileinfo y mbstring.
- MariaDB 12.0.2 / InnoDB en una instancia aislada de pruebas, compatible con el acceso PDO MySQL de la aplicación.
- SQLite en bases desechables para la demostración y pruebas de transacción.
- Servidor integrado PHP en puertos locales separados para ambas bases.
- Python estándar para sesiones, formularios, archivos y comprobaciones HTTP.

## Resultados

| Comprobación | Resultado |
|---|---|
| Sintaxis de todos los archivos PHP | Correcta |
| Sintaxis de JavaScript de la aplicación y del script de navegador | Correcta |
| Instalación de esquema y datos SQLite | Correcta |
| Inicio con `php bin/demo.php` y respuesta HTTP 200 | Correcto |
| Instalación de esquema y datos sobre MariaDB/InnoDB | Correcta |
| Aceptación HTTP sobre SQLite | 42 comprobaciones correctas |
| Aceptación HTTP sobre MariaDB | 42 comprobaciones correctas |
| Servicio de compra con base temporal | 16 comprobaciones correctas |
| Dos procesos comprando la última unidad en InnoDB | Un pedido, un rechazo y existencias en cero |
| Docker/MySQL 8.4 exacto | Configuración incluida; no ejecutada en este entorno |
| Revisión visual en navegador de escritorio y móvil | Pendiente; el entorno bloqueó el proceso de Chromium |

Los registros completos de las pruebas ejecutadas están en `docs/resultados/`. Son salidas de pruebas reales, no actas de aceptación comercial.

## Casos cubiertos

Las pruebas HTTP verifican catálogo público, ficha completa, token CSRF, prohibición de compra anónima, registro, asignación obligatoria del rol comprador, carrito, confirmación e historial, idempotencia, privacidad del pedido, inicio de sesión por perfil, pedidos del vendedor, aislamiento entre vendedores, carga de imágenes, rechazo de SVG, edición de existencias, rechazo de formularios desactualizados, gestión de cuentas, revocación de sesiones, prohibición de acceso a cuentas inactivas, retiro del catálogo al desactivar vendedores, protección del último administrador, filtros, búsqueda, recursos, errores 404, limitación de intentos y cierre de sesión.

La prueba del servicio de compra valida cantidades negativas, cero, tipo incorrecto y mayores a 99; falta de existencias; cambio de precio; producto oculto; carrito vacío; rol incorrecto; descuento y total exactos; reenvío de operación y reversión completa después de provocar una falla real de escritura con un trigger en la base temporal.

La prueba de concurrencia inicia dos procesos PHP independientes con compradores distintos que compiten por una sola unidad en la misma base MariaDB. Solo una transacción puede registrar la compra. El script limpia los usuarios, producto y pedido que crea para este caso.

## Prueba visual incluida

El diseño utiliza CSS Grid, consultas de medios, navegación móvil, estados de foco, etiquetas de formularios, enlaces para saltar al contenido y soporte para movimiento reducido. Su implementación adaptable no equivale a una certificación de accesibilidad ni a una comprobación visual completada.

El inicio de Chrome y de Chromium Headless fue bloqueado por el entorno de ejecución de macOS con un error de permiso de MachPortRendezvous. Por ello **no se incluyen capturas simuladas ni se afirma que se revisó visualmente la interfaz**.

Se incluye `tests/browser.cjs` para reproducir la revisión en un equipo que permita iniciar Chromium. Con una base de demostración desechable y un servidor activo:

```bash
npm install
npx playwright install chromium
TEST_URL=http://127.0.0.1:8080 npm run test:browser -- --confirm-demo
```

El script recorre catálogo, producto, registro, carrito, pedido, cuenta, taller y administración; comprueba desbordamiento horizontal a 1440, 768, 390 y 350 píxeles; busca imágenes rotas y errores JavaScript, y guarda capturas reales en `docs/capturas/`. Esta prueba crea una cuenta y un pedido; requiere datos de demostración y existencias disponibles.

## Límites del alcance validado

No se probó una pasarela bancaria, envío de correos ni logística porque no están integrados en esta versión. Tampoco se publicó la aplicación en Internet. El operador debe configurar alojamiento, HTTPS, cuentas reales, contactos y políticas antes de abrirla al público. Las comprobaciones ejecutadas respaldan los flujos funcionales descritos; no sustituyen una auditoría externa de seguridad o una prueba de carga.
