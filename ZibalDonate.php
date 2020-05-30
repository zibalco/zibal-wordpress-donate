<?php
/*
Plugin Name: Zibal Donate - حمایت مالی 
Plugin URI: https://zibal.ir/
Description: افزونه حمایت مالی از وبسایت ها -- برای استفاده تنها کافی است کد زیر را درون بخشی از برگه یا نوشته خود قرار دهید  [ZibalDonate]
Version: 1.0
Author:  Yahya Kangi
Author URI: http://github.com/YahyaKng
*/

defined('ABSPATH') or die('Access denied!');
define ('ZibalDonateDIR', plugin_dir_path( __FILE__ ));
// define ('ZIBAL_DONATE_TABLE'  , 'zibal_donate');

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

function post_to_zibal($url, $data = false) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, "https://gateway.zibal.ir/".$url);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json; charset=utf-8'));
  curl_setopt($ch, CURLOPT_POST, 1);
  if ($data) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

  }
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
  $result = curl_exec($ch);
  curl_close($ch);
  return !empty($result) ? json_decode($result) : false;
}

if ( is_admin() )
{
        add_action('admin_menu', 'ZD_AdminMenuItem');
        function ZD_AdminMenuItem()
        {
				add_menu_page( 'تنظیمات افزونه حمایت مالی - زیبال', 'حمایت مالی زیبال', 'administrator', 'ZD_MenuItem', 'ZD_MainPageHTML', /*plugins_url( 'myplugin/images/icon.png' )*/'', 6 ); 
        }
}

function ZD_MainPageHTML()
{
	include('ZD_AdminPage.php');
}

function ZD_HamianHTML()
{
	include('ZD_Hamian.php');
}


add_action( 'init', 'ZibalDonateShortcode');
function ZibalDonateShortcode(){
	add_shortcode('ZibalDonate', 'ZibalDonateForm');
}

