# Guía de desarrollo — ai-kit-filament

> Guía interna del paquete `freddymx/ai-kit-filament`. Para instalación y uso de consumo, ver `README.md`.

## 1. Objetivo

Este paquete entrega un **panel Filament v5 listo para usar** sobre el service layer de ai-kit: gestionar knowledge bases (Collections) y documentos, ver conversaciones con token usage, probar el chat en un Playground y mirar métricas en un Dashboard. El integrador solo hace `->plugin(AiKitPlugin::make())` en su `PanelProvider`; **no escribe controllers ni Blade propios**. Todo aparece bajo el grupo de navegación `AI Kit`.

Concretamente, resuelve el "no-code" del pipeline RAG: crear una collection, subir un PDF/Word/Excel o agregar una URL, ver el estado de indexación e inspeccionar los chunks, replay de conversaciones con tokens por mensaje, demo en vivo contra una collection y estimación de costo.

## 2. Relación con la suite

| Paquete | Rol | Dependencia |
| --- | --- | --- |
| `freddymx/ai-kit-core` | Ingestion, retrieval, agents, CLI, HTTP API, MCP, `AiProviderResolver` / `UsageStats` | requerido `^0.4` |
| `freddymx/ai-kit-ui-livewire` | Componente `<livewire:ai-kit.chat>` (Livewire 4) con streaming y citas | requerido `^0.4` |
| **`freddymx/ai-kit-filament`** (este) | Panel de administración Filament v5 | — |

Es **una de las cuatro caras sobre el mismo service layer**: la misma lógica es scriptable por CLI, invocable por HTTP API y expuesta a agentes vía MCP (todo en `ai-kit-core`), además de este panel. El Playground **embebe el componente Livewire del paquete UI** (`<livewire:ai-kit.chat :collection="..." :history="true">`), no reimplementa el chat.

El vendor Composer es `freddymx`, pero el **namespace PHP sigue siendo `SolutionForest\AiKit\Filament\`** (PSR-4 `src/`). `dev-main` tiene branch alias `0.4.x-dev`.

## 3. Stack y requisitos

- **PHP** `^8.5`; **Laravel** `^12.0|^13.0` (`illuminate/support` + dev `laravel/framework`).
- **Filament** `^5.11` (en el `composer.lock` de desarrollo: `v5.12.0`).
- **Livewire 4** (transitivo vía Filament; lock `v4.4.10`). No hay build step: vistas Blade con estilos inline, sin compilar JS/CSS.
- `freddymx/ai-kit-core: ^0.4` y `freddymx/ai-kit-ui-livewire: ^0.4`.
- **Tests:** Pest `^5.0` (lock `v5.3.7`) + `orchestra/testbench: ^10.0|^11.0`.
- **Dev-deps adicionales:** `laravel/boost: ^2.10`, `laravel/pao: ^1.1` (esta última no se usa en `src/` ni en tests, al igual que en el core).

## 4. Estado actual

- **Versión publicada: `v0.4.4`** (tag exacto en `HEAD`, `git describe` → `v0.4.4`).
- **Tests: `24 passed / 141 assertions`** (`vendor/bin/pest`, verificado en este repo).
- Capacidades implementadas:
  - **Collections** con *relation manager* de Documents: upload de `pdf`, `txt`, `md`, `html`, `.docx`, `.xlsx`, **Add from URL**, ver chunks, re-index, delete, badge de status (`pending / processing / indexed / failed`) y `chunk_count`.
  - **Conversations read-only**: columna con tokens totales (in / out) y vista de mensajes con tokens por mensaje; `canCreate()` es `false`.
  - **Playground**: selector de collection + chat en vivo embebido del paquete UI.
  - **Dashboard** propio (`AI Kit → Dashboard`): documents indexed, chunks, conversaciones de la semana, tokens (30d), costo estimado (30d) y chart apilado prompt vs. completion por día.
  - **AI Settings page**: overrides de provider en DB con descubrimiento de modelos locales; feature flags.
- `composer validate` pasa sin errores.

## 5. Estructura del código

```
src/AiKitPlugin.php                 # Plugin: registra resources y pages condicionadas
src/AiKitFilamentServiceProvider.php# loadViewsFrom(..., 'ai-kit-filament')
src/Pages/                          # AiSettingsPage, AnalyticsDashboard, Playground
src/Resources/
  CollectionResource.php            # CRUD + DocumentsRelationManager
  DocumentResource.php              # rutas/upload; shouldRegisterNavigation() = false
  ConversationResource.php          # solo lectura + columna de tokens
  */Pages/                          # List/Create/Edit Collections, List/Create Documents,
                                    # List/View Conversations
  CollectionResource/RelationManagers/DocumentsRelationManager.php
