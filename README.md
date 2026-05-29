# Image Editor para Laravel

Pacote Laravel (Inertia + Vue 3) com galeria de imagens, captura por webcam, upload e editor completo (filtros, recorte, desfoque, pixelização, desenho, texto, marca de água, etc.).

---
## Requisitos

| Camada | Requisitos |
|--------|------------|
| **PHP** | 8.3+, Laravel 10 ou 11 |
| **Pacote** | [inertiajs/inertia-laravel](https://github.com/inertiajs/inertia-laravel), [intervention/image-laravel](https://github.com/Intervention/image-laravel) (instalados como dependências do pacote) |
| **Frontend** | Node.js 18+, Vue 3, `@inertiajs/vue3`, Vite, `laravel-vite-plugin`, `axios` |

O projeto host deve usar **Inertia.js** com **Vue 3** (não Livewire nem Blade isolado para as páginas do editor).

---

## Instalação no projeto Laravel

### 1. Composer

**Repositório remoto (quando publicado):**

```bash
composer require pdmfc/image-editor
```

**Desenvolvimento local (path repository)** — em `composer.json` do host:

```json
"repositories": [
    {
        "type": "path",
        "url": "../image-editor"
    }
],
"require": {
    "pdmfc/image-editor": "@dev"
}
```

Depois:

```bash
composer update pdmfc/image-editor
```

O `ImageEditorServiceProvider` regista-se automaticamente (Laravel package discovery) e expõe:

- **Rotas API** (sempre): `/api/camera/*`, `/api/image/*`
- **Rotas web de demo** (opcional, desligadas por defeito): `/camera`, `/camera/form-example`

As páginas Inertia em produção devem ser definidas no **projeto host** (com `auth`, `userId` real, etc.). Ver secção [Rotas no projeto host](#rotas-no-projeto-host).

### 2. Storage

```bash
php artisan storage:link
mkdir -p storage/app/public/photos/tmp
chmod -R 775 storage/app/public/photos
```

As fotos ficam em `storage/app/public/photos/tmp/{userId}/` e são servidas pela aplicação em `/api/camera/photos/{userId}/{filename}` (URLs relativas ao host — não dependem de `APP_URL` para a galeria).

### 3. Dependências JavaScript (projeto host)

No diretório do Laravel host:

```bash
npm install vue@^3 @inertiajs/vue3 axios
npm install -D vite laravel-vite-plugin @vitejs/plugin-vue
```

Use também **Tailwind CSS** no host se as classes do editor e do modal forem necessárias.

### 4. Assets Vue do pacote no host

O Vite do host precisa de resolver os ficheiros `.vue` do pacote. Crie a pasta e ligue os diretórios do pacote (ajuste o caminho se o vendor estiver noutro sítio):

```bash
mkdir -p resources/js/vendor/image-editor

cd resources/js/vendor/image-editor

ln -sf "$(pwd)/../../../../vendor/pdmfc/image-editor/src/Resources/js/Components" Components
ln -sf "$(pwd)/../../../../vendor/pdmfc/image-editor/src/Resources/js/Pages" Pages
ln -sf "$(pwd)/../../../../vendor/pdmfc/image-editor/src/Resources/css" css

# Ficheiros estáticos do build Vite do pacote (se existirem na sua cópia)
# cp ../../../../vendor/pdmfc/image-editor/src/Resources/js/app.js .
# cp ../../../../vendor/pdmfc/image-editor/src/Resources/js/app.css .
```

Em desenvolvimento com **path repository**, `vendor/pdmfc/image-editor` é um symlink para a pasta `image-editor` do repositório.

> **Nota:** O pacote publica configuração e stubs opcionais (`image-editor-config`, `image-editor-demo-routes`). Os assets Vue ligam-se manualmente como acima (ou copiando `Components`, `Pages` e `css`).

### 5. Inertia — layout raiz

No middleware Inertia do host (`app/Http/Middleware/HandleInertiaRequests.php`):

```php
protected $rootView = 'image-editor::app';
```

Isto usa o layout do pacote (`@inertia`, Vite, Font Awesome). Alternativa: publicar a view e personalizá-la.

Publicar configuração (opcional):

```bash
php artisan vendor:publish --tag=image-editor-config
```

### 6. `resources/js/app.js`

Exemplo mínimo (páginas do host + páginas do pacote; **sem** segundo `createInertiaApp`):

```javascript
import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import axios from 'axios'
import './vendor/image-editor/css/app.css'

window.axios = axios
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

createInertiaApp({
  resolve: (name) => {
    const hostPages = import.meta.glob('./Pages/**/*.vue')
    const packagePages = import.meta.glob('./vendor/image-editor/Pages/**/*.vue')

    if (hostPages[`./Pages/${name}.vue`]) {
      return resolvePageComponent(`./Pages/${name}.vue`, hostPages)
    }

    return resolvePageComponent(
      `./vendor/image-editor/Pages/${name}.vue`,
      packagePages
    )
  },
  setup({ el, App, props, plugin }) {
    return createApp({ render: () => h(App, props) }).use(plugin).mount(el)
  }
})
```

### 7. Vite (`vite.config.js`)

```javascript
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.js'],
      refresh: true
    }),
    vue()
  ]
})
```

No layout Inertia do pacote, o Vite carrega apenas `resources/js/app.js` do host.

### 8. Rotas no projeto host

O pacote **não regista páginas web por defeito**. Defina as rotas no host, por exemplo em `routes/web.php`:

```php
use PDMFC\ImageEditor\Support\ImageEditorSession;

Route::middleware(['web', 'auth'])->get('/registos/{registo}/imagem', function ($registo) {
    $userId = $registo->id; // ou o ID que envia ao QR / galeria
    ImageEditorSession::primeBroadcastUser($userId);

    return inertia('MeuFormulario', ['userId' => $userId]);
});
```

Para **testar sem escrever rotas**, active temporariamente as rotas de demo do pacote:

```env
IMAGE_EDITOR_DEMO_ROUTES=true
```

Ou publique o stub e registe-o no host:

```bash
php artisan vendor:publish --tag=image-editor-demo-routes
```

```php
// routes/web.php
Route::middleware('web')->group(base_path('routes/image-editor-demo.php'));
```

### 9. Compilar e testar

```bash
npm run dev
# ou
npm run build

php artisan serve
```

Abra a rota que definiu no host (ex.: `/meu-formulario`). Com `IMAGE_EDITOR_DEMO_ROUTES=true`, pode usar `/camera` ou `/camera/form-example`.

### 10. Página de exemplo no host (opcional)

Copie o modelo do pacote para o projeto:

```text
vendor/pdmfc/image-editor/src/Resources/js/Pages/FormExample.vue
  → resources/js/Pages/FormExample.vue
```

Ajuste o import do modal:

```javascript
import CameraFormModal from '../vendor/image-editor/Components/CameraFormModal.vue'
```

Registe uma rota no host, por exemplo em `routes/web.php`:

```php
Route::get('/meu-formulario', fn () => inertia('FormExample'))->name('form.example');
```

---

## Laravel Nova (campo Vue embutido)

O modal **`CameraFormModal`** pode ser usado em campos Nova / Raven **sem** `@inertiajs/vue3` no `package.json` do host. O pacote resolve os botões da toolbar por:

1. prop `:action-buttons`
2. `Nova.config('imageEditor').actionButtons`
3. props Inertia partilhadas (apps Inertia puras)
4. valores por defeito

### PHP — `NovaServiceProvider`

```php
Nova::script('imageEditor', fn () => [
    'actionButtons' => config('image-editor.action_buttons', []),
]);
```

Ou passe `:action-buttons` directamente no componente Vue.

### Vite do host

```javascript
{
  find: '@image-editor',
  replacement: path.resolve(__dirname, 'vendor/pdmfc/image-editor/src/Resources/js'),
}
```

### Tailwind

Inclua os ficheiros do pacote no `content` do Tailwind do host:

```javascript
'./vendor/pdmfc/image-editor/src/Resources/**/*.{js,vue,blade.php}',
```

### `userId`

A prop **`user-id`** do modal é uma chave de armazenamento (`photos/tmp/{id}/`), **não** tem de ser `auth()->id()`. Pode ser o ID do registo (ex.: Fact, Processo), desde que use apenas `a-z`, `A-Z`, `0-9`, `_` e `-`.

Exemplo com carregamento lazy do modal:

```javascript
import { defineAsyncComponent } from 'vue'

const CameraFormModal = defineAsyncComponent(() =>
  import('@image-editor/Components/CameraFormModal.vue')
)
```

```vue
<CameraFormModal
  v-if="showEditor"
  v-model:open="showEditor"
  :user-id="storageId"
  :action-buttons="actionButtons"
  @use-in-form="onUseInForm"
/>
```

---

## Utilização num formulário

O fluxo recomendado separa **guardar no servidor** de **associar ao formulário**:

1. O utilizador abre o editor (popup).
2. Carrega, tira foto ou escolhe uma imagem na galeria.
3. Edita e clica **Guardar** no editor (persiste em `storage/app/public/photos/tmp/{userId}/`).
4. Na miniatura, clica no ícone **Usar no formulário** (tooltip).
5. O popup fecha e o formulário mostra a pré-visualização.

O componente **`CameraFormModal`** inclui overlay, barra com título, botão **Fechar**, bloqueio de scroll e o editor (`Camera` em modo modal).

### Exemplo mínimo (Vue / Inertia)

```vue
<script setup>
import { ref } from 'vue'
import CameraFormModal from '../vendor/image-editor/Components/CameraFormModal.vue'

const showEditor = ref(false)
const imageFilename = ref('')   // guardar na BD (ex.: coluna image_path)
const imagePreviewUrl = ref('') // só para <img>; pode incluir ?v=timestamp
const imagePreviewKey = ref(0)

const onImageChosen = (payload) => {
  if (payload?.filename) {
    imageFilename.value = payload.filename
  }
  if (payload?.url) {
    imagePreviewUrl.value = payload.url
    imagePreviewKey.value += 1
  }
}
</script>

<template>
  <form @submit.prevent="submit">
    <label>Título</label>
    <input v-model="title" type="text" />

    <div v-if="imagePreviewUrl" class="my-3">
      <img
        :key="imagePreviewKey"
        :src="imagePreviewUrl"
        alt="Imagem do formulário"
        class="max-h-48 object-contain"
      />
      <p class="text-xs text-gray-500">{{ imageFilename }}</p>
    </div>

    <button type="button" @click="showEditor = true">
      Abrir editor de imagens
    </button>

    <!-- Campo hidden para submissão -->
    <input type="hidden" name="image_filename" :value="imageFilename" />
  </form>

  <CameraFormModal
    v-model:open="showEditor"
    :user-id="userId"
    :initial-filename="imageFilename || undefined"
    :subtitle="imageFilename || undefined"
    @use-in-form="onImageChosen"
  />
</template>
```

(`userId` vem das props Inertia — ver secção abaixo.)

### QR Code — `user_id` vindo do formulário

O pacote **não** chama `Auth::user()`. O **host** decide qual ID enviar (não tem de ser o utilizador logado — pode ser o ID do registo, de um técnico, etc.) e passa-o à página Inertia; o editor reenvia-o no POST.

**Exemplo no host:**

```php
use PDMFC\ImageEditor\Support\ImageEditorSession;

$userId = $registo->user_id;
ImageEditorSession::primeBroadcastUser($userId);

return inertia('MeuFormulario', ['userId' => $userId]);
```

**Página Vue:**

```vue
<script setup>
defineProps({ userId: { type: [Number, String], required: true } })
</script>

<CameraFormModal v-model:open="showEditor" :user-id="userId" />
```

O pedido é `POST /api/camera/qrcode` com `{ user_id: ... }`. O pacote envia à API externa:

- `user_token` — ID sanitizado do utilizador
- `endpoint` — URL absoluta de callback enviada à API QR (ver `QRCODE_CALLBACK_URL` abaixo)
- `delivery_mode` — por omissão `callback_base64` (configurável por `QRCODE_DELIVERY_MODE`)

A API QR devolve o código (SVG ou imagem em base64). As fotos enviadas pelo telemóvel chegam ao callback e são guardadas em **`storage/app/public/photos/tmp/{userId}/`**. Cada utilizador só vê as suas imagens na galeria.

O popup do QR **pode fechar** — a galeria e o editor (modal) continuam abertos e actualizam em **tempo real** via **Laravel Reverb** + **Echo** (evento `PhotosUploadedFromMobile` no canal privado `image-editor.photos.{userId}`).

### Reverb + Echo no host

1. **`.env`** (além do QR):

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

APP_URL=https://seu-dominio.test
QRCODE_URL=https://api-qr.exemplo/qrcode
QRCODE_API_TOKEN=seu-token
QRCODE_DELIVERY_MODE=callback_base64
# URL absoluta do callback (obrigatória para QR). Defina host + path do projeto.
QRCODE_CALLBACK_URL=https://seu-dominio-publico.test/api/camera/callback/files/{userId}
# Path da rota POST (opcional; se vazio, extrai-se do path de QRCODE_CALLBACK_URL)
# IMAGE_EDITOR_CALLBACK_PATH=api/camera/callback/files/{userId}
IMAGE_EDITOR_BROADCASTING=true
```

**Callback QR (por projeto, via `.env`):**

| Variável | Descrição |
|----------|-----------|
| `QRCODE_CALLBACK_URL` | URL **completa** enviada à API QR. Obrigatória para QR. Deve incluir `{userId}` ou `{user_id}`. |
| `IMAGE_EDITOR_CALLBACK_PATH` | Path da rota `POST` no Laravel (opcional). Se omitido, usa o path de `QRCODE_CALLBACK_URL`. |
| `IMAGE_EDITOR_ROUTES_PREFIX` | Prefixo das restantes rotas do editor (`qrcode`, `photos`, …). Default: `api`. |

Exemplo com path customizado noutro projeto:

```env
QRCODE_CALLBACK_URL=https://app.cliente.com/webhooks/image-upload/{userId}
IMAGE_EDITOR_CALLBACK_PATH=webhooks/image-upload/{userId}
```

O valor de `endpoint` enviado à API QR é exactamente `QRCODE_CALLBACK_URL` com o `{userId}` substituído. A rota Laravel regista-se no mesmo path (via `IMAGE_EDITOR_CALLBACK_PATH` ou extraído da URL).

2. **`php artisan reverb:start`** (ou processo supervisor em produção).

3. **`resources/js/bootstrap.js`** — configurar `window.Echo` (Laravel 11 + Reverb):

```js
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

window.Echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
  wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
  forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
  enabledTransports: ['ws', 'wss'],
})
```

Instalar no host: `npm install laravel-echo pusher-js`

4. **Autorizar o canal privado** — use `ImageEditorSession::primeBroadcastUser($userId)` ao abrir o editor (ver exemplo acima). Pode sobrescrever com `image-editor.broadcasting.authorize` em `config/image-editor.php`.

5. O componente `Camera.vue` subscreve automaticamente se `window.Echo` existir.

Em desenvolvimento com telemóvel, use túnel (ngrok) em `QRCODE_CALLBACK_URL` e confirme que o browser do editor alcança o Reverb (`REVERB_HOST` / portas).

### Props e eventos do `CameraFormModal`

| Prop | Tipo | Descrição |
|------|------|-----------|
| `open` | `boolean` | Visibilidade do popup (`v-model:open`) |
| `initial-filename` | `string?` | Foto a pré-selecionar ao abrir |
| `title` | `string` | Título da barra (default: «Editor de imagens») |
| `subtitle` | `string?` | Texto após o título (ex.: nome do ficheiro) |
| `user-id` | `string \| number` | ID do utilizador (do host, ex. `Auth::id()`) |
| `z-index` | `number \| string` | Default `200` |

| Evento | Payload | Descrição |
|--------|---------|-----------|
| `update:open` | `boolean` | Sincronização do `v-model:open` |
| `close` | — | Popup fechado (Fechar ou após usar no formulário) |
| `use-in-form` | `{ filename, url, is_blank_canvas? }` | Imagem escolhida para o formulário |

A `url` inclui parâmetro `?v=` com o timestamp do ficheiro para evitar cache do browser após reedição da mesma foto.

### Backend no submit do formulário

Guarde o **`filename`** e o **`user_id`**, não a URL com `?v=` (é só para pré-visualização no browser):

```php
$userId = $request->input('user_id');
$filename = $request->input('image_filename');
$path = 'photos/tmp/' . $userId . '/' . $filename;

