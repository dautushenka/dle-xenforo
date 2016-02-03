<?php

//use XenForo_Model_User as XFCP_DLEIntegration_Model_User;

class DLEIntegration_Model_User extends XFCP_DLEIntegration_Model_User
{
    public function validateAuthentication($nameOrEmail, $password, &$error = '')
    {
        /** @var $error XenForo_Phrase */
        $user_id = parent::validateAuthentication($nameOrEmail, $password, $error);

        $DLE = DLEIntegration_DLE::getInstance();
        
        if ($user_id) {
            $DLE->login($nameOrEmail, $password);
            return $user_id;
        }

        if ($error->getPhraseName() !== 'requested_user_x_not_found') {
            return $user_id;
        }
        
        $user = $DLE->findDLEUser($nameOrEmail, $password);
        if (!$user) {
            return $user_id;
        }

        $data = array(
            'username'          => $DLE->convertEncodingFromDLE($user->name),
            'email'             => $DLE->convertEncodingFromDLE($user->email),
            'last_activity'     => $user->lastdate,
            'register_date'     => $user->reg_date,
        );

        $options = XenForo_Application::getOptions();

        $writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
        if ($options->registrationDefaults)
        {
            $writer->bulkSet($options->registrationDefaults, array('ignoreInvalidFields' => true));
        }
        $writer->bulkSet($data);
        $writer->setPassword($password, false, null, true);
        $writer->set('user_group_id', XenForo_Model_User::$defaultRegisteredGroupId);
        $writer->set('language_id', XenForo_Visitor::getInstance()->get('language_id'));
        $writer->advanceRegistrationUserState();
        $writer->preSave();

        $writer->save();

        $user = $writer->getMergedData();

        if ($user['user_state'] == 'email_confirm')
        {
            XenForo_Model::create('XenForo_Model_UserConfirmation')->sendEmailConfirmation($user);
        }
        
        $error = '';
        $DLE->login($nameOrEmail, $password);
        
        return $user['user_id'];
    }
} 