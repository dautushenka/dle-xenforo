<?php

class DLEIntegration_Listener
{
	public static function loadClassControllerRegister($class, array &$extend) {
        $extend[] = 'DLEIntegration_ControllerPublic_Register';
	}
    
	public static function loadClassControllerLogout($class, array &$extend) {
        $extend[] = 'DLEIntegration_ControllerPublic_Logout';
	}
    
	public static function loadDataWriterUser($class, array &$extend) {
        $extend[] = 'DLEIntegration_DataWriter_User';
	}
    
	public static function loadClassModel($class, array &$extend) {
		switch($class) {
			case 'XenForo_Model_User':
				$extend[] = 'DLEIntegration_Model_User';
				break;
			case 'XenForo_Model_UserConfirmation':
				$extend[] = 'DLEIntegration_Model_UserConfirmation';
				break;
		}
	}
	
    public static function init(XenForo_Dependencies_Abstract $dependencies, array $data)
    {
//        XenForo_Template_Helper_Core::$helperCallbacks += array(
//            'steamid' => array('Steam_Helper_Steam', 'convertIdToString')
//        );
    }
}