src/Widgets/                        # AiKitStatsWidget, TokenUsageChart
src/Support/LocalModelDiscovery.php # GET /v1/models o /api/tags, timeout 5s, nunca lanza
resources/views/                    # analytics.blade.php, chunks.blade.php,
                                    # conversation.blade.php, playground.blade.php,
                                    # filament/pages/ai-settings-page.blade.php
tests/
  TestCase.php, Pest.php            # Testbench + providers de Filament/core/UI/panel fixture
  Feature/PanelTest.php             # 9 tests: registro, widgets, flags, upload, stats, playground
  Feature/SettingsPageTest.php      # 15 tests: page, save/reset, key, discovery, fallback
  Fixtures/AdminPanelProvider.php   # panel 'admin', path 'admin', AiKitPlugin
  Support/FakeEmbedder.php          # embedder determinístico (DIMENSIONS fijas)
```

Notas de registro: los widgets **no** se registran panel-wide (`getWidgets()` del panel no los contiene); se renderizan como `getHeaderWidgets()` de `AnalyticsDashboard`. `DocumentResource` se registra para rutas y lógica de upload pero está oculto en la navegación; los documentos se gestionan inline desde cada Collection. `AiKitPlugin::register()` condiciona `AnalyticsDashboard` con `features.dashboard` y `AiSettingsPage` con `features.settings`; `Playground` siempre se registra.

## 6. Configuración

Los flags viven en **`config/ai-kit.php` del core**, no en este paquete:

| Flag | Env | Default | Efecto aquí |
| --- | --- | --- | --- |
| `features.dashboard` | `AI_KIT_DASHBOARD` | `true` | Registra página/ruta del Dashboard |
| `features.token_usage` | `AI_KIT_TOKEN_USAGE` | `true` | Columna de tokens, tokens por mensaje, stats de tokens/costo y chart |
| `features.settings` | `AI_KIT_SETTINGS` | `true` | Registra la página AI Settings |
| `features.apply_to_sdk` | `AI_KIT_APPLY_TO_SDK` | `true` | Publica además `ai.default` / `ai.default_for_embeddings` en el SDK |

### AI Settings page

Ruta `/admin/ai-settings` en el setup default, grupo `AI Kit`, `navigationSort = 5`, slug `ai-settings` (`AiSettingsPage`). Secciones:

- **Providers**: `chat_provider` y `embeddings_provider`, con opciones `local`, `anthropic`, `openai`, `openrouter`, `gemini`, `groq`, `mistral`, `deepseek`, `xai` (placeholder *Use .env default*).
- **Chat / Embeddings**: `chat_model`, `embeddings_model`, `embeddings_dimensions`. Campos cloud y local conviven en la misma sección pero solo se muestra uno según el provider (`Get`).
- **Local server** (visible si alguno de los providers es `local`): `local_driver` (`openai-compatible` | `ollama`), `local_url` (requerido, placeholder `http://localhost:11434/v1`), `local_key` (password revealable), `local_chat_model`, `local_embeddings_model`, `local_embeddings_dimensions`.
- **Effective configuration**: tabla colapsada con el origen (`database` / `env`) y el valor vigente de cada key.

**Precedencia:** la fila de `ai_kit_settings` (DB) gana sobre `config/ai-kit.php` (`.env`); un valor `null` cae al default. `save()` hace upsert vía `AiProviderResolver::save()` y llama de inmediato a `reapply()`; `Reset to .env` borra la fila y vuelve a aplicar `config()` en el **mismo request**, sin reload ni `config:clear`. `applyToConfig()` también publica `ai.providers.local` (url/key/modelos/dimensiones); los globales del SDK se publican solo si `features.apply_to_sdk` está en `true`.

**Tenancy:** con `AI_KIT_TENANCY=true` la página edita **la fila del tenant activo** (una por tenant en la misma DB). El host debe setearlo antes de abrir el panel, por ejemplo `AiKit::setTenant($tenantId)` en un middleware del panel o `AiKit::withTenant($id, ...)`; este paquete **no** se integra automáticamente con Filament Tenancy. Sin tenant activo, `mount()` muestra un `Notification::warning()` aclarando la situación y `save()`/`reset()` muestran otro warning y **no escriben** (el core lanza `RuntimeException`, la página la convierte en notificación para no dar 500). Con tenancy desactivado se mantiene la fila global de siempre.

