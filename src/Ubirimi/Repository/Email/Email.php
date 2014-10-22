<?php

namespace Ubirimi\Repository\Email;

use Exception;
use Swift_Mailer;
use Swift_Message;
use Swift_SendmailTransport;
use Swift_SmtpTransport;
use Ubirimi\Container\UbirimiContainer;
use Ubirimi\Repository\SMTPServer;
use Ubirimi\Repository\User\User;
use Ubirimi\Util;
use Ubirimi\Yongo\Repository\Issue\Component;
use Ubirimi\Yongo\Repository\Issue\CustomField;
use Ubirimi\Yongo\Repository\Issue\Event;
use Ubirimi\Yongo\Repository\Issue\Issue;
use Ubirimi\Yongo\Repository\Issue\Version;
use Ubirimi\Yongo\Repository\Project\Project;

class Email {

    public static $smtpSettings;

    public function sendNewsletter($toEmailAddress, $content, $subject) {
        $emailContent = Email::getEmailHeader();
        $emailContent .= '<br />';
        $emailContent .= '<br />';

        $emailContent .= '<div style="color: #333333; font: 17px Trebuchet MS, sans-serif; white-space: wrap; padding-top: 5px;text-align: left;padding-left: 2px;">' . $content . '</div>';

        $emailContent .= Email::getEmailFooter();

        if (strpos($_SERVER['HTTP_HOST'], 'localhost') === false) {
            $transport = Swift_SendmailTransport::newInstance('/usr/sbin/sendmail -bs');
            $mailer = Swift_Mailer::newInstance($transport);
            $message = Swift_Message::newInstance($subject)
                ->setFrom(array('flavius@ubirimi.com'))
                ->setTo(array($toEmailAddress))
                ->setBody($emailContent, 'text/html');

            $mailer->send($message);
        }
    }

    public function sendNewUserNotificationEmail($clientId, $firstName, $lastName, $username, $password, $email, $clientDomain) {
        $subject = Email::$smtpSettings['email_prefix'] . ' ' . 'Ubirimi - A new account has been created for you';

        EmailQueue::add($clientId,
                        Email::$smtpSettings['from_address'],
                        $email,
                        null,
                        $subject,
                        Util::getTemplate('_newUser.php', array(
                            'firstName' => $firstName,
                            'lastName' => $lastName,
                            'username' => $username,
                            'password' => $password,
                            'clientDomain' => $clientDomain)
                        ),
                        Util::getServerCurrentDateTime());
    }

    public function sendNewCustomerNotificationEmail($clientId, $firstName, $lastName, $email, $password, $clientDomain) {
        $subject = Email::$smtpSettings['email_prefix'] . ' ' . 'Ubirimi - A new customer account has been created for you';

        EmailQueue::add($clientId,
                        Email::$smtpSettings['from_address'],
                        $email,
                        null,
                        $subject,
                        Util::getTemplate('_newUser.php', array(
                            'firstName' => $firstName,
                            'lastName' => $lastName,
                            'email' => $email,
                            'password' => $password,
                            'isCustomer' => true,
                            'clientDomain' => $clientDomain)
                        ),
                        Util::getServerCurrentDateTime());
    }

    public function sendNewUserRepositoryNotificationEmail($clientId, $firstName, $lastName, $username, $password, $email, $repositoryName) {
        EmailQueue::add($clientId,
                        Email::$smtpSettings['from_address'],
                        $email,
                        null,
                        Email::$smtpSettings['email_prefix'] . ' ' . 'Ubirimi - You have been granted access to ' . $repositoryName . ' SVN Repository',
                        Util::getTemplate('_newRepositoryUser.php',array('first_name' => $firstName,
                                                                                  'last_name' => $lastName,
                                                                                  'username' => $username,
                                                                                  'password' => $password,
                                                                                  'repoName' => $repositoryName,
                                                                                  'clientData' => UbirimiContainer::get()['session']->get('client'))),
                        Util::getServerCurrentDateTime());
    }

