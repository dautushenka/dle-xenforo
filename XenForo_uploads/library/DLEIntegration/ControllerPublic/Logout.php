<?php

//use XenForo_ControllerPublic_Logout as XFCP_Steam_ControllerPublic_Register;

class DLEIntegration_ControllerPublic_Logout extends XFCP_DLEIntegration_ControllerPublic_Logout {
    
    public function actionIndex()
    {
        $response = parent::actionIndex();
        
        DLEIntegration_DLE::getInstance()->logout();
        
        return $response;
    }


}

?>