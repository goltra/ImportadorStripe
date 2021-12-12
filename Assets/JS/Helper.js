function muestraSpinner(mostrar = true) {
    if (mostrar === true) {
        $("#container-spinner").show();
        $("#spinner").show();
    }else {
        $("#container-spinner").hide();
        $("#spinner").hide();
    }
}