// Para mostrar de novo na UI (mesmo host da app):
$previewUrl = '/api/camera/photos/' . $userId . '/' . rawurlencode($filename);
```

Em produção, mantenha `IMAGE_EDITOR_DEMO_ROUTES` desligado (é o default) e use apenas rotas do host.

### Página completa (sem formulário)

Defina no host uma rota que renderize `Camera.vue` (galeria + editor a ecrã inteiro), ou use a rota de demo com `IMAGE_EDITOR_DEMO_ROUTES=true` em desenvolvimento.

Para embutir só o editor noutro contexto avançado, importe `Camera.vue` com `as-modal` e trate os eventos manualmente; para formulários, prefira **`CameraFormModal`**.

---

## Rotas principais

### API (registadas pelo pacote)

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/api/camera/photos?user_id=` | Lista de fotos do utilizador |
| POST | `/api/camera/upload` | Upload (`user_id` no body) |
| POST | `/api/camera/capture` | Captura webcam |
| POST | `{IMAGE_EDITOR_CALLBACK_PATH ou path de QRCODE_CALLBACK_URL}` | Callback QR (API externa, sem CSRF) |
| POST | `/api/camera/qrcode` | Obter QR (`user_id` no body) |
| POST | `/api/image/edit` | Aplicar edições / guardar (`user_id` no body) |
| DELETE | `/api/camera/photos` | Eliminar foto |

