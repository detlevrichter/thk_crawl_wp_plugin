wp.blocks.registerBlockType('competency/slider', {
    edit: () => {
        return wp.element.createElement(
            'p',
            {},
            'Competency Slider (Editor Preview)'
        );
    },
    save: () => {
        return null; // Server Side Render
    }
});
