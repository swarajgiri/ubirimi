<?php

namespace Ubirimi\Yongo\Controller\Administration\Issue\Priority;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Ubirimi\SystemProduct;
use Ubirimi\UbirimiController;
use Ubirimi\Util;
use Ubirimi\Yongo\Repository\Issue\Settings;


class AddController extends UbirimiController
{
    public function indexAction(Request $request, SessionInterface $session)
    {
        Util::checkUserIsLoggedInAndRedirect();

        $emptyPriorityName = false;
        $priorityExists = false;

        if ($request->request->has('new_priority')) {
            $name = Util::cleanRegularInputField($request->request->get('name'));
            $description = Util::cleanRegularInputField($request->request->get('description'));
            $color = Util::cleanRegularInputField($request->request->get('color'));

            if (empty($name))
                $emptyPriorityName = true;

            // check for duplication
            $priority = $this->getRepository('yongo.issue.settings')->getByName($session->get('client/id'), 'priority', mb_strtolower($name));
            if ($priority)
                $priorityExists = true;

            if (!$priorityExists && !$emptyPriorityName) {
                $iconName = 'generic.png';
                $currentDate = Util::getServerCurrentDateTime();

                $this->getRepository('yongo.issue.settings')->create(
                    'issue_priority',
                    $session->get('client/id'),
                    $name,
                    $description,
                    $iconName,
                    $color,
                    $currentDate
                );

                $this->getRepository('ubirimi.general.log')->add(
                    $session->get('client/id'),
                    SystemProduct::SYS_PRODUCT_YONGO,
                    $session->get('user/id'),
                    'ADD Yongo Issue Priority ' . $name,
                    $currentDate
                );

                return new RedirectResponse('/yongo/administration/issue/priorities');
            }
        }

        $menuSelectedCategory = 'issue';

        $sectionPageTitle = $session->get('client/settings/title_name') . ' / ' . SystemProduct::SYS_PRODUCT_YONGO_NAME . ' / Create Issue Priority';

        return $this->render(__DIR__ . '/../../../../Resources/views/administration/issue/priority/Add.php', get_defined_vars());
    }
}
