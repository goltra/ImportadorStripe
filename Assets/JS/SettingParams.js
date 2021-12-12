
$(function(){
    $('#frm_sk').submit(function(event){
        if($("#name").val()==='' || $("#sk").val()===''){
            alert('Debe especificar el nombre y el sk antes de guardar');
            event.preventDefault();
        }
    });
});

