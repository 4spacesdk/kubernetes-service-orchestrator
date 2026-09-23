import type {Component} from 'vue';
import JsonViewerModule from 'vue-json-viewer';

/**
 * `vue-json-viewer` is CommonJS. Vite 4 handed its default import over as the component; Vite 8
 * hands over the module, `{default: component, __esModule: true}`, and the viewer drew nothing. Taken
 * apart here, once, for the three places that show JSON.
 */
const JsonViewer: Component = (JsonViewerModule as unknown as { default?: Component }).default ?? (JsonViewerModule as Component);

export default JsonViewer;
