<?php

namespace Ubirimi\Yongo\Controller\Administration\Project;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Ubirimi\SystemProduct;
use Ubirimi\UbirimiController;
use Ubirimi\Util;
use Ubirimi\Yongo\Repository\Project\Project;
use Ubirimi\Repository\Log;
use Ubirimi\Repository\Client;
use Ubirimi\Yongo\Repository\Issue\TypeScheme;
use Ubirimi\Yongo\Repository\Issue\TypeScreenScheme;
use Ubirimi\Yongo\Repository\Project\ProjectCategory;
use Ubirimi\Yongo\Repository\Workflow\WorkflowScheme;

class EditController extends UbirimiController
{
    public function indexAction(Request $request, SessionInterface $session)
    {
        Util::checkUserIsLoggedInAndRedirect();

        $clientId = $session->get('client/id');
        $loggedInUserId = $session->get('user/id');

        $projectId = $request->get('id');
        $leadUsers = Client::getUsers($session->get('client/id'));

        // todo: leadul sa fie adaugat in lista de useri pentru acest proiect
        $project = Project::getById($projectId);

        if ($project['client_id'] != $session->get('client/id')) {
            return new RedirectResponse('/general-settings/bad-link-access-denied');
        }

        $issueTypeScheme = TypeScheme::getByClientId($session->get('client/id'), 'project');
        $issueTypeScreenScheme = TypeScreenScheme::getByClientId($session->get('client/id'));
        $workflowScheme = WorkflowScheme::getMetaDataByClientId($session->get('client/id'));
        $projectCategories = ProjectCategory::getAll($session->get('client/id'));

        $emptyName = false;
        $duplicate_name = false;
        $duplicateCode = false;
        $emptyCode = false;

        if ($request->request->has('confirm_edit_project')) {
            $name = Util::cleanRegularInputField($request->request->get('name'));
            $code = Util::cleanRegularInputField($request->request->get('code'));
            $description = Util::cleanRegularInputField($request->request->get('description'));

            $issueTypeSchemeId = $request->request->get('issue_type_scheme');
            $workflowSchemeId = $request->request->get('workflow_scheme');
            $projectCategoryId = $request->request->get('project_category');

            if (-1 == $projectCategoryId) {
                $projectCategoryId = null;
            }

            $leadId = Util::cleanRegularInputField($request->request->get('lead'));

            if (empty($name)) {
                $emptyName = true;
            } else {
                $duplicateProjectByName = Project::getByName(mb_strtolower($name), $projectId, $session->get('client/id'));
                if ($duplicateProjectByName) {
                    $duplicate_name = true;
                }
            }

            if (empty($code)) {
                $emptyCode = true;
            } else {
                $project_exists = Project::getByCode(mb_strtolower($code), $projectId, $session->get('client/id'));
                if ($project_exists)
                    $duplicateCode = true;
            }

            if (!$emptyName && !$emptyCode && !$duplicate_name && !$duplicateCode) {
                $currentDate = Util::getServerCurrentDateTime();
                Project::updateById($projectId, $leadId, $name, $code, $description, $issueTypeSchemeId, $workflowSchemeId, $projectCategoryId, $currentDate);

                Log::add(
                    $session->get('client/id'),
                    SystemProduct::SYS_PRODUCT_YONGO,
                    $session->get('user/id'),
                    'UPDATE Yongo Project ' . $name,
                    $currentDate
                );

                return new RedirectResponse('/yongo/administration/projects');
            }
        }

        $menuSelectedCategory = 'project';

        $sectionPageTitle = $session->get('client/settings/title_name') . ' / ' . SystemProduct::SYS_PRODUCT_YONGO_NAME . ' / Update Project';

        return $this->render(__DIR__ . '/../../../Resources/views/administration/project/Edit.php', get_defined_vars());
    }
}
