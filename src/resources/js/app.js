import '../css/app.css';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { setUnauthorizedHandler } from './services/api';
import { useAuthStore } from './stores/auth';

const app = createApp(App);
app.use(createPinia());

// Sessão derrubada pelo servidor (token revogado, usuário desativado): volta ao login.
setUnauthorizedHandler(() => {
    useAuthStore().clear();
    router.push({ name: 'login' });
});

app.use(router);
app.mount('#app');
