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

Production.NumSerie = {
    /* ============================== PRIVATE VARIABLES ============================== */
    modalNumSerie: null,
    modalInputNumSerie: null,

    /* ============================== PRIVATE EVENTS ============================== */
    /**
     * Inicializate input select type Select2.
     * Need use JQuery.
     *
     * @param {String} className
     * @private
     */
    _initSelectNumSerie(className) {
        const $selects = $(className);
        $selects.select2({
            width: 'style',
            theme: 'bootstrap-5',
            placeholder: function () {
                return $(this).attr('title') || '';
            },
            minimumResultsForSearch: 5,
        });

        $selects.each(function () {
            if (!$(this).val()) return;

            const row = this.closest('tr');
            if (row) row.classList.add('numserie-ok');
        });

        $(document)
            .off('change.numserie', className)
            .on('change.numserie', className, function () {
                const row = this.closest('tr');
                if (row) row.classList.remove('numserie-ok', 'numserie-error');
            });
    },

    /**
     * Update Row (tr) of the numserie table.
     *
     * @param {String} id       NumSerie id row
     * @param {String} html     New HTML to update the row
     * @private
     */
    _updateRow(id, html) {
        const row = document.getElementById("produced-row-" + id);
        if (row && html) {
            row.outerHTML = html;
        }
    },

    /**
     *
     * @param {FormData} data
     * @param onSuccess
     * @param onError
     * @private
     */
    _save(data, onSuccess = null, onError = null) {
        animateSpinner("add");
        fetch(window.location.href, {
            method: "POST",
            body: data
        }).then(response => {
            if (!response.ok) throw new Error(response.status + " " + response.statusText);
            return response.json();
        }).then(result => {
            if (!result.ok) {
                console.log('_save: ', result.error || "error");
                if (typeof onError === "function") {
                    onError(result);
                }
                return;
            }

            if (typeof onSuccess === "function") {
                onSuccess(result);
            }
        }).catch(error => {
            console.log(error.message);
        }).finally(() => {
            animateSpinner("remove");
        });
    },

    /* ============================== EVENT HANDLERS ============================== */
    /**
     * Confirm the numserie associated to the produced item.
     *
     * @param {HTMLButtonElement} button
     */
    confirm(button){
        const id = button.dataset.id || "";
        const target = button.dataset.target || "";
        const token = button.dataset.multireqtoken || "";
        const input = document.getElementById(target);
        const numserie = input?.value.trim();
        if (!id || !input || !numserie) {
            console.log('confirmNumSerie: data error');
            return;
        }

        const data = new FormData();
        data.append("action", "verify-numserie");
        data.append("id", id);
        data.append("numserie", numserie);
        data.append("multireqtoken", token);

        Production.NumSerie._save(data, (result) => {
            this._updateRow(id, result.html);
        });
    },

    /**
     * Assign a numserie ingredient for consume action.
     * Recibe button confirm action with:
     *   - data-id = id numserie ingredient
     *   - data-target = id of select2 numserie input
     *
     * @param {HTMLButtonElement} button
     */
    consume(button) {
        const id = button.dataset.id || "";
        const target = button.dataset.target || "";
        const select = document.getElementById(target);
        const idnumserie = select?.value || "";

        // Check for all needed values
        if (!id || !target || !select || !idnumserie) {
            console.log("consume: data error");
            return;
        }

        // Consume the numserie selected
        const data = new FormData();
        data.append("action", "consume-numserie");
        data.append("id", id);
        data.append("idnumserie", idnumserie);

        const row = button.closest('tr');
        Production.NumSerie._save(data,
            () => {
                // Update UX
                const pending = row.querySelector('.js-numserie-pending');
                const assigned = row.querySelector('.js-numserie-assigned');
                const btnConfirm = row.querySelector('.js-btn-confirm');
                const btnRelease = row.querySelector('.js-btn-release');

                const option = select.options[select.selectedIndex];
                const selectedText = option?.dataset?.numserie || "";
                if (pending) pending.classList.add('d-none');
                if (assigned) {
                    assigned.classList.remove('d-none');

                    const input = assigned.querySelector('input');
                    if (input) input.value = selectedText;
                }

                if (btnConfirm) btnConfirm.classList.add('d-none');
                if (btnRelease) btnRelease.classList.remove('d-none');
                row.classList.remove('numserie-error');
                row.classList.add('numserie-ok');
            },
            (result) => {
                row.classList.remove('numserie-ok', 'numserie-error');
                row.classList.add('numserie-error');
                alert(result.error);
            }
        );
    },

    /**
     * Release a numserie ingredient from consume.
     * Recibe button release action with:
     *   - data-id = id numserie ingredient
     *   - data-target = id of select2 numserie input
     *
     * @param {HTMLButtonElement} button
     */
    release(button) {
        const id = button.dataset.id || "";
        const target = button.dataset.target || "";
        const select = document.getElementById(target);
        const idnumserie = select?.value || "";
        // Check for all needed values
        if (!id || !target || !select || !idnumserie) {
            console.log("release: data error");
            return;
        }

        const row = button.closest('tr');
        const data = new FormData();
        data.append("action", "release-numserie");
        data.append("id", id);
        data.append("idnumserie", idnumserie);

        Production.NumSerie._save(data,
            () => {
                // Update UX
                row.classList.remove('numserie-ok', 'numserie-error');
                const pending = row.querySelector('.js-numserie-pending');
                if (pending) pending.classList.remove('d-none');

                const assigned = row.querySelector('.js-numserie-assigned');
                if (assigned) assigned.classList.add('d-none');

                // Botones
                const btnConfirm = row.querySelector('.js-btn-confirm');
                if (btnConfirm) btnConfirm.classList.remove('d-none');

                const btnRelease = row.querySelector('.js-btn-release');
                if (btnRelease) btnRelease.classList.add('d-none');

                // Reset select2
                if (select) {
                    $(select).val(null).trigger('change');
                }
            },
            (result) => {
                if (row) {
                    row.classList.remove('numserie-ok');
                    row.classList.add('numserie-error');
                }
                alert(result.error);
            }
        );
    },

    /**
     * Set data to modal from selected numserie and show modal view
     *
     * @param {Event} event
     */
    showNumSerieModal(event) {
        const button = event.relatedTarget;
        if (!this.modalNumSerie || !button) {
            return;
        }

        this.modalInputNumSerie.value = button.dataset.numserie || "";
        this.modalInputNumSerie.dataset.id = button.dataset.id || "";
        this.modalInputNumSerie.dataset.multireqtoken = button.dataset.multireqtoken || "";
    },

    /**
     * Change the nunserie selected by the new numserie introduced.
     *
     * @param {Event} event
     */
    submitChange(event) {
        console.log("submitChange", this.modalInputNumSerie);
        event.preventDefault();
        if (!this.modalNumSerie || !this.modalInputNumSerie) {
            return;
        }

        const id = this.modalInputNumSerie.dataset.id || "";
        const token = this.modalInputNumSerie.dataset.multireqtoken || "";
        const numserie = this.modalInputNumSerie?.value.trim() || "";
        if (!id || !token || !numserie) {
            return;
        }

        const data = new FormData();
        data.append("action", "save-numserie");
        data.append("id", id);
        data.append("numserie", numserie);
        data.append("multireqtoken", token);

        Production.NumSerie._save(data, (result) => {
            this._updateRow(id, result.html);
            $(this.modalNumSerie).modal('hide');
        });
    },

    /* ============================== PUBLIC EVENTS ============================== */
    /**
     * Open the traceability PDF in a new tab.
     */
    printTraceability() {
        const modal = document.getElementById("numserie-traceability-modal");
        const id = modal?.dataset.traceId || "";
        const numserie = modal?.dataset.traceNumserie || "";
        const code = new URLSearchParams(window.location.search).get('code') || '';
        if (!id || !numserie) return;

        const url = window.location.pathname
            + '?code=' + encodeURIComponent(code)
            + '&action=print-traceability'
            + '&id=' + encodeURIComponent(id)
            + '&numserie=' + encodeURIComponent(numserie);
        window.open(url, '_blank');
    },

    /**
     * Fetch and display the traceability modal for a given serial number.
     *
     * @param {HTMLButtonElement} button
     */
    showTraceability(button) {
        const id = button.dataset.id || "";
        const numserie = button.dataset.numserie || "";
        if (!id || !numserie) return;

        const modal = document.getElementById("numserie-traceability-modal");
        const body = document.getElementById("numserie-traceability-body");
        if (!modal || !body) return;

        modal.dataset.traceId = id;
        modal.dataset.traceNumserie = numserie;

        const data = new FormData();
        data.append("action", "get-traceability");
        data.append("id", id);
        data.append("numserie", numserie);

        animateSpinner("add");
        fetch(window.location.href, {
            method: "POST",
            body: data,
        }).then(response => {
            if (!response.ok) throw new Error(response.status + " " + response.statusText);
            return response.json();
        }).then(result => {
            if (result.ok) {
                body.innerHTML = result.html;
                $(modal).modal('show');
            } else {
                console.error("[showTraceability]", result.error || "error");
            }
        }).catch(err => {
            console.error("[showTraceability]", err);
        }).finally(() => {
            animateSpinner("remove");
        });
    },

    /**
     * Initialize the NumSerie module.
     */
    init() {
        this.modalInputNumSerie = document.getElementById("change-numserie-value");
        this.modalNumSerie = document.getElementById("change-numserie-modal");
        $(this.modalNumSerie).on('show.bs.modal', this.showNumSerieModal.bind(this));

        const form = this.modalNumSerie?.querySelector("form");
        form?.addEventListener("submit", this.submitChange.bind(this));

        this._initSelectNumSerie('.js-select2-numserie');
    },
};

/* ============================== MAIN ENTRY ============================== */
/**
 * Logic specific to the NumSerie management.
 * Handles subscriptions to its own events.
 */
document.addEventListener('DOMContentLoaded', () => {
    Production.NumSerie.init();
});
