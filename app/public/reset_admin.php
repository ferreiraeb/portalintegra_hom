<?php
$pwd = 'Admin@123'; // troque aqui se quiser
$hash = password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 10]);
echo "HASH: $hash\n";
?>