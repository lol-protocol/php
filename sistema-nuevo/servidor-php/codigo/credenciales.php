<?php

declare(strict_types=1);

/**
 * Credencial demo del backoffice (usuario único, sin roles).
 *
 * Usuario:     admin
 * Contraseña:  admin123
 *
 * El hash de abajo se generó una vez con password_hash('admin123', PASSWORD_DEFAULT);
 * nunca se guarda ni compara la contraseña en texto plano. Para un despliegue real,
 * esto debería salir del código fuente (variable de entorno / gestor de secretos) y
 * soportar más de un usuario — queda fuera de alcance para este prototipo.
 */
return [
    'username' => 'admin',
    'password_hash' => '$2y$12$G3u25fAQTrUyIGHbYfHjqeX01bRMz5qD/lbaENU/NPh2QMb51SaCm',
];
