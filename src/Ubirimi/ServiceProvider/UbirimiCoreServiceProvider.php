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

namespace Ubirimi\ServiceProvider;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\WebProcessor;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Session\Attribute\NamespacedAttributeBag;
use Symfony\Component\HttpFoundation\Session\Flash\AutoExpireFlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\Templating\Loader\FilesystemLoader;
use Symfony\Component\Templating\PhpEngine;
use Symfony\Component\Templating\TemplateNameParser;
use Ubirimi\Api\Service\BasicAuthenticationService;
use Ubirimi\Container\ServiceProviderInterface;
use Ubirimi\DbMonologHandler;
use Ubirimi\Service\ClientService;
use Ubirimi\Service\DatabaseConnectorService;
use Ubirimi\Service\EmailService;
use Ubirimi\Service\LoginTimeService;
use Ubirimi\Service\MessageQueueService;
use Ubirimi\Service\PasswordService;
use Ubirimi\Service\RepositoryService;
use Ubirimi\Service\TemplateService;
use Ubirimi\Service\UserService;
use Ubirimi\Service\WarmUpService;

class UbirimiCoreServiceProvider implements ServiceProviderInterface
{
    public function register(\Pimple $pimple)
    {
        $pimple['db.connection'] = $pimple->share(function() {
            $databaseConnector = new DatabaseConnectorService();

            return $databaseConnector->getConnection();
        });

        $pimple['repository'] = $pimple->share(function() {
            return new RepositoryService();
        });

        $pimple['api.auth'] = $pimple->share(function($pimple) {
            $basicAuthenticationService = new BasicAuthenticationService();
            $basicAuthenticationService->setPasswordService($pimple['password']);

            return $basicAuthenticationService;
        });

        $pimple['password'] = $pimple->share(function() {
            return new PasswordService();
        });

        $pimple['dispatcher'] = $pimple->share(function() {
            return new EventDispatcher();
        });

        $pimple['logger'] = $pimple->share(function() {

            $logger = new Logger('ubirimi.activity');
            $IntrospectionProcessor = new IntrospectionProcessor();
            $webProcessor = new WebProcessor();

            if (!file_exists(__DIR__ . '/../../../app/logs/ubirimi.log')) {
                mkdir(__DIR__ . '/../../../app/logs', 0755, true);
            }
            $logger->pushHandler(new StreamHandler(__DIR__ . '/../../../app/logs/ubirimi.log', Logger::DEBUG));
            $logger->pushHandler(new DbMonologHandler(), Logger::DEBUG);
            $logger->pushProcessor($IntrospectionProcessor);
            $logger->pushProcessor($webProcessor);

            return $logger;
        });

        $pimple['email'] = $pimple->share(function($pimple) {
            if (php_sapi_name() === "cli") {
                return new EmailService();
            }

            return new EmailService($pimple['session']);
        });

        $pimple['client'] = $pimple->share(function($pimple) {
            return new ClientService();
        });

        $pimple['user'] = $pimple->share(function($pimple) {
            if (php_sapi_name() === "cli") {
                return new UserService();
            }

            return new UserService($pimple['session']);
        });

        $pimple['login.time'] = $pimple->share(function($pimple) {
            return new LoginTimeService();
        });

        if (php_sapi_name() !== "cli") {
            $pimple['session'] = $pimple->share(function() {
                $lastDot = strrpos($_SERVER['SERVER_NAME'], '.');
                $secondToLastDot = strrpos($_SERVER['SERVER_NAME'], '.', $lastDot - strlen($_SERVER['SERVER_NAME']) - 1);

                $storage = new NativeSessionStorage(array('cookie_domain' => substr($_SERVER['SERVER_NAME'], $secondToLastDot)), new NativeFileSessionHandler());

                return new Session($storage, new NamespacedAttributeBag(), new AutoExpireFlashBag());
            });
        }

        $pimple['warmup'] = $pimple->share(function($pimple) {
            if (php_sapi_name() === "cli") {
                return new WarmUpService();
            }
            return new WarmUpService($pimple['session']);
        });

        $pimple['messageQueue'] = $pimple->share(function($pimple) {
            return new MessageQueueService();
        });

        $pimple['template'] = $pimple->share(function() {
            $loader = new FilesystemLoader(array(
                __DIR__ . '/../Yongo/Resources/views/email/%name%',
                __DIR__ . '/../GeneralSettings/Resources/views/email/%name%',
                __DIR__ . '/../Calendar/Resources/views/email/%name%',
                __DIR__ . '/../SvnHosting/Resources/views/email/%name%',
                __DIR__ . '/../Resources/views/email/%name%'));

            return new PhpEngine(new TemplateNameParser(), $loader);
        });
    }

    public function boot(\Pimple $pimple)
    {

    }
}
