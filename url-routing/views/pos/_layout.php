<?php
use App\Support\Cart;

$sitio = 'Contrastocolor';
$tiposBusqueda = [8 => t('tipo.productos'), 4 => t('tipo.categorias'), 7 => t('tipo.colecciones'), 6 => t('tipo.etiquetas'), 5 => t('tipo.atributos')];
$carritoTotal = (new Cart())->totalArticulos();
$navExtra = [['href' => '/c/', 'label' => t('layout.carrito') . ($carritoTotal > 0 ? " ({$carritoTotal})" : '')]];
include __DIR__ . '/../_layout.php';
