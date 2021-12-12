$(document).ready(
    function () {
        $("input[type=checkbox]").click(function (ev) {
            $("input[type=checkbox]").prop('checked',false);
            ev.target.checked=true;
        });
    }
)

