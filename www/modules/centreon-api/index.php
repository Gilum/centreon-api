<?php

require_once realpath(dirname(__FILE__) . "/../../../config/centreon.config.php");
require_once _CENTREON_PATH_ . 'www/class/centreon.class.php';
require_once _CENTREON_PATH_ . "/www/class/centreonDB.class.php";
require_once dirname(__FILE__) . '/class/webService.class.php';
require_once dirname(__FILE__) . '/exceptions.php';


$pearDB = new CentreonDB();

/* Purge old token */
$pearDB->query("DELETE FROM ws_token WHERE generate_date < DATE_SUB(NOW(), INTERVAL 1 HOUR)");

/* Test if the call is for authenticate */
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_GET['action']) && $_GET['action'] == 'authenticate') {
    if (false === isset($_POST['username']) || false === isset($_POST['password'])) {
        CentreonWebService::sendJson("Bad parameters", 400);
    }

    /* @todo Check if user already have valid token */
    require_once _CENTREON_PATH_ . "/www/class/centreonLog.class.php";
    require_once _CENTREON_PATH_ . "/www/class/centreonAuth.class.php";

    /* Authenticate the user */
    $log = new CentreonUserLog(0, $pearDB);
    $auth = new CentreonAuth($_POST['username'], $_POST['password'], 0, $pearDB, $log, 1, "", "API");
    if ($auth->passwdOk == 0) {
        CentreonWebService::sendJson("Bad credentials", 403);
        exit();
    }

    /* Check if user exists in contact table */
    $reachAPI = 0;
    $res = $pearDB->prepare("SELECT contact_id, reach_api, contact_admin FROM contact WHERE contact_activate = '1' AND contact_register = '1' AND contact_alias = ?");
    $res = $pearDB->execute($res, array($_POST['username']));
    while ($data = $res->fetchRow()) {
      if (isset($data['contact_admin']) && $data['contact_admin'] == 1) {
            $reachAPI = 1;
        } else {
            if (isset($data['reach_api']) && $data['reach_api'] == 1) {
               $reachAPI = 1;
            }
        }
    }

    /* Sorry no access for this user */
    if ($reachAPI == 0) {
        CentreonWebService::sendJson("Unauthorized - Account not enabled", 401);
        exit();
    }

    /* Insert Token in API webservice session table */
    $token = base64_encode(uniqid('', true));
    $res = $pearDB->prepare("INSERT INTO ws_token (contact_id, token, generate_date) VALUES (?, ?, NOW())");
    $pearDB->execute($res, array($auth->userInfos['contact_id'], $token));

    /* Send Data in Json */
    CentreonWebService::sendJson(array('authToken' => $token));
}

/* Test authentication */
if (false === isset($_SERVER['HTTP_CENTREON_AUTH_TOKEN'])) {
    CentreonWebService::sendJson("Unauthorized", 401);
}

/* Create the default object */
$res = $pearDB->prepare("SELECT c.* FROM ws_token w, contact c WHERE c.contact_id = w.contact_id AND token = ?");
$res = $pearDB->execute($res, array($_SERVER['HTTP_CENTREON_AUTH_TOKEN']));
if (PEAR::isError($res)) {
    CentreonWebService::sendJson("Database error", 500);
}
$userInfos = $res->fetchRow();
if (is_null($userInfos)) {
    CentreonWebService::sendJson("Unauthorized", 401);
}

$centreon = new Centreon($userInfos);
$oreon = $centreon;

CentreonWebService::router();
