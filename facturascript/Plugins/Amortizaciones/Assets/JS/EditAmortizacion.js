/**
 * This file is part of Amortizaciones plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Amortizaciones  Copyright (C) 2023-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */

/**
 *
 * @param {Event} event
 */
function setAmountFromInvoice(event) {
    let data = {
        action: 'invoice-data',
        invoice: event.target.value
    };

    $.ajax({
        method: "POST",
        url: window.location.href,
        data: data,
        dataType: "json",
        success: function (result) {
            document.querySelector('input[name="valor"]').value = result.base;
            document.querySelector('select[name="coddivisa"]').value = result.divisa;
        },
        error: function (msg) {
            alert(msg.status + " " + msg.responseText);
        }
    });
}

/**
 * Main process
 *   - Set event listener for invoice field to get invoice amount.
 */
$(document).ready(function () {
    let invoice = document.querySelector('input[data-field="idfactura"]');
    invoice.addEventListener('change', setAmountFromInvoice, false);
});