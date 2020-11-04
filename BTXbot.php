<?php

#
#Config
#
define('DEBUG_FILE_NAME', 'suckcock.txt'); // name of file for debug log
define('CLIENT_ID', 'local.5fa2ac4e798ca9.55002694'); // like a local.sdfsdfsdfdsf.sdfsdfsd
define('CLIENT_SECRET', 'OeE6tZSnx4unyxT5KLKVmvy40kHgTetXm7gBiTadbGDis7smtt'); // like a SDFGSDFSD34543SDFSDFSDFSDSDFSDF
#
#Config
#


$appsConfig = array();
if (file_exists(__DIR__.'/config.php'))
    include(__DIR__.'/config.php');

if ($_REQUEST['event'] == 'ONAPPINSTALL')
{
    // handler for events
    $handlerBackUrl = ($_SERVER['SERVER_PORT']==443||$_SERVER["HTTPS"]=="on"? 'https': 'http')."://".$_SERVER['SERVER_NAME'].(in_array($_SERVER['SERVER_PORT'], Array(80, 443))?'':':'.$_SERVER['SERVER_PORT']).$_SERVER['SCRIPT_NAME'];

    // If your application supports different localizations
    // use $_REQUEST['data']['LANGUAGE_ID'] to load correct localization

    // register new bot
    $result = restCommand('imbot.register', Array(
        'CODE' => 'echobot',
        'TYPE' => 'B',
        'EVENT_MESSAGE_ADD' => $handlerBackUrl,
        'EVENT_WELCOME_MESSAGE' => $handlerBackUrl,
        'EVENT_BOT_DELETE' => $handlerBackUrl,
        'OPENLINE' => 'Y', // this flag only for Open Channel mode http://bitrix24.ru/~bot-itr
        'PROPERTIES' => Array(
            'NAME' => 'EchoBot '.(count($appsConfig)+1),
            'COLOR' => 'GREEN',
            'EMAIL' => 'test@test.ru',
            'PERSONAL_BIRTHDAY' => '2016-03-11',
            'WORK_POSITION' => 'My first echo bot',
            'PERSONAL_WWW' => 'http://bitrix24.com',
            'PERSONAL_GENDER' => 'M',
            'PERSONAL_PHOTO' => base64_encode(file_get_contents(__DIR__.'/avatar.png')),
        )
    ), $_REQUEST["auth"]);
    $botId = $result['result'];

    $result = restCommand('imbot.command.register', Array(
        'BOT_ID' => $botId,
        'COMMAND' => 'echo',
        'COMMON' => 'Y',
        'HIDDEN' => 'N',
        'EXTRANET_SUPPORT' => 'N',
        'LANG' => Array(
            Array('LANGUAGE_ID' => 'en', 'TITLE' => 'Get echo message', 'PARAMS' => 'some text'),
        ),
        'EVENT_COMMAND_ADD' => $handlerBackUrl,
    ), $_REQUEST["auth"]);
    $commandEcho = $result['result'];

    $result = restCommand('imbot.command.register', Array(
        'BOT_ID' => $botId,
        'COMMAND' => 'echoList',
        'COMMON' => 'N',
        'HIDDEN' => 'N',
        'EXTRANET_SUPPORT' => 'N',
        'LANG' => Array(
            Array('LANGUAGE_ID' => 'en', 'TITLE' => 'Get list of colors', 'PARAMS' => ''),
        ),
        'EVENT_COMMAND_ADD' => $handlerBackUrl,
    ), $_REQUEST["auth"]);
    $commandList = $result['result'];

    $result = restCommand('imbot.command.register', Array(
        'BOT_ID' => $botId,
        'COMMAND' => 'help',
        'COMMON' => 'N',
        'HIDDEN' => 'N',
        'EXTRANET_SUPPORT' => 'N',
        'LANG' => Array(
            Array('LANGUAGE_ID' => 'en', 'TITLE' => 'Get help message', 'PARAMS' => 'some text'),
        ),
        'EVENT_COMMAND_ADD' => $handlerBackUrl,
    ), $_REQUEST["auth"]);
    $commandHelp = $result['result'];

    $result = restCommand('event.bind', Array(
        'EVENT' => 'OnAppUpdate',
        'HANDLER' => $handlerBackUrl
    ), $_REQUEST["auth"]);

    // save params
    $appsConfig[$_REQUEST['auth']['application_token']] = Array(
        'BOT_ID' => $botId,
        'COMMAND_ECHO' => $commandEcho,
        'COMMAND_HELP' => $commandHelp,
        'COMMAND_LIST' => $commandList,
        'LANGUAGE_ID' => $_REQUEST['data']['LANGUAGE_ID'],
        'AUTH' => $_REQUEST['auth'],
    );
    saveParams($appsConfig);

    // write debug log
    writeToLog(Array($botId, $commandEcho, $commandHelp, $commandList), 'ImBot register');
}
// receive event "Application install"
else if ($_REQUEST['event'] == 'ONAPPUPDATE')
{
    // check the event - authorize this event or not
    if (!isset($appsConfig[$_REQUEST['auth']['application_token']]))
        return false;

    if ($_REQUEST['data']['VERSION'] == 2)
    {
        // Some logic in update event for VERSION 2
        // You can execute any method RestAPI, BotAPI or ChatAPI, for example delete or add a new command to the bot
        /*
        $result = restCommand('...', Array(
            '...' => '...',
        ), $_REQUEST["auth"]);
        */

        /*
        For example delete "Echo" command:

        $result = restCommand('imbot.command.unregister', Array(
            'COMMAND_ID' => $appsConfig[$_REQUEST['auth']['application_token']]['COMMAND_ECHO'],
        ), $_REQUEST["auth"]);
        */
    }
    else
    {
        // send answer message
        $result = restCommand('app.info', array(), $_REQUEST["auth"]);
    }

    // write debug log
    writeToLog($result, 'ImBot update event');
}








