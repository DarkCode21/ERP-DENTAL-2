var datos;
var tableLines;
function getData()
{


    let data = {action: 'get-data'

    };
    $.ajax({
        type: 'POST',
        url: '',
        dataType: 'json',
        async: true,
        data: data,
        success: function (results) {
            console.log(results);
            datos = results;
            showLines();
        },
        error: function (xhr, status, error)
        {
            console.log("error al cargar precintos");
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
        groupBy: "valor",
        groupHeader: [
            function (value, count, data) {
                return  "<span style='color:#000; margin-left:10px;'>Serie: </span>" + value;
            }
        ],
        columns: [

            {title: "serie", field: "valor", width: 100, visible: true, headerFilter: "input"},
            {title: "referencia", field: "referencia", width: 150, visible: true, headerFilter: "input"},
            {title: "descripcion", field: "descripcion", width: 300, visible: true, headerFilter: "input"},
            {title: "Stock", field: "stockfis", width: 100, visible: true, hozAlign: 'right', formatter: "number", formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    symbol: "",
                    symbolAfter: "p",
                    precision: 2,
                }, bottomCalc: "sum", bottomCalcParams: {
                    precision: 2,
                }},
            {title: "Res.", field: "reservada", width: 100, visible: true, hozAlign: 'right', formatter: "number", formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    symbol: "",
                    symbolAfter: "p",
                    precision: false,
                }},
            {title: "Pte.", field: "pterecibir", width: 100, visible: true, hozAlign: 'right', formatter: "number", formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    symbol: "",
                    symbolAfter: "p",
                    precision: false,
                }},
            {title: "pedido", field: "codigo", width: 150, visible: true},
            {title: "fecha", field: "fecha", width: 150, visible: true},
            {title: "cantidad", field: "cantpedida", width: 100, visible: true},

        ]
    });

}

$(document).ready(function () {
    getData();
});

