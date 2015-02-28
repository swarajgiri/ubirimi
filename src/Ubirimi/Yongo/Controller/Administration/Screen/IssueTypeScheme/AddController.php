<?php

/*
 *  Copyright (C) 2012-2014 SC Ubirimi SRL <info-copyright@ubirimi.com>
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

namespace Ubirimi\Yongo\Controller\Administration\Screen\IssueTypeScheme;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Ubirimi\SystemProduct;
use Ubirimi\UbirimiController;
use Ubirimi\Util;
use Ubirimi\Yongo\Repository\Issue\IssueType;
use Ubirimi\Yongo\Repository\Issue\IssueTypeScreenScheme;

class AddController extends UbirimiController
{
    public function indexAction(Request $request, SessionInterface $session)
    {
        Util::checkUserIsLoggedInAndRedirect();

        $emptyName = false;

        $allIssueTypes = $this->getRepository(IssueType::class)->getAll($session->get('client/id'));

        if ($request->request->has('new_issue_type_screen_scheme')) {
            $name = Util::cleanRegularInputField($request->request->get('name'));
            $description = Util::cleanRegularInputField($request->request->get('description'));

            if (empty($name))
                $emptyName = true;

            if (!$emptyName) {
                $issueTypeScreenScheme = new IssueTypeScreenScheme($session->get('client/id'), $name, $description);
                $currentDate = Util::getServerCurrentDateTime();
                $issueTypeScreenSchemeId = $issueTypeScreenScheme->save($currentDate);

                $issueTypes = $this->getRepository(IssueType::class)->getAll($session->get('client/id'));
                while ($issueType = $issueTypes->fetch_array(MYSQLI_ASSOC)) {
                    $this->getRepository(IssueTypeScreenScheme::class)->addData($issueTypeScreenSchemeId, $issueType['id'], $currentDate);
                }

                $this->getLogger()->addInfo('ADD Yongo Issue Type Screen Scheme ' . $name, $this->getLoggerContext());

                return new RedirectResponse('/yongo/administration/screens/issue-types');
            }
        }
        $sectionPageTitle = $session->get('client/settings/title_name') . ' / ' . SystemProduct::SYS_PRODUCT_YONGO_NAME . ' / Create Issue Type Screen Scheme';

        return $this->render(__DIR__ . '/../../../../Resources/views/administration/screen/issue_type_scheme/Add.php', get_defined_vars());
    }
}
