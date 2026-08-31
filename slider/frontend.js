jQuery(function ($) {

    function collectValues(container) {
        let values = {};

        container.find('.competency-row').each(function () {
            const row = $(this);

            // Nur aktive Zeilen berücksichtigen
            if (row.find('.row-toggle').is(':checked')) {
                row.find('.competency-range').each(function () {
                    values[$(this).data('slug')] = $(this).val();
                });
            }
        });

        return values;
    }

    function updateUrl(values) {
        const params = new URLSearchParams();
        const kategorie =
            new URLSearchParams(window.location.search).get("Kategorie") || "Default";

        Object.keys(values).forEach(function (key) {
            params.set(key, values[key]);
        });

        params.set('Kategorie', kategorie);

        const newUrl =
            window.location.pathname +
            '?' +
            params.toString() +
            window.location.hash;

        history.replaceState(null, '', newUrl);
    }

    function updateResultsLink(values) {
        const params = new URLSearchParams(values);
        const kategorie = new URLSearchParams(window.location.search).get("Kategorie") || "Default";
        params.set('Kategorie', kategorie);
        $('.results-link').attr('href', '/results/?' + params.toString());
    }

    function valuesToQueryString(values) {
        return new URLSearchParams(values).toString();
    }

    function requestOffers(container) {
        const values = collectValues(container);

        updateUrl(values);
        updateResultsLink(values);

        const kategorie = new URLSearchParams(window.location.search).get("Kategorie") || "Default";
        const params = new URLSearchParams(values);
        params.set('Kategorie', kategorie);

        $.post(CompetencySlider.ajaxurl + "?" + params.toString(), {
            action: 'get_offers',
            values: values
        }, function (response) {
            container.find('.competency-offers').html(response);
        });
    }

    function toggleRow(row) {
        const active = row.find('.row-toggle').is(':checked');

        row.find('.competency-range').prop('disabled', !active);

        if (active) {
            row.removeClass('row-disabled');
        } else {
            row.addClass('row-disabled');
        }
    }

    // Slider geändert
    $(document).on('change input', '.competency-range', function () {
        const container = $(this).closest('.competency-slider-block');

        $(this).next('.competency-value').text($(this).val());

        requestOffers(container);
    });

    // Checkbox geändert
    $(document).on('change', '.row-toggle', function () {
        const row = $(this).closest('.competency-row');
        const container = row.closest('.competency-slider-block');

        toggleRow(row);
        requestOffers(container);
    });

    // Initialisierung
    $('.competency-slider-block').each(function () {
        const container = $(this);

        container.find('.competency-row').each(function () {
            toggleRow($(this));
        });

        requestOffers(container);
    });

});