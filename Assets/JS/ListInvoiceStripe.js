function sendInvoiceToProcess(id) {
    alert('hola');
    console.log('sendInvoiceToProcess ', id);
}

$(function () {
    var currentFIni = $("#f-ini-date").val();
    var date = new Date();
    var monthDay = 1;
    if (currentFIni !== '' && currentFIni.length === 10) {
        monthDay =parseInt(currentFIni.split('-')[2]);
        date = new Date(
            parseInt(currentFIni.split('-')[0]),
            (parseInt(currentFIni.split('-')[1]) - 1),
            monthDay
        );
    }

    var firstDay = new Date(date.getFullYear(), date.getMonth(), monthDay);
    var lastDay = new Date(date.getFullYear(), date.getMonth() + 1, 0);
    console.log('currentFIni', currentFIni.split('-'));
    console.log('currentFIni', currentFIni.length);



    $("#f-ini-date").val(firstDay.getFullYear().toString() + "-" + ("0" + (firstDay.getMonth() + 1)).toString().slice(-2) + "-" + ("0" + firstDay.getDate().toString()).slice(-2));
    $("#f-fin-date").val(lastDay.getFullYear().toString() + "-" + ("0" + (lastDay.getMonth() + 1)).toString().slice(-2) + "-" + ("0" + lastDay.getDate()).toString().slice(-2));
    $("#f-ini-date").on("input", () => {
        changeEndDate();
    });

})

function changeEndDate() {
    f_ini = $("#f-ini-date").val().split('-');
    if (f_ini.length > 0) {
        var lastDay = new Date(f_ini[0], f_ini[1], 0);
        //CONTROLAR QUE EL MES TENGA DOS DIGITOS.
        var txtDate = lastDay.getFullYear().toString() + "-" + ("0" + (lastDay.getMonth() + 1)).toString().slice(-2) + "-" + ("0" + lastDay.getDate()).toString().slice(-2);
        $("#f-fin-date").val(txtDate);
    }

}


