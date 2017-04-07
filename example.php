<?php
require_once 'class.myOOP.php'; // Sınıfımızı import ediyoruz.
$myOOPConfig = ['host' => 'localhost','database' => 'veritabaniadi','username' => 'kullaniciadi','password' => 'sifre']; // Ayarlarınızı yazın
$db = new myOOP($myOOPConfig);

#Kullanım Örnekleri;

#SELECT İŞLEMİ
// Kullanım : $row = $db->select("##tabloAdi##","##Where##","select","##order##");
$row = $db->select("uyeler","durum='1'","select","order by id desc");

#INSERT İŞLEMİ
$array = array(
  "isim" => "Muhammed",
  "soyisim" => "Aziz",
  "tarih" => date("Y-m-d")
);
$db->insert("uyeler,$array");

#UPDATE İŞLEMİ
$array = array(
  "isim" => "Muhammed",
  "soyisim" => "Aziz",
  "tarih" => date("Y-m-d")
);
$db->update("uyeler,$array","id='1'");

#DELETE İŞLEMİ
$db->delete("uyeler","id='1'");

#FOREACH
foreach($db->select("uyeler","durum='1'","foreach","order by id desc") as $row):
  echo $row["isim"];
endforeach;

#ROWCOUNT (belirtilen değerlerdeki verilerin sayısı)
$veriSayisi = $db->rowCount("uyeler","durum='1'");

#TARİH FORMATINI DEĞİŞTİRME
$simdi = date("Y-m-d H:i:s");
$simdi = $db->dateFormat($simdi,"d.m.Y H:i");

#SEFLINK OLUŞTURMA
$degisken = "İçerik Başlığı Bu Alanda Yer Alacak";
$degiskenSeflink = $db->seflink($degisken); // Çıktı : icerik-basligi-bu-alanda-yer-alacak

#myOOPCrypto Oluşturma ve Çözme (Encode ve Decode);
$sifre = "1234567";
$sifreEncode = $db->encode($sifre);
$sifreDecode = $db->decode($sifre);

#Metnin belirli bir karakter sayısı kadar kesmek ve sonuna ... eklemek
$metin = "Lorem ipsum dolor sit amet, coansectetuer adipiscing elit";
$metinSinirla = $db->sinirla($metin,5); // Çıktı : Lorem

?>