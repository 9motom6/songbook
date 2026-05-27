<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\Songbook\AppInfo\Application::APP_ID, OCA\Songbook\AppInfo\Application::APP_ID . '-main');
Util::addStyle(OCA\Songbook\AppInfo\Application::APP_ID, OCA\Songbook\AppInfo\Application::APP_ID . '-main');

?>

<div id="songbook"></div>
