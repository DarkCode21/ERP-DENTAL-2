$(document).ready(function () {
    var input = $('#formEditAlbaranClienteTrazabilidadNew input[name="fecha"], #formEditFacturaClienteTrazabilidadNew input[name="fecha"]');

    // si existe el input lo eliminamos
    if (input.length) {
        input.parent().parent().remove();
    }
});