import {defineComponent, h} from 'vue';
import type {Component} from 'vue';
import JsonViewerModule from 'vue-json-viewer';
import '@/scss/json-viewer.scss';

/**
 * `vue-json-viewer` is CommonJS. Vite 4 handed its default import over as the component; Vite 8
 * hands over the module, `{default: component, __esModule: true}`, and the viewer drew nothing.
 * Taken apart here, once, for the places that show JSON - which also all get kso's theme
 * (`scss/json-viewer.scss`) rather than the package's white one, unless they ask for another.
 */
const Viewer: Component = (JsonViewerModule as unknown as { default?: Component }).default ?? (JsonViewerModule as Component);

const JsonViewer = defineComponent({
    name: 'JsonViewer',
    inheritAttrs: false,
    setup(_, {attrs, slots}) {
        return () => h(Viewer, {theme: 'jv-kso', ...attrs}, slots);
    },
});

export default JsonViewer;
