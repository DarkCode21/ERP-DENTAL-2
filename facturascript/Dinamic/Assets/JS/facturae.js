var OKsignInvoice = function (signatureB64, certificateB64, extraData) {
    AutoScript.saveDataToFile(signatureB64, "Guardar fichero", "fra-firmada", "xsig", "Doc firmado"
        , function () {
            alert("Fichero guardado correctamente");
        },
        function (errorType, errorMessage) {
            alert("Error al guardar fichero firmado: " + errorMessage);
        });

}
var KOsignInvoice = function (type, message) {
    alert("Error en la firma de la factura <br/>" + message);
}

function createFacturae() {
    document.f_factura_e.multireqtoken.value = document.f_factura_e.multireqtoken.value + "1";
    let data = $("#facturae>form").serialize() + "&sign=2";

    $.ajax({
        method: "POST",
        url: window.location.href,
        data: data,
        dataType: "json",
        success: function (results) {
            console.log(results);
            var dataB64 = AutoScript.getBase64FromText(results.content);
            AutoScript.sign(dataB64, "SHA512withRSA", "FacturaE", null, OKsignInvoice, KOsignInvoice);

        },
        error: function (msg) {
            alert(msg.status + " " + msg.responseText);
        }
    });
}

$(document).ready(function () {
    AutoScript.cargarAppAfirma();
});