var datos;
var tableLines;
function getData() {
    let data = {action: 'get-data'
        , desFec: $("#date-from").val()
        , hasFec: $("#date-to").val()
        , codsubcuenta: $("#codsubcuenta").val()
    };
    $.ajax({
        type: 'POST',
        url: '',
        dataType: 'json',
        async: true,
        data: data,
        success: function (results) {
            datos = results;
            showLines();
        },
        error: function (xhr, status, error) {
            console.log("error al obtener el mayor");
            console.log(error);
        }
    });
}
function showLines()
{
    tableLines = new Tabulator("#data", {
        height: 500,
        width: "100%",
        data: datos,
        layout: "fitColumns",
        columns: [
            {title: "Fecha", field: "fecha", width: 150, visible: true, headerFilter: "input"},
            {title: "Concepto", field: "concepto", width: 600, visible: true, headerFilter: "input"},
            {title: "Debe", field: "debe", width: 100, visible: true, hozAlign: 'right', formatter: "money", formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    symbol: "",
                    symbolAfter: "p",
                    precision: 2
                }, bottomCalc: "sum", bottomCalcParams: {
                    precision: 2
                }},
            {title: "Haber", field: "haber", width: 100, visible: true, hozAlign: 'right', formatter: "money", formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    symbol: "",
                    symbolAfter: "p",
                    precision: 2,
                }, bottomCalc: "sum", bottomCalcParams: {
                    precision: 2
                }},
            {title: "Saldo", field: "saldo", width: 100, visible: true, hozAlign: 'right', formatter: "money", formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    symbol: "",
                    symbolAfter: "p",
                    precision: 2,
                }},
            {title: "Punteado", field: "punteada", formatter: "tickCross", formatterParams: {
                    allowEmpty: false,
                    allowTruthy: false,
                    tickElement: "<i class='fa fa-check'></i>",
                    crossElement: "<i class='fa fa-times'></i>",
                }}
        ]
    });

}
$(document).ready(function () {
    $("#cuenta").autocomplete({
        source: function (request, response) {
        $.ajax({
            url: location.href,
            type: 'POST',
            dataType:'JSON',
            data:{
                    search: request.term,
                    action: 'searchcuenta'
            },
            success:function(data){
                response(data);
            }

            
            });
        },

        select:function(event, ui){
        $("#cuenta").val(ui.item.label);
                $("#codsubcuenta").val(ui.item.value);
                return false;
        },
        focus:function(event, ui){
        $("#cuenta").val(ui.item.label);
                $("#codsubcuenta").val(ui.item.value);
                return false;
        }
    });
});