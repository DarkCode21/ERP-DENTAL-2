/**
 * This file is part of DocumentosRecurrentes plugin for FacturaScripts.
 * FacturaScripts         Copyright (C) 2015-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 * DocumentosRecurrentes  Copyright (C) 2020-2021 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */

/**
 * Add a hiden value into array code field.
 *
 * @param {Element} parent
 * @param {int} value
 */
function addInputID(parent, value) {
    var input = document.createElement('INPUT');
    input.type = 'hidden';
    input.name = 'code[]';
    input.value = value;
    parent.appendChild(input);
}

$(document).ready(function () {
    const form = document.getElementById("modalgenerate-recurring").parentElement;
    form.onsubmit = function() {
        let listForm = "#form" + form.parentElement.id;
        $(listForm + " :input[name=\"code[]\"]").each(function() {
            if (this.checked) {
                addInputID(form, $(this).val());
            }
        });
        return true;
    };
});