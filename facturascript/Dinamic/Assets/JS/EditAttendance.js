/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */

let deleteButton;
let saveButton;

/**
 * Disables the delete and save buttons in the attendance form.
 *
 * @param {boolean} newState
 * @private
 */
function _setDisableToButtons(newState)
{
    deleteButton.disabled = newState;
    saveButton.disabled = newState;
}

/**
 * Set buttons status with length of user reason.
 *
 * @param {Event} event
 * @private
 */
function _reasonChanged(event)
{
    const value = event.target.value || '';
    _setDisableToButtons(value.length < 15);
}

/**
 * Main process.
 *   - Add key event listeners to the reason input.
 *   - Set delete and save buttons.
 *   - Set initial state to delete and save buttons.
 */
$(document).ready(function () {
    const inputReason = document.querySelector('input[name="reason"]');
    if (inputReason && false === inputReason.readOnly) {
        inputReason.addEventListener('keyup', _reasonChanged);
        const form = document.querySelector('#formEditAttendance');
        const formRow = form.querySelector('.card-footer .form-row');
        deleteButton = formRow.querySelector('.btn-danger');
        saveButton = formRow.querySelector('.btn-primary');
        _setDisableToButtons(true);
    }
});