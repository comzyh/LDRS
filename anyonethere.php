<?php
/**
 * @Author: Comzyh
 * @Date:   2015-06-09 10:56:16
 * @Last Modified by:   Comzyh
 * @Last Modified time: 2016-04-01 01:32:58
 */
$config = [
	'db_filename' => 'anyonethere.db',
	'bot_api_url' => 'https://api.telegram.org/bot<your_token_here>/',
	'default_reporter' => 'LabDesktop01'
];
class MyDB extends SQLite3{
  function __construct($db_filename){
	 $this->open($db_filename);
  }
}
class TelegramBot{
	private $bot_api_url;
	function __construct($bot_api_url){
		$this->bot_api_url = $bot_api_url;
	}
	function curl_post($url, $data){
		$ch = curl_init($url); //请求的URL地址
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);//$data JSON类型字符串
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($data)));
		$data = curl_exec($ch);
		if ($fp = fopen('post_data_resp', "w")){
			@fwrite($fp, $data);
			fclose($fp);
		}
	}
	function sendMessage($data){
		$json = json_encode($data);
		$this->curl_post($this->bot_api_url . "sendMessage", $json);
	}
}
/**
* Get Human Readable Name from MAC address
*/
class MacName{

	private $db;
	function __construct($db){
		$this->db = $db;
	}
	function mac_perfix($mac, $length){
		$str = strtoupper(preg_replace("/[\s-+:]/", "", $mac));
		return substr($str, 0, $length);
	}
	function manufacturer($mac){
		$sql = "SELECT manufacturer FROM maclist WHERE perifx = '$mac'";
		$ret = $this->db->query($sql);
		if ($ret){
			return $ret->fetchArray(SQLITE3_ASSOC)['manufacturer'];
		}
		else{
			return "Unknown Vender";
		}
	}
	function mac_to_name($mac){
		$sql = "SELECT name FROM mac_to_name WHERE mac_address = '$mac'";
		$ret = $this->db->query($sql);
		if ($ret){
			return $ret->fetchArray(SQLITE3_ASSOC)['name'];
		}
		else{
			return NULL;
		}
	}
	function set_mac_name($mac, $name){
		if ($name != '-'){
			$sql = "INSERT OR REPLACE INTO mac_to_name(mac_address, name) VALUES('$mac', '$name')";
		}
		else{
			$sql = "DELETE FROM mac_to_name WHERE mac_address = '$mac'";
		}
		echo $sql;
		$this->db->exec($sql);
	}
	function nick_name($mac){
		$mac_perfix = $this->mac_perfix($mac, 6);
		$nick_name = $this->mac_to_name($mac); // use $mac rather than $mac_perfix
		if ($nick_name == NULL)
			$nick_name = $this->manufacturer($mac_perfix);
		return $mac . "($nick_name)";
	}

}
$db = new MyDB($config["db_filename"]);
$macname = new MacName($db);
function create_db($db)
{
	$sql="CREATE TABLE IF NOT EXISTS [ping] (
		  [reporter] VARCHAR(50),
		  [mac_address] CHAR(50),
		  [ip_address] CHAR(50),
		  [report_time] DATETIME,
		  [rtt] FLOAT);";
	$ret = $db->exec($sql);

	$sql = "CREATE TABLE IF NOT EXISTS [tg_subscribe] (
			[chat_id] INT NOT NULL ON CONFLICT REPLACE UNIQUE);";
	$ret = $db->exec($sql);

	$sql = "CREATE TABLE IF NOT EXISTS [mac_to_name] (
		  [mac_address] CHAR(50) UNIQUE,
		  [name] CHAR(50));";
	$ret = $db->exec($sql);

	$sql = "CREATE TABLE IF NOT EXISTS [maclist] (
		[perifx] CHAR NOT NULL ON CONFLICT REPLACE UNIQUE,
		[manufacturer] CHAR NOT NULL)";
	$ret = $db->exec($sql);
}

