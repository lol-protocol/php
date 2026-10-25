# Seguridad

Este repositorio reúne proyectos que manejan datos de personas (nombres, direcciones, teléfonos, información genealógica). [`PRIVACIDAD-Y-HONRA.md`](PRIVACIDAD-Y-HONRA.md) explica las decisiones de privacidad de los proyectos; este archivo es solo para reportar una vulnerabilidad o una fuga real.

## Cómo reportar

Si encuentras una vulnerabilidad de seguridad, o una forma de llegar a datos personales que no deberían estar a la vista (por ejemplo, una entrada de `phone-directory/` que se muestra sin pasar por la vista de visitante, o una forma de saltarse el filtro de `defamatory-content-review/`), **no publiques los detalles en un issue ni en un pull request**.

- Si en la pestaña **Security** del repositorio aparece **«Report a vulnerability»**, úsalo: el reporte llega en privado a quien mantiene el repositorio.
- Si no aparece, abre un issue que diga solo «Tengo un reporte de seguridad para quien mantiene el repositorio», **sin detalles**, y espera a que te contacten para pasarlos en privado.

Incluye, si puedes:

- qué proyecto, y qué archivo o ruta;
- los pasos para reproducirlo;
- qué datos quedan expuestos y a quién.

## Qué no es esto

- Dudas sobre lo que dice una ley de privacidad o de derechos de autor (`privacy-laws/`, `copyright-laws/`): esas bases son informativas, no asesoría legal, y no son un hallazgo de seguridad.
- Un cabo suelto que `PRIVACIDAD-Y-HONRA.md` ya da por pendiente: no hace falta reportarlo, ya se sabe.
- Una mejora sin riesgo real para los datos de nadie: eso va como issue o pull request normal.