function ZibalDonateForm() {
  $out = '';
  $error = '';
  $message = '';
  
	$MerchantID = get_option( 'ZD_MerchantID');
  $ZD_IsOK = get_option( 'ZD_IsOK');
  $ZD_IsError = get_option( 'ZD_IsError');
  $ZD_Unit = get_option( 'ZD_Unit');
  
  $Amount = '';
  $Description = '';
  $Name = '';
  $Mobile = '';
  // $Email = '';
  
  //////////////////////////////////////////////////////////
  //            REQUEST
  if(isset($_POST['submit']) && $_POST['submit'] == 'پرداخت')
  {
    
    if($MerchantID == '')
    {
      $error = 'کد دروازه پرداخت وارد نشده است' . "<br>\r\n";
    }
    
    
    $Amount = filter_input(INPUT_POST, 'ZD_Amount', FILTER_SANITIZE_SPECIAL_CHARS);
    
    if(is_numeric($Amount) != false)
    {
      //Amount will be based on Rial  - Required
      if($ZD_Unit == 'تومان')
        $SendAmount =  $Amount * 10;
      else
        $SendAmount =  $Amount;
    }
    else
    {
      $error .= 'مبلغ به درستی وارد نشده است' . "<br>\r\n";
    }
    
    $Description =    filter_input(INPUT_POST, 'ZD_Description', FILTER_SANITIZE_SPECIAL_CHARS);  // Required
    $Name =           filter_input(INPUT_POST, 'ZD_Name', FILTER_SANITIZE_SPECIAL_CHARS);  // Required
    $Mobile =         filter_input(INPUT_POST, 'mobile', FILTER_SANITIZE_SPECIAL_CHARS); // Optional
    
    $SendDescription = $Name . ' | ' . $Mobile . ' | ' . ' | ' . $Description ;  
    
    if($error == '') // اگر خطایی نباشد
    {
      $CallbackURL = ZD_GetCallBackURL();  // Required
      
      $data = [
        'merchant' => $MerchantID,
        'amount' => $SendAmount,
        'description' => $SendDescription,
        'mobile' => $Mobile,
        'callbackUrl' => $CallbackURL,
      ];

      $result = post_to_zibal('v1/request', $data);
      $result = (array) $result;

      //Redirect to URL
      if($result['result'] == 100)
      {
        $Location = 'https://gateway.zibal.ir/start/'.$result['trackId'];
        
        return "<script>document.location = '${Location}'</script><center>در صورتی که به صورت خودکار به درگاه بانک منتقل نشدید <a href='${Location}'>اینجا</a> را کلیک کنید.</center>";
      } 
      else 
      {
        $error .= ZD_GetRequestResults($result['status']) . "<br>\r\n";
      }
    }
  }
  //// END REQUEST
  
  
  ////////////////////////////////////////////////////
  ///             RESPONSE
  if(isset($_GET['status']) && isset($_GET['trackId']))
  {    
    if($_GET['status'] == '2'){
      
      $verifyData = [
        'merchant' => $MerchantID,
        'trackId' => $_GET['trackId'],
      ];
      $verifyResult = post_to_zibal('v1/verify', $verifyData);
      $verifyResult = (array) $verifyResult;

      if( isset($verifyResult['result']) && $verifyResult['result'] == 100 )
      {
        $message .= get_option( 'ZD_IsOk') . "<br>\r\n";
        $message .= 'شناسه مرجع تراکنش:'. $verifyResult['refNumber'] . "<br>\r\n";
        
        $ZD_TotalAmount = get_option("ZD_TotalAmount");
        update_option("ZD_TotalAmount" , $ZD_TotalAmount + $verifyResult['amount']);
      }
      // elseif( isset($verifyResult['result']) && $verifyResult['result'] != 100)
      // {
      //   $error .= 'تراکنش تایید نشد' . "<br>\r\n";
      //   $error .= get_option( 'ZD_IsError') . "<br>\r\n";
      //   $error .= ZD_GetVerfityStatus($verifyResult['status']) . "<br>\r\n";
      // }
      elseif( isset($verifyResult['result']) && $verifyResult['result'] == 201 )
      {
        $error .= 'تراکنش قبلا تایید شده بود.' . "<br>\r\n";
        $error .= get_option( 'ZD_IsError') . "<br>\r\n";
        $error .= ZD_GetRequestResults($verifyResult['result']) . "<br>\r\n";
      }
      else 
      {
        $error .= 'تراکنش تایید نشد' . "<br>\r\n";
        $error .= get_option( 'ZD_IsError') . "<br>\r\n";
        $error .= ZD_GetVerfityStatus($verifyResult['status']) . "<br>\r\n";
      }
    } 
    else
    {
      $error .= 'تراکنش توسط کاربر بازگشت خورد';
    }
  }
  ///     END RESPONSE
  
  $style = '';
  
  if(get_option('ZD_UseCustomStyle') == 'true')
  {
    $style = get_option('ZD_CustomStyle');
  }
  else
  {
    $style = '#ZD_MainForm {  width: 400px;  height: auto;  margin: 0 auto;  direction: rtl; }  #ZD_Form {  width: 96%;  height: auto;  float: right;  padding: 10px 2%; }  #ZD_Message,#ZD_Error {  width: 90%;  margin-top: 10px;  margin-right: 2%;  float: right;  padding: 5px 2%;  border-right: 2px solid #006704;  background-color: #e7ffc5;  color: #00581f; }  #ZD_Error {  border-right: 2px solid #790000;  background-color: #ffc9c5;  color: #580a00; }  .ZD_FormItem {  width: 90%;  margin-top: 10px;  margin-right: 2%;  float: right;  padding: 5px 2%; }    .ZD_FormLabel {  width: 35%;  float: right;  padding: 3px 0; }  .ZD_ItemInput {  width: 64%;  float: left; }  .ZD_ItemInput input {  width: 90%;  float: right;  border-radius: 3px;  box-shadow: 0 0 2px #00c4ff;  border: 0px solid #c0fff0;  font-family: inherit;  font-size: inherit;  padding: 3px 5px; }  .ZD_ItemInput input:focus {  box-shadow: 0 0 4px #0099d1; }  .ZD_ItemInput input.error {  box-shadow: 0 0 4px #ef0d1e; }  input.ZD_Submit {  background: none repeat scroll 0 0 #2ea2cc;  border-color: #0074a2;  box-shadow: 0 1px 0 rgba(120, 200, 230, 0.5) inset, 0 1px 0 rgba(0, 0, 0, 0.15);  color: #fff;  text-decoration: none;  border-radius: 3px;  border-style: solid;  border-width: 1px;  box-sizing: border-box;  cursor: pointer;  display: inline-block;  font-size: 13px;  line-height: 26px;  margin: 0;  padding: 0 10px 1px;  margin: 10px auto;  width: 50%;  font: inherit;  float: right;  margin-right: 24%; }';
  }
  
  
	$out = '
  <style>
    '. $style . '
  </style>
      <div style="clear:both;width:100%;float:right;">
	        <div id="ZD_MainForm">
          <div id="ZD_Form">';
          
if($message != '')
{    
    $out .= "<div id=\"ZD_Message\">
    ${message}
            </div>";
}

if($error != '')
{    
    $out .= "<div id=\"ZD_Error\">
    ${error}
            </div>";
}

     $out .=      '<form method="post">
              <div class="ZD_FormItem">
                <label class="ZD_FormLabel">مبلغ :</label>
                <div class="ZD_ItemInput">
                  <input style="width:60%" type="text" name="ZD_Amount" value="'. $Amount .'" />
                  <span style="margin-right:10px;">'. $ZD_Unit .'</span>
                </div>
              </div>
              
              <div class="ZD_FormItem">
                <label class="ZD_FormLabel">نام و نام خانوادگی :</label>
                <div class="ZD_ItemInput"><input type="text" name="ZD_Name" value="'. $Name .'" /></div>
              </div>
              
              <div class="ZD_FormItem">
                <label class="ZD_FormLabel">تلفن همراه :</label>
                <div class="ZD_ItemInput"><input type="text" name="mobile" value="'. $Mobile .'" /></div>
              </div>
              
              <div class="ZD_FormItem">
                <label class="ZD_FormLabel">توضیحات :</label>
                <div class="ZD_ItemInput"><input type="text" name="ZD_Description" value="'. $Description .'" /></div>
              </div>
              
              <div class="ZD_FormItem">
                <input type="submit" name="submit" value="پرداخت" class="ZD_Submit" />
              </div>
              
            </form>
          </div>
        </div>
      </div>
	';
  
  return $out;
}

