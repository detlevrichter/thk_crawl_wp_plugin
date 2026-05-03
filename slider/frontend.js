jQuery(function ($) {

    function collectValues(container) {
        let values = {};
        container.find('.competency-range').each(function () {
            values[$(this).data('slug')] = $(this).val();
        });
        return values;
    }

    function updateUrl(values) {
        const params = new URLSearchParams();
        const kategorie = new URLSearchParams(window.location.search).get("Kategorie") || "Default"; 
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

    function requestOffers(container) {
        const values = collectValues(container);

        updateUrl(values);
        updateResultsLink(values);
        const query = valuesToQueryString(values);
        $.post(CompetencySlider.ajaxurl + "?" + query, {
            action: 'get_offers',
            values: values
        }, function (response) {
            container.find('.competency-offers').html(response);
        });
    }
    $(document).on('change', '.competency-range', function () {
        const container = $(this).closest('.competency-slider-block');

        // Wertanzeige aktualisieren
        $(this)
            .next('.competency-value')
            .text($(this).val());

        requestOffers(container);
    });

    // Initialer Call (setzt URL sauber + lädt Offers)
    $('.competency-slider-block').each(function () {
        const container = $(this);
        updateUrl(collectValues(container));
        requestOffers(container);
    });
    function updateResultsLink(values) {
        const params = new URLSearchParams(values).toString();
        $('.results-link').attr('href', '/results/?' + params);
    }
    function valuesToQueryString(values) {
        return new URLSearchParams(values).toString();
    }
});
