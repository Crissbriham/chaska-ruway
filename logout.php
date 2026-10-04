<?php
require_once 'includes/helpers.php';
session_destroy();
session_start();
avisar('Cerraste sesión.');
ir('index.php');
