<?php 

defined('ABSPATH') or die('Access denied!');

if ( $_POST ) {
	
	if ( isset($_POST['ZD_MerchantID']) ) {
		update_option( 'ZD_MerchantID', $_POST['ZD_MerchantID'] );
	}
	
	if ( isset($_POST['ZD_IsOK']) ) {
		update_option( 'ZD_IsOK', $_POST['ZD_IsOK'] );
	}
  
	if ( isset($_POST['ZD_IsError']) ) {
		update_option( 'ZD_IsError', $_POST['ZD_IsError'] );
	}
	
  if ( isset($_POST['ZD_Unit']) ) {
		update_option( 'ZD_Unit', $_POST['ZD_Unit'] );
	}
  
  if ( isset($_POST['ZD_UseCustomStyle']) ) {
		update_option( 'ZD_UseCustomStyle', 'true' );
    
    if ( isset($_POST['ZD_CustomStyle']) )
    {
      update_option( 'ZD_CustomStyle', strip_tags($_POST['ZD_CustomStyle']) );
    }
    
	}
  else
  {
    update_option( 'ZD_UseCustomStyle', 'false' );
  }
  
	echo '<div class="updated" id="message"><p><strong>تنظیمات ذخیره شد</strong>.</p></div>';
	
}
?>
<h2 id="add-new-user">تنظیمات افزونه حمایت مالی - زیبال</h2>
<h2 id="add-new-user">جمع تمام پرداخت ها : <?php echo get_option("ZD_TotalAmount"); ?>  ریال</h2>
<form method="post">
  <table class="form-table">
    <tbody>
      <tr class="user-first-name-wrap">
        <th><label for="ZD_MerchantID">کد درگاه پرداخت (مرچنت)</label></th>
        <td>
          <input type="text" class="regular-text" value="<?php echo get_option( 'ZD_MerchantID'); ?>" id="ZD_MerchantID" name="ZD_MerchantID">
          <p class="description indicator-hint">جهت تست میتوانید از zibal برای کد درگاه پرداخت استفاده نمایید.</p>
        </td>
      </tr>
      <tr>
        <th><label for="ZD_IsOK">پرداخت صحیح</label></th>
        <td><input type="text" class="regular-text" value="<?php echo get_option( 'ZD_IsOK'); ?>" id="ZD_IsOK" name="ZD_IsOK"></td>
      </tr>
      <tr>
        <th><label for="ZD_IsError">خطا در پرداخت</label></th>
        <td><input type="text" class="regular-text" value="<?php echo get_option( 'ZD_IsError'); ?>" id="ZD_IsError" name="ZD_IsError"></td>
      </tr>
      
      <tr class="user-display-name-wrap">
        <th><label for="ZD_Unit">واحد پول</label></th>
        <td>
          <?php $ZD_Unit = get_option( 'ZD_Unit'); ?>
          <select id="ZD_Unit" name="ZD_Unit">
            <option <?php if($ZD_Unit == 'تومان' ) echo 'selected="selected"' ?>>تومان</option>
            <option <?php if($ZD_Unit == 'ریال' ) echo 'selected="selected"' ?>>ریال</option>
          </select>
        </td>
      </tr>
      
      <tr class="user-display-name-wrap">
        <th>استفاده از استایل سفارشی</th>
        <td>
          <?php $ZD_UseCustomStyle = get_option('ZD_UseCustomStyle') == 'true' ? 'checked="checked"' : ''; ?>
          <input type="checkbox" name="ZD_UseCustomStyle" id="ZD_UseCustomStyle" value="true" <?php echo $ZD_UseCustomStyle ?> /><label for="ZD_UseCustomStyle">استفاده از استایل سفارشی برای فرم</label><br>
        </td>
      </tr>
      
      
      <tr class="user-display-name-wrap" id="ZD_CustomStyleBox" <?php if(get_option('ZD_UseCustomStyle') != 'true') echo 'style="display:none"'; ?>>
        <th>استایل سفارشی</th>
        <td>
          <textarea style="width: 90%;min-height: 400px;direction:ltr;" name="ZD_CustomStyle" id="ZD_CustomStyle"><?php echo get_option('ZD_CustomStyle') ?></textarea><br>
        </td>
      </tr>
      
    </tbody>
  </table>
  <p class="submit"><input type="submit" value="به روز رسانی تنظیمات" class="button button-primary" id="submit" name="submit"></p>
</form>

<script>
  if(typeof jQuery == 'function')
  {
    jQuery("#ZD_UseCustomStyle").change(function(){
      if(jQuery("#ZD_UseCustomStyle").prop('checked') == true)
        jQuery("#ZD_CustomStyleBox").show(500);
      else
        jQuery("#ZD_CustomStyleBox").hide(500);
    });
  }
</script>

