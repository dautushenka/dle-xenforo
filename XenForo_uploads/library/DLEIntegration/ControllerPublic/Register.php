<?php

//use XenForo_ControllerPublic_Register as XFCP_Steam_ControllerPublic_Register;

class DLEIntegration_ControllerPublic_Register extends XFCP_DLEIntegration_ControllerPublic_Register {

    protected function _completeRegistration(array $user, array $extraParams = array())
    {
        $response = parent::_completeRegistration($user, $extraParams);

        $password = $this->_input->filterSingle('password', XenForo_Input::STRING);
        DLEIntegration_DLE::getInstance()->login($user['username'], $password);
        
        return $response;
    }
}

?>