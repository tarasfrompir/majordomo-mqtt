<?php
chdir(dirname(__FILE__) . '/../');

include_once("./config.php");
include_once("./lib/loader.php");

set_time_limit(0);

include_once("./load_settings.php");

set_time_limit(0);

include_once(ROOT . "3rdparty/phpmqtt/phpMQTT.php");
include_once(DIR_MODULES . "mqtt/mqtt.class.php");

$mqtt = new mqtt();

//$mqtt->prepareQueueTable();
$mqtt->getConfig();

if ($mqtt->config['MQTT_CLIENT']) {
    $client_name = $mqtt->config['MQTT_CLIENT'];
} else {
    $client_name = "MajorDoMo MQTT Cycle";
}
$client_name = $client_name . uniqid();

if ($mqtt->config['MQTT_AUTH']) {
    $username = $mqtt->config['MQTT_USERNAME'];
    $password = $mqtt->config['MQTT_PASSWORD'];
}

$host = 'localhost';

if ($mqtt->config['MQTT_HOST']) {
    $host = $mqtt->config['MQTT_HOST'];
}

if ($mqtt->config['MQTT_LOG']) {
    $log = true;
} else {
    $log = false;
}

if ($mqtt->config['MQTT_PORT']) {
    $port = $mqtt->config['MQTT_PORT'];
} else {
    $port = 1883;
}

if ($mqtt->config['MQTT_QUERY']) {
    $query = $mqtt->config['MQTT_QUERY'];
} else {
    $query = '/var/now/#';
}

$mqtt_client = new Bluerhinos\phpMQTT($host, $port, $client_name);
$mqtt_client->debug = $log; // for debug

if ($mqtt->config['MQTT_AUTH']) {
	$connect = $mqtt_client->connect(true, NULL, $username, $password);
    if (!$connect) {
        exit(1);
    }
} else {
	$connect = $mqtt_client->connect();
    if (!$connect) {
        exit(1);
    }
}

$query_list = explode(',', $query);
$total = count($query_list);
echo date('H:i:s') . " Topics to watch: $query (Total: $total)\n";
for ($i = 0; $i < $total; $i++) {
    $path = trim($query_list[$i]);
    echo date('H:i:s') . " Path: $path\n";
    $topics[$path] = array("qos" => 0, "function" => "procmsg");
}
foreach ($topics as $k => $v) {
    echo date('H:i:s') . " Subscribing to: $k  \n";
    $rec = array($k => $v);
    $mqtt_client->subscribe($rec, 0);
}
$checked_time = time();

$all_topics = array();
while ($mqtt_client->proc()) {

    /*
    $tmp=SQLSelect("SELECT * FROM mqtt_queue ORDER BY ID");
    if ($tmp[0]['ID']) {
     $total=count($tmp);
     for($i=0;$i<$total;$i++) {
      SQLExec('DELETE FROM mqtt_queue WHERE ID='.$tmp[$i]['ID']);
      $mqtt_client->publish($tmp[$i]['PATH'],$tmp[$i]['VALUE']);
     }
    }
    */

    if (time() - $checked_time > 20) {
        $checked_time = time();
        setGlobal((str_replace('.php', '', basename(__FILE__))) . 'Run', $checked_time, 1);
    }
    if (file_exists('./reboot') || IsSet($_GET['onetime'])) {
			
		    $mqtt_client->close();
        exit;
    }
}

$mqtt_client->close();

/**
 * Process message
 * @param mixed $topic Topic
 * @param mixed $msg Message
 * @return void
 */
function procmsg($topic, $msg) {
    global $all_topics;
    //$url = BASE_URL . '/ajax/mqtt.html?op=process&topic='.urlencode($topic)."&msg=".urlencode($msg);
    //getURLBackground($url);
    if (!isset($topic) || !isset($msg)) return false;
    if (!isset($all_topics[$topic]) or $all_topics[$topic] != $msg) {
        $all_topics[$topic] = $msg;
        callAPI('/api/module/mqtt','GET',array('topic'=>$topic,'msg'=>$msg));
    
    }
    //DebMes($all_topics);
}

