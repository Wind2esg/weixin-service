<!-- 
/**
 * js_sdk_service.php
 * 
 * @author wind2esg
 * @date 20191017
 * 
 * ez centre control service as open api for wechat js sdk combo.
 * 
 * wechat official js sdk doc
 * https://developers.weixin.qq.com/doc/offiaccount/OA_Web_Apps/JS-SDK
 *
 * add your own access control and appId and appSecret
 * even manipulate how maintain the access token and js api ticket
 */ 
 -->
<?php 
// as open api, set for CORS and CORB 
header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Methods: POST,OPTIONS');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
header('Content-Type:application/json;charset=UTF-8;');

class JsSdkHelper {
  private $appId;
  private $appSecret;

  // init with your appId and appSecret
  public function __construct($appId, $appSecret) {
    $this->appId = $appId;
    $this->appSecret = $appSecret;
  }

  private function httpGet($url) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 500);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_URL, $url);

    $res = curl_exec($curl);
    curl_close($curl);

    return $res;
  }
  
  // maintain access token
  private function getAccessToken() {
    $data = json_decode(file_get_contents("access_token.json"));
    if ($data->expire_time < time()) {
      $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$this->appId&secret=$this->appSecret";
      $res = json_decode($this->httpGet($url));
      $access_token = $res->access_token;
      if ($access_token) {
        $data->expire_time = time() + 7000;
        $data->access_token = $access_token;
        $fp = fopen("access_token.json", "w");
        fwrite($fp, json_encode($data));
        fclose($fp);
      }
    } else {
      $access_token = $data->access_token;
    }
    return $access_token;
  }

  // maintain jsapi ticket
  private function getJsApiTicket() {
    $data = json_decode(file_get_contents("jsapi_ticket.json"));
    if ($data->expire_time < time()) {
      $accessToken = $this->getAccessToken();
      $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken";
      $res = json_decode($this->httpGet($url));
      $ticket = $res->ticket;
      if ($ticket) {
        $data->expire_time = time() + 7000;
        $data->jsapi_ticket = $ticket;
        $fp = fopen("jsapi_ticket.json", "w");
        fwrite($fp, json_encode($data));
        fclose($fp);
      }
    } else {
      $ticket = $data->jsapi_ticket;
    }

    return $ticket;
  }

  private function createNonceStr($length = 16) {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $str = "";
    for ($i = 0; $i < $length; $i++) {
      $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
    }
    return $str;
  }

  public function getPackage($request_url) {
    $jsapiTicket = $this->getJsApiTicket();
    $url = $request_url;
    $timestamp = time();
    $nonceStr = $this->createNonceStr();

	// notice the order
    $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
    
    $signature = sha1($string);
    $package = array(
      "appId"     => $this->appId,
      "nonceStr"  => $nonceStr,
      "timestamp" => $timestamp,
      "url"       => $url,
      "signature" => $signature,
      "rawString" => $string
    );
    return $package; 
  }
}

// TODO block

// stringquery and json
$reqData = isset($_POST)? $_POST : json_decode(file_get_contents("php://input"),true);

function serviceAccessCheck(){
  // depend on the param sent by request
  // check if the request is allowed access the service
  // return ture/false
}
$check = serviceAccessCheck();

if($check){
  $jsSdkHelper = new JsSdkHelper('<appId>', '<appSecret>');
  $package = $jsSdkHelper->getPackage($reqData["url"]);
  echo json_encode($package);
}else{
  echo "you have no access";
}

?>