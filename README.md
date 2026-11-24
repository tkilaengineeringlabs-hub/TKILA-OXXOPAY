# Cannaland OXXO PAY v1.0.21

Pasarela OXXO PAY para WooCommerce de **Cannaland**, desarrollada por **Tkila Intelligence Labs** sobre el plugin de Digital FEMSA. La versión 1.0.21 conserva la lógica funcional de pagos de v1.0.15 y añade el ticket PNG con código de barras generado y validado localmente.

## Funcionalidades

- Referencias OXXO PAY con vigencia de 48 horas y pedidos pendientes hasta la confirmación validada.
- Webhooks existentes vinculados al identificador remoto guardado del pedido.
- Integración TeraWallet conservada: guard de checkout y soporte de pago dividido para staging. El split permanece deshabilitado por defecto y depende de `TKILA_OXXO_WALLET_SPLIT_STAGING`; no se habilita automáticamente en producción.
- Pantalla personalizada con importe, pedido, referencia completa, código de barras, vencimiento e instrucciones, además de acceso a Google Maps.
- Ticket PNG de alta resolución con el diseño de la tarjeta y logotipos locales, sin botones ni contador regresivo.
- Generación local de Code 128, conservando la referencia exacta y los ceros iniciales. El código del PNG final se decodifica antes de permitir la descarga; si no coincide, no se descarga un ticket incompleto.
- Exportación sin solicitudes a FEMSA, imágenes externas ni servicios de terceros. Las bibliotecas JavaScript y sus licencias están incluidas en el plugin.

## Instalación o actualización

Requisitos: WordPress, WooCommerce activo y PHP **8.1 o posterior**. Las pruebas de integración simuladas utilizan fuentes fijadas de WordPress 6.8.3 y WooCommerce 10.3.5; esto no sustituye la validación del sitio y sus extensiones.

1. Descarga **`cannaland-oxxo-pay-v1.0.21.zip`** del paquete de entrega correspondiente a la versión 1.0.21. Utiliza ese archivo instalable; el ZIP automático de código fuente de GitHub no incorpora todas las dependencias de ejecución.
2. En WordPress, abre **Plugins → Añadir nuevo → Subir plugin** y selecciona el ZIP. Para actualizar, reemplaza la versión existente sin desinstalarla.
3. Activa WooCommerce y Cannaland OXXO PAY.
4. Configura el entorno y sus credenciales en **WooCommerce → Ajustes → Pagos → OXXO PAY**. Las credenciales se configuran únicamente en el sitio; no se incluyen en este repositorio.
5. Configura el webhook existente `https://tusitio.com/?wc-api=wc_spin` en Digital FEMSA, sustituyendo el dominio.
6. Comprueba creación de referencia, confirmación y descarga del ticket en staging antes de usar pagos reales.

La publicación en GitHub no despliega el plugin ni modifica la configuración de WordPress.

## TeraWallet

La versión conserva íntegros los archivos funcionales de v1.0.15. El soporte de staging exige las condiciones y marcas del débito original; no cambia el saldo, los cálculos ni el ciclo de reembolso de TeraWallet. Consulta [la investigación de compatibilidad](docs/terawallet-compatibility.md), [las pruebas](tests/README.md) y las limitaciones de validación antes de habilitar un split.

## Pruebas y empaquetado

La [documentación de recuperación](RECOVERY.md) describe las dependencias, la base congelada v1.0.15 y las verificaciones. El código del exportador está en `resources/js/ticket/export.js`; el bundle distribuido, en `assets/js/oxxo-ticket.js`.

Las verificaciones incluyen:

- SHA-256 de los 313 PHP de referencia, permitiendo únicamente las excepciones autorizadas de plantilla y cabecera de versión.
- Siete suites PHP sobre importes, checkout, ambiente, TeraWallet, renderizado y propiedad de webhooks.
- Compilación reproducible con versiones fijadas de las bibliotecas locales.
- Chrome real con descarga y decodificación de PNG: escritorio, móvil emulado, recursos bloqueados, ceros iniciales y referencias largas.
- Rechazo de referencias inválidas, fallos de canvas o logotipo y PNG incompletos o corruptos.
- Comparación del Code 128 generado con el ejemplo de imagen publicado por Digital FEMSA.
- Validación de estructura, archivos e integridad del ZIP instalable.

Con la base de pruebas y las dependencias descritas en `RECOVERY.md` preparadas:

```sh
NODE_PATH=/ruta/dependencias-ticket/node_modules:/ruta/dependencias-pruebas/node_modules \
SPIN_TEST_SOURCES=/ruta/hook-sources \
python3 scripts/package-recovery.py
```

El paquete incorpora el SDK PHP y los assets de ejecución. Excluye `node_modules`, pruebas, herramientas de desarrollo y archivos temporales. La base ZIP de v1.0.15 bajo `tests/fixtures` es una referencia de integridad para desarrollo, no el instalador actual.

Las pruebas de pagos son simuladas; las pruebas PNG utilizan navegador real y decodificación por software. No acreditan una transacción real con FEMSA, aceptación física en caja ni una instalación posterior al cambio en Horsements.

## Licencia y atribución

Desarrollado originalmente en México por [Digital FEMSA](https://www.digitalfemsa.com) y adaptado para Cannaland por Tkila Intelligence Labs. Se conservan la [licencia MIT original](LICENSE) y los [avisos de las bibliotecas del ticket](assets/js/oxxo-ticket.LICENSE.txt).
