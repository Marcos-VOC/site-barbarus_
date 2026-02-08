<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

session_unset();
session_destroy();

session_start();
setFlash('success', 'Sessão encerrada.');
redirect('socio.php');
