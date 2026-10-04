<?php
require_once 'includes/helpers.php';
unset($_SESSION['id_vendedor'], $_SESSION['taller']);
avisar('Cerraste la sesión de vendedor.');
ir('vendedor_login.php');
