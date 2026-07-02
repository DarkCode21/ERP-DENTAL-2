/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */

/**
 * Public const for confirmation text dialog and special operations.
 * The values are set in the TWIG template.
 */
let deleteCancel = "";
let deleteConfirm = "";
let deleteMessage = "";
let deleteTitle = "";
let currentWorkPeriods = [];

/**
 * Clear the attendance modal.
 */
function clearAttendanceManual()
{
    const modal = document.getElementById('modalAttendance');
    const dateInput = modal.querySelector('input[name="date"]');
    dateInput.disable = false;

    const timeHelp = modal.querySelector('#timeHelp');
    timeHelp.textContent = '';

    const kindSelect = modal.querySelector('select[name="kind"]');
    kindSelect.value = 1;

    const noteInput = modal.querySelector('input[name="note"]');
    noteInput.value = '';
}

/**
 * Exec a delete action.
 *
 * @param {String} action
 * @param {Number} id
 */
function deleteAction(action, id)
{
    const form = $("#formEmployeePanel");
    form.find(':input[name="action"]').val(action);
    form.append('<input type="hidden" name="idmodel" value="' + id + '"/>');
    form.submit();
}

/**
 * Delete an attendance.
 * First show a confirmation dialog.
 *
 * @param {String} model
 * @param {Number} id
 * @returns {boolean}
 */
function deleteActionConfirm(model, id)
{
    bootbox.confirm({
        title: deleteTitle,
        message: deleteMessage,
        closeButton: false,
        buttons: {
            cancel: {
                label: '<i class="fa-solid fa-times"></i> ' + deleteCancel
            },
            confirm: {
                label: '<i class="fa-solid fa-check"></i> ' + deleteConfirm,
                className: "btn-danger"
            }
        },
        callback: function (result) {
            if (result) {
                deleteAction("delete-" + model, id);
            }
        }
    });

    return false;
}

/**
 * Delete an attendance.
 *
 * @param {Number} id
 */
function deleteAttendance(id)
{
    deleteActionConfirm("attendance", id);
}

/**
 * Delete a holiday.
 *
 * @param {Number} id
 */
function deleteHoliday(id)
{
    deleteActionConfirm("holidays", id);
}

/**
 * Show the attendance modal for edit.
 *
 * @param {Element} button
 */
function incidenceAttendance(button)
{
    const modal = document.getElementById('modalAttendance');
    const dateInput = modal.querySelector('input[name="date"]');
    dateInput.value = button.dataset.date;

    const timeHelp = modal.querySelector('#timeHelp');
    timeHelp.textContent = button.dataset.time;

    const kindSelect = modal.querySelector('select[name="kind"]');
    kindSelect.value = button.dataset.kind;

    const noteInput = modal.querySelector('input[name="note"]');
    noteInput.value = button.dataset.note;

    $(modal).modal('show');
}

/**
 * Insert a new attendance with the current date and indicate kind.
 *
 * @param {String} kind
 * @param {Boolean} adjust
 */
async function insertAttendance(kind, adjust)
{
    const location = await getGeoLocation();
    const form = $("#formEmployeePanel");
    form.find(':input[name="action"]').val("insert-attendance");
    form.append('<input type="hidden" name="origin" value="3"/>');
    form.append('<input type="hidden" name="kind" value="' + kind + '"/>');
    form.append('<input type="hidden" name="location" value="' + location + '"/>');
    form.append('<input type="hidden" name="adjust" value="' + adjust + '"/>');
    form.submit();
}

/**
 * Insert a new attendance with the employee dates.
 */
async function insertAttendanceManual()
{
    const location = await getGeoLocation();
    const form = $("#formEmployeePanel");
    form.find(':input[name="action"]').val("insert-attendance");
    form.append('<input type="hidden" name="origin" value="1"/>');
    form.append('<input type="hidden" name="location" value="' + location + '"/>');
    form.append('<input type="hidden" name="adjust" value="false"/>');
    form.submit();
}

/**
 * Insert a new attendance with the employee decision of if the extra time is computable.
 *
 * @param {Element} button
 */
function insertComputableAttendance(button)
{
    const computable = button.getAttribute('data-computable');
    const kind= button.getAttribute('data-kind');
    insertAttendance(kind, computable === 'false');
}

/**
 * Insert a new holiday with the current date.
 */
function insertHolidays()
{
    $("#formEmployeePanel :input[name=\"action\"]").val("insert-holidays");
    $("#formEmployeePanel").submit();
}

/**
 * Reload the attendance list for date.
 *
 * @param {String} action
 * @param {String} date
 */
function showAttendance(action, date)
{
    const form = $("#formEmployeePanel");
    form.find(':input[name="action"]').val(action + "-attendance");
    form.append('<input type="hidden" name="selectDate" value="' + date + '"/>');
    form.submit();
}

/**
 * Show the computable modal windows to confirm extra time.
 * First check if the current time is into a work period.
 * If yes, insert the attendance without adjusting to the work period.
 * If not, show a computable question to user.
 *
 * @param {String} kind
 */
function showComputableModal(kind)
{
    // Check if the current time is into a work period.
    const now = new Date();
    const nowTime = now.getHours() + ':' + now.getMinutes();
    for (const period of currentWorkPeriods) {
        if (period.starttime <= nowTime && period.endtime >= nowTime) {
            insertAttendance(kind, false);
            return;
        }
    }

    // Show the computable question.
    document.getElementById('btnComputable').setAttribute('data-kind', kind);
    document.getElementById('btnNotComputable').setAttribute('data-kind', kind);
    $(document.querySelector('#modalComputable')).modal({
        backdrop: 'static',
        keyboard: false,
        focus: true,
        show: true,
    });
}

/**
 * Main process.
 *  - Control enters key in noEnterKey class inputs.
 */
$(document).ready(function () {
    $(".noEnterKey").keypress(function (e) {
        return !(e.which === 13 || e.keyCode === 13);
    });
});
