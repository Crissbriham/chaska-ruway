<?php
function formatearPrecio($monto) {
    return 'S/ ' . number_format($monto, 2, '.', ',');
}