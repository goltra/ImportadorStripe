## Versión 0.3
- Al convertir las fechas a timestamp para usarlas en los filtros de stripe, reseteo horas y minutos para que siempre sean
00:00:00
- InvoiceStripe-> processInvoicesObject Se modifica la función para que tenga en cuenta los impuestos que se han definido en 
la factura de stripe.
## Versión 0.4
- Importar los descuentos que se producen cuando la suscripcion tiene un bono
- Cambio en la forma de obtener el impuesto. Esta versión da por hecho que el iva viene dado por la factura (impuesto por defecto)
y no por cada linea. Si es necesario se cambiará en futuras versiones.
## Versión 0.5
- Ahora se aplica el impuesto tanto si viene de lineas como si viene como impuesto por defecto en facturas. Prevalece el de linea
- En listado de productos establezco el limit a 1000 en caso que no se haya mandado ninguno.
## Versión 0.6
- A la hora de generar una factura en FS se tiene en cuenta si el cliente en el campo "regimen iva" tiene el varlo "Exencto"
en cuyo caso no se aplican impuestos.
- El descuento aplicado por un cupón aparece en las factura de FS como dtopor1
## Versión 0.7
- Correcciones PHP 7.4
## Versión 0.8
-- Correcciones PHP 8
## Versión 0.9
-- Correcciones PHP 8
## Versión 1
-- Correcciones PHP 8
## Versión 1.1
-- Sustitución clase BusinessDocumentTools por Calculator
-- Correcciones para fs 2022.60

