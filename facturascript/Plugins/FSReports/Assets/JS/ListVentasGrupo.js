var datos;
var tableLines;
function getDatosVentas()
{
    let tipodesglose = $("input:radio[name=tipodesglose]:checked").val();

    let data = {action: 'get-datos-venta'
        , desFec: $("#date-from").val()
        , hasFec: $("#date-to").val()
        , codgrupo: $("#codgrupo").val()
        , codagente: $("#codagente").val()
        , tipodesglose: tipodesglose
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
            switch (tipodesglose) {
                case 'r':
                    showResumen();
                    break;
                case 'd':
                    showLines();
                    break;
                case 'sp':
                    showSeries();
                    break;
            }



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
        groupBy: "grupo",
        groupHeader: [
            function (value, count, data) {
                return value + "<span style='color:#000; margin-left:10px;'></span>";
            }
        ],
        columns: [

            {title: "Grupo", field: "grupo", width: 100, visible: false, headerFilter: "input"},
            {title: "cliente", field: "nombrecliente", width: 400, visible: true, headerFilter: "input"},
            {title: "Ventas", field: "importe", width: 150, visible: true, hozAlign: 'right', formatter: "money", formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    symbol: "",
                    symbolAfter: "p",
                    precision: 2,
                }, bottomCalc: "sum", bottomCalcParams: {
                    precision: 2,
                }},
            {title: "Cantidad", field: "unid", width: 150, visible: true, hozAlign: 'right', formatter: "money", formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    symbol: "",
                    symbolAfter: "p",
                    precision: false,
                }},
            {title: "Precio Medio", field: "pvmedio", width: 150, visible: true, hozAlign: 'right', formatter: "money", formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    symbol: "",
                    symbolAfter: "p",
                    precision: false,
                }},
            {title: "Ult. fecha", field: "ultimafecha", width: 200, visible: true},
        ]
    });

}
function showResumen()
{
    tableLines = new Tabulator("#data", {
        height: 500,
        width: "100%",
        data: datos,
        layout: "fitColumns",
        columns: [
            {title: "Grupo", field: "grupo", width: 300, visible: true, headerFilter: "input"},
            {title: "Ventas", field: "importe", width: 150, visible: true, hozAlign: 'right', formatter: "money", formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    symbol: "",
                    symbolAfter: "p",
                    precision: 2
                }, bottomCalc: "sum", bottomCalcParams: {
                    precision: 2
                }},
            {title: "Cantidad", field: "unid", width: 150, visible: true, hozAlign: 'right', formatter: "money", formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    symbol: "",
                    symbolAfter: "p",
                    precision: false,
                }},
            {title: "Precio Medio", field: "pvmedio", width: 150, visible: true, hozAlign: 'right', formatter: "money", formatterParams: {
                    decimal: ",",
                    thousand: ".",
                    symbol: "",
                    symbolAfter: "p",
                    precision: 2
                }},
            {title: "Ult. fecha", field: "ultimafecha", width: 200, visible: true},
        ]
    });

}
function showSeries()
{

        tableLines = new Tabulator("#data", {
            height: 500,
            width: "100%",
            data: datos,
            layout: "fitColumns",
            groupBy: "serie",
            groupHeader: [
                function (value, count, data) {
                    return value + "<span style='color:#000; margin-left:10px;'></span>";
                }
            ],
            columns: [

                {title: "Serie", field: "serie", width: 100, visible: false, headerFilter: "input"},
                {title: "referencia", field: "referencia", width: 150, visible: true, headerFilter: "input"},
            {title: "descripcion", field: "descripcion", width: 400, visible: true, headerFilter: "input"},
            {title: "Cantidad", field: "cuantos", width: 150, visible: true, hozAlign: 'right', formatter: "money", formatterParams: {
                        decimal: ",",
                        thousand: ".",
                        symbol: "",
                        symbolAfter: "p",
                        precision: false,
                    }},
            {title: "Ventas", field: "simporte", width: 150, visible: true, hozAlign: 'right', formatter: "money", formatterParams: {
                        decimal: ",",
                        thousand: ".",
                        symbol: "",
                        symbolAfter: "p",
                        precision: 2,
                    }, bottomCalc: "sum", bottomCalcParams: {
                        precision: 2,
                    }},

                
                
            ]
        });
    }
$(document).ready(function () {
    getDatosVentas();
});