**Descubrimiento de modelos:** `LocalModelDiscovery::fetch()` usa `GET {url}/v1/models` para `openai-compatible` (lee `data[].id`, agregando `/v1` si falta) y `GET {url}/api/tags` para `ollama` (lee `models[].name`). Corre al elegir `local`, al cambiar de driver, al salir del campo `local_url` (`live(onBlur: true)`) y con el botón de refresh. Timeout de **5s**, `Accept: JSON` y Bearer token opcional. **Nunca lanza**: cualquier fallo vuelve como `['models' => [], 'error' => ...]` y el formulario cambia el Select por un `TextInput` `{name}_manual` para escribir el id a mano; el error también se muestra como `Notification::warning()`.

## 7. Flujo de desarrollo local

```bash
cd ../ai-kit-filament
composer install          # usa los path repos ../ai-kit-core y ../ai-kit-ui-livewire
vendor/bin/pest           # 26 passed / 159 assertions
composer validate
```

Los siblings viven al mismo nivel y se declaran como repos `path` con `symlink: true` en `composer.json`, así que los cambios del core/UI se ven sin publicar. `composer.lock` está **gitignoreado**; en CI se usa `composer update --prefer-dist --no-interaction --no-progress`.

`.github/workflows/tests.yml` corre solo con `release: [published]` y `workflow_dispatch` (no en cada push), sobre PHP `8.5`, con `zip, sqlite3, pdo_sqlite`, y clona `../ai-kit-core` y `../ai-kit-ui-livewire` antes de instalar.

**Cambios transversales:** publicar siempre en orden **core → ui-livewire → filament**. En 0.x, `^0.4` cubre `0.4.*` pero **no** `0.5.0`: los tres paquetes se mueven juntos y hay que actualizar constraints y `branch-alias` en el mismo release. Los tags se publican por separado en cada repo.

## 8. Comandos y flujos clave

Registro del plugin (en el `PanelProvider` de la app):

```php
use SolutionForest\AiKit\Filament\AiKitPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->default()->id('admin')->path('admin')
        ->plugin(AiKitPlugin::make());
}
```

| Flujo | Camino real en el código |
| --- | --- |
| Upload → indexación | `FileUpload` en `disk('local')`, `directory('ai-kit/uploads')` → `DocumentResource::prepareUpload()` resuelve `source_path`, `mime` y `title` → `IngestionService::queue($record)` encadena `ExtractText → ChunkDocument → EmbedChunks` en la conexión `AI_KIT_QUEUE`. Estado `pending → processing → indexed` (o `failed`). Add from URL usa `createFromUrl(..., sync: false)`. |
| Ver chunks | Action `viewChunks` renderiza `ai-kit-filament::chunks` con `$record->chunks()->orderBy('chunk_index')->get()`; muestra `embedded`/`pending`, `embedding_model` y 600 chars por chunk. |
| Token usage | `UsageStats::conversationTokens()` en la columna de Conversations y en `ViewConversation`; `UsageStats::messageTokens($message->usage)` por mensaje en `conversation.blade.php`. En el Dashboard, `UsageStats::totals(30)` y `daily(14)`. |
| Descubrir y guardar modelo local | Elegir `local` → seleccionar driver / escribir URL → el page consulta el catálogo → elegir modelo del Select → **Save**: `local_chat_model` se escribe en `ai_kit_settings` y `applyToConfig()` lo refleja en `ai-kit.*` y `ai.providers.local` al instante. |
| Resetear a `.env` | **Reset to .env** (con confirmación) → `AiSettingsPage::reset()` → `AiProviderResolver::reset()` borra la fila → `applyToConfig()` restaura los valores de entorno. |

## 9. Decisiones de diseño y gotchas

