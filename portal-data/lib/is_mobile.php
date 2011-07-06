<?php

/* is_mobile()
 * Shitty mobile device detection using the user agent.
 * 
 * Copyright (C) 2006-2011 Scott Zeid
 * https://github.com/scottywz/portal
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 * 
 * Except as contained in this notice, the name(s) of the above copyright holders
 * shall not be used in advertising or otherwise to promote the sale, use or
 * other dealings in this Software without prior written authorization.
 */

/**
 * Determine whether the user agent represents a mobile device.
 * 
 * The user can use the following query string parameters to override this
 * function's output:
 *   * !mobile, nomobile - Cause this function to return False.
 *   * mobile - Cause this function to return True.
 *   * mobile=[...], device=[...] - Override the device type.
 * 
 * device=[...] takes precedence over mobile=[...].
 * 
 * Valid device types are android, webos, unknown, apple, and apple-tablet.
 * iPads are not considered to be mobile devices, but this function will still
 * return "apple-tablet" as the device name.  Support for Android and webOS
 * tablets may be added in the future; the device name will be "android-tablet"
 * or "webos-tablet".
 * 
 * @param bool $return_device Return a string representing the type of device.
 * @param bool $use_get Allow overriding default behavior using query strings.
 * @return mixed If $return_device is false, returns a boolean value.
 */
function is_mobile($return_device = False, $use_get = True) {
 # config
 $user_agent = $_SERVER["HTTP_USER_AGENT"];
 $nomobile = False; $forcemobile = False; $forcedevice = "";
 if ($use_get) {
  if (isset($_GET["!mobile"]) || isset($_GET["nomobile"]))
   $nomobile = True;
  elseif (isset($_GET["mobile"])) {
   $forcedevice = strtolower($_GET["mobile"]);
   if (!stristr($forcedevice, "tablet") && $forcedevice != "ipad")
    $forcemobile = True;
   if (!$forcedevice) $forcedevice = "unknown";
  }
  if (!empty($_GET["device"])) {
   $forcedevice = strtolower($_GET["device"]);
   if (!stristr($forcedevice, "tablet") && $forcedevice != "ipad") {
    $forcemobile = True;
    $nomobile = False;
   }
  }
 }
 # is mobile device?
 if (((
    stristr($user_agent, "iPhone") || stristr($user_agent, "iPod") ||
    stristr($user_agent, "Android") || stristr($user_agent, "webOS")
   ) && $nomobile == False) || $forcemobile == True)
  $mobile = True;
 else
  $mobile = False;
 # which mobile device
 $device = "unknown";
 if (stristr($user_agent, "iPhone") || stristr($user_agent, "iPod"))
  $device = "apple";
 if (stristr($user_agent, "iPad")) $device = "apple-tablet";
 if (stristr($user_agent, "Android")) $device = "android";
 if (stristr($user_agent, "webOS")) $device = "webos";
 if ($forcedevice != "") $device = $forcedevice;
 if ($forcedevice == "iphone" || $forcedevice == "ipod") $device = "apple";
 if (stristr($forcedevice, "ipad")) $device = "apple-tablet";
 if (((!$mobile && !$forcemobile) || $nomobile || $forcedevice === "") &&
     !stristr($device, "tablet"))
  $device = "";
 # return value
 if ($return_device == False) return $mobile;
 else return $device;
}

?>
