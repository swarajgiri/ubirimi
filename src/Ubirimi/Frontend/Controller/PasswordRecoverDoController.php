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

namespace Ubirimi\Frontend\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Ubirimi\Container\UbirimiContainer;
use Ubirimi\Repository\User\UbirimiUser;
use Ubirimi\UbirimiController;
use Ubirimi\Util;

class PasswordRecoverDoController extends UbirimiController
{
    public function indexAction(Request $request, SessionInterface $session)
    {

        if ($request->request->has('retrieve')) {

            $errorNotInClientDomain = false;
            $emailAddressNotExists = false;

            $httpHOST = Util::getHttpHost();

            $address = Util::cleanRegularInputField($request->request->get('address'));
            $exists = Util::checkEmailAddressExistence($address);

            if ($exists) {

                $baseURL = Util::getHttpHost();

                $userData = $this->getRepository(UbirimiUser::class)->getByEmailAddressAndBaseURL($address, $baseURL);

                if ($userData) {
                    $password = Util::updatePasswordForUserId($userData['id']);

                    UbirimiContainer::get()['email']->passwordRestore($userData['client_id'], $address, $password);

                    return new RedirectResponse('/recover-password/response');
                } else {
                    $errorNotInClientDomain = true;
                }
            } else {
                $emailAddressNotExists = true;
            }

            $content = 'PasswordRecover.php';

            return $this->render(__DIR__ . '/../Resources/views/_main.php', get_defined_vars());
        } else if ($request->request->has('go_back')) {
            return new RedirectResponse('/');
        }
    }
}
