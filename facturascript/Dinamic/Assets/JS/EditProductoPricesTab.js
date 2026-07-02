/*!
 * Copyright (C) 2020-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * Author Daniel Fernández Giménez <hola@danielfg.es>
 */
let waitCounterPrices = 0;

function getVariantInputs(div) {
    let variant = {
        'idvariante': $(div).data('idvariante'),
        'rates': [],
    };

    // recogemos todos los inputs que hay en el div inputs
    $(div).find('.inputs input').each(function () {
        let name = $(this).attr('name');
        let value = $(this).val();

        if ($(this).attr('type') === 'number') {
            if (value === '' || value === null || isNaN(value)) {
                value = 0;
            }

            value = parseFloat(value);
        }

        variant[name] = value;
    });

    // recorremos las tarifas de la variante
    $(div).find('.rates tr').each(function () {
        let rate = {
            codtarifa: $(this).data('codtarifa'),
            precio: $(this).find('.rate-price').val(),
            precioimp: $(this).find('.rate-pricetax').val(),
        };

        variant.rates.push(rate);
    });

    return variant;
}

function recalculateVariant(action, divs) {
    let variants = [];
    let formData = new FormData();

    // recorremos el array de divs
    divs.forEach(function (variantDiv) {
        let variant = getVariantInputs(variantDiv);
        variants.push(variant);
    });

    formData.append('variants', JSON.stringify(variants));
    return sendPricesFormAction(action, formData);
}

async function recalculateVariantWait(action, event, input) {
    if (event.keyCode === 9) {
        // si se pulsa tabulador no hacemos nada
        return false;
    }

    // usamos un contador y un temporizador para solamente procesar la última llamada
    waitCounterPrices++;
    let waitNum = waitCounterPrices;
    await new Promise(r => setTimeout(r, 300));
    if (waitNum < waitCounterPrices) {
        return false;
    }

    // obtenemos el div superior con la clase 'variant-price', y creamos un array de divs
    let divs = [];
    divs.push($(input).closest('.variant-price'));

    console.log('waitNum: ' + waitNum);
    return recalculateVariant(action, divs);
}

async function recalculateVariantGlobalWait(action, event, input) {
    if (event.keyCode === 9) {
        // si se pulsa tabulador no hacemos nada
        return false;
    }

    // usamos un contador y un temporizador para solamente procesar la última llamada
    waitCounterPrices++;
    let waitNum = waitCounterPrices;
    await new Promise(r => setTimeout(r, 300));
    if (waitNum < waitCounterPrices) {
        return false;
    }

    if (action === 'changeCost') {
        $('.prices-tab-cost').val($(input).val());
    } else if (action === 'changeMargin') {
        $('.prices-tab-margin').val($(input).val());
    } else if (action === 'changePrice') {
        $('.prices-tab-price').val($(input).val());
    } else if (action === 'changePriceTax') {
        $('.prices-tab-pricetax').val($(input).val());
    }

    let divs = [];
    $('.variant-price').each(function () {
        divs.push($(this).closest('.variant-price'));
    });

    console.log('waitNum: ' + waitNum);
    return recalculateVariant(action, divs);
}

function resetRates(button) {
    let variants = [];
    let formData = new FormData();

    // obtenemos el div superior con la clase 'variant-price'
    let div = $(button).closest('.variant-price');

    // creamos un array de variantes
    let variant = {
        idvariante: $(div).data('idvariante'),
    };

    // añadir la variante al array de variantes
    variants.push(variant);

    formData.append('variants', JSON.stringify(variants));
    return sendPricesFormAction('resetRates', formData);
}

function resetRatesGlobal() {
    let variants = [];
    let formData = new FormData();

    $('.variant-price').each(function () {
        let variant = {
            idvariante: $(this).data('idvariante'),
        };

        variants.push(variant);
    });

    formData.append('variants', JSON.stringify(variants));
    return sendPricesFormAction('resetRates', formData);
}

function saveVariant(button) {
    // obtenemos el div superior con la clase 'variant-price'
    let div = $(button).closest('.variant-price');
    let variant = getVariantInputs(div);

    // añadir la variante al array de variantes
    let variants = [];
    variants.push(variant);

    let formData = new FormData();
    formData.append('variants', JSON.stringify(variants));
    return sendPricesFormAction('saveVariant', formData);
}

function saveVariantGlobal() {
    let variants = [];
    let formData = new FormData();

    // recorremos cada div de variante
    $('.variant-price').each(function () {
        let variant = getVariantInputs($(this));
        variants.push(variant);
    });

    formData.append('variants', JSON.stringify(variants));
    return sendPricesFormAction('saveVariant', formData);
}

function sendPricesFormAction(action, formData) {
    animateSpinner('add');
    formData.append('action', action);
    formData.append('ajax', true);

    fetch(window.location, {
        method: 'POST',
        body: formData
    }).then(function (response) {
        if (response.ok) {
            return response.json();
        }
        return Promise.reject(response);
    }).then(function (data) {

        // recorremos el array de variantes
        if (data.variants) {
            data.variants.forEach(function (variant) {
                // obtenemos el div con el mismo data-idvariante
                let variantDiv = $('.variant-price[data-idvariante="' + variant.idvariante + '"]');

                // recorremos todos los valores obtenidos y buscamos un input con el mismo name
                // excluimos precio, margen, precioimp y coste
                for (let key in variant) {
                    if (key === 'precio' || key === 'margen' || key === 'precioimp' || key === 'coste') {
                        continue;
                    }

                    $(variantDiv).find('input[name="' + key + '"]').val(variant[key]);
                }

                if (data.resetRates) {
                    $(variantDiv).find('.rates').html(variant.rates);
                }

                if (data.changeCost || data.changeMargin) {
                    $(variantDiv).find('.prices-tab-price').val(variant.precio);

                    if ($(variantDiv).find('.prices-tab-pricetax').val() === '') {
                        $(variantDiv).find('.prices-tab-pricetax').attr('placeholder', variant.precioimp);
                    } else {
                        $(variantDiv).find('.prices-tab-pricetax').val(variant.precioimp);
                    }
                }

                if (data.changePrice) {
                    $(variantDiv).find('.prices-tab-margin').val(variant.margen);

                    if ($(variantDiv).find('.prices-tab-pricetax').val() === '') {
                        $(variantDiv).find('.prices-tab-pricetax').attr('placeholder', variant.precioimp);
                    } else {
                        $(variantDiv).find('.prices-tab-pricetax').val(variant.precioimp);
                    }
                }

                if (data.changePriceTax) {
                    $(variantDiv).find('.prices-tab-margin').val(variant.margen);
                    $(variantDiv).find('.prices-tab-price').val(variant.precio);
                }
            });
        }

        if (Array.isArray(data.messages)) {
            data.messages.forEach(function (msg) {
                if (msg.level === 'danger') {
                    setToast(msg.message, msg.level, '', 0);
                } else {
                    setToast(msg.message, msg.level);
                }
            });
        }

        animateSpinner('remove');
    }).catch(function (error) {
        alert('error TarifasAvanzadas');
        console.warn(error);
        animateSpinner('remove', false);
    });

    return false;
}