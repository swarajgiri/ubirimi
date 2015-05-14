<?php

/*
 *  Copyright (C) 2012-2015 SC Ubirimi SRL <info-copyright@ubirimi.com>
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License version 2 as
 *  published by the Free Software Foundation.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301, USA.
 */

use Ubirimi\Service\ConfigService;
use Ubirimi\Container\UbirimiContainer;
use Ubirimi\Service\UbirimiInjector;
use Ubirimi\ServiceProvider\UbirimiCoreServiceProvider;

require_once __DIR__ . '/../vendor/autoload.php';

/* parse .properties file and make them available in the container */
$configsApplication = ConfigService::process(__DIR__ . '/../app/config/app.properties');
$configsDatabase = ConfigService::process(__DIR__ . '/../app/config/db.properties');
$configsSMTP = ConfigService::process(__DIR__ . '/../app/config/smtp.properties');
$configsSubversion = ConfigService::process(__DIR__ . '/../app/config/subversion.properties');
$configsRabbitMQ = ConfigService::process(__DIR__ . '/../app/config/rabbitmq.properties');

$configs = array_merge($configsApplication, $configsDatabase, $configsSMTP, $configsSubversion, $configsRabbitMQ);

/* register global configs to the container */
UbirimiContainer::loadConfigs($configs);
UbirimiContainer::register(new UbirimiCoreServiceProvider());