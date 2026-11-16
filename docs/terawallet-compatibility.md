# TeraWallet + OXXO PAY: investigación y límite de compatibilidad

Revisión: 2026-11-16. Base: plugin Digital FEMSA 1.0.12, SDK incluido 1.1.0. No se realizaron solicitudes a los endpoints de pagos, ni se utilizaron credenciales. Las fuentes externas describen el código público consultado; no identifican la versión instalada en Cannaland.

## Cómo aplica TeraWallet el saldo

En el [frontend oficial de TeraWallet](https://github.com/malsubrata/woo-wallet/blob/master/includes/class-woo-wallet-frontend.php), `woo_wallet_add_partial_payment_fee()` agrega un fee negativo con ID `_via_wallet_partial_payment`. El importe solicitado se obtiene de sesión/saldo y se limita según el carrito. La modalidad fiscal puede conservar impuestos o incluirlos en el abono mediante un fee gravable. Por eso no se debe recalcular el abono usando únicamente el saldo actual del usuario.

El [registro de hooks y persistencia](https://github.com/malsubrata/woo-wallet/blob/master/includes/class-woo-wallet.php) conserva la clave del fee como `_legacy_fee_key`; el código también contempla `legacy_fee_key` antes de guardar. Reconocer un fee por su nombre traducido no sería fiable.

El [código del wallet](https://github.com/malsubrata/woo-wallet/blob/master/includes/class-woo-wallet-wallet.php) permite debitar al crear el pedido o al alcanzar un estado pagado. `debit_partial_payment_for_order()` usa la marca `_partial_pay_through_wallet_compleate` y controles propios para evitar duplicar débitos. El fee por sí solo no prueba que el dinero ya se haya debitado o reservado. Esta integración no escribe esas marcas ni debita o reembolsa el wallet.

El total persistido de WooCommerce ya incluye el fee. Restar otra vez el saldo de sesión produciría un cobro insuficiente. Los cupones ordinarios siguen siendo descuentos comerciales separados; los cupones de cashback requieren validar el comportamiento de la versión instalada.

## Qué está documentado por FEMSA

| Mecanismo | Evidencia | Límite para un wallet externo |
| --- | --- | --- |
| Productos, impuestos, envío y descuentos | [Create order](https://developers.digitalfemsa.io/reference/createorder) y modelos incluidos | Productos, impuestos y envíos no admiten importes negativos según los modelos. |
| `discount_lines` con `amount`, `code`, `type` | SDK `OrderDiscountLinesRequest`, tipos documentados `loyalty`, `campaign`, `coupon`, `sign` | No encontramos definición que autorice tratar saldo monetario externo como descuento comercial ni que defina `sign` como pago externo. |
| `charges.amount` | [Create charge](https://developers.digitalfemsa.io/reference/orderscreatecharge), SDK `ChargeRequest` | Existe el campo, pero no documenta que un cargo cash inferior al total cierre una orden financiada parcialmente fuera de FEMSA. Tampoco documenta cómo registrar ese abono externo. |
| Efectivo con referencia y vencimiento | [Cargo único](https://developers.digitalfemsa.io/docs/cargo-unico-efectivo-direct-api) | El ejemplo cobra el total del desglose. No demuestra soporte para pagos parciales con wallet externo. |
| `metadata` | Create order | Datos informativos; no reducen el monto ni registran un pago. |

La página enlazada de creación de descuentos no pudo recuperarse en esta revisión; los tipos indicados provienen del SDK distribuido con el plugin. No se extrapolan reglas de Conekta a Digital FEMSA.

## Decisión implementada

Se conserva el bloqueo de todo fee negativo antes de construir/enviar la orden. No se inventan descuentos, productos de saldo pendiente ni cargos de billetera. Los casos sin wallet continúan conciliando productos, cantidades, cupones, fees positivos, impuestos y envío contra el total persistido, en centavos exactos. Se mantienen todas las correcciones previas.

Saldo pendiente cero, pedido ya pagado o desglose discrepante también bloquean la creación de referencia. El bloqueo no cancela referencias anteriores ni revierte débitos que otro plugin haya realizado antes de entrar al gateway. Si TeraWallet debita al crear el pedido, ese escenario debe verificarse en staging antes de ofrecer la combinación a clientes. Las pruebas no representan el ciclo de vida real de TeraWallet.

## Alternativas concretas para consultar con Digital FEMSA

Ejemplo común: productos $100.00, cupón $10.00, wallet $20.00, saldo OXXO $70.00, sin impuestos ni envío.

1. **Cargo parcial con abono externo registrado.** ¿Puede una orden comercial por $90.00 recibir un cargo cash por $70.00 y registrar los $20.00 externos? Solicitar endpoint, campos, tipos admitidos y ejemplo completo. Confirmar cuándo emite `order.paid`, qué sucede al vencer, cómo concilia y cómo evita duplicados. La existencia de `charges.amount` no responde estas preguntas.
2. **Orden de cobro únicamente por el remanente.** ¿Permite el proveedor una orden de recaudación por $70.00 vinculada a la venta por $90.00, con concepto explícito de saldo pendiente? Solicitar reglas para desglose, IVA, envío, reportes y comprobantes. No implementarla como producto ficticio ni repartir arbitrariamente el wallet entre precios sin su aprobación.
3. **Ajuste reconocido por el proveedor.** ¿Alguno de los tipos documentados de `discount_lines` está destinado expresamente a abonos monetarios externos? Solicitar definición contractual/técnica y ejemplos. No asumir que `sign` o `loyalty` cumplen esa función.

Mientras no se confirme una alternativa, la opción operativa es pagar el pedido íntegramente por OXXO sin saldo parcial, o usar el flujo propio de TeraWallet cuando cubra el total. No se cambió automáticamente la configuración para imponer estas opciones.

## Verificación necesaria para habilitar la combinación

- Fijar versiones de WooCommerce/TeraWallet y revisar checkout clásico, Blocks y pago de pedido existente.
- Confirmar reserva/débito del wallet durante la vigencia de la referencia y evitar descontarlo nuevamente en reintentos.
- Probar impuestos del wallet, cupones normales/cashback, cantidades, envío y fees, con igualdad exacta de centavos en el payload y la referencia devuelta.
- Validar la representación acordada en sandbox FEMSA, junto con eventos de pago, expiración y cancelación. Si exige cambiar webhooks, será una fase separada autorizada.
- Probar fallos de transporte y concurrencia. La arquitectura actual no ofrece idempotencia general de creación de referencias; las pruebas añadidas cubren únicamente reintentos rechazados antes del envío.