Todas as rotas API usam o prefixo `/api`. Por omissão:

- rotas interativas do editor (`/api/camera/photos`, upload, edição, etc.) usam middleware `web`
- o callback QR usa middleware `api` e o path definido em `QRCODE_CALLBACK_URL` / `IMAGE_EDITOR_CALLBACK_PATH`

Isto evita herdar `throttle:api` agressivo nas leituras normais da galeria. Pode ajustar em `config/image-editor.php` (`routes.browser_middleware`, `routes.callback_middleware`, `routes.prefix`, `routes.callback_path`).

### Web (opcional — demo ou host)

| Método | Rota | Quem regista |
|--------|------|----------------|
| GET | `/camera` | Demo do pacote (`IMAGE_EDITOR_DEMO_ROUTES=true`) ou stub publicado / rota do host |
| GET | `/camera/form-example` | Idem (requer `FormExample.vue` no host) |

Em integração real, substitua estes paths pelas rotas da sua aplicação.

---

## Botões da barra (upload, QR, câmara, folha)

Configure no `.env` do host quais botões aparecem na galeria:

```env
# ou IMAGE_EDITOR_ACTION_BUTTONS=...
ACTION_BUTTONS=upload,qrcode,camera,canvas
```

Valores aceites (separados por vírgula):

