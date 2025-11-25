# Importador Stripe
Plugin para FacturaScripts:
- https://facturascripts.com/plugins/importadorstripe

## Descripción
Stripe es un sistema de pagos online que entre otras cosas nos permite gestionar cobros recurrentes de servicios a los 
que se suscriben nuestros clientes y generar las facturas correspondientes a esos cobros. El problema de esas facturas es
que puede que no nos sean utiles y tengamos que duplicar trabajo transcribiendolas a nuestro programa de facturación.
Este plugin permite crear facturas en Facturascripts de forma manual o automática a partir de las facturas que se generan en Stripe.
Además se pueden vincular artículos de Fs con los productos de Stripe.

## Como funciona
Para poder usar el plugin StripeInvoice es necesario configurar ciertos aspectos.

1. Desde el menú *Ajustes* se deben dar de alta las claves secretas que van a permitir a Facturascripts comunicarse con 
Stripe.  Podemos dar de alta varias claves en caso que tengamos varias cuentas de Stripe.

2. Desde el menú *Clientes* debemos vincular los clientes de Stripe con los clientes de Facturascript para poder generar las facturas y vincularlas.

3. Desde el menú *Productos* debemos vincular los productos de Stripe con productos de Facturascript con el de generar
las facturas de una forma coherente  (impuestos, cuentas contables, etc...). Si alguna de las facturas que intentamos
importar desde Stripe tiene productos que no tienen correlación con productos.

4. Ya solo nos queda ir a *Facturas*, seleccionar la cuenta de Stripe de la que deseamos importar y definir el intervalo 
de tiempo. Se cargarán las facturas que hay en Stripe pendientes de procesar. Solo tenemos que pulsar sobre el signo "más"
de la factura. El sistema comprobará si el cliente de la factura de Stripe tiene una correlación con un cliente de Facturascripts.
En caso afirmativo continuará, si no te dará la opción de asociarlo a un cliente existente o bien crear un nuevo cliente.
Por último podemos indicar si la factura generada tiene que darse por pagada y si se debe enviar por email a la dirección de la
ficha del cliente de Facturascripts.

5. Cola de transacciones: Aquí es donde estarán todas las transacciones realizadas en los webhooks que tengas configurados con stripe (Se explica más adelante)

## Menu ajustes
En el menú de ajustes tenemos dos secciones:

### Sección 1: Donde configuarmos las sk de stripe
Aquí agregaremos el nombre de la cuenta (el identificador interno que queramos tener en FS para saber desde que cuenta de Stripe ha llegado), el sk de stripe y la serie donde se van a crear las facturas.
Esto es así porque igual queremos que cada cuenta de Stripe se facture en una serie distinta.

### Sección 2: Configuración general del plugin
Aquí vamos a tener distintos parámetros a configurar en el caso que queramos usarlo de forma automática:
* Código del cliente por defecto: Cómo podemos tener el caso en que se genere una factura desde un cliente que no esté vinculado, podemos crear un cliente que sea "por defecto" para que lo vincule a esa factura y luego sólo necesitamos crear el cliente, vincularlo y cambiarlo en la factura de forma manual.
* Código del producto por defecto: Igual que el cliente, también puede pasar con un producto nuevo dado de alta en stripe que no lo hayamos vinculado.
* Enviar email al cliente cuando se genere la factura: Por si queremos mandar la factura al cliente una vez generada.
* Mostrar el cliente de stripe en la factura: Si se marca "Si", aparecerá el cliente de stripe en el campo observaciones de la factura.
* Email técnico para las comunicaciones de stripe: Email al que llegarán emails de error en caso que algo ha fallado.
* Email administrativo para las comunicaciones de stripe: Email al que llegará información de contabilidad, como por ejemplo la creación de una nueva remesa por un cobro de stripe.
* Generar remesas con las facturas pagadas: Si se marca "Si", activamos para que se puedan crear remesas desde un pago de stripe (Se explica más adelante)
* Id de la cuenta bancaria donde llegan las transferencias: Id de la cuenta bancaria donde llegan los pagos de stripe
* Enviar las facturas a Verifactu: Si queremos enviar las facturas a Verifactu (Se explica más adelante)

## Creación de facturas de forma automática
Si tienes mucha facturación recurrente en Stripe, igual te interesa que se generen las facturas de forma automática, es decir, que cada vez que tu cliente haga un pago de un servicio, producto o suscripción, se cree una factura en facturascripts.
Para ello, neceistas configurar un webhook en stripe, que no es más que un canal de comunicación entre Stripe y tu facturascripts. Hay que tener en cuenta que tu facturascripts tiene que estar en internet.

