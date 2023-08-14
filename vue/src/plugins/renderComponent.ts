import {createApp} from 'vue'
import type {Component} from 'vue'

export default function renderComponent(container: string | Element,
                                        component: Component,
                                        props: Record<string, unknown> | null,
                                        appContext: any) {
    const app = createApp(component, props);
    Object.assign(app._context, appContext);
    app.mount(container);
    return () => {
        app?.unmount()
    }
}
