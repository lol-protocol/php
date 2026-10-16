# _Garbage

Codigo de `vps-setup/` que quedo sin uso.

- `ufw_check_enabled.sh`: funcion de `lib.sh` que ningun script llamaba
  (`ufw_allow()` ya comprueba por su cuenta que UFW este activo).

Se conserva aqui en vez de borrarse por si sirve de referencia, pero no se
sourcea desde ningun script.

- `VPS-SETUP-GUIDE.md`: guia paso a paso que repetia lo de `README.md` (instalacion, DNS,
  verificacion, troubleshooting) y `DEPLOYMENT.md`/`DOMAINS.md`, y habia quedado desactualizada
  (sin los scripts 07-10; incluia un ejemplo de backup que ya no forma parte del proyecto).
  Su contenido util vive ahora en `README.md` (uso y troubleshooting), `DEPLOYMENT.md`
  (despliegue completo) y `DOMAINS.md` (DNS y SSL por dominio).

- `DEPLOYMENT-manual.md` y `DOMAINS-manual.md`: versiones anteriores de `DEPLOYMENT.md` y
  `DOMAINS.md`. Explicaban como configurar cada dominio y app A MANO (`nano` + `ln -sf` +
  `certbot`), lo que hoy hacen `10_A`/`10_B` y los scripts 03-06 con validaciones, y tenian datos
  que ya no valen: `initech.fun` como dominio por defecto de los scripts, un bloque PHP en el
  vhost de la landing, rutas de apps distintas de las que usan los scripts, el VPS como "activo"
  con una IP fija y un ejemplo de backup. Un vhost creado como ahi se indica no lleva la marca
  `# vps-setup:` y los scripts se niegan despues a tocarlo.
