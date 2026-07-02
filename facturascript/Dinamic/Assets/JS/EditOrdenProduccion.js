/**
 * This file is part of the Produccion plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Produccion      Copyright (C) 2020-2026 Jose Antonio Cuello Principal <yopli2000@gmail.com> *
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
"use strict";

/**
 * Global namespace for Production plugin.
 */
window.Production = window.Production || {};

/**
 * Get all data of the given form element.
 *
 * @param {HTMLFormElement} form
 * @returns {Object}
 */
function getFormData(form) {
    if (!form) return {};

    const data = { action: "selectTraza" };
    new FormData(form).forEach((value, key) => {
        data[key] = value;
    });
    return data;
}

/**
 * Set disabling to product lote without a traceability.
 *
 * @param {HTMLInputElement} input
 */
function widgetTextSetLotes(input) {
    const form = input.closest("form");
    if (!form) return;

    const data = getFormData(form);
    if (data.activetab === "EditOrdenProducto") {
        if (data.referencia === "" || data.trazabilidad !== "lotes") {
            input.disabled = true;
            const column = input.closest('[class*="col-"]');
            column.classList.add("d-none");
        }
    }
}

/**
 * Set disabling to product lote without a traceability.
 * Load serialnum list for reference with traceability.
 *
 * @param {HTMLSelectElement} select
 */
function widgetSelectSetLotes(select) {
    const form = select.closest("form");
    if (!form) return;

    const data = getFormData(form);
    data.action = "selectIngredient";
    // compatibilidad con name="numserie" (legacy) y name="idlote" (nuevo)
    const currentValue = select.getAttribute('value') ?? select.value ?? "";
    data.numserie = currentValue;

    if (data.activetab === "EditOrdenIngrediente") {
        if (data.referencia === "" || data.trazabilidad !== "lotes") {
            select.disabled = true;
            const column = select.closest('[class*="col-"]');
            column.classList.add("d-none");
            if (currentValue === "") return;
        }

        select.innerHTML = "";

        const payload = new FormData();
        Object.entries(data).forEach(([key, val]) => payload.append(key, val));

        fetch(window.location.href, {
            method: "POST",
            body: payload
        }).then(response => {
            if (!response.ok) throw new Error(response.status + " " + response.statusText);
            return response.json();
        }).then(results => {
            results.forEach(element => {
                const option = document.createElement("option");
                option.value = element.key;
                option.textContent = element.value;
                if (String(element.key) === String(currentValue)) option.selected = true;
                select.appendChild(option);
            });
        }).catch(error => {
            alert(error.message);
        });
    }
}

function widgetSelectSetNumserie(select) {
    const form = select.closest("form");
    if (!form) return;

    const data = getFormData(form);
    if (data.activetab === "EditOrdenProducto") {
        if (data.referencia === "" || data.trazabilidad !== "numserie") {
            select.disabled = true;
            const column = select.closest('[class*="col-"]');
            column.classList.add("d-none");
        }
    }
}

/**
 * Set traceability status.
 */
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll('select[data-field="numserietype"]').forEach(select => {
        widgetSelectSetNumserie(select);
    });

    document.querySelectorAll('select[name="numserie"], select[name="idlote"]').forEach(select => {
        widgetSelectSetLotes(select);
    });

    document.querySelectorAll('input[name="numserie"]').forEach(input => {
        widgetTextSetLotes(input);
    });
});