| Chave | Botão |
|-------|--------|
| `upload` | Carregar ficheiro |
| `qrcode` | QR Code (também `qr`) |
| `camera` | Tirar foto (webcam) |
| `canvas` | Nova folha em branco (também `blank`) |

Exemplo só upload e câmara:

```env
ACTION_BUTTONS=upload,camera
```

Por página/modal, pode sobrescrever com a prop Vue:

```vue
<CameraFormModal :action-buttons="['upload', 'qrcode']" ... />
```

---

## Funcionalidades

- Captura por webcam, upload (JPEG, PNG, GIF, WebP), QR (se configurado)
- Galeria: selecionar, duplicar, eliminar, folha em branco
- Editor: filtros, recorte, desfoque, pixelização (zona / pincel), nitidez, desenho, texto, marca de água, comparar antes/depois, undo/redo
- Modo formulário: ícone «Usar no formulário» nas miniaturas (`as-modal`)
- Notificações e confirmação antes de eliminar

---

## Segurança

- Rotas com middleware `web` / `api`
- Validação de tipos de ficheiro no upload
- Nomes de ficheiro sanitizados no servidor

Em produção, proteja as rotas com `auth` ou políticas do seu projeto.

---

## Resolução de problemas

| Problema | Solução |
|----------|---------|
| Página Inertia em branco | Confirme symlinks em `resources/js/vendor/image-editor`, um único `createInertiaApp` em `app.js`, e `npm run dev` ativo |
| Imagens não aparecem | Permissões em `storage/app/public/photos/tmp`; testar `/api/camera/photos/{userId}/{filename}` no browser |
| Formulário não atualiza após editar | Use «Usar no formulário» **depois** de Guardar; o pacote envia URL com `?v=timestamp` |
| 419 / CSRF em POST | Sessão `web` ativa; token CSRF no layout (`image-editor::app` já inclui meta) |

```bash
php artisan route:clear
php artisan config:clear
```

---

## Licença

MIT — ver [licença MIT](https://opensource.org/licenses/MIT).