    public function sendUserChangedPasswordForRepositoryNotificationEmail($clientId, $firstName, $lastName, $username, $password, $email, $repositoryName) {
        EmailQueue::add($clientId,
                        Email::$smtpSettings['from_address'],
                        $email,
                        null,
                        Email::$smtpSettings['email_prefix'] . ' ' . 'Ubirimi - Password change for ' . $repositoryName . ' SVN Repository',
                        Util::getTemplate('_userChangePassword.php', array('first_name' => $firstName,
                                                                                 'last_name' => $lastName,
                                                                                 'username' => $username,
                                                                                 'password' => $password,
                                                                                 'repoName' => $repositoryName,
                                                                                 'clientData' => UbirimiContainer::get()['session']->get('client'))),
                        Util::getServerCurrentDateTime());
    }

    public function triggerNewIssueNotification($clientId, $issue, $project, $loggedInUserId) {

        $eventCreatedId = UbirimiContainer::get()['repository']->get('yongo.issue.event')->getByClientIdAndCode($clientId, Event::EVENT_ISSUE_CREATED_CODE, 'id');
        $users = UbirimiContainer::get()['repository']->get('yongo.project.project')->getUsersForNotification($project['id'], $eventCreatedId, $issue, $loggedInUserId);

        while ($users && $user = $users->fetch_array(MYSQLI_ASSOC)) {
            if ($user['user_id'] == $loggedInUserId && !$user['notify_own_changes_flag']) {
                continue;
            }

            Email::sendEmailNewIssue($clientId, $issue, $user);
        }
    }

    public function triggerAssignIssueNotification($clientId, $issue, $oldUserAssignedName, $newUserAssignedName, $project, $loggedInUserId, $comment) {

        $eventAssignedId = Event::getByClientIdAndCode($clientId, Event::EVENT_ISSUE_ASSIGNED_CODE, 'id');
        $projectId = $project['id'];
        $users = UbirimiContainer::get()['repository']->get('yongo.project.project')->getUsersForNotification($projectId, $eventAssignedId, $issue, $loggedInUserId);
        $loggedInUser = UbirimiContainer::get()['repository']->get('ubirimi.user.user')->getById($loggedInUserId);

        while ($users && $user = $users->fetch_array(MYSQLI_ASSOC)) {

            if ($user['user_id'] == $loggedInUserId && !$user['notify_own_changes_flag']) {
                continue;
            }

            Email::sendEmailIssueAssign($issue, $clientId, $oldUserAssignedName, $newUserAssignedName, $user, $comment, $loggedInUser);
        }
    }

    private function sendEmailNewIssue($clientId, $issue, $userToNotify) {
        $issueId = $issue['id'];
        $projectId = $issue['issue_project_id'];
        $versionsAffected = UbirimiContainer::get()['repository']->get('yongo.issue.version')->getByIssueIdAndProjectId($issueId, $projectId, Issue::ISSUE_AFFECTED_VERSION_FLAG);
        $versionsFixed = UbirimiContainer::get()['repository']->get('yongo.issue.version')->getByIssueIdAndProjectId($issueId, $projectId, Issue::ISSUE_FIX_VERSION_FLAG);
        $components = UbirimiContainer::get()['repository']->get('yongo.issue.component')->getByIssueIdAndProjectId($issueId, $projectId);
        $clientDomain = Util::getSubdomain();

        $customFieldsSingleValue = UbirimiContainer::get()['repository']->get('yongo.issue.customField')->getCustomFieldsData($issueId);
        $customFieldsUserPickerMultiple = UbirimiContainer::get()['repository']->get('yongo.issue.customField')->getUserPickerData($issueId);

        $subject = Email::$smtpSettings['email_prefix'] . ' ' .
                            "[Issue] - New issue CREATED " .
                            $issue['project_code'] . '-' .
                            $issue['nr'];

        EmailQueue::add($clientId,
                        Email::$smtpSettings['from_address'],
                        $userToNotify['email'],
                        null,
                        $subject,
                        Util::getTemplate('_newIssue.php', array(
                            'issue' => $issue,
                            'client_domain' => $clientDomain,
                            'custom_fields_single_value' => $customFieldsSingleValue,
                            'custom_fields_user_picker_multiple' => $customFieldsUserPickerMultiple,
                            'components' => $components,
                            'versions_fixed' => $versionsFixed,
                            'versions_affected' => $versionsAffected)
                        ),
                        Util::getServerCurrentDateTime());
    }