/////////////////////////////////////////////////
// تنظیمات اولیه در هنگام اجرا شدن افزونه.
register_activation_hook(__FILE__,'ZibalDonate_install');
function ZibalDonate_install()
{
	ZD_CreateDatabaseTables();
}
function ZD_CreateDatabaseTables()
{
		global $wpdb;
		// Other Options
		add_option("ZD_TotalAmount", 0, '', 'yes');
		add_option("ZD_TotalPayment", 0, '', 'yes');
		add_option("ZD_IsOK", 'با تشکر پرداخت شما به درستی انجام شد.', '', 'yes');
		add_option("ZD_IsError", 'متاسفانه پرداخت انجام نشد.', '', 'yes');
    
    $style = '#ZD_MainForm {
  width: 400px;
  height: auto;
  margin: 0 auto;
  direction: rtl;
}

#ZD_Form {
  width: 96%;
  height: auto;
  float: right;
  padding: 10px 2%;
}

#ZD_Message,#ZD_Error {
  width: 90%;
  margin-top: 10px;
  margin-right: 2%;
  float: right;
  padding: 5px 2%;
  border-right: 2px solid #006704;
  background-color: #e7ffc5;
  color: #00581f;
}

#ZD_Error {
  border-right: 2px solid #790000;
  background-color: #ffc9c5;
  color: #580a00;
}

