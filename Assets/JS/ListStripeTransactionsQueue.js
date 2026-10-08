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

    // Quitamos el parámetro de la URL para no reabrir el modal al recargar.
    params.delete('linkInvoice');
    var query = params.toString();
    window.history.replaceState({}, '', window.location.pathname + (query ? '?' + query : ''));

    new bootstrap.Modal(modalElement).show();
});
