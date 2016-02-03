Требования интеграции
PHP 5.2 и выше
Сайт и форум должны быть установлены на одном домене второго уровня (поддомены допускаются)
Если сайт и форум использует разный сервер то сервера должны иметь внешнее подключение к  базам данных.

Изменение файлов нужно проводить с помощью рдактора который не исменяет кодировку исходного файла, например Notepad++

Подготовка ДЛЕ:
1. Идем в админку:
	Настройки пользователей -> Авторизовать пользователей на домене и всех его поддоменах -> Да
	Настройки безопасности скрипта -> Сбрасывать ключ авторизации при каждом входе? -> Нет

Подготовка форума:
1. Открываем файл library/config.php
	Дбавляем следующие строки
	$config['globalSalt'] = 'ae5a99d00f58945a30b1ce054a1e89ef';
	$config['cookie']['domain'] = '.sapmle.com';

2. В этих строках добавленых в пункте 1, вместо ae5a99d00f58945a30b1ce054a1e89ef поставить свою случайную послежовательность чисел.
Вместо .sapmle.com нужно прописать домен второго уровня с точкой спереди на котором установлен форум, например если форум находиться по адрессу http://forum.sapmle.com или http://www.sapmle.com то нужно указать домен
".sapmle.com" (не забываем про точку спереди), также такое значение будет если сайт и форум используют один домен например http://sapmle.com/dle, http://sapmle.com/forum

Установка интеграции на форум:
	1. Из папки XenForo_uploads копируем файлы в корень форума. 
	2. Файл c cайта /engine/data/dbconfig.php копируем в папку /library/DLEIntegration/config
	3. Открываем файл /library/DLEIntegration/config/dle_config.php там устанавливаем или изменяем если требуются параметры, описание смотрите в коментариях
	4. Заходим в админку форума Home -> Add-on -> Install New Add-on. И загружаем файл addon-DLEIntegration.xml.

Установка интеграции на ДЛЕ:
	1. Из папки DLE_uploads копируем файлы в корень ДЛЕ, файлы из templates копируем в папку с вашим шаблоном. Исходную папку выбирайте в зависимости от кодировки ДЛЕ (кодировка сайта), настройки интеграции находяться в файле /engine/data/dle_xen_conf.php
	2. Файл с форума /library/config.php копируем в папку /engine/modules/XenIntegration/
	3. Редактируем файл /engine/init.php
		После 
			require_once ENGINE_DIR . '/modules/gzip.php';
		Вставить
			require_once ENGINE_DIR . '/modules/XenIntegration/XenIntegration.php';

	4. Редируем файл /engine/modules/sitelogin.php
		После 
			<?php
		Вставить
			require_once ENGINE_DIR . '/modules/XenIntegration/XenIntegration.php';

		Перед
			header( "Location: ".str_replace("index.php","",$_SERVER['PHP_SELF']) );
		Вставить
			XenIntegration::getInstance()->logout();

		Перед
			?>
		Вставить
			XenIntegration::getInstance()->login($member_id);

	5. Редируем файл /engine/modules/register.php
		Перед
			msgbox( $lang['reg_ok'], $lang['reg_ok_1'] );
		Вставить
			XenIntegration::getInstance()->updateMember($row, $land, $info);

		После
			$id = $db->insert_id();
		Вставить
			XenIntegration::getInstance()->createMember(stripslashes($name), $user_arr[2], $email);

	6. Редатируем файл /engine/modules/profile.php
		Перед
			if( strlen( $password1 ) > 0 ) {
			
			$password1 = md5( md5( $password1 ) );

		Вставить
			XenIntegration::getInstance()->updateProfile($row, $email, $password1, $land, $info);

	7. Редактируем файл /engine/modules/lostpassword.php
		После
			$db->query( "UPDATE " . USERPREFIX . "_users set password='" . md5( md5( $new_pass ) ) . "', allowed_ip = '' WHERE user_id='$douser'" );
			$db->query( "DELETE FROM " . USERPREFIX . "_lostdb WHERE lostname='$douser'" );
		Вставить
			XenIntegration::getInstance()->lostPassword($row, $new_pass);

	8. Для вывода блока последних сообщений с форума в шаблон добавьте (настройки в файле /engine/data/dle_xen_conf.php, вид в шаблоне block_forum_posts.tpl)
		{include file="engine/modules/XenIntegration/last_topic_block.php"}
