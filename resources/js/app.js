import './bootstrap';
import Alpine from 'alpinejs';
import { renderCharts } from './charts';

window.Alpine = Alpine;
Alpine.start();

renderCharts();
