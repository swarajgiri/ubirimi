<?php

namespace Ubirimi\Yongo\Controller\Issue;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Ubirimi\Container\UbirimiContainer;
use Ubirimi\UbirimiController;
use Ubirimi\Util;

class SaveIssueTransitionController extends UbirimiController
{
    public function indexAction(Request $request, SessionInterface $session)
    {
        Util::checkUserIsLoggedInAndRedirect();

        $clientId = UbirimiContainer::get()['session']->get('client/id');
        $loggedInUserId = UbirimiContainer::get()['session']->get('user/id');

        $issueId = $request->request->get('issue_id');
        $fieldTypes = isset($_POST['field_types']) ? $_POST['field_types'] : array();
        $fieldValues = isset($_POST['field_values']) ? $_POST['field_values'] : array();

        $stepIdFrom = $request->request->get('step_id_from');
        $stepIdTo = $request->request->get('step_id_to');
        $workflowId = $request->request->get('workflow_id');
        $attachIdsToBeKept = isset($_POST['attach_ids']) ? $_POST['attach_ids'] : array();
        $attIdsSession = $session->has('added_attachments_in_screen') ? $session->get('added_attachments_in_screen') : array();

        $fieldTypesCustom = isset($_POST['field_types_custom']) ? $_POST['field_types_custom'] : null;
        $fieldValuesCustom = isset($_POST['field_values_custom']) ? $_POST['field_values_custom'] : null;

        $clientSettings = $this->getRepository('ubirimi.general.client')->getSettings($clientId);
        $issueCustomFieldsData = array();

        for ($i = 0; $i < count($fieldTypesCustom); $i++) {
            if ($fieldValuesCustom[$i] != 'null' && $fieldValuesCustom[$i] != '') {
                $issueCustomFieldsData[$fieldTypesCustom[$i]] = $fieldValuesCustom[$i];
            } else {
                $issueCustomFieldsData[$fieldTypesCustom[$i]] = null;
            }
        }

        for ($i = 0; $i < count($attIdsSession); $i++) {
            $attachmentId = $attIdsSession[$i];
            if (!in_array($attachmentId, $attachIdsToBeKept)) {
                $attachment = $this->getRepository('yongo.issue.attachment')->getById($attachmentId);
                Attachment::deleteById($attachmentId);
                unlink('./../../..' . $attachment['path'] . '/' . $attachment['name']);
            }
        }

        $session->remove('added_attachments_in_screen');
        $issueData = $this->getRepository('yongo.issue.issue')->getById($issueId, $loggedInUserId);
        $workflowData = $this->getRepository('yongo.workflow.workflow')->getDataByStepIdFromAndStepIdTo($workflowId, $stepIdFrom, $stepIdTo);

        // check if the transition can be executed with respect to the transition conditions
        $canBeExecuted = $this->getRepository('yongo.workflow.workflow')->checkConditionsByTransitionId($workflowData['id'], $loggedInUserId, $issueData);

        if ($canBeExecuted) {
            $currentDate = Util::getServerCurrentDateTime();

            $newIssueSystemFieldsData = array('issue_project_id' => $issueData['issue_project_id']);

            for ($i = 0; $i < count($fieldTypes); $i++) {
                $newIssueSystemFieldsData[$fieldTypes[$i]] = $fieldValues[$i];
            }

            $oldIssueCustomFieldsData = array();
            foreach ($issueCustomFieldsData as $key => $value) {
                $keyData = explode("_", $key);

                $oldIssueCustomFieldsData[$keyData[0]] = $this->getRepository('yongo.issue.customField')->getCustomFieldsDataByFieldId($issueId, $key);
                unset($issueCustomFieldsData[$key]);
                $issueCustomFieldsData[$keyData[0]] = $value;
            }

            $fieldChanges = $this->getRepository('yongo.issue.issue')->computeDifference($issueData, $newIssueSystemFieldsData, $oldIssueCustomFieldsData, $issueCustomFieldsData);

            if (in_array(Field::FIELD_COMMENT_CODE, $fieldTypes)) {
                if ($fieldValues[array_search('comment', $fieldTypes)]) {
                    $commentText = $fieldValues[array_search('comment', $fieldTypes)];

                    $this->getRepository('yongo.issue.comment')->add($issueId, $loggedInUserId, $commentText, $currentDate);
                    $fieldChanges[] = array('comment', $commentText);
                }
            }

            try {
                $this->getRepository('yongo.issue.issue')->updateById($issueId, $newIssueSystemFieldsData, $currentDate);

                // save custom fields
                if (count($issueCustomFieldsData)) {
                    $this->getRepository('yongo.issue.customField')->updateCustomFieldsData($issueId, $issueCustomFieldsData, $currentDate);
                }
            } catch (\Exception $e) {

            }

            $smtpSettings = $session->get('client/settings/smtp');
            if ($smtpSettings) {
                Email::$smtpSettings = $smtpSettings;
            }

            $this->getRepository('yongo.workflow.workflowFunction')->triggerPostFunctions($clientId, $issueData, $workflowData, $fieldChanges, $loggedInUserId, $currentDate);

            // update the date_updated field
            $this->getRepository('yongo.issue.issue')->updateById($issueId, array('date_updated' => $currentDate), $currentDate);

            return new Response('success');
        } else {
            return new Response('can_not_be_executed');
        }
    }
}