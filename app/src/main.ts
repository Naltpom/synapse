import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import './main.css'
// Applique le thème (clair/sombre) avant le montage pour éviter tout flash.
import './lib/theme'

createApp(App).use(router).mount('#app')
