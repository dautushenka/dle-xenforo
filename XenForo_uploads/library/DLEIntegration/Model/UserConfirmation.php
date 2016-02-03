<?php

//use XenForo_Model_User as XFCP_DLEIntegration_Model_User;

class DLEIntegration_Model_UserConfirmation extends XFCP_DLEIntegration_Model_UserConfirmation
{
    public function resetPassword($userId, $sendEmail = true)
    {
        $password = parent::resetPassword($userId, $sendEmail);

        $dw = XenForo_DataWriter::create('XenForo_DataWriter_User');
        $dw->setExistingData($userId);
        $dw->get('username');

        DLEIntegration_DLE::getInstance()->update($dw->get('username'), array(
            'password' => md5(md5(DLEIntegration_DLE::getInstance()->convertEncodingToDLE($password)))
        ));
        
        return $password;
    }
} 