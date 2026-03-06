import 'primereact/resources/themes/lara-light-teal/theme.css';
import { ADIOS } from 'adios/Loader';

class Axia4App extends ADIOS {}

const app: Axia4App = new Axia4App({ rootUrl: (window as any).__ADIOS_ROOT_URL__ ?? '' });

(globalThis as any).app = app;
(globalThis as any).app.renderReactElements();
