# Manual de uso — Importador Stripe

Plugin para FacturaScripts que genera facturas de cliente en FacturaScripts a partir de las
facturas existentes en Stripe, de forma manual o automática (webhooks), y que puede crear
remesas a partir de los pagos (payouts) recibidos.

---

## Índice

1. [Qué hace el plugin](#1-qué-hace-el-plugin)
2. [Requisitos](#2-requisitos)
3. [Instalación](#3-instalación)
4. [Conceptos previos](#4-conceptos-previos)
5. [Configuración (Ajustes)](#5-configuración-ajustes)
6. [Vincular clientes](#6-vincular-clientes)
7. [Vincular productos](#7-vincular-productos)
8. [Importar facturas manualmente](#8-importar-facturas-manualmente)
9. [Importación automática por webhooks](#9-importación-automática-por-webhooks)
10. [Remesas automáticas (payouts)](#10-remesas-automáticas-payouts)
11. [Verifactu](#11-verifactu)
12. [Cola de transacciones](#12-cola-de-transacciones)
13. [Logs](#13-logs)
14. [Preguntas frecuentes y resolución de problemas](#14-preguntas-frecuentes-y-resolución-de-problemas)
15. [Anexo: datos que escribe en Stripe](#15-anexo-datos-que-escribe-en-stripe)

---

## 1. Qué hace el plugin

- Asocia los **clientes** de Stripe con los clientes de FacturaScripts.
- Asocia los **productos** de Stripe con los artículos de FacturaScripts.
- Genera **facturas de cliente** en FacturaScripts a partir de las facturas de Stripe:
  - **Manualmente**, desde el listado de facturas pendientes.
  - **Automáticamente**, mediante webhooks (suscripciones, pagos con tarjeta, cobros SEPA…).
- Opcionalmente crea **remesas SEPA** a partir de los payouts recibidos en el banco.
- Opcionalmente envía las facturas generadas a **Verifactu**.

---

## 2. Requisitos

- FacturaScripts 2026 (o superior).
- Una cuenta de **Stripe** y su **clave secreta** (`sk_...`).
- Que FacturaScripts sea accesible desde Internet para poder recibir los **webhooks** de Stripe.
- Opcional:
  - Plugin **RemesasSEPA** (para generar remesas con los pagos).
  - Plugin **Verifactu** (para enviar las facturas a Verifactu).

---

## 3. Instalación

1. Copia la carpeta `ImportadorStripe` en `Plugins/` de FacturaScripts.
2. Actívalo en **Admin → Plugins**.
3. Instala sus dependencias (SDK de Stripe) si no vienen incluidas:

   ```bash
   cd Plugins/ImportadorStripe
   composer install
   ```

Tras activarlo aparece un nuevo menú **Stripe** con las secciones: *Ajustes*, *Clientes*,
*Productos*, *Facturas* y *Cola de transacciones*.

---

## 4. Conceptos previos

- **Cuenta de Stripe (sk):** cada clave secreta que des de alta en el plugin representa una cuenta
  de Stripe. Puedes tener varias y cada una puede facturar en una **serie** distinta.
- **Vinculación:** el plugin guarda en los metadatos de Stripe el código del cliente/artículo de
  FacturaScripts asociado. Así, cada vez que llega una factura, sabe a quién facturar y con qué
  artículo.
- **Cola de transacciones:** los webhooks no generan la factura directamente; la dejan en una cola
  que se procesa por cron o manualmente. Esto evita timeouts y pagos simultáneos.
- **Webhook:** canal de comunicación de Stripe hacia FacturaScripts. Cada cuenta de Stripe tiene su
  propia URL con un token.

---

## 5. Configuración (Ajustes)

Entra en **Stripe → Ajustes**. Hay tres bloques:

### 5.1 Cuentas de Stripe (claves secretas)

Formulario para dar de alta una cuenta:

| Campo | Descripción |
|---|---|
| **Nombre de la cuenta** | Identificador interno en FacturaScripts (ej. `Tienda`, `Suscripciones`). |
| **Clave secreta (sk_xxx)** | Clave secreta de Stripe. No se vuelve a mostrar una vez guardada. |
| **Serie** | Serie de FacturaScripts en la que se facturarán los cobros de esa cuenta. |

Al guardar, en la lista inferior verás por cada cuenta:

- **Webhook Facturas:** `(tu dominio)/WebhookStripe?source=TOKEN`
- **Webhook Remesas:** `(tu dominio)/WebhookStripeRemesasSepa?source=TOKEN` (solo si tienes
  activadas las remesas y el plugin RemesasSEPA).

### 5.2 Configuración general

| Ajuste | Para qué sirve |
|---|---|
| **Código del cliente por defecto** | Cliente de FacturaScripts que se usará cuando el cliente de Stripe no esté vinculado. |
| **Código del producto por defecto** | Artículo que se usará cuando el producto de Stripe no esté vinculado. |
| **Enviar email al cliente cuando se genere la factura** | Si está en *Sí*, se envía la factura por email al cliente (solo flujos automáticos). |
| **Mostrar el cliente de Stripe en la factura** | Si está en *Sí*, añade la referencia del cliente de Stripe a las observaciones de la factura. |
| **Email técnico** | Recibe los avisos de error de los webhooks (cola, incobrables…). |
| **Email administrativo** | Recibe información de contabilidad (remesa creada, remesa procesada, errores de factura…). |
| **Generar remesa con las facturas pagadas** | Activa la creación de remesas desde los payouts. Requiere el plugin RemesasSEPA. |
| **Id de la cuenta bancaria** | Cuenta bancaria donde llegan las transferencias de Stripe (necesaria para la remesa). |
| **Enviar facturas a Verifactu** | Si está en *Sí*, las facturas se envían a Verifactu. Requiere el plugin Verifactu. |

> El **cliente y producto por defecto** son importantes: si no están configurados y llega una
> factura de un cliente/producto no vinculado, la factura quedará con avisos en las observaciones.

---

## 6. Vincular clientes

**Stripe → Clientes**:

1. Selecciona la cuenta de Stripe (y opcionalmente un email para filtrar).
2. Pulsa **Consultar**; se listan los clientes de Stripe.
3. Pulsa el **“+”** del cliente para asociarlo a un cliente de FacturaScripts.
4. Se abre el listado de clientes de FacturaScripts; usa el buscador y pulsa **Seleccionar**.

El plugin guarda el código del cliente en los metadatos de Stripe (`fs_idFsCustomer`). La próxima
factura de ese cliente ya se facturará a nombre del cliente correcto.

También puedes cambiar la vinculación desde el listado de facturas (icono del lápiz) o crear un
cliente nuevo desde el asistente de importación.

---

## 7. Vincular productos

**Stripe → Productos**:

1. Selecciona la cuenta de Stripe.
2. Pulsa **Consultar**; se listan los productos de Stripe.
3. Pulsa el **“+”** para asociarlo a un artículo de FacturaScripts.
4. Busca el artículo y pulsa **Seleccionar**.

Se guarda el código del artículo en los metadatos del producto (`fs_idProduct`). Si una factura
contiene un producto no vinculado, se usará el **producto por defecto** y se avisará en las
observaciones.

---

## 8. Importar facturas manualmente

**Stripe → Facturas**:

1. Selecciona la cuenta de Stripe y, si quieres, un rango de fechas.
2. Pulsa **Consultar**. Se muestran las facturas de Stripe **pagadas**, con importe mayor que 0 y
   que **aún no han sido facturadas** en FacturaScripts.
3. Pulsa el **“+”** de la factura. El asistente:

   - Si el cliente de Stripe **no** está vinculado, te permite:
     - **Crear un cliente nuevo** en FacturaScripts, o
     - **Usar un cliente existente**.
   - Si el cliente ya está vinculado, muestra el formulario de creación:
     - **Pagada** (Sí/No): si *Sí*, el recibo se marca como pagado.
     - **Forma de pago**: se aplica si marcas *Pagada*.
     - **Enviar factura por email** (Sí/No).
4. Pulsa **Crear Factura**.

Al generarla, el plugin escribe el número de factura en los metadatos de la factura de Stripe
(`fs_idFactura`), de forma que no vuelva a aparecer como pendiente.

---

## 9. Importación automática por webhooks

La generación automática requiere configurar un webhook en Stripe que avise a FacturaScripts cada
vez que se pague una factura.

### 9.1 Crear el webhook en Stripe

En **Stripe → Desarrolladores → Webhooks → Añadir destino**:

- **URL:** la que aparece en *Ajustes* para esa cuenta:
  `(tu dominio)/WebhookStripe?source=TOKEN`
- **Eventos a escuchar:**
  - `invoice.finalized` — pago **SEPA** domiciliado (se factura al finalizar la factura).
  - `invoice.paid` — pago con **tarjeta** u otros métodos.
  - `invoice.marked_uncollectible` — factura marcada como **incobrable** (envía un email al
    administrador).
- **Versión de la API:** `2020-08-27` (la que usa el plugin).

### 9.2 Cómo se procesan

1. Cuando Stripe paga una factura, llama a tu FacturaScripts y **encola** la transacción
   (*Cola de transacciones*, estado **Pendiente**).
2. La cola se procesa:
   - Automáticamente por el **cron** (cada 5 minutos), o
   - Manualmente desde *Cola de transacciones* (botón **Procesar**).
3. Al procesar, se genera la factura en FacturaScripts igual que en la importación manual, pero
   **sin marcarla como pagada** (el cobro se refleja con las remesas o manualmente).

**Reglas de los webhooks:**

- Si el importe es **0 €**, no se factura.
- `invoice.finalized` solo se encola si el método de pago es **SEPA** (`sepa_debit`).
- `invoice.paid` solo se encola si el método **no** es SEPA (tarjeta, etc.).
- Si el cliente/producto no están vinculados, se usan los **valores por defecto** y se anota en
  las observaciones.

### 9.3 Cron

El plugin registra una tarea cron llamada `procesar-cola-pagos-stripe` que se ejecuta **cada 5
minutos**. Necesitas tener el cron de FacturaScripts funcionando (haciendo ping al cron).

---

## 10. Remesas automáticas (payouts)

Permite crear una remesa con todas las facturas incluidas en un pago (payout) que Stripe ingresa
en tu banco. **Requiere el plugin RemesasSEPA** y tener activada la opción
*Generar remesa con las facturas pagadas*.

### Configurar el webhook de remesas

En **Stripe → Desarrolladores → Webhooks → Añadir destino**:

- **URL:** `(tu dominio)/WebhookStripeRemesasSepa?source=TOKEN`
- **Evento:** `payout.paid`
- **Versión de la API:** `2020-08-27`

### Funcionamiento

1. Cuando Stripe confirma el pago al banco (`payout.paid`), el plugin:
   - Crea una **remesa** con la descripción y fecha del pago.
   - Encola cada factura incluida en ese payout.
   - Envía un email **administrativo** con el total y las líneas encoladas.
2. El cron va procesando las líneas y las asigna a la remesa.
3. Cuando no quedan líneas pendientes, la remesa pasa a estado **Revisar**, se calcula el total y
   se envía otro email administrativo.
4. Revisa la remesa y márcala como pagada: los recibos quedarán **cobrados**.

> Es importante que las facturas del payout ya estén vinculadas en Stripe (es decir, que el webhook
> de facturas esté configurado y funcionando).

---

## 11. Verifactu

Si tienes instalado y activado el plugin **Verifactu** y marcas *Enviar facturas a Verifactu* en los
ajustes, las facturas generadas por webhook se crean en estado **Verifactu** (en lugar de
**Emitida**) siempre que el cliente esté vinculado.

---

## 12. Cola de transacciones

**Stripe → Cola de transacciones** muestra todas las transacciones recibidas por los webhooks.

**Campos:**

| Campo | Descripción |
|---|---|
| **Cuenta de stripe** | Cuenta desde la que llegó el evento. |
| **Tipo de evento** | `Pago` (payout) o `Suscripcion` (factura cobrada). |
| **Evento** | `po_...` (payout) o `in_...` (factura). |
| **Fecha del pago** | Fecha en que se produjo la acción. |
| **Transacción** | `in_...` (factura), `ch_...` (cargo), `pi_...` (payment intent). |
| **Tipo de destino** | `Cliente` (factura) o `Remesa` (payout). |
| **Destino** | Id del cliente de Stripe o id de la remesa. |
| **Estado** | `Pendiente`, `Procesado` o `Error`. |
| **Tipo de error** | Descripción del error, si lo hubo. |
| **Fecha** | Fecha en que se añadió a la cola. |

- Las filas en estado **Pendiente** se pueden procesar con el botón **Procesar** (de una en una).
- Si hay un **Error**, revisa el tipo de error y los logs.
- Puedes filtrar por cuenta, evento, destino y estado.

---

## 13. Logs

El plugin escribe ficheros de log en la **raíz de FacturaScripts**:

| Fichero | Contenido |
|---|---|
| `invoice-log.txt` | Todo el proceso de importación/creación de facturas. |
| `remesa-sepa-log.txt` | Todo el proceso de creación de remesas. |
| `stripe-log.txt` | Otros mensajes genéricos. |

Estos ficheros son la primera parada para diagnosticar cualquier problema.

---

## 14. Preguntas frecuentes y resolución de problemas

**No aparece ninguna factura al pulsar «Consultar».**
Comprueba que las facturas en Stripe estén **pagadas**, tengan importe **mayor que 0** y que no
hayan sido facturadas ya (no tengan el metadato `fs_idFactura`).

**Una factura se ha creado con el cliente por defecto.**
El cliente de Stripe no estaba vinculado. Configura el **cliente por defecto**, vincula el cliente
correcto y cambia el cliente de la factura manualmente si es necesario.

**Una factura se ha creado con el producto por defecto.**
El producto de Stripe no estaba vinculado. Vincúlalo desde *Stripe → Productos* y revisa la factura.

**El webhook no llega.**
- Verifica que FacturaScripts es accesible desde Internet.
- Comprueba la URL y el `source=TOKEN` en *Ajustes*.
- En Stripe, revisa el historial de entregas del webhook.
- Revisa `invoice-log.txt`.

**Las transacciones se quedan en «Pendiente».**
El cron no está ejecutándose. Configura el cron de FacturaScripts o pulsa **Procesar** manualmente.

**No se genera la remesa.**
Comprueba que el plugin RemesasSEPA está activo, que la opción está en *Sí* y que has indicado la
cuenta bancaria. Revisa `remesa-sepa-log.txt`.

**No se envían los emails.**
Revisa la configuración de correo de FacturaScripts y los emails técnico/administrativo de los
ajustes.

---

## 15. Anexo: datos que escribe en Stripe

El plugin utiliza **metadatos** en Stripe para recordar las vinculaciones:

| Objeto de Stripe | Metadato | Contenido |
|---|---|---|
| Cliente (`customer`) | `fs_idFsCustomer` | Código del cliente de FacturaScripts. |
| Producto (`product`) | `fs_idProduct` | Referencia/código del artículo de FacturaScripts. |
| Factura (`invoice`) | `fs_idFactura` | Código de la factura generada en FacturaScripts. |

No borres estos metadatos: son los que permiten no duplicar facturas y saber a quién facturar.

---

*Este manual describe el comportamiento del plugin Importador Stripe. Para dudas o incidencias,
revisa primero los ficheros de log indicados en el apartado 13.*
