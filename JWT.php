<?php

include_once './FirebaseJWT/JWT.php';
include_once './FirebaseJWT/ExpiredException.php';
include_once './FirebaseJWT/BeforeValidException.php';
include_once './FirebaseJWT/SignatureInvalidException.php';

/**
 * IMPORTANT:
 * You must specify supported algorithms for your application. See
 * https://tools.ietf.org/html/draft-ietf-jose-json-web-algorithms-40
 * for a list of spec-compliant algorithms.
 */
function createJWT($claims) {
    $claims['exp'] = time() + 60 * 1;
    $claims['iat'] = time();
    $claims['nbf'] = time();
    $key = "qwerty";
    return \Firebase\JWT\JWT::encode($claims, $key, 'HS512');
}

function verifyJWT($token) {
    $key = "qwerty";
    try {
        $obj = \Firebase\JWT\JWT::decode($token, $key, array('HS512'));
        return $obj->id;
    } catch (\Firebase\JWT\SignatureInvalidException $exc) {
        echo 'SignatureInvalidException';
    } catch (\Firebase\JWT\BeforeValidException $exc) {
        echo 'BeforeValidException';
    } catch (\Firebase\JWT\ExpiredException $exc) {
        echo 'ExpiredException';
    } catch (Exception $exc) {
        echo $exc->getTraceAsString();
    }
    return null;
}
