<?php
class myOOP extends PDO {
	private $error;
	private $sql;
	private $bind;
	private $errorCallbackFunction;
	private $errorMsgFormat;
	private $skey = "#v@(&9v;//Wpr3M/;Z9cSf#^!q^{dSz:"; // myOOPCrypto için benzersiz anahtar.
	private $prefix = "";
	public function __construct(Array $sqlSetting) {
		$host = $sqlSetting["host"];
		$dbName = $sqlSetting["database"];
		$username = $sqlSetting["username"];
		$password = $sqlSetting["password"];
		$dsn="mysql:host=$host;dbname=$dbName";
		$user="$username";
		$passwd="$password";
		$options = array(
			PDO::ATTR_PERSISTENT => true, 
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
		);

		try {
			parent::__construct($dsn, $user, $passwd, $options);
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
		}
	}
	private function debug() {
		if(!empty($this->errorCallbackFunction)) {
			$error = array("Error" => $this->error);
			if(!empty($this->sql))
				$error["SQL Statement"] = $this->sql;
			if(!empty($this->bind))
				$error["Bind Parameters"] = trim(print_r($this->bind, true));

			$backtrace = debug_backtrace();
			if(!empty($backtrace)) {
				foreach($backtrace as $info) {
					if($info["file"] != __FILE__)
						$error["Backtrace"] = $info["file"] . " at line " . $info["line"];	
				}		
			}

			$msg = "";
			if($this->errorMsgFormat == "html") {
				if(!empty($error["Bind Parameters"]))
					$error["Bind Parameters"] = "<pre>" . $error["Bind Parameters"] . "</pre>";
				$css = trim(file_get_contents(dirname(__FILE__) . "/error.css"));
				$msg .= '<style type="text/css">' . "\n" . $css . "\n</style>";
				$msg .= "\n" . '<div class="db-error">' . "\n\t<h3>SQL Error</h3>";
				foreach($error as $key => $val)
					$msg .= "\n\t<label>" . $key . ":</label>" . $val;
				$msg .= "\n\t</div>\n</div>";
			}
			elseif($this->errorMsgFormat == "text") {
				$msg .= "SQL Error\n" . str_repeat("-", 50);
				foreach($error as $key => $val)
					$msg .= "\n\n$key:\n$val";
			}

			$func = $this->errorCallbackFunction;
			$func($msg);
		}
	}
	public function delete($table, $where, $bind="") {
		$sql = "DELETE FROM " . $table . " WHERE " . $where . ";";
		return $this->run($sql, $bind);
	}

	private function filter($table, $info) {
		$driver = $this->getAttribute(PDO::ATTR_DRIVER_NAME);
		if($driver == 'sqlite') {
			$sql = "PRAGMA table_info('" . $table . "');";
			$key = "name";
		}
		elseif($driver == 'mysql') {
			$sql = "DESCRIBE " . $table . ";";
			$key = "Field";
		}
		else {	
			$sql = "SELECT column_name FROM information_schema.columns WHERE table_name = '" . $table . "';";
			$key = "column_name";
		}	

		if(false !== ($list = $this->run($sql))) {
			$fields = array();
			foreach($list as $record)
				$fields[] = $record[$key];
			return array_values(array_intersect($fields, array_keys($info)));
		}
		return array();
	}

	private function cleanup($bind) {
		if(!is_array($bind)) {
			if(!empty($bind))
				$bind = array($bind);
			else
				$bind = array();
		}
		return $bind;
	}
	
	public function insert($table, $info) {
		$fields = $this->filter($table, $info);
		$sql = "INSERT INTO " . $table . " (" . implode($fields, ", ") . ") VALUES (:" . implode($fields, ", :") . ");";
		$bind = array();
		foreach($fields as $field)
			$bind[":$field"] = $info[$field];
		return $this->run($sql, $bind);
	}

