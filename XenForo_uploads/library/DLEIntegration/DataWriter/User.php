<?php

//use XenForo_DataWriter_User as XFCP_DLEIntegration_DataWriter_User;

class DLEIntegration_DataWriter_User extends XFCP_DLEIntegration_DataWriter_User
{
    private $fieldsDLE2Xen = array(
        'name'      => 'username',
        'email'     => 'email',
        'lastdate'  => 'last_activity',
        'reg_date'  => 'register_date',
        'signature' => 'signature',
        'info'      => 'about',
        'fullname'  => 'custom_title',
        'land'      => 'location',
//        'logged_ip' => '',
        'banned'    => 'is_banned',
    );
    
    private $password;
    
    public function setPassword($password, $passwordConfirm = false, XenForo_Authentication_Abstract $auth = null, $requirePassword = false)
    {
        $this->password = $password;
        
        parent::setPassword($password, $passwordConfirm, $auth, $requirePassword);
    }

    public function setCustomFields(array $fieldValues, array $fieldsShown = null)
    {
        parent::setCustomFields($fieldValues, $fieldsShown);
    }

    protected function _save()
    {
        parent::_save();
        
        $fields = array();

        foreach ($this->_newData as $data) {
            foreach ($data as $column => $value) {
                if (in_array($column, $this->fieldsDLE2Xen)) {
                    $fields[array_search($column, $this->fieldsDLE2Xen)] = $value;
                }
            }
        }
        
        if ($fields || $this->password) {
            
            if ($this->password) {
                $fields['password'] = md5(md5(DLEIntegration_DLE::getInstance()->convertEncodingToDLE($this->password)));
            }
            
            if ($this->isUpdate()) {
                if ($username = $this->getExisting('username')) {
                    DLEIntegration_DLE::getInstance()->update($username, $fields);
                }
            }
            else {
                DLEIntegration_DLE::getInstance()->insert($fields);
            }
        }
    }


}