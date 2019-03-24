<?php

include_once './JWT.php';

$claims['id'] = 84;

$token = createJWT($claims);
print $token;

print '<br>';

$id = verifyJWT($token);
print $id;