.ZD_FormItem {
  width: 90%;
  margin-top: 10px;
  margin-right: 2%;
  float: right;
  padding: 5px 2%;
}

.ZD_FormLabel {
  width: 35%;
  float: right;
  padding: 3px 0;
}

.ZD_ItemInput {
  width: 64%;
  float: left;
}

.ZD_ItemInput input {
  width: 90%;
  float: right;
  border-radius: 3px;
  box-shadow: 0 0 2px #00c4ff;
  border: 0px solid #c0fff0;
  font-family: inherit;
  font-size: inherit;
  padding: 3px 5px;
}

.ZD_ItemInput input:focus {
  box-shadow: 0 0 4px #0099d1;
}

.ZD_ItemInput input.error {
  box-shadow: 0 0 4px #ef0d1e;
}

input.ZD_Submit {
  background: none repeat scroll 0 0 #2ea2cc;
  border-color: #0074a2;
  box-shadow: 0 1px 0 rgba(120, 200, 230, 0.5) inset, 0 1px 0 rgba(0, 0, 0, 0.15);
  color: #fff;
  text-decoration: none;
  border-radius: 3px;
  border-style: solid;
  border-width: 1px;
  box-sizing: border-box;
  cursor: pointer;
  display: inline-block;
  font-size: 13px;
  line-height: 26px;
  margin: 0;
  padding: 0 10px 1px;
  margin: 10px auto;
  width: 50%;
  font: inherit;
  float: right;
  margin-right: 24%;
}';
  add_option("ZD_CustomStyle", $style, '', 'yes');
  add_option("ZD_UseCustomStyle", 'false', '', 'yes');
}

function ZD_GetRequestResults($resultNumber)
{
  switch($resultNumber)
  {
    case 100:
      return 'با موفقیت تایید شد.';
    case 102:
      return 'merchant یافت نشد.';
    case 103:
      return 'merchant غیرفعال';
    case 104:
      return 'merchant نامعتبر';
    case 201:
      return 'قبلا تایید شده.';
    case 105:
      return 'amount بایستی بزرگتر از 1,000 ریال باشد.';
    case 106:
      return '‌callbackUrl نامعتبر می‌باشد. (شروع با http و یا https)';
    case 113:
      return 'amount مبلغ تراکنش از سقف میزان تراکنش بیشتر است.';
  }
  
  return '';
}

function ZD_GetVerfityStatus($statusNumber)
{
  switch($statusNumber)
  {
    case 100:
      return 'با موفقیت تایید شد.';
    case 102:
      return 'merchant یافت نشد.';
    case 103:
      return 'merchant غیرفعال';
    case 104:
      return 'merchant نامعتبر';
    case 201:
      return 'قبلا تایید شده.';
    case 105:
      return 'amount بایستی بزرگتر از 1,000 ریال باشد.';
    case 106:
      return '‌callbackUrl نامعتبر می‌باشد. (شروع با http و یا https)';
    case 113:
      return 'amount مبلغ تراکنش از سقف میزان تراکنش بیشتر است.';
  }
  
  return '';
}

function ZD_GetCallBackURL()
{
  $pageURL = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
  
  $ServerName = htmlspecialchars($_SERVER["SERVER_NAME"], ENT_QUOTES, "utf-8");
  $ServerPort = htmlspecialchars($_SERVER["SERVER_PORT"], ENT_QUOTES, "utf-8");
  $ServerRequestUri = htmlspecialchars($_SERVER["REQUEST_URI"], ENT_QUOTES, "utf-8");
  
  if ($_SERVER["SERVER_PORT"] != "80")
  {
      $pageURL .= $ServerName .":". $ServerPort . $_SERVER["REQUEST_URI"];
  } 
  else 
  {
      $pageURL .= $ServerName . $ServerRequestUri;
  }
  return $pageURL;
}

?>