	public function run($sql, $bind="") {
		$this->sql = trim($sql);
		$this->bind = $this->cleanup($bind);
		$this->error = "";

		try {
			$pdostmt = $this->prepare($this->sql);
			if($pdostmt->execute($this->bind) !== false) {
				if(preg_match("/^(" . implode("|", array("select", "describe", "pragma")) . ") /i", $this->sql))
					return $pdostmt->fetchAll(PDO::FETCH_ASSOC);
				elseif(preg_match("/^(" . implode("|", array("delete", "insert", "update")) . ") /i", $this->sql))
					return $pdostmt->rowCount();
			}	
		} catch (PDOException $e) {
			$this->error = $e->getMessage();	
			$this->debug();
			return $e->getMessage();
		}
	}

	public function select($table, $where="", $type="select",$order="",$bind="", $fields="*") {
		$sql = "SELECT " . $fields . " FROM " . $table;
		if(!empty($where)){
			if($where != " "){
				$sql .= " WHERE " . $where;
			}
		}
		$sql .= " $order;";
		if($type == "select"){
			return @$this->run($sql, $bind)[0];
		} elseif($type="foreach"){
			return $this->run($sql, $bind);
		} else {
			return $this->run($sql, $bind);
		}
	}

	public function setErrorCallbackFunction($errorCallbackFunction, $errorMsgFormat="html") {
		if(in_array(strtolower($errorCallbackFunction), array("echo", "print")))
			$errorCallbackFunction = "print_r";

		if(function_exists($errorCallbackFunction)) {
			$this->errorCallbackFunction = $errorCallbackFunction;	
			if(!in_array(strtolower($errorMsgFormat), array("html", "text")))
				$errorMsgFormat = "html";
			$this->errorMsgFormat = $errorMsgFormat;	
		}	
	}
	public function update($table, $info, $where, $bind="") {
		$fields = $this->filter($table, $info);
		$fieldSize = sizeof($fields);

		$sql = "UPDATE " . $table . " SET ";
		for($f = 0; $f < $fieldSize; ++$f) {
			if($f > 0)
				$sql .= ", ";
			$sql .= $fields[$f] . " = :update_" . $fields[$f]; 
		}
		$sql .= " WHERE " . $where . ";";

		$bind = $this->cleanup($bind);
		foreach($fields as $field)
			$bind[":update_$field"] = $info[$field];
		
		return $this->run($sql, $bind);
	}
	public function pull($tablo,$bilgi,$where=""){
		$data = $this->select($tablo,$where);
		return $data[$bilgi];
	}
	public function turkishLira($para, $virgul=2, $ondalik='.', $tam='.') {
		$para = str_replace(",",".",$para);
		return number_format($para, $virgul, $ondalik, $tam);
	}
	public function distanceGeoPoints($lat1, $lng1, $lat2, $lng2) {
		$earthRadius = 3958.75;
		$dLat = deg2rad($lat2-$lat1);
		$dLng = deg2rad($lng2-$lng1);
		$a = sin($dLat/2) * sin($dLat/2) +
		   cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
		   sin($dLng/2) * sin($dLng/2);
		$c = 2 * atan2(sqrt($a), sqrt(1-$a));
		$dist = $earthRadius * $c;
		$meterConversion = 1609;
		$geopointDistance = $dist * $meterConversion;
		return $geopointDistance;
	}
	public function errors(){
		global $lang;
		if(isset($_GET["error"])){
				$error = $_GET["error"];
				if(isset($_GET["eType"])){	
					$type = $_GET["eType"];
				} else {
					$type = "danger";
				}
				return '<div class="alert alert-'.$type.'">'.$lang["tr"][$error].'</div>';
		} else {
			return "";
		}
	}
	public function rowCount($tablo,$where=""){
		$sayi = count($this->select($tablo,$where,"foreach"));
		return $sayi;
	}
	public function dateFormat($tarih,$format='d/m/Y H:i:s'){
		$date = new DateTime($tarih);
		$cikti =  $date->format($format);
		$aylarIng = array( "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"); 
		$gunlerIng = array("Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"); 
		$aylar = array("Ocak", "Þubat", "Mart", "Nisan", "Mayýs", "Haziran","Temmuz", "Aðustos", "Eylül", "Ekim", "Kasým", "Aralýk"); 
		$gunler = array("Pazartesi", "Salý", "Çarþamba", "Perþembe", "Cuma", "Cumartesi", "Pazar"); 
		$cikti = str_replace($aylarIng, $aylar, $cikti); 
		$cikti = str_replace($gunlerIng, $gunler, $cikti); 
		return $cikti;  
		
	}
	public function aradancek($bununla,$bunun,$metin){
		$kes = explode($bununla,$metin);
		$yinekes = explode($bunun,$kes[1]);
		return $yinekes[0];
	}
	public function js_yonlendir($url,$sure=0) {
		if($sure == 0){
			echo "<script>location.href='$url';</script>";
		} else {
			$surel = $sure * 1000;
			echo "<script>public function Redirect() {  window.location=\"$url\"; }  setTimeout('Redirect()', $surel);</script>";
		}
		
	}
	public function filtre($text){
		$gelenkod = array("location","refresh","script","frame","\\n");
		$degis = array("","","","","<br>");

		$yeni = str_replace($gelenkod,$degis,$text);
		return $yeni;
	}
	public function dosyaBilgi($url,$bilgi="uzanti") {
		$path_parts = pathinfo($url);

		switch ($bilgi) {
			case "klasor" : 
			return $path_parts['dirname'];
			break;
			case "tamDosya" :
			return $path_parts['basename'];
			break;
			case "uzanti" :
			return $path_parts['extension'];
			break;
			case "dosya" : 
			return $path_parts['filename']; 
			break;
			default :
			return $path_parts['extension'];
			break;		
		}
	}
	public function dosyaKopyala($url, $klasor) { 
		@$dosya = fopen($url,"rb");
		if (!$dosya) {
			return false;
		} else {
			$isim = basename($url);
			$dc = fopen($klasor."$isim","wb");
			while (!feof($dosya)) {
			$satir = fread($dosya,1028);
			fwrite($dc,$satir);
			}
			fclose($dc);
			return $isim;
		}
	}
	public function youtubeThumb($url,$upload){
		$content = file_get_contents($url);
		$filename = sifre(20);
		$handle = fopen(''.$upload.'/'.$filename.'.jpg', 'w+');
		fwrite($handle, $content);
		return "$filename.jpg";
	}
	public function slug($kisim="") {
		@$the_url = "$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        @$parts = explode("/",$the_url);
		if(@$kisim == ""){
			@$sonKisim = (count($parts) - 1);
			@$cikti = explode("?",$parts[$sonKisim]);
			return @$cikti[0]."1";
		} else {
			@$cikti = preg_split('@/@', $parts[$kisim], NULL, PREG_SPLIT_NO_EMPTY);
			if(@$cikti == ""){
				return "anasayfa";
			} else {
			@$ciktis = explode("?",$cikti[0]);
			return @$ciktis[0];
			}
			
		}
    }
	public function table($name){
		return $this->prefix.$name;
	}
	public function tarih($d2){
		$d1 = date('Y-m-d H:i:s');
			if(!is_int($d1)) $d1=strtotime($d1);
			if(!is_int($d2)) $d2=strtotime($d2);
			$d=abs($d1-$d2);
		if ($d1-$d2<0) {
		$ifade = "sonra";
		} else {
		$ifade = "önce";
		 }
		$once = ""; 
			if($d>=(60*60*24*365))    $sonuc  = $once . floor($d/(60*60*24*365)) . " yýl $ifade";
			else if($d>=(60*60*24*30))     $sonuc = $once . floor($d/(60*60*24*30)) . " ay $ifade";
			else if($d>=(60*60*24*7))  $sonuc  = $once . floor($d/(60*60*24*7)) . " hafta $ifade";
			else if($d>=(60*60*24))    $sonuc  = $once . floor($d/(60*60*24)) . " gün $ifade";
			else if($d>=(60*60))   $sonuc = $once . floor($d/(60*60)) . " saat $ifade";
			else if($d>=60) $sonuc  = $once . floor($d/60)  . " dakika $ifade";
			else $sonuc = "Az $ifade";
			return $sonuc;
	}
	public function redirect($url){
		if (!headers_sent()){    
			header('Location: '.$url);
			exit;
		}else{  
			echo '<script type="text/javascript">';
			echo 'window.location.href="'.$url.'";';
			echo '</script>';
			echo '<noscript>';
			echo '<meta http-equiv="refresh" content="0;url='.$url.'" />';
			echo '</noscript>'; exit;
		}
	}
	public function cleanInput($input) {

    $search = array( 
      '@<script[^>]*?>.*?</script>@si', // Strip out javascript 
      '@<[\/\!]*?[^<>]*?>@si', // Strip out HTML tags 
      '@<style[^>]*?>.*?</style>@siU', // Strip style tags properly 
      '@<![\s\S]*?--[ \t\n\r]*>@' // Strip multi-line comments 
    ); 
    $output = preg_replace($search, '', $input); 
    return $output; 
	}
	public function ip() {
		return $_SERVER['REMOTE_ADDR'];
	}
	public function seflink($s){
		$tr = array('þ','Þ','ý','Ý','ð','Ð','ü','Ü','ö','Ö','ç','Ç'); // deðiþecek türkçe karakterler 
		$en = array('s','s','i','i','g','g','u','u','o','o','c','c');  // yeni karakterler
		$s = str_replace($tr,$en,$s); 
		$s = strtolower($s); 
		$s = preg_replace('/&amp;amp;amp;amp;amp;amp;amp;amp;.+?;/', '-', $s); 
		$s = preg_replace('/[^%a-z0-9 _-]/', '-', $s); 
		$s = preg_replace('/\s+/', '-', $s); 
		$s = preg_replace('|-+|', '-', $s); 
		$s = str_replace("--","-",$s); 
		$s = trim($s, '-'); 
		return $s;
	}
	public function history($url=""){
		$ham = explode('?', $_SERVER['HTTP_REFERER']);
		$urll = $ham[0];
		if($url == ""){
			return "$urll";
		} else {
			return $urll."?".$url;
		}
	}
	public function err(){
		if(isset($_GET["error"])){
			$errors = $this->filtrele($_GET["error"],"text");
			if($this->lang("err_$errors") != ""){
				return '<div class="alert alert-danger">'.$this->lang("err_$errors").'</div>';
			}
		}
	}
	public function urlDuzelt($url){
		$ham = explode('?', $_SERVER['REQUEST_URI']);
		$urll = $ham[0];
		return "$urll?$url";
	}
	public function now() {
		return date('Y-m-d H:i:s');
	}
	public function telefon($text) {
		$text  = preg_replace("/[^0-9]/", "", $text);
		$first = substr("$text",0,1);
		if($first == "0") { $text = substr($text,1); }

		$doksan = substr("$text",0,2);
		if($doksan != "90") { 
			return false;
			}
		else {
			$numara = substr($text,2);
			if(substr("$numara",0,1) == "0") { 
				$numara = substr($numara,1); }

			if(strlen($numara) != "10") { 
				return false;
				}
			else { 
				$new_telefon = "$numara";
				return $new_telefon; 
				}
		}

		
	}
	public function sifre($hane=6,$tur="hash"){
		if($tur == "md5"){
			$bir = str_repeat("1", $hane);
			$dokuz = str_repeat("9", $hane);
			$kripto = md5(rand($bir,$dokuz));
		} elseif($tur == 1) {
			$bir = str_repeat(1, $hane);
			$dokuz = str_repeat(9, $hane);
			$kripto = rand(intval($bir),intval($dokuz));
		} elseif($tur = "hash") {
			$random = '';
			$kripto = "";
			for ($i = 0; $i < $hane; $i++) {
				$kripto .= mt_rand(33, 126);
			}
		}
		return substr($kripto,0,$hane);
	}
	public function yuzde($sayi, $yuzde_deger,$secenek){
		$yuzdemiz = ($sayi * $yuzde_deger) / 100;
		$fark = $sayi - $yuzdemiz;
		$topla = $sayi + $yuzdemiz;
		$carp = $sayi * $yuzdemiz;
		$bol = $sayi / $yuzdemiz;
		 
		if($secenek == 1){
		return $yuzdemiz;
		}elseif($secenek == 2){
		return $fark;
		}elseif($secenek == 3){
		return $topla;
		}elseif($secenek == 4){
		return $carp;
		}elseif($secenek == 5){
		return $bol;
		}else{
		return $yuzdemiz;
		}
	}
	public function sinirla($metin,$sinir='25'){
		$detay = $metin;
		$uzunluk = strlen($detay);
		$limit = $sinir;
		if ($uzunluk > $limit) {
		$detay = mb_substr($detay,0,$limit,"UTF-8") . "...";
		}
		return $detay;
	}
	public function topla($degerler){
		$dizi=explode(",", $degerler);
		$dizi_boyutu=count($dizi);
		$toplam=0;
		for($i=0; $i<=$dizi_boyutu; $i++){
		 @$toplam+=$dizi[$i];
		}
		return $toplam;
	}
	public function buyukharf($str){
		$str = str_replace(array('i', 'ý', 'ü', 'ð', 'þ', 'ö', 'ç'), array('Ý', 'I', 'Ü', 'Ð', 'Þ', 'Ö', 'Ç'), $str);
		return StrToUpper($str);
	}
	public function mailGonder($kime,$unvan,$konu,$mesaj,$ek="",$oda="Otematik"){
		$mail = new PHPMailer();
		$mail->IsSMTP();
		$mail->SMTPAuth = true;
		$mail->Host = 'mail.alanaadi.com';
		$mail->Port = 587;
		$mail->Username = 'mail@alanaadi.com';
		$mail->Password = 'sifreniz';
		$mail->SetFrom($mail->Username, $oda);
		$mail->AddAddress($kime, $unvan);
		$mail->CharSet = 'UTF-8';
		$mail->Subject = $konu;
		$mail->MsgHTML($mesaj);
		if($mail->Send()) {
			return true;
		} else {
			return 'Mail gönderilirken bir hata oluþtu: ' . $mail->ErrorInfo;
		}
	}
	public function filtrele($value,$type='int'){
		if($type == "int"){
			if(filter_var($value,FILTER_VALIDATE_INT)){
				if($value == ""){
					return 0;
				} else {
					return $value;
				}
			}else{
				return 0;
			}
		} elseif($type == "text") {
			return strip_tags($value);
		} elseif($type == "date") {
		
		} elseif($type == "urlencode"){
			return urldecode(str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $value));
		}
	}
	public function full_copy( $source, $target ) {
		if ( is_dir( $source ) ) {
			@mkdir( $target );
			$d = dir( $source );
			while ( FALSE !== ( $entry = $d->read() ) ) {
				if ( $entry == '.' || $entry == '..' ) {
					continue;
				}
				$Entry = $source . '/' . $entry; 
				if ( is_dir( $Entry ) ) {
					$this->full_copy( $Entry, $target . '/' . $entry );
					continue;
				}
				copy( $Entry, $target . '/' . $entry );
			}

			$d->close();
		}else {
			copy( $source, $target );
		}
	}
	public function bol($kismen){
		return  $this->filtrele($this->slug($kismen + $this->slash()),"urlencode");
	}

	public function dosyaVarmi($adres) {
		if (file_exists($adres)) {
			return true;
		} else {
			return false;
		}
	}
	public function url($type = "pre"){
		if(isset($_SERVER['HTTPS'])){
			$protocol = ($_SERVER['HTTPS'] && $_SERVER['HTTPS'] != "off") ? "https" : "http";
		}
		else{
			$protocol = 'http';
		}
		if($type == "pre"){
			return $protocol . "://" . $_SERVER['HTTP_HOST']."/".baseUrl;
		}elseif($type == "preColl"){
			return $protocol . "://" . $_SERVER['HTTP_HOST'];
		} elseif($type == "host") {
			 $donen = str_replace("www.","",$_SERVER['HTTP_HOST']);
			 $explodeNokta = explode(".",$donen);
			 $kacNokta = count($explodeNokta);
			 if($kacNokta == 4){
				 if($explodeNokta[2] == "tr"){
					 return $donen;
				 } else {
					return $explodeNokta[1].".".$explodeNokta[2].".".$explodeNokta[3];
				 }
			 } else {
				 return $donen;
			 }
		} elseif($type == "sub") {
			$explod = explode(".",$_SERVER['HTTP_HOST']);
			if($explod[1] == "otematik"){
				return $explod[0];
			} else {
				return "otem";
			}
		}else {
			return $protocol . "://" . $_SERVER['HTTP_HOST']."/".baseUrl;
		}
	}
	public function kurlar($doviz=0,$tur="alis"){
		$connect_web = simplexml_load_file('http://www.tcmb.gov.tr/kurlar/today.xml');
		if($tur == "alis"){
			$usd_buying = $connect_web->Currency[0]->BanknoteBuying;
			if($usd_buying == ""){
				return "3";
			} else {
				return $usd_buying;
			}
		} else {
			$usd_buying = $connect_web->Currency[0]->BanknoteSelling;
			if($usd_buying == ""){
				return "3";
			} else {
				return $usd_buying;
			}
		}
	}
	public function sendAndroidNotification($title,$message, $device_ids="all"){
		//print_r($device_ids); die;
		$sound = 0;
		$vibration = 0;
		// API access key from Google API's Console
		//define( 'API_KEY', Yii::app()->params->API_KEY );
		// prep the bundle
		if($device_ids == "all"){
			foreach($this->veriForeach("devices","dStatus='1'") as $device){
				$deviceToken[] = $device["dToken"];
			}
			$device_ids = $deviceToken;
		} 
		$msg = array(
			'message' 	=> $message,
			'title'		=> $title,
			'subtitle'	=> '',
			'tickerText'	=> $message,
			'vibrate'	=> $vibration,
			'sound'		=> $sound,
			'largeIcon'	=> 'large_icon',
			'smallIcon'	=> 'small_icon',
		);
		
		$fields = array(
			'registration_ids' 	=> $device_ids,
			'data'			=> $msg
		);
		 
		$headers = array(
			'Authorization: key=###ANDROID NOTIFICATION KEY###',
			'Content-Type: application/json'
		);
		 
		$ch = curl_init();
		curl_setopt( $ch,CURLOPT_URL, 'https://android.googleapis.com/gcm/send' );
		curl_setopt( $ch,CURLOPT_POST, true );
		curl_setopt( $ch,CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch,CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch,CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch,CURLOPT_POSTFIELDS, json_encode( $fields ) );
		$result = curl_exec($ch );
		curl_close( $ch );
		return true;
	
	}
	public function isMobile() {
		return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
	}
	public  function safe_b64encode($string) {
        $data = base64_encode($string);
        $data = str_replace(array('+','/','='),array('-','_',''),$data);
        return $data;
    }
    public function safe_b64decode($string) {
        $data = str_replace(array('-','_'),array('+','/'),$string);
        $mod4 = strlen($data) % 4;
        if ($mod4) {
            $data .= substr('====', $mod4);
        }
        return base64_decode($data);
    }
    public  function encode($value){ 
        if(!$value){return false;}
        $text = $value;
        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        $crypttext = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $this->skey, $text, MCRYPT_MODE_ECB, $iv);
        return trim($this->safe_b64encode($crypttext)); 
    }
    public function decode($value){
        if(!$value){return false;}
        $crypttext = $this->safe_b64decode($value); 
        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        $decrypttext = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $this->skey, $crypttext, MCRYPT_MODE_ECB, $iv);
        return trim($decrypttext);
    }
}
?>