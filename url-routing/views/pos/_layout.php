<?php
use App\Support\Cart;

$sitio = 'Contrastocolor';
$tiposBusqueda = [8 => 'Productos', 4 => 'Categorías', 7 => 'Colecciones', 6 => 'Etiquetas', 5 => 'Atributos'];
$carritoTotal = (new Cart())->totalArticulos();
$navExtra = [['href' => '/cart/', 'label' => 'Carrito' . ($carritoTotal > 0 ? " ({$carritoTotal})" : '')]];
include __DIR__ . '/../_layout.php';
