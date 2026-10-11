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
