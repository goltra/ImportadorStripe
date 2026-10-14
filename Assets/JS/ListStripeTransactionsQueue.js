/**
 * Cuando se procesa una línea cuya factura de Stripe no está vinculada, el controlador
 * recarga la página con el parámetro linkInvoice=<id>. Aquí abrimos el modal para que el
 * usuario elija la factura de FacturaScripts, la vincule en Stripe y procese la línea.
 */
$(function () {
    var params = new URLSearchParams(window.location.search);
    var code = params.get('linkInvoice');
    if (!code) {
        return;
    }

    var modalElement = document.getElementById('modalstripe-link');
    if (!modalElement) {
        return;
    }

    var modalForm = modalElement.closest('form');
    if (!modalForm) {
        return;
    }

    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'codes[]';
    input.value = code;
    modalForm.appendChild(input);

    var listForm = document.getElementById('formListStripeTransactionsQueue');
    if (listForm) {
        // El modal se renderiza fuera del formulario del listado, así que su formulario no
        // incluye los filtros ni la búsqueda. Los copiamos para que, al enviarlo, el controlador
        // conserve los filtros en lugar de vaciarlos.
        var fields = listForm.querySelectorAll(
            'input[name^="filter"], select[name^="filter"], input[name="query"],' +
            'input[name="order"], input[name="offset"], input[name="loadfilter"]'
        );
        fields.forEach(function (field) {
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = field.name;
            hidden.value = field.value;
            modalForm.appendChild(hidden);
        });

        // La recarga de página pierde el check de la fila, así que lo volvemos a marcar.
        listForm.querySelectorAll('input.listAction[name="codes[]"]').forEach(function (checkbox) {
            if (checkbox.value === code) {
                checkbox.checked = true;
            }
        });
    }

    // Quitamos el parámetro de la URL para no reabrir el modal al recargar.
    params.delete('linkInvoice');
    var query = params.toString();
    window.history.replaceState({}, '', window.location.pathname + (query ? '?' + query : ''));

    new bootstrap.Modal(modalElement).show();
});