- **`reset(...$properties)`:** Livewire declara ese método en cada componente, así que la firma debe permanecer compatible. Sin argumentos (nuestro action) borra los overrides; con argumentos delega en `parent::reset(...$properties)` para conservar el comportamiento de Livewire.
- **Sin tenant = sin escritura:** `save()`/`reset()` capturan `Throwable` pero solo convierten en warning el `RuntimeException` del guard "ai-kit tenant"; cualquier otro error se relanza para no ocultar bugs. El warning incluye el mensaje del core y no toca la DB.
- **Cambio de tenant en el host:** `AiKit::setTenant()` re-publica la config del nuevo tenant (y `withTenant()` también al restaurar en `finally`). En Octane/workers, setear el tenant por request es obligatorio: `config()` es process-global y si no se re-aplica se vería la config del tenant anterior.
- **Filament v5, no v3/v4:** `AiSettingsPage::form(Schema $schema)` usa `Filament\Schemas\Schema` y `Filament\Schemas\Components\{Form, Actions, Section, Html}` más `Filament\Actions\Action`. Trabajar con la API de `Forms\Form` antigua rompe el page.
- **Key cifrada y enmascarada:** el core guarda `local_key` cifrado y `effective()` lo devuelve enmascarado (`supe********`). `save()` descarta el campo si está vacío, `null` o igual al mask; `discoveryKey()` tampoco envía el mask como Bearer token. **El mask nunca se guarda.**
- **Campos ocultos:** en Filament v5 el default es `isDehydratedWhenHidden = false`, así que un field oculto **no entra al state deshidratado** (no se graba "a null" literalmente; la columna queda `null` o conserva su valor). Es intencional: los pickers locales llevan `dehydratedWhenHidden(false)` para que el Select y el input manual no se sobreescriban; `save()` re-mapea `{name}_manual` sobre `{name}`. Los tests congelan este comportamiento (`chat_model` cloud oculto queda `null`).
- **`LocalModelDiscovery` nunca lanza:** captura `Throwable`, llama a `report($e)` y devuelve un `error` amigable; por eso la UI siempre tiene un fallback manual.
- **CSS del host:** el Playground embebe el componente del paquete UI; las reglas del host sobre `p`/`li` pueden ganar por especificidad al `text-align` heredado. El paquete UI lo mitiga con prefijo `ck-` y `text-align:left!important` en los hijos markdown; si el panel se ve corrido, revisar ese componente, no esta Blade.
- **`features.settings = false`** oculta la página y su ruta, pero **no desactiva los overrides**: el resolver del core sigue leyendo `ai_kit_settings` (y `applyToConfig()` sigue publicando en `config()`).

## 10. Verificación y publicación

Checklist antes de publicar:

1. `vendor/bin/pest` en este paquete (y en los hermanos si el cambio es transversal) — estado actual: `26 passed / 159 assertions`.
2. `composer validate` sin errores.
3. Revisar los cuatro flags con `false` y `true`, en especial que `settings=false` no rompa el resolver y que `token_usage=false` oculte columna + stats + chart.
4. Verificar el flujo completo en el panel fixture: upload → reindex → chunks → Playground → save/reset de AI Settings.
5. Tag semver en 0.x: **patch** para fixes (`0.4.3 → 0.4.4`), **minor** para features (`0.4.4 → 0.5.0`); actualizar `branch-alias` y constraints `^0.4` de los hermanos cuando corresponda.
6. `git push origin main` y `git push origin vx.y.z`. Packagist se autoactualiza por el hook de GitHub; no se publica a mano.
7. Verificación con **instalación limpia**: `composer require freddymx/ai-kit-filament:<versión>` en un proyecto Laravel 12/13 con PHP 8.5, registrar el plugin y confirmar que `/admin` muestra el grupo `AI Kit`.

El CI corre al publicar el release, así que conviene confirmar que el workflow `tests` termina en verde.

## 11. Límites conocidos / próximos pasos

- El costo es **estimado**: `UsageStats` multiplica tokens logueados por `config('ai-kit.pricing')` ($ por 1M tokens input/output) según el modelo activo; modelos sin entrada explícita caen en `default` con costo `0`.
- El descubrimiento depende de que el servidor local exponga `/v1/models` o `/api/tags` con esos shapes; si no, se cae al input manual (y un timeout de 5s puede dar falsos negativos en servidores fríos).
- Requiere **PHP 8.5 y Filament 5**: no hay compatibilidad con versiones anteriores del stack en esta rama `0.4.x`.
- `DocumentResource` no aparece en el menú (se gestiona dentro de Collection); los widgets tampoco se registran panel-wide. Son decisiones deliberadas, no bugs.
- `laravel/pao` está en `require-dev` pero no se usa en este paquete.
- Esta guía refleja el estado del repo al **25/11/2026**. Después de cada release conviene revisarla, sobre todo la versión publicada, el resultado de los tests y las constraints `^0.4`.
