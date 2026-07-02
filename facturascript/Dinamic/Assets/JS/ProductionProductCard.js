/**
* This file is part of the Produccion plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Produccion      Copyright (C) 2020-2026 Jose Antonio Cuello Principal <yopli2000@gmail.com> *
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
'use strict';

/**
 * Defines if a process is in progress.
 *
 * @type {boolean}
 * @private
 */
let _isProcessing = false;

/**
 * Show product modal with product data.
 *
 * @param {Object} data
 * @private
 */
function _showProductModal(data)
{
    // Header
    const ref = document.getElementById('pmReference');
    if (ref) ref.textContent = data.reference ?? '';

    const name = document.getElementById('pmName');
    if (name) name.textContent = data.name ?? '';

    // For-sell badge
    const badge = document.getElementById('pmForSellBadge');
    if (badge) {
        const forSell = (data.forsell === true || data.forsell === 1 || data.forsell === '1');
        if (forSell) {
            badge.classList.remove('d-none');
        } else {
            badge.classList.add('d-none');
        }
    }

    // Image + fallback
    const img = document.getElementById('pmImage');
    const noImg = document.getElementById('pmNoImage');
    if (img && noImg) {
        if (data.imageUrl) {
            img.src = data.imageUrl;
            img.alt = data.name ?? data.reference ?? '';
            img.classList.remove('d-none');
            noImg.classList.add('d-none');
        } else {
            img.src = '';
            img.alt = '';
            img.classList.add('d-none');
            noImg.classList.remove('d-none');
        }
    }

    // Product Card details
    const stock = document.getElementById('pmStock');
    if (stock) stock.textContent = (data.stock ?? '').toString();

    const available = document.getElementById('pmAvailable');
    if (available) available.textContent = (data.available ?? '').toString();

    const cost = document.getElementById('pmCost');
    if (cost) cost.textContent = (data.cost ?? '').toString();

    const price = document.getElementById('pmPrice');
    if (price) price.textContent = (data.price ?? '').toString();

    const barcode = document.getElementById('pmBarcode');
    if (barcode) barcode.textContent = data.barcode ?? '';

    const manufacturer = document.getElementById('pmManufacturer');
    if (manufacturer) manufacturer.textContent = data.manufacturer ?? '';

    const family = document.getElementById('pmFamily');
    if (family) family.textContent = data.family ?? '';

    // Show modal
    const modalEl = document.getElementById('productModal');
    $('#productModal').modal('show');
}

/**
 * Get product data from the selected form and show modal.
 *
 * @param {HTMLFormElement} form
 */
function showProduct(form) {
    if (_isProcessing) return;

    const refInput = form.querySelector('input[name="referencia"]');
    if (!refInput || !refInput.value) {
        return;
    }

    _isProcessing = true;
    const formData = new FormData(form);
    formData.append('action', 'getProductData');

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    }).then(response => {
        return response.json();
    }).then(result => {
        if (result.ok) {
            _showProductModal(result.data);
        }
    }).catch(err => {
        console.error('[showProduct] ajax error:', err);
    }).finally(() => {
        _isProcessing = false;
    });
}

/**
 * Main process.
 * Add event listeners:
 *   - Move modal to parent div for remove footer card.
 *   - Remove focus from active element when modal is closed, for remove warning.
 */
document.addEventListener('DOMContentLoaded', function () {
    const parentDiv= document.getElementById('production-product-card');
    const modalDiv= document.getElementById('productModal');
    if (parentDiv && modalDiv) {
        parentDiv.innerHTML = '';
        parentDiv.classList.remove('d-none', 'col');
        parentDiv.appendChild(modalDiv);
    }

    $(modalDiv).on('hide.bs.modal', function () {
        if (document.activeElement) {
            document.activeElement.blur();
        }
    });
});
