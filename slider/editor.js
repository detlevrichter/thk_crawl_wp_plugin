wp.blocks.registerBlockType('competency/slider', {
    edit: function () {
        return wp.element.createElement(
            'div',
            { className: 'components-placeholder' },
            wp.element.createElement('h3', {}, wp.i18n.__('Interests and skills', 'competency-slider')),
            wp.element.createElement(
                'p',
                {},
                wp.i18n.__(
                    'Target group, criteria selection and the interest/skill sliders are rendered on the front end.',
                    'competency-slider'
                )
            )
        );
    },
    save: function () {
        return null; // Server Side Render
    }
});