    public function getMailer($smtpSettings) {
        $smtpSecurity = null;
        if ($smtpSettings['smtp_protocol'] == SMTPServer::PROTOCOL_SECURE_SMTP)
            $smtpSecurity = 'ssl';

        if (isset($smtpSettings['tls_flag']))
            $smtpSecurity = 'tls';

        $transport = Swift_SmtpTransport::newInstance($smtpSettings['hostname'], $smtpSettings['port'], $smtpSecurity)
                            ->setUsername($smtpSettings['username'])
                            ->setPassword($smtpSettings['password']);

        return Swift_Mailer::newInstance($transport);
    }

    /* @TODO: remove when email refactoring has been done */
    private function getEmailHeader($product = null) {
        $text = '<div style="background-color: #F6F6F6; padding: 10px; margin: 10px; width: 720px;">';
        $text .= '<div style="color: #333333;font: 17px Trebuchet MS, sans-serif;white-space: nowrap;padding-bottom: 5px;padding-top: 5px;text-align: left;padding-left: 2px;">';

        $text .= '<a href="https://www.ubirimi.com"><img src="https://www.ubirimi.com/img/email-logo-yongo.png" border="0" /></a>';
        $text .= '<div><img src="https://www.ubirimi.com/img/bg.page.png" /></div>';
        $text .= '</div>';

        return $text;
    }

    private function getEmailFooter() {
        return '</div>';
    }

    public function sendEmailIssueAssign($issue, $clientId, $oldUserAssignedName, $newUserAssignedName, $user, $comment, $loggedInUser) {
        if (Email::$smtpSettings) {

            $subject = Email::$smtpSettings['email_prefix'] . ' ' .
                "[Issue] - Issue UPDATED " .
                $issue['project_code'] . '-' .
                $issue['nr'];

            $date = Util::getServerCurrentDateTime();

            EmailQueue::add($clientId,
                Email::$smtpSettings['from_address'],
                $user['email'],
                null,
                $subject,
                Util::getTemplate('_issueAssign.php', array(
                        'clientDomain' => Util::getSubdomain(),
                        'issue' => $issue,
                        'comment' => $comment,
                        'project' => array('id' => $issue['issue_project_id'], 'name' => $issue['project_name']),
                        'loggedInUser' => $loggedInUser,
                        'oldUserAssignedName' => $oldUserAssignedName,
                        'newUserAssignedName' => $newUserAssignedName)
                ),
                $date);
        }
    }

    public function sendEmailIssueChanged($issue, $project, $loggedInUser, $clientId, $fieldChanges, $userToNotify) {
        if (Email::$smtpSettings) {
            EmailQueue::add($clientId,
                Email::$smtpSettings['from_address'],
                $userToNotify['email'],
                null,
                Email::$smtpSettings['email_prefix'] . ' ' . "[Issue] - Issue UPDATED " . $issue['project_code'] . '-' . $issue['nr'],
                Util::getTemplate('_issueUpdated.php', array(
                        'clientDomain' => Util::getSubdomain(),
                        'issue' => $issue,
                        'project' => $project,
                        'user' => $loggedInUser,
                        'fieldChanges' => $fieldChanges)
                ),
                Util::getServerCurrentDateTime());
        }
    }

    public function triggerIssueUpdatedNotification($clientId, $issue, $loggedInUserId, $changedFields) {

        $projectId = $issue['issue_project_id'];
        $eventUpdatedId = Event::getByClientIdAndCode($clientId, Event::EVENT_ISSUE_UPDATED_CODE, 'id');
        $users = UbirimiContainer::get()['repository']->get('yongo.project.project')->getUsersForNotification($projectId, $eventUpdatedId, $issue, $loggedInUserId);
        $project = UbirimiContainer::get()['repository']->get('yongo.project.project')->getById($projectId);
        $loggedInUser = UbirimiContainer::get()['repository']->get('ubirimi.user.user')->getById($loggedInUserId);

        while ($users && $user = $users->fetch_array(MYSQLI_ASSOC)) {
            if ($user['user_id'] == $loggedInUserId && !$user['notify_own_changes_flag']) {
                continue;
            }

            Email::sendEmailIssueChanged($issue, $project, $loggedInUser, $clientId, $changedFields, $user);
        }
    }

