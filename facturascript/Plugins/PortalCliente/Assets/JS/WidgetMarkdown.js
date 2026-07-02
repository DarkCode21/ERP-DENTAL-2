/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 * @Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

$(document).ready(function () {
    $('.markdown-editor').each(function () {
        easymde = new EasyMDE({
            element: $(this)[0],
            spellChecker: false,
            required: true,
        });
    });
});