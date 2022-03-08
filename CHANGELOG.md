## Version 0.3
- Al convertir las fechas a timestamp para usarlas en los filtros de stripe, reseteo horas y minutos para que siempre sean
00:00:00
- InvoiceStripe-> processInvoicesObject Se modifica la función para que tenga en cuenta los impuestos que se han definido en 
la factura de stripe.
## Version 0.4
- Importar los descuentos que se producen cuando la suscripcion tiene un bono
- Cambio en la forma de obtener el impuesto. Esta versión da por hecho que el iva viene dado por la factura (impuesto por defecto)
y no por cada linea. Si es necesario se cambiará en futuras versiones.