/**
 * Save application configuration.
 * WARNING: this method is only created for demonstration, never store config like this
 *
 * @param $params
 * @return bool
 */
function saveParams($params)
{
    $config = "<?php\n";
    $config .= "\$appsConfig = ".var_export($params, true).";\n";
    $config .= "?>";

    file_put_contents(__DIR__."/config.php", $config);

    return true;
}
/**
 * Send rest query to Bitrix24.
 *
 * @param $method - Rest method, ex: methods
 * @param array $params - Method params, ex: Array()
 * @param array $auth - Authorize data, received from event
 * @param boolean $authRefresh - If authorize is expired, refresh token
 * @return mixed
 */
function restCommand($method, array $params = Array(), array $auth = Array(), $authRefresh = true)
{
    $queryUrl = $auth["client_endpoint"].$method;
    $queryData = http_build_query(array_merge($params, array("auth" => $auth["access_token"])));

    writeToLog(Array('URL' => $queryUrl, 'PARAMS' => array_merge($params, array("auth" => $auth["access_token"]))), 'ImBot send data');

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_SSL_VERIFYPEER => 1,
        CURLOPT_URL => $queryUrl,
        CURLOPT_POSTFIELDS => $queryData,
    ));

    $result = curl_exec($curl);
    curl_close($curl);

    $result = json_decode($result, 1);

    if ($authRefresh && isset($result['error']) && in_array($result['error'], array('expired_token', 'invalid_token')))
    {
        $auth = restAuth($auth);
        if ($auth)
        {
            $result = restCommand($method, $params, $auth, false);
        }
    }

    return $result;
}
/**
 * Get new authorize data if you authorize is expire.
 *
 * @param array $auth - Authorize data, received from event
 * @return bool|mixed
 */
function restAuth($auth)
{
    if (!CLIENT_ID || !CLIENT_SECRET)
        return false;

    if(!isset($auth['refresh_token']))
        return false;

    $queryUrl = 'https://oauth.bitrix.info/oauth/token/';
    $queryData = http_build_query($queryParams = array(
        'grant_type' => 'refresh_token',
        'client_id' => CLIENT_ID,
        'client_secret' => CLIENT_SECRET,
        'refresh_token' => $auth['refresh_token'],
    ));

    writeToLog(Array('URL' => $queryUrl, 'PARAMS' => $queryParams), 'ImBot request auth data');

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $queryUrl.'?'.$queryData,
    ));

    $result = curl_exec($curl);
    curl_close($curl);

    $result = json_decode($result, 1);
    if (!isset($result['error']))
    {
        $appsConfig = Array();
        if (file_exists(__DIR__.'/config.php'))
            include(__DIR__.'/config.php');

        $result['application_token'] = $auth['application_token'];
        $appsConfig[$auth['application_token']]['AUTH'] = $result;
        saveParams($appsConfig);
    }
    else
    {
        $result = false;
    }

    return $result;
}
/**
 * Write data to log file. (by default disabled)
 * WARNING: this method is only created for demonstration, never store log file in public folder
 *
 * @param mixed $data
 * @param string $title
 * @return bool
 */
function writeToLog($data, $title = '')
{
    if (!DEBUG_FILE_NAME)
        return false;

    $log = "\n------------------------\n";
    $log .= date("Y.m.d G:i:s") . "\n";
    $log .= (strlen($title) > 0 ? $title : 'DEBUG') . "\n";
    $log .= print_r($data, 1);
    $log .= "\n------------------------\n";

    file_put_contents(__DIR__ . "/" . DEBUG_FILE_NAME, $log, FILE_APPEND);

    return true;
}