    public function sendContactMessage($to_address, $name, $subject, $message, $email) {
        $mailer = Util::getUbirmiMailer('contact');

        $message = Swift_Message::newInstance('Contact message - Ubirimi.com')
                            ->setFrom(array('contact@ubirimi.com'))
                            ->setTo($to_address)
                            ->setBody(
                                Util::getTemplate('_contact.php', array(
                                    'name' => $name,
                                    'email' => $email,
                                    'message' => $message,
                                    'subject' => $subject)),
                                'text/html'
                            );

        try {
            $mailer->send($message);
        } catch (Exception $e) {

        }
    }

    public function sendEmailNotificationNewComment($issue, $clientId, $project, $userToNotify, $content, $user) {
        if (Email::$smtpSettings) {
            $subject = Email::$smtpSettings['email_prefix'] . ' ' . "[Issue] - Issue COMMENT " . $issue['project_code'] . '-' . $issue['nr'];

            $date = Util::getServerCurrentDateTime();

            EmailQueue::add($clientId,
                Email::$smtpSettings['from_address'],
                $userToNotify['email'],
                null,
                $subject,
                Util::getTemplate('_newComment.php',array(
                        'clientDomain' => Util::getSubdomain(),
                        'issue' => $issue,
                        'project' => $project,
                        'content' => $content,
                        'user' => $user)
                ),
                $date);
        }
    }

    public function sendEmailRetrievePassword($address, $password) {
        $tpl = UbirimiContainer::get()['savant'];
        $tpl->assign(array('password' => $password));

        $message = Swift_Message::newInstance('Restore password - Ubirimi.com')
                        ->setFrom(array('support@ubirimi.com'))
                        ->setTo(array($address))
                        ->setBody($tpl->fetch('_restorePassword.php'), 'text/html');

        $mailer = Util::getUbirmiMailer();

//        try {
            $mailer->send($message);
//        } catch (Exception $e) {
//
//        }
    }

    private function sendEmailDeleteIssue($issue, $clientId, $user, $loggedInUser, $project) {

        if (Email::$smtpSettings) {
            $subject = Email::$smtpSettings['email_prefix'] . ' ' .
                "[Issue] - Issue DELETED " .
                $issue['project_code'] . '-' .
                $issue['nr'];

            EmailQueue::add($clientId,
                Email::$smtpSettings['from_address'],
                $user['email'],
                null,
                $subject,
                Util::getTemplate('_deleteIssue.php', array('issue' => $issue, 'loggedInUser' => $loggedInUser, 'project' => $project)),
                Util::getServerCurrentDateTime());
        }
    }

    public function triggerDeleteIssueNotification($clientId, $issue, $project, $extraInformation) {
        $projectId = $issue['issue_project_id'];

        $loggedInUser = $extraInformation['loggedInUser'];
        $loggedInUserId = $loggedInUser['id'];

        $eventDeletedId = Event::getByClientIdAndCode($clientId, Event::EVENT_ISSUE_DELETED_CODE, 'id');
        $users = UbirimiContainer::get()['repository']->get('yongo.project.project')->getUsersForNotification($projectId, $eventDeletedId, $issue, $loggedInUserId);

        while ($users && $user = $users->fetch_array(MYSQLI_ASSOC)) {
            if ($user['user_id'] == $loggedInUserId && !$user['notify_own_changes_flag']) {
                continue;
            }
            Email::sendEmailDeleteIssue($issue, $clientId, $user, $loggedInUser, $project);
        }
    }

