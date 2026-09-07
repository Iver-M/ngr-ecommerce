<?php
require_once dirname(__DIR__, 2) . '/core/paths.php';
session_start();
session_destroy();
header("Location: login.php");
exit;
