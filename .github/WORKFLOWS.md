<!-- Generado por .github/scripts/listar-workflows.py: no lo edites a mano. -->
# Workflows

Lista generada de `.github/workflows/`: el nombre, cuándo corre cada uno y sus jobs, leídos del propio
workflow. Para qué sirve cada uno está en la sección «CI/CD» del [README](../README.md); esta tabla
se regenera con `python3 .github/scripts/listar-workflows.py`, y `estructura.yml` falla si no está al día.

| Archivo | Nombre | Cuándo corre | Jobs |
|---|---|---|---|
| [`estructura.yml`](workflows/estructura.yml) | Estructura del repo | `push`, `pull_request`, `workflow_dispatch` | `readme-projects`, `readme-workflows`, `workflows-listado`, `claude-md-rutas`, `enlaces-md`, `composer-validate`, `landing-page` |
| [`iconos.yml`](workflows/iconos.yml) | Iconos | `push`, `pull_request`, `workflow_dispatch` | `iconos` |
| [`informe-de-ramas.yml`](workflows/informe-de-ramas.yml) | Informe de ramas | `push`, `pull_request`, `schedule`, `workflow_dispatch` | `ramas` |
| [`laws-freshness.yml`](workflows/laws-freshness.yml) | Leyes - vigencia de los datos | `push`, `schedule`, `workflow_dispatch` | `freshness`, `issue` |
| [`laws-links.yml`](workflows/laws-links.yml) | Leyes - enlaces de referencia | `pull_request`, `schedule`, `workflow_dispatch` | `links`, `issue` |
| [`laws-modules.yml`](workflows/laws-modules.yml) | Leyes (privacidad y copyright) | `push`, `pull_request` | `test`, `database` |
| [`marcalibros-b.yml`](workflows/marcalibros-b.yml) | Marcalibros (generar catalogo y pruebas) | `push`, `pull_request`, `workflow_dispatch` | `catalogo` |
| [`pruebas-backoffice.yml`](workflows/pruebas-backoffice.yml) | Pruebas backoffice | `push`, `pull_request`, `workflow_dispatch` | `analisis-estatico`, `pruebas` |
| [`pruebas-citas-pos.yml`](workflows/pruebas-citas-pos.yml) | Pruebas citas POS | `push`, `pull_request`, `workflow_dispatch` | `pruebas` |
| [`pruebas-cobros-ingresos-funnels.yml`](workflows/pruebas-cobros-ingresos-funnels.yml) | Pruebas cobros, ingresos y funnels | `push`, `pull_request`, `workflow_dispatch` | `tests` |
| [`pruebas-defamatory-content-review.yml`](workflows/pruebas-defamatory-content-review.yml) | Pruebas defamatory-content-review | `push`, `pull_request`, `workflow_dispatch` | `phpunit`, `chat-front-guard` |
| [`pruebas-document-formats.yml`](workflows/pruebas-document-formats.yml) | Pruebas document-formats | `push`, `pull_request`, `workflow_dispatch` | `document-formats`, `document-formats-database` |
| [`pruebas-listado-colores.yml`](workflows/pruebas-listado-colores.yml) | Pruebas Listado-colores | `push`, `pull_request`, `workflow_dispatch` | `listado-colores`, `listado-colores-matriz` |
| [`pruebas-listado-paises.yml`](workflows/pruebas-listado-paises.yml) | Pruebas Listado-paises | `push`, `pull_request`, `workflow_dispatch` | `listado-paises`, `reproducir` |
| [`pruebas-marcalibros.yml`](workflows/pruebas-marcalibros.yml) | Pruebas marcalibros | `push`, `pull_request`, `workflow_dispatch` | `php`, `catalogo`, `navegador` |
| [`pruebas-phone-directory.yml`](workflows/pruebas-phone-directory.yml) | Pruebas phone-directory | `push`, `pull_request`, `workflow_dispatch` | `phone-directory`, `phone-directory-ports` |
| [`pruebas-portadas-svg.yml`](workflows/pruebas-portadas-svg.yml) | Pruebas portadas SVG | `push`, `pull_request`, `workflow_dispatch` | `pruebas` |
| [`pruebas-signos-zodiacales.yml`](workflows/pruebas-signos-zodiacales.yml) | Pruebas signos-zodiacales | `push`, `pull_request`, `workflow_dispatch` | `signos-zodiacales`, `galeria`, `php` |
| [`pruebas-siluetas-ancestrales.yml`](workflows/pruebas-siluetas-ancestrales.yml) | Pruebas siluetas-ancestrales | `push`, `pull_request`, `workflow_dispatch` | `siluetas-ancestrales` |
| [`pruebas-url-routing.yml`](workflows/pruebas-url-routing.yml) | Pruebas url-routing | `push`, `pull_request`, `workflow_dispatch` | `url-routing`, `analisis-estatico`, `despliegue` |
| [`pruebas-web-animations.yml`](workflows/pruebas-web-animations.yml) | Pruebas web-animations | `push`, `pull_request`, `workflow_dispatch` | `web-animations` |
| [`vps-setup-e2e.yml`](workflows/vps-setup-e2e.yml) | vps-setup e2e | `pull_request`, `schedule`, `workflow_dispatch` | `install-all` |
| [`vps-setup.yml`](workflows/vps-setup.yml) | vps-setup | `push`, `pull_request` | `scripts`, `real-tools` |