### ¿Cómo lo hago?
Para hacerlo es muy sencillo, por un lado, tienes que configurar como mínimo el cliente y producto por defecto en los ajustes del plugin y agregar el sk de stripe. 
Abajo en la tercera sección te aparecerá algo así como "(cuenta de stripe) >> Webhook facturas" y una url tipo /WebhookStripe?source=xxxx

Esa url es la que necesitamos configurar en stripe:
Para ello, nos vamos a Stripe >> Desarrolladores >> Webhooks y creamos un nuevo destino. 
Ajustes:
- Url: (dominio facturascripts)/WebhookStripe?source=xxx
- Evento: invoice.payment_succeeded
- Versión de la api: 2017-08-15

Y listo ya lo tienes contectado. En este apartado luego podrás ver los pings que te haga Stripe cada vez que se haya pagado una suscripción o producto.

#### ¿Y en facturascripts?
Cuando Stripe mande una factura a facturascripts por el webhook, esta factura se quedará en cola, ese apartado lo podrás ver en Menú >> Cola de transacciones.
Esta transacción la puedes ejecutar manualmente pulsando en el check y dandole al botón procesar o configurando un cron en tu servidor de forma que llame a tu facturascripts cada 5 min.

## Creación de remesas cuando recibo un cobro de Stripe
Si necesitas contrastar y tener reflejado que facturas pertenecen a cierto pago de stripe (payout a tu cuenta bancaria), nosotros hemos creado un sistema donde mediante otro webhook puedes agregar todas las facturas involucradas en un payout en una remesa. De modo que cuando te llegue el cobro del banco las puedas dar todas como pagadas de golpe.
Para ello es importante que sepas que tienes que tener todas las facturas vinculadas en stripe, por lo que te recomendamos que configures el anterior webhook.

### ¿Cómo lo hago?
1. Instalas el plugin remesasSEPA (https://facturascripts.com/plugins/remesassepa)
2. Te vas a Ajustes y activas la opción "Generar remesas con las facturas pagadas" y además agregas el número de cuenta (necesario para crear la remesa)
3. En la tercera sección en ajustes, ahora verás que en la cuenta de stripe aparece un segundo webhook /WebhookStripeRemesasSepa?source=xxx

Para ello, igual que en el apartado anterior, nos vamos a stripe y lo configuramos en Stripe >> Desarrolladores >> Webhooks y creamos un nuevo destino.
justes:
- Url: (dominio facturascripts)/WebhookStripeRemesasSepa?source=xxx
- Evento: payout.paid
- Versión de la api: 2017-08-15

Y listo, cuando stripe reciba el ok que se ha recibido el pago en el banco, llamará a tu facturascripts.

#### ¿Y en facturascripts?
Cuando llame Stripe a este webhook, mandará un payout. Por tanto, si todo es correcto, se creará una remesa, con id del payout y una descripción. 
Si está correcto, te llegará un email administrativo informando del proceso, total de la remesa y las líneas que ha metido en la cola.
Se procesarán todas las transacciones de ese payout y se agregará una linea a la cola para ser procesada posteriormente. 
Conforme vaya pasando el cron irá procesando las líneas y una vez que no queden más, se completará la remesa (te envia un email administrativo), calculando el valor (para que lo contrastes con el banco) y te la dejará preparada para que la des como pagada y todos las facturas se queden como cobradas.


## Cola de transacciones
En un principio la comunicación de con los webhooks era directa, pero teníamos problemas de timeouts o de pins que entraban al mismo tiempo y luego daba error al generar la factura porque tenían el mismo código.
Por tanto, decidimos crear la cola, que entre otras cosas, te da más control de lo que está pasando.

### Campos:
* Cuenta de stripe: Desde que cuenta ha llegado (la configurada en ajustes)
* Tipo de evento: Pago (payout) o suscripción (invoice pagado en stripe)
* Evento: po_xx si es un payout o in_xxx si es un invoice
* Fecha del pago: fecha que se hizo la acción
* Tipo de transacción: tipo de elemento que ha llegado de stripe, puede ser un paiment intent, un cargo, una factura
* Transacción: in_xxx si es factura, chx_xx si es un cargo, pi_xxx si es un payment intent
* Tipo de destino: cliente si es una factura ya que necesitamos el cus_xxx (por la vinculación) para crearla o remesa si es un payout para saber a que remesa agregarlo
* Destino: id del customer de stripe o id de la remesa
* Estado: Pendiente, procesada, error
* Tipo de error: si ha dado error, pues que error ha dado
* Fecha: fecha a la que se agrego en la cola.

En principio, si configuras el cron va a ir automático, pero puedes procesar una línea de forma manual siempre que no esté procesada.


## Logs
* Si al generar una factura da error, podemos ver todo el proceso en el fichero físico: invoice-log.txt que se guarda en el alojamiento
* Si al generar una remesa da error, podemos ver todo el proceso en el fichero físico: remesa-sepa-log.txt que se guarda en el alojamiento.
