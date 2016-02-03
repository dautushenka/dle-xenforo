<?php

return array(
    'db' => array(
        'adapter' => 'mysqli',
        'host' => 'localhost',
        'port' => '3306',
        'username' => '',
        'password' => '',
        'dbname' => '',
        'adapterNamespace' => 'Zend_Db_Adapter'
    ),
    'cache' => array(
        'enabled' => false,
        'cacheSessions' => false,
        'frontend' => 'core',
        'frontendOptions' => array(
            'caching' => true,
            'cache_id_prefix' => 'xf_'
        ),
        'backend' => 'file',
        'backendOptions' => array(
            'file_name_prefix' => 'xf_'
        )
    ),
    'debug' => false,
    'enableListeners' => true,
    'development' => array(
        'directory' => '', // relative to the configuration directory
        'default_addon' => ''
    ),
    'superAdmins' => '1',
    'globalSalt' => 'ae5a99d00f58945a30b1ce054a1e89ef',
    'jsVersion' => '',
    'cookie' => array(
        'prefix' => 'xf_',
        'path' => '/',
        'domain' => ''
    ),
    'enableMail' => true,
    'enableMailQueue' => true,
    'internalDataPath' => 'internal_data',
    'externalDataPath' => 'data',
    'externalDataUrl' => 'data',
    'javaScriptUrl' => 'js',
    'checkVersion' => true,
    'enableGzip' => true,
    'enableContentLength' => true,
    'adminLogLength' => 60, // number of days to keep admin log entries
    'chmodWritableValue' => 0,
    'rebuildMaxExecution' => 8,
    'passwordIterations' => 10,
    'enableTemplateModificationCallbacks' => true,
    'enableClickjackingProtection' => true,
    'maxImageResizePixelCount' => 20000000
);

?>