/**
 * This file is part of PagosMultiples plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * PagosMultiples Copyright (C) 2022-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */

function getCash() {
    var cash = {};
    $(".settlement").each(function() {
        cash[$(this).attr("name")] = parseFloat($(this).val());
    });
    return cash;
}

function getTotalGroup() {
    var total = $(".group-total").val();
    return parseFloat(total);
}

function getDiets() {
    var diets = $("input[name=total_diets]").val();
    return parseFloat(diets);
}

function getChecks() {
    var checks = $("input[name=bankchecks]").val();
    return parseFloat(checks);
}

$(document).ready(function () {
    $(".settlement").change(function() {
        var calculate = $("select[name=automatic] option").filter(":selected").val();
        if (calculate === '1') {
            var data = {
                action: 'cash-calculate',
                cash: getCash(),
                checks: getChecks(),
                diets: getDiets(),
                total: getTotalGroup()
            };
            $.ajax({
                method: 'POST',
                url: window.location.href,
                data: data,
                dataType: 'json',
                success: function(result) {
                    $(".settlement-total").val(result.total);
                    $(".settlement-difference").val(result.difference);
                }
            });
        }
    });
});