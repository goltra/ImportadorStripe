# Importador Stripe

## Descripción
Stripe es un sistema de pagos online que entre otras cosas nos permite gestionar cobros recurrentes de servicios a los 
que se suscriben nuestros clientes y generar las facturas correspondientes a esos cobros. El problema de esas facturas es
que puede que no nos sean utiles y tengamos que duplicar trabajo transcribiendolas a nuestro programa de facturación.
Este plugin permite crear facturas en Facturascripts a partir de las facturas que se generan en Stripe.
Además se pueden vincular artículos de Fs con los productos de Stripe.

## Como funciona

Para poder usar el plugin StripeInvoice es necesario configurar ciertos aspectos.

1- Desde el menú *Ajustes* se deben dar de alta las claves secretas que van a permitir a Facturascripts comunicarse con 
Stripe.  Podemos dar de alta varias claves en caso que tengamos varias cuentas de Stripe.

2- Desde el menú *Productos* debemos vincular los productos de Stripe con productos de Facturascript con el de generar
las facturas de una forma coherente  (impuestos, cuentas contables, etc...). Si alguna de las facturas que intentamos
importar desde Stripe tiene productos que no tienen correlación con productos 

3- Ya solo nos queda ir a *Facturas*, seleccionar la cuenta de Stripe de la que deseamos importar y definir el intervalo 
de tiempo. Se cargarán las facturas que hay en Stripe pendientes de procesar. Solo tenemos que pulsar sobre el signo "más"
de la factura. El sistema comprobará si el cliente de la factura de Stripe tiene una correlación con un cliente de Facturascripts.
En caso afirmativo continuará, si no te dará la opción de asociarlo a un cliente existente o bien crear un nuevo cliente.
Por último podemos indicar si la factura generada tiene que darse por pagada y si se debe enviar por email a la dirección de la
ficha del cliente de Facturascripts.

