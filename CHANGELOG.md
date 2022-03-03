## Version 0.3
- Al convertir las fechas a timestamp para usarlas en los filtros de stripe, reseteo horas y minutos para que siempre sean
00:00:00
- InvoiceStripe-> processInvoicesObject Se modifica la función para que tenga en cuenta los impuestos que se han definido en 
la factura de stripe.