    public function sendFeedback($userData, $like, $improve, $newFeatures, $experience) {

        $text = Email::getEmailHeader();
        $text .= '<div style="color: #333333; font: 17px Trebuchet MS, sans-serif; white-space: nowrap; padding-top: 5px;text-align: left;padding-left: 2px;">' . $userData['first_name'] . ' ' . $userData['last_name'] . ' sent the following feedback: </div>';
        $text .= '<br />';
        $text .= '<table cellpadding="2" cellspacing="0" border="0">';
            $text .= '<tr>';
                $text .= '<td><b>Likes:</b></td>';
                $text .= '<td>' . $like . '</td>';
            $text .= '</tr>';
            $text .= '<tr>';
                $text .= '<td><b>To be improved:</b></td>';
                $text .= '<td>' . $improve . '</td>';
            $text .= '</tr>';
            $text .= '<tr>';
                $text .= '<td><b>New features:</b></td>';
                $text .= '<td>' . $newFeatures . '</td>';
            $text .= '</tr>';
            $text .= '<tr>';
                $text .= '<td><b>Overall experience:</b></td>';
                $text .= '<td>' . $experience . '</td>';
            $text .= '</tr>';

        $text .= '</table>';

        $text .= '<div>User giving feedback: </div>';
        $text .= '<div>Email: ' . $userData['email'] . '</div>';
        $text .= '<div>Client ID: ' . $userData['client_id'] . '</div>';
        $text .= '<div>Username: ' . $userData['username'] . '</div>';

        $text .= Email::getEmailFooter();

        if (strpos($_SERVER['HTTP_HOST'], 'localhost') === false) {
            $transport = Swift_SendmailTransport::newInstance('/usr/sbin/sendmail -bs');
            $mailer = Swift_Mailer::newInstance($transport);
            $message = Swift_Message::newInstance('Feedback - Ubirimi.com')
                ->setFrom(array('no-reply@ubirimi.com'))
                ->setTo(array('domnulnopcea@gmail.com', 'domnuprofesor@gmail.com'))
                ->setBody($text, 'text/html');

            $mailer->send($message);
        }
    }

    public function shareIssue($clientId, $issue, $userThatShares, $userToSendEmailAddress, $noteContent) {
        if (Email::$smtpSettings) {
            $subject = Email::$smtpSettings['email_prefix'] . ' ' .
                $userThatShares['first_name'] . ' ' .
                $userThatShares['last_name'] . ' shared ' .
                $issue['project_code'] . '-' . $issue['nr'] . ': ' . substr($issue['summary'], 0, 20) . ' with you';

            $date = Util::getServerCurrentDateTime();

            EmailQueue::add($clientId,
                Email::$smtpSettings['from_address'],
                $userToSendEmailAddress,
                null,
                $subject,
                Util::getTemplate('_issueShare.php', array(
                        'issue' => $issue,
                        'userThatShares' => $userThatShares,
                        'noteContent' => $noteContent,
                        'clientDomain' => Util::getSubdomain())
                ),
                $date);
        }
    }

    public function shareCalendar($clientId, $calendar, $userThatShares, $userToSendEmailAddress, $noteContent) {
        if (Email::$smtpSettings) {
            $subject = Email::$smtpSettings['email_prefix'] . ' ' .
                $userThatShares['first_name'] . ' ' .
                $userThatShares['last_name'] . ' shared calendar ' .
                $calendar['name'] . ' with you';

            $date = Util::getServerCurrentDateTime();

            EmailQueue::add($clientId,
                Email::$smtpSettings['from_address'],
                $userToSendEmailAddress,
                null,
                $subject,
                Util::getTemplate('_share.php', array('calendar' => $calendar,
                    'userThatShares' => $userThatShares,
                    'noteContent' => $noteContent,
                    'clientDomain' => Util::getSubdomain())),
                $date);
        }
    }

    public function shareEvent($clientId, $event, $userThatShares, $userToSendEmailAddress, $noteContent) {
        if (Email::$smtpSettings) {
            $subject = Email::$smtpSettings['email_prefix'] . ' ' .
                $userThatShares['first_name'] . ' ' .
                $userThatShares['last_name'] . ' shared event ' .
                $event['name'] . ' with you';

            $date = Util::getServerCurrentDateTime();

            EmailQueue::add($clientId,
                Email::$smtpSettings['from_address'],
                $userToSendEmailAddress,
                null,
                $subject,
                Util::getTemplate('_eventShare.php', array('event' => $event,
                    'userThatShares' => $userThatShares,
                    'noteContent' => $noteContent,
                    'clientDomain' => Util::getSubdomain())),
                $date);
        }
    }
}