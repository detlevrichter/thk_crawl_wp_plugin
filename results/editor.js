wp.blocks.registerBlockType('competency/results', {
    edit: function () {
        return wp.element.createElement(
            'div',
            { className: 'components-placeholder' },
            wp.element.createElement(
                'h3',
                {},
                wp.i18n.__('Personalised continuing education catalogue', 'competency-slider')
            ),
            wp.element.createElement(
                'p',
                {},
                wp.i18n.__('The offer cards are rendered on the front end from the URL parameters.', 'competency-slider')
            )
        );
    },
    save: function () {
        return null; // Server Side Render
    }
});