if ($_SERVER['REQUEST_METHOD']=='POST'){
	create_db($db);
	$post_data = file_get_contents('php://input');
	if ($fp = fopen('post_data', "w")){
		@fwrite($fp, $post_data);
		fclose($fp);
	}
	$data = json_decode($post_data, true);
	$now_time = date('Y-m-d H:i:s');
	if (array_key_exists('type', $data) && $data['type'] == 'report'){
		$new_mac = [];
		$reporter = $db->escapeString($data['reporter']);
		foreach ($data['data'] as $record) {
			$ip = $db->escapeString($record[0]);
			$mac = $db->escapeString($record[1]);
			$rtt = $db->escapeString($record[2]);

			# New device
			$sql = "SELECT count(*) as count FROM ping WHERE mac_address == '$mac' AND report_time > DATETIME('$now_time', '-30 minutes')";
			$row = $db->query($sql)->fetchArray(SQLITE3_ASSOC);
			if ($row && $row['count'] == 0){
				$new_mac[] = $mac;
			}
			$sql="INSERT INTO ping (reporter, mac_address, ip_address, rtt, report_time) VALUES ('$reporter','$mac','$ip','$rtt','$now_time')";
			$ret = $db->exec($sql);
		}
		if (count($new_mac)){
			$sql = "SELECT chat_id FROM tg_subscribe";
			$ret = $db->query($sql);
			$bot = new TelegramBot($config['bot_api_url']);
			$text = "";
			foreach ($new_mac as $mac){
				$text = $text . $macname->nick_name($mac) . "\n";
			}
			$text = $text . 'just connected.';
			echo $text;
			while($row = $ret->fetchArray(SQLITE3_ASSOC) ){
				echo "\n" . $row['chat_id'];
				$bot->sendMessage([
					'chat_id' => $row['chat_id'],
					'text' => $text
					]);
				}
			}
	}
	else if (array_key_exists('update_id', $data) && $data['update_id']){ // Telegram Bot
		$message = $data['message'];
		$bot = new TelegramBot($config['bot_api_url']);
		$chat_id = $message['chat']['id'];
		$args = preg_split("/\s+/", $message['text']);
		$reply = [
				"chat_id" => $chat_id,
				"reply_to_message_id" => $message['message_id']
			];
		$command = preg_split('/@/', $args[0])[0];
		switch ($command) {
			case '/anyonethere':
				$sql = "SELECT Count(DISTINCT(mac_address)) as count, report_time FROM ping WHERE report_time > DATETIME((SELECT MAX(report_time) FROM ping), '-5 minutes')";
				$row = $db->query($sql)->fetchArray(SQLITE3_ASSOC);
				if ($row){
					$count = $row['count'];
					$report_time = $row['report_time'];
					$reply['text'] = "There are $count device(s) around $report_time .";
					echo $reply['text'];
				}
				else{
					$reply['text'] = 'Sorry, maybe no one there';
				}
				$bot->sendMessage($reply);
				break;
			case '/help':
				$reply['text'] = "I'm a Bot for 你";
				$bot->sendMessage($reply);
				break;
			case '/subscribe':
				$sql = "REPLACE INTO tg_subscribe (chat_id) VALUES ($chat_id)";
				$ret = $db->exec($sql);
				$reply['text'] = 'Subscribe successfully, you will receive a message after a new device is connected.';
				$bot->sendMessage($reply);
				break;
			case '/unsubscribe':
				$sql = "DELETE FROM tg_subscribe WHERE chat_id = $chat_id";
				$ret = $db->exec($sql);
				$reply['text'] = 'Unsubscribe successfully, you will no longer receive the device connection message.';
				$bot->sendMessage($reply);
				break;
			case '/listdevices':
			case '/whosthere':
				if ($command == '/listdevices'){
					$sql = "SELECT DISTINCT mac_address FROM ping WHERE report_time > DATETIME('$now_time', '-48 hours')";
					$text = "the %d device(s) appeared in 48 hours are:\n";
				}
				else if ($command == '/whosthere'){
					$sql = "SELECT DISTINCT mac_address FROM ping WHERE report_time > DATETIME('$now_time', '-10 minutes')";
					$text = "the %d device(s) appeared in 10 minutes are:\n";
				}
				$ret = $db->query($sql);
				$devices = [];
				$device_num = 0;
				while($row = $ret->fetchArray(SQLITE3_ASSOC) ){
					$device_num = $device_num + 1;
					$devices[] = $row['mac_address'];
				}
				$text = sprintf($text, $device_num);
				foreach ($devices as $mac){
					$text = $text . $macname->nick_name($mac) . "\n";
				}
				$reply['text'] = $text;
				$bot->sendMessage($reply);
				break;
			case '/name':
				if (count($args) < 3){
					$reply['text'] = "name require at least 2 arguments.";
					$bot->sendMessage($reply);
					break;
				}
				$mac = $args[1];
				$name = $args[2];
				$macname->set_mac_name($mac, $name);
				if ($name != '-')
					$reply['text'] = "Name of $mac has been change to $name.";
				else
					$reply['text'] = "Name of $mac has been deleted.";
				$bot->sendMessage($reply);
				break;
			default:
				$reply['text'] = "Unknown command, please check your spelling.";
				$bot->sendMessage($reply);
				break;
		}
	}
}
else if ($_GET['data']){
	$reporter = $config['default_reporter'];
	$sql = "SELECT mac_address, ip_address, rtt, report_time FROM ping WHERE reporter == '$reporter' order by report_time DESC LIMIT 1000;";
	$ret = $db->query($sql);
	$ret_array = [];
	while($row = $ret->fetchArray(SQLITE3_ASSOC) ){
	  $ret_array[] = [$row['mac_address'],$row['ip_address'],$row['rtt'],$row['report_time']];
	}
	echo (json_encode($ret_array));
} else if ($_GET['mac']){
	$mac = $_GET['mac'];
	$reporter = 'LabDesktop01';
	$sql = "SELECT mac_address, report_time FROM ping WHERE reporter == '$reporter' and mac_address == '$mac' order by report_time DESC LIMIT 1000;";
	$ret = $db->query($sql);
	$ret_array = [];
	echo ("<table>\n");
	while($row = $ret->fetchArray(SQLITE3_ASSOC) ){
	  // $ret_array[] = [$row['mac_address'],$row['report_time']];
		$report_time = $row['report_time'];
		$line = "<tr><td>$mac</td><td>$report_time</td></tr>\n";
		echo ($line);
	}
	echo ("</table>\n");
	// echo (json_encode($ret_array));
} else {

?>
<!DOCTYPE html>
<html>
<head>
	<title>AnyoneThere</title>
	<script src="//cdn.bootcss.com/jquery/2.1.4/jquery.min.js"></script>
</head>
<body>
<table>
	<thead>
		<th>MAC</th>
		<th>姓名</th>
		<th>IP</th>
		<th>上次出现时间</th>
	</thead>
	<tbody id="data_body">

	</tbody>
</table>
<script type="text/javascript">
	var load_data = function() {
		$.getJSON('?data=data', function(data) {
			var data_arr = data; // mac ip rtt time
			data_arr.sort(function(a, b) {
				return -(new Date(a[3]) - new Date(b[3]));
			});
			var mac_dict = {};
			var last_update = new Date(data_arr[0][3]);
			for (var i = 0; i < data_arr.length; i++) {
				var rec = data_arr[i];
				if (mac_dict[rec[0]] === undefined)
					mac_dict[rec[0]] = [];
				mac_dict[rec[0]].push(rec);
			}
			var data_body = $('#data_body');
			while (data_body.firstChild)
				data_body.removeChild(data_body.firstChild);
			for (var mac in mac_dict) {
				var tr = document.createElement('tr');
				//mac
				var td = document.createElement('td');
				var a = document.createElement('a');
				a.href = '//' + location.hostname + '/' + location.pathname + '?mac=' + mac;
				a.textContent = mac;
				td.appendChild(a)
				tr.appendChild(td)

				//name
				var td = document.createElement('td');
				td.textContent = 'Anonymous';
				tr.appendChild(td)
					//ip
				var td = document.createElement('td');
				td.textContent = mac_dict[mac][0][1];
				tr.appendChild(td);
				//time
				var td = document.createElement('td');
				td.textContent = mac_dict[mac][0][3];
				tr.appendChild(td);
				data_body.append(tr);
			}
		});
	};
	$(document).ready(function() {
		load_data();
	})
</script>
</body>
</html>
<?php } ?>
