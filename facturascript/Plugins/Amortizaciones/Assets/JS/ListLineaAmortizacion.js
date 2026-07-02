/**
 * This file is part of Amortizaciones plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Amortizaciones  Copyright (C) 2023-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */

const FORM_LINE_NAME = 'formListLineaAmortizacion';

/**
 * Get the query params from an url.
 *
 * @param {String} url
 * @returns {{}}
 * @private
 */
function _getQueryParams(url)
{
    const queryString = url.split('?')[1];
    const params = {};
    if (queryString) {
        queryString.split('&').forEach((param) => {
            const [key, value] = param.split('=');
            params[key] = decodeURIComponent(value);
        });
    }
    return params;
}

/**
 * Set the line data into the form parent of div with id.
 *
 * @param {String} id
 * @param {Object} line
 * @private
 */
function _setLineData(id, line)
{
    const divElement = document.querySelector('#' + id);
    const parentForm = divElement.closest('form');
    parentForm.querySelector('input[name="idamortizacion"]').value = line.idamortizacion;
    parentForm.querySelector('input[name="idlinea"]').value = line.idlinea;
    parentForm.querySelector('input[name="idasiento"]').value = line.idasiento;
    parentForm.querySelector('input[name="ano"]').value = line.ano;
    parentForm.querySelector('input[name="periodo"]').value = line.periodo;
    parentForm.querySelector('input[name="cantidad"]').value = line.cantidad;
    parentForm.querySelector('input[name="amortizado"]').value = line.amortizado;
    parentForm.querySelector('input[data-source="asientos"][data-field="idasiento"]').value = line.concepto;

    const parts = line.fecha.replace('/', '-').split('-');
    const date = new Date(`${parts[2]}-${parts[1]}-${parts[0]}`);
    parentForm.querySelector('input[name="fecha"]').value = date.toISOString().split('T')[0];
}

/**
 * Search the line data and show the modal to edit it.
 *
 * @param {Event} event
 */
function editLine(event)
{
    const tr = event.currentTarget;
    const href = tr.getAttribute('data-href');
    const data = {
        'action': 'line-data',
        'code': _getQueryParams(href).code,
    };

    $.ajax({
        method: "POST",
        url: window.location.href,
        data: data,
        dataType: "json",
        success: function (result) {
            if (result.error) {
                alert(result.message);
                return;
            }
            _setLineData('modaleditline', result.line);
            showModalEdit('modaleditline');
        },
        error: function (msg) {
            console.log('editLine error', msg.responseText);
        }
    });
}

/**
 * Shown the modal with the id.
 *
 * @param {String} id
 */
function showModalEdit(id)
{
    $('#' + id).modal({
        backdrop: 'static',
        focus: true,
        show: true,
    });
}

/**
 * Main function.
 *   - Add event listener to each row in the table. When clicked, show the modal to edit the line.
 */
$(document).ready(function () {
    let table = document.querySelector('#' + FORM_LINE_NAME + ' table');
    if (table) {
        let rows = table.querySelectorAll('tbody tr');
        rows.forEach(function (element) {
            element.addEventListener('mousedown', editLine);
        });
    }
});