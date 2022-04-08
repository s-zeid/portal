<?php const PORTAL_COPYRIGHT_YEARS = [2006, 2022];
 
/* Portal                                                                   {{{1
 * 
 * Copyright (C) 2006-2022 S. Zeid
 * https://code.s.zeid.me/portal
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

// portal-data path and $debug  {{{1

/* Relative path to the settings folder; default is "portal-data".
 * This should be the same on both the filesystem and in URLs.
 * Use "." for the current directory.
 */
if (!isset($CONFIG_DIR))
 $CONFIG_DIR = "portal-data";

// Set to True to get the $portal array when visiting the portal
// Use only for debugging purposes.
$debug = False;

////////////////////////////////////////////////////////////////////////////////

// Setup  {{{1

// Workaround for templates raising fatal errors in PHP >= 5.4.0 when
// date.timezone is not set.  If that is the case, then this line will
// raise a warning.
date_default_timezone_set(date_default_timezone_get());

require("../lib/spyc.php");
require("../lib/templum_php5.php");
require("../src/is_mobile.php");
require("../src/qmark_icon.php");

// Configuration loading and sanitation  {{{1
$portal = spyc_load(
           str_replace("\r\n", "\n", str_replace("\r", "\n", file_get_contents(
            "$CONFIG_DIR/settings.yaml"
           )))
          );
$portal["CONFIG_DIR"] = $CONFIG_DIR;
$name = $portal["name"] = $portal["name"];
$theme = $portal["theme"] = $portal["theme"];
if (!isset($portal["banner"]))
 $portal["banner"] = array("type" => "text", "content" => $name);
$portal["banner"]["type"] = strtolower($portal["banner"]["type"]);
if (!in_array($portal["banner"]["type"], array("text", "image", "none")))
 $portal["banner"]["type"] = "text";
$use_templum_for_banner_content = isset($portal["banner"]["content"]) &&
                                  $portal["banner"]["type"] != "none";

$openid_enabled = !empty($portal["openid"]["xrds"]) &&
                  ((!empty($portal["openid"]["provider"]) &&
                    !empty($portal["openid"]["local_id"])) ||
                   (!empty($portal["openid"]["server"]) &&
                    !empty($portal["openid"]["delegate"])));

$ga_enabled = !empty($portal["google-analytics"]["account"]) &&
              !empty($portal["google-analytics"]["style"]) &&
              in_array($portal["google-analytics"]["style"],array("new","old"));

$request_uri = (!empty($_SERVER["REQUEST_URI"])) ? $_SERVER["REQUEST_URI"] : "";
$url_scheme = ((!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] != "off")
               || (!empty($_SERVER["HTTP_X_FORWARDED_PROTO"])
                   && $_SERVER["HTTP_X_FORWARDED_PROTO"] == "https")
               || (!empty($_SERVER["HTTP_X_FORWARDED_PROTOCOL"])
                   && $_SERVER["HTTP_X_FORWARDED_PROTOCOL"] == "https")
               || (!empty($_SERVER["HTTP_X_FORWARDED_SSL"])
                   && $_SERVER["HTTP_X_FORWARDED_SSL"] == "on") 
               || (!empty($_SERVER["HTTP_FRONT_END_HTTPS"])
                   && $_SERVER["HTTP_FRONT_END_HTTPS"] == "on")) ? 
              "https" : "http";
if (!empty($portal["url-root"]))
 $url_root = $portal["url-root"] = rtrim($portal["url-root"], "/");
else {
 $url_root = "$url_scheme://{$_SERVER["HTTP_HOST"]}";
 $url_root .= implode("/",explode("/", $_SERVER["PHP_SELF"], -1));
 $portal["url-root"] = $url_root;
}

// Mobile device detection  {{{1
$mobile = is_mobile(False, True);
$device = is_mobile(True, True);

// Template namespace  {{{1
$namespace = array();
$names = explode(",", "CONFIG_DIR,device,ga_enabled,mobile,name,"
          ."openid_enabled,portal,request_uri,url_scheme");
foreach ($names as $n) {
 $namespace[$n] = &$$n;
}
$namespace["__private"] = array(
 "portal_copyright_years" => portal_copyright_years(),
);

// Debug output  {{{1
if ($debug) {
 header("Content-type: text/plain");
 print_r($portal);
 exit();
}

// JSON output  {{{1
if (isset($_GET["json"])) {
 // Update namespace
 $names = explode(",", "_403,_404,action,highlight,minibar,narrow,orientation,"
           ."request_uri,small,target,theme,url_root");
 foreach ($names as $n) {
  $namespace[$n] = &$$n;
 }
 if ($use_templum_for_banner_content)
  $portal["banner"]["content"] = tpl($portal["banner"]["content"], $namespace);
 if (is_array($portal["sites"])) {
  foreach ($portal["sites"] as $slug => &$site) {
   $keys = array("name", "icon", "url", "desc");
   foreach ($keys as $key) {
    if (!empty($site[$key])) {
     $site[$key] = $v = tpl($site[$key], $namespace);
     if ($key == "url") {
      if (strpos($v, "/") === 0 && strpos($v, "//") !== 0)
       $site[$key] = $v = "$url_scheme://{$_SERVER["HTTP_HOST"]}/$v";
     }
     if ($key == "icon") {
      if (preg_match("/(((http|ftp)s|file|data)?\:|\/\/)/i", $v))
       $site[$key] = $v = array("large" => $v, "small" => $v);
      else if (strpos($v, "/") === 0) {
       $v = "$url_scheme://{$_SERVER["HTTP_HOST"]}/$v";
       $site[$key] = $v = array("large" => $v, "small" => $v);
      } else {
       $site[$key] = $v = array(
        "large" => $url_root."/$CONFIG_DIR/icons/".$v,
        "small" => $url_root."/$CONFIG_DIR/icons/small/".$v
       );
      }
     }
    }
   }
  }
 }
 header("Content-Type: application/json; charset=utf-8");
 $data = array(
  "name"       => $portal["name"],
  "url"        => $portal["url"],
  "url-root"   => $portal["url-root"],
  "config-dir" => $CONFIG_DIR,
  "banner"     => $portal["banner"],
  "sites"      => $portal["sites"]
 );
 if (defined("JSON_PRETTY_PRINT") && defined("JSON_UNESCAPED_SLASHES"))
  echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
 else
  echo json_encode($data);
} // JSON output

// HTML output {{{1
else if (!isset($_GET["css"]) || !trim($_GET["css"]) != "") {
 
 $action = "index";
 if (isset($_GET["minibar"])) {
  $minibar = True;
  $action = "minibar";
  $highlight = (!empty($_GET["highlight"])) ? $_GET["highlight"] : "";
  $orientation = $portal["minibar-orientation"];
  if (!isset($_GET["horizontal"]) || !isset($_GET["vertical"])) {
   if (isset($_GET["horizontal"])) $orientation = "horizontal";
   elseif (isset($_GET["vertical"])) $orientation = "vertical";
  }
 } else
 $minibar = False;
 
 $target = (!empty($_GET["target"])) ? $_GET["target"] : $portal["link-target"];
 $theme = (!empty($_GET["theme"])) ? $_GET["theme"] : $theme;
 $narrow = (isset($_GET["narrow"])) ? True : $portal["narrow"];
 if (isset($_GET["!narrow"]) || isset($_GET["wide"])) $narrow = False;
 $small = (isset($_GET["small"])) ? True : $portal["small"];
 if (isset($_GET["!small"]) || isset($_GET["large"]) || isset($_GET["big"]))
  $small = False;
 $_403 = isset($_GET["403"]);
 $_404 = isset($_GET["404"]);
 if ($_403 || $_404) {
  $action = "error";
  $request_uri = $portal["url"];
  if      ($_403) header("HTTP/1.0 403 Forbidden");
  else if ($_404) header("HTTP/1.0 404 Not Found");
 }
 
 // Update namespace
 $names = explode(",", "_403,_404,action,highlight,minibar,narrow,orientation,"
           ."request_uri,small,target,theme,url_root");
 foreach ($names as $n) {
  $namespace[$n] = &$$n;
 }
 
 // Template expansion for config values
 if ($use_templum_for_banner_content)
  $portal["banner"]["content"] = tpl($portal["banner"]["content"], $namespace);
 if (isset($portal["custom-head-content"]))
  $portal["custom-head-content"] = tpl($portal["custom-head-content"],
                                       $namespace);
 else
  $portal["custom-head-content"] = "";
 if (isset($portal["custom-footer-content"]))
  $portal["custom-footer-content"] = tpl($portal["custom-footer-content"],
                                         $namespace);
 else
  $portal["custom-footer-content"] = "";
 
 $any_icons = false;
 if (is_array($portal["sites"])) {
  foreach ($portal["sites"] as $slug => &$site) {
   $keys = array("name", "icon", "url", "desc");
   if (!empty($site["icon"]))
    $any_icons = true;
   foreach ($keys as $key) {
    if (!empty($site[$key]))
     $site[$key] = tpl($site[$key], $namespace);
   }
  }
 }
 
 $div_body_classes = "";
 if ($narrow)
  $div_body_classes .= " narrow";
 if ($small)
  $div_body_classes .= " small";
 if ($any_icons)
  $div_body_classes .= " any-icons";
 $div_body_classes = trim($div_body_classes);
 
 // Update namespace
 $names = explode(",", "any_icons,div_body_classes");
 foreach ($names as $n) {
  $namespace[$n] = &$$n;
 }
 
 // Yadis XRDS header; needs to be sent as a proper header instead of a meta
 // tag in order to validate as HTML5
 if ($openid_enabled)
  header("X-XRDS-Location: ".rawurlencode($portal["openid"]["xrds"]));
 
 // HTML template  {{{1
 echo htmlsymbols(tpl(<<<HTML
<!DOCTYPE html>

<html>
 <head>
  <meta charset="utf-8" />
  <!--
  
   Portal
   
   Copyright (C) {{\$__private["portal_copyright_years"]}} S. Zeid
   https://code.s.zeid.me/portal
   
   Permission is hereby granted, free of charge, to any person obtaining a copy
   of this software and associated documentation files (the "Software"), to deal
   in the Software without restriction, including without limitation the rights
   to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
   copies of the Software, and to permit persons to whom the Software is
   furnished to do so, subject to the following conditions:
   
   The above copyright notice and this permission notice shall be included in
   all copies or substantial portions of the Software.
   
   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
   IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
   FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
   AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
   LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
   OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
   THE SOFTWARE.
   
   Except as contained in this notice, the name(s) of the above copyright holders
   shall not be used in advertising or otherwise to promote the sale, use or
   other dealings in this Software without prior written authorization.
  
  -->
  <title>{{\$portal["name"]}}</title>
@if (file_exists("\$CONFIG_DIR/favicon.png")):
  <link rel="shortcut icon" type="image/png" href="{{\$CONFIG_DIR}}/favicon.png" />
@endif
@if (\$_403 || \$_404):
  <base href="{{\$url_root}}/" />
@endif
@if (\$openid_enabled):
@ /* OpenID */
  <!--openid-->
@if (!empty(\$portal["openid"]["provider"])):
   <link rel="openid2.provider" href="{{\$portal["openid"]["provider"]}}" />
@endif
@if (!empty(\$portal["openid"]["local_id"])):
   <link rel="openid2.local_id" href="{{\$portal["openid"]["local_id"]}}" />
@endif
@if (!empty(\$portal["openid"]["server"])):
   <link rel="openid.server" href="{{\$portal["openid"]["server"]}}" />
@endif
@if (!empty(\$portal["openid"]["delegate"])):
   <link rel="openid.delegate" href="{{\$portal["openid"]["delegate"]}}" />
@endif
  <!--/openid-->
@endif // OpenID
  <meta name="generator" content="Portal by S. Zeid; X11 License; https://code.s.zeid.me/portal" />
  <link rel="stylesheet" type="text/css" href="{{\$url_scheme}}://fonts.googleapis.com/css?family=Ubuntu:regular,italic,bold,bolditalic" />
  <link rel="stylesheet" type="text/css" href="?css={{\$theme}}&amp;.css" />
@if (\$mobile):
  <meta name="viewport" content="width=532; initial-scale=0.6; minimum-scale: 0.6" />
@endif
@if (\$ga_enabled && \$portal["google-analytics"]["style"] == "new"):
@ /* Google Analytics - New style */
  <script type="text/javascript">
   var _gaq = _gaq || [];
   _gaq.push(['_setAccount', '{{\$portal["google-analytics"]["account"]}}']);
   _gaq.push(['_trackPageview']);
   (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
   })();
  </script>
@endif // Google Analytics - New style
[[if (\$portal["custom-head-content"])
   echo indent(htmlsymbols(trim(\$portal["custom-head-content"], "\r\n")), 2)."\n";]]
 </head>
 <body id="action_{{\$action}}"[[if (\$mobile || \$device) {
]] class="[[if (\$mobile) echo "mobile "; if (\$device) echo "device_\$device";]]"[[
}]]>
@if (\$minibar):
@ /* Minibar */
  <div id="minibar" class="{{\$orientation}}">
   [[

/* Minibar site list */
foreach (\$portal["sites"] as \$slug => &\$site) {
 if ((!isset(\$site["minibar"]) || \$site["minibar"] !== False) &&
     !empty(\$site["url"])) {
  \$code = "";
  if (\$orientation == "vertical") \$code .= "<div>";
  // Link
  \$code .= "<a href=\"".htmlentitiesu8(\$site["url"], True)."\" target=\"_blank\"";
  // Highlight
  if (\$highlight == \$slug) \$code .= ' class="highlight"';
  // Site name
  if (!empty(\$site["name"])) {
   \$name = str_replace("\n", " ", htmlentitiesu8(strip_tags(\$site["name"]), False));
   \$name = str_replace("&amp;", "&", \$name);
  } else {
   \$name = htmlentitiesu8(\$site["url"], True);
  }
  \$code .= " title=\"".\$name;
  // Site description
  if (isset(\$site["desc"]) && trim(\$site["desc"])) {
   \$desc = str_replace("\n", "&#x0a;",
                        htmlentitiesu8(strip_tags(\$site["desc"]), False));
   \$desc = str_replace("&amp;", "&", \$desc);
   \$code .= " &mdash; ".\$desc;
  }
  // Icon
  if (!empty(\$site["icon"])) {
   \$icon_url = htmlentitiesu8(\$site["icon"], True);
   if (preg_match("/(((http|ftp)s|file|data)?\:|\/\/)/i", \$site["icon"]))
    \$icon_url = \$icon_url;
   else if (strpos(\$site["icon"], "/") === 0)
    \$icon_url = "\$url_scheme://{\$_SERVER["HTTP_HOST"]}/\$icon_url";
   else
    \$icon_url = "\$CONFIG_DIR/icons/small/\$icon_url";
   \$code .= "\"><img src=\"\$icon_url\" alt=\"Icon\" /></a>";
  } else {
   \$icon_url = htmlentitiesu8("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVQI12NgAAIAAAUAAeImBZsAAAAASUVORK5CYII=", True);
   \$code .= "\"><img src=\"\$icon_url\" class=\"empty\" alt=\"Icon\" /></a>";
  }
  if (\$orientation == "vertical") \$code .= "</div>";
  echo \$code;
 }
}

]]

  </div>
@//Minibar
@else:
@ /* Normal mode */
@if (\$portal["banner"]["type"] != "none"):
@ /* Banner */
  <div id="header" class="{{\$portal["banner"]["type"]}}[[if (\$small) echo ' small';]]">
   <h1 id="title">
    <span>
     <a href="{{\$request_uri}}">
@if (\$portal["banner"]["type"] == "image"):
@ /* Image banner */
      <img src="[[echo htmlentitiesu8((!empty(\$portal["banner"]["content"]))
                        ? \$portal["banner"]["content"]
                        : "\$CONFIG_DIR/images/banner.png", True
                       );]]" alt="{{\$name}}" />
@else: // Image banner
@ /* Text banner */
[[echo indent(htmlsymbols((!empty(\$portal["banner"]["content"]))
               ? \$portal["banner"]["content"] : \$name), 6)."\n";]]
@endif // Text banner
     </a>
    </span>
   </h1>
  </div>
@endif // Banner
  <div id="body"[[if (\$div_body_classes) {
]] class="[[echo "\$div_body_classes";]]"[[
}]]>
@if (\$_403):
   <p>You don't have permission to view this page.</p>
@elseif (\$_404):
   <p>The page you were looking for was not found.</p>
@else:
[[

/* Normal site list */
foreach (\$portal["sites"] as \$slug => &\$site) {
 if (!isset(\$site["index"]) || \$site["index"] !== False) {
  \$code = "";
  \$code .= "<p class=\"site";
  if (!empty(\$site["url"])) \$code .= " has-url";
  if (empty(\$site["name"])) \$code .= " no-name";
  if (empty(\$site["icon"])) \$code .= " no-icon";
  \$code .= "\">\n";
  // Link
  if (!empty(\$site["url"])) {
   \$code .= " <a href=\"".htmlentitiesu8(\$site["url"], True)."\"";
  // Link target
  if (\$target) \$code .= " target=\"\$target\"";
  \$code .= ">\n";
  } else
   \$code .= " <span>\n";
  // Image
  if (!empty(\$site["icon"])) {
   \$icon_url = htmlentitiesu8(\$site["icon"], True);
   if (preg_match("/(((http|ftp)s|file|data)?\:|\/\/)/i", \$site["icon"]))
    \$icon_url = \$icon_url;
   else if (strpos(\$site["icon"], "/") === 0)
    \$icon_url = "\$url_scheme://{\$_SERVER["HTTP_HOST"]}/\$icon_url";
   else
    \$icon_url = "\$CONFIG_DIR/icons".((\$small)?"/small":"")."/\$icon_url";
   \$code .= "  <span><img src=\"\$icon_url\" alt=\" \" />";
  } else
   \$code .= "  <span>";
  // Site name
  if (isset(\$site["name"]) && trim(\$site["name"])) {
   \$code .= "<strong class=\"name\">".htmlsymbols(\$site["name"])."</strong>";
  }
  \$code .= "</span>";
  // Site description
  if (isset(\$site["desc"]) && trim(\$site["desc"])) {
   \$code .= "<br />\n  <span class=\"desc\">";
   \$code .= str_replace("\n", "&#x0a;", htmlsymbols(\$site["desc"]))."</span>";
  }
  // Close stuff
  \$code .= "\n ".((!empty(\$site["url"])) ? "</a>" : "</span>")."\n</p>";
  echo indent(\$code, 3);
  echo "\n";
 }
}

]]
@endif
  </div>
  <div id="footer" class="footer[[if (\$small) echo " small";]]">
   <p>
    <a href="https://code.s.zeid.me/portal">Portal software</a>
    copyright &copy; [[echo portal_copyright_years("&ndash;");]] <a href="https://s.zeid.me/">S. Zeid</a>.
   </p>
[[if (\$portal["custom-footer-content"])
   echo indent(htmlsymbols(trim(\$portal["custom-footer-content"], "\r\n")), 3)."\n";]]
@if (\$portal["show-validator-links"]):
@ /* W3C Validator links */
   <p>
    <a href="http://validator.w3.org/check?uri=referer">
     <img src="{{\$CONFIG_DIR}}/images/html5.png" alt="Valid HTML5" class="button_80x15" />
    </a>&nbsp;
    <a href="http://jigsaw.w3.org/css-validator/check/referer?profile=css3">
     <img src="{{\$CONFIG_DIR}}/images/css.png" alt="Valid CSS" class="button_80x15" />
    </a>
   </p>
@endif // W3C Validator links
  </div>
@if (\$ga_enabled && \$portal["google-analytics"]["style"] != "new"):
@ /* Google Analytics - Old style */
  <script type="text/javascript">
   var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
   document.write(unescape("%3Cscript src='" + gaJsHost + "google-analytics.com/ga.js' type='text/javascript'%3E%3C/script%3E"));
  </script>
  <script type="text/javascript">
   try{
    var pageTracker = _gat._getTracker("{{\$portal["google-analytics"]["account"]}}");
    pageTracker._trackPageview();
   } catch(err) {}
  </script>
@endif // Google Analytics - Old style
@endif // Normal link listing
 </body>
</html>
HTML
, $namespace));

} // HTML template and output

// CSS output  {{{1
else {
 $theme = $portal["themes"][$_GET["css"]];
 $custom_css = (isset($theme["custom_css"])) ? $theme["custom_css"] : "";
 $theme = tpl_r($theme);

 // Update namespace
 $names = explode(",", "theme");
 foreach ($names as $n) {
  $namespace[$n] = &$$n;
 }
 
 header("Content-type: text/css");

 // CSS template  {{{1
 echo tpl(<<<CSS
/* Portal
 * 
 * Copyright (C) {{\$__private["portal_copyright_years"]}} S. Zeid
 * https://code.s.zeid.me/portal
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

body {
 margin: 0px;
 background: {{\$theme["bg"]}};
 font-family: "Ubuntu", "DejaVu Sans", "Bitstream Vera Sans", "Verdana",
              sans-serif;
 font-size: 1em;
 text-align: center;
 color: {{\$theme["fg"][0]}};
}
:focus {
 outline: none;
}
a {
 color: {{\$theme["fg"][1]}}; text-decoration: none;
}
a:hover, .site.has-url:hover * {
 color: {{\$theme["fg"][2]}};
}
a:active, .site.has-url:active * {
 color: {{\$theme["fg"][3]}};
}
h1, .h1 {
 font-size: 2.5em;
 font-weight: normal;
}
h2, .h2, .name {
 font-size: 1.5em;
 font-weight: normal;
}
img {
 border-style: none;
}
.monospace {
 font-family: "Courier New", "Courier", monospace;
}
.small {
 font-size: .6em;
}

#header {
 margin: 1em;
}
 #header.text #title span {
  background: {{\$theme["logo_bg"]}};
@if (stripos(\$theme["logo_bg"], "transparent") === False):
  border: 1px solid {{\$theme["logo_border"]}};
@endif
  padding: .2em;
 }
 #header a {
  color: {{\$theme["fg"][0]}};
 }
#body {
 width: 500px;
 margin: 1em auto;
}
#body.narrow {
 width: 250px;
}
#body.small {
 width: 312px;
 margin: 0.5em auto;
}
#body.narrow.small {
 width: 156px;
}
 .site {
  margin-top: 1em; margin-bottom: 1em;
  text-align: left;
  background: {{\$theme["link_bg"]}};
 }
 .site.has-url:hover, #minibar a:hover {
  background: {{\$theme["link_bg_h"]}};
 }
 .site.has-url:active, #minibar a:active {
  background: {{\$theme["link_bg_a"]}};
 }
  .site a, .site > span {
   display: block;
  }
  .site img {
   width: 32px; height: 32px;
   margin: 10px;
   vertical-align: top;
   /*background: {{\$theme["ico_bg"]}};*/
  }
  .small .site img {
   width: 16px; height: 16px;
   margin: 6px;
  }
  .site.no-icon .name {
   margin-left: 15px;
  }
  .any-icons .site.no-icon .name {
   margin-left: 52px;
  }
  .small .site.no-icon .name {
   margin-left: 9px;
  }
  .small.any-icons .site.no-icon .name {
   margin-left: 28px;
  }
  .site .name {
   display: inline-block;
   width: 436px;
   margin-right: 12px;
   padding: 12px 0;
   vertical-align: middle;
  }
  .narrow .site .name {
   width: 186px;
  }
  .small .site .name {
   width: 276px;
   margin-right: 8px;
   padding: 5px 0 7px 0;
  }
  .narrow.small .site .name {
   width: 120px;
  }
  .site .desc {
   display: block;
   margin-left: 15px; margin-right: 12px;
   padding-bottom: 12px;
   text-align: justify;
  }
  .site.no-name .desc {
   margin-top: -0.375em;
  }
  .any-icons .site .desc {
   margin-left: 52px;
  }
  .small .site .desc {
   margin-left: 9px; margin-right: 8px;
   padding-bottom: 8px;
  }
  .small.any-icons .site .desc {
   margin-left: 28px;
  }
#footer {
 font-size: .6em;
}
#footer.small {
 font-size: .5em;
}
.button_80x15 {
 width: 80px; height: 15px;
}

.mobile {
 background-attachment: scroll;
}
 .mobile #body {
  font-size: 1.5em;
  width: 484px;
 }
 .mobile #body.narrow {
  width: 363px;
 }
 .mobile #body.small {
  width: 363px;
  font-size: 0.9em;
 }
 .mobile #body.narrow.small {
  width: 230px;
 }
  .mobile .site img {
   width: 48px; height: 48px;
  }
  .mobile .small .site img {
   width: 24px; height: 24px;
  }
  .mobile .any-icons .site.no-icon .name {
   margin-left: 68px;
  }
  .mobile .small.any-icons .site.no-icon .name {
   margin-left: 36px;
  }
  .mobile .site .name {
   width: 396px;
  }
  .mobile .narrow .site .name {
   width: 283px;
  }
  .mobile .small .site .name {
   width: 319px;
  }
  .mobile .narrow.small .site .name {
   width: 186px;
  }
  .mobile .site.no-name .desc {
   margin-top: -0.625em;
  }
  .mobile .any-icons .site .desc {
   margin-left: 68px;
  }
  .mobile .any-icons.small .site .desc {
   margin-left: 36px;
  }
  .mobile .button_80x15 {
   width: 120px; height: 22.5px;
  }
 .mobile #footer {
  font-size: 1.2em;
 }
 .mobile.device_apple #footer {
  font-size: 0.75em;
 }

#action_minibar {
 overflow: hidden;
}
#action_minibar.horizontal {
 background-image: none;
}
#minibar div, #minibar.horizontal {
 margin-top: -1px;
}
#minibar a {
 width: 24px; height: 25px;
 margin: 0;
 padding: 4px 4px 0 4px;
}
#minibar.horizontal a {
 height: 26px;
 padding-bottom: 5px;
}
#minibar a.highlight {
 background: {{\$theme["link_bg"]}};
}
 #minibar a img {
  margin-top: 4px;
  width: 16px; height: 16px;
 }
 #minibar a img.empty {
  background-image: url("{{qmark_icon()}}");
  background-position: center center;
  background-repeat: no-repeat;
  background-size: 16px 16px;
 }
#action_minibar.mobile {
 font-size: 1em;
}
CSS
, $namespace, False);

if ($custom_css) echo "\n\n".tpl($custom_css, $namespace, False);

} // CSS template and output

// Helper functions  {{{1

function copyright_year($start = Null, $end = Null, $separator = Null) {
 if (!$start) $start = date("Y");
 if (!$end) $end = date("Y");
 if (!$separator) $separator = "-";
 if ($start == $end) return $start;
 return "$start$separator$end";
}

function htmlentitiesu8($s, $encode_twice = False) {
 if ($encode_twice) $s = htmlentitiesu8($s, False);
 return htmlentities($s, ENT_COMPAT, "UTF-8");
}

function htmlspecialcharsu8($s, $encode_twice = False) {
 if ($encode_twice) $s = htmlspecialcharsu8($s, False);
 return htmlspecialchars($s, ENT_COMPAT, "UTF-8");
}

function htmlsymbols($s, $encode_twice = False) {
 return htmlspecialchars_decode(htmlentitiesu8($s, $encode_twice));
}

function indent($s, $n) {
 $s = explode("\n", $s);
 foreach ($s as $i => $l) {
  $s[$i] = str_repeat(" ", $n).$l;
 }
 return implode("\n", $s);
}

function portal_copyright_years($separator = Null) {
 [$start, $end] = PORTAL_COPYRIGHT_YEARS;
 return copyright_year($start, $end, $separator);
}

function tpl($s, $namespace = Null, $esc = True) {
 global $portal;
 if (is_null($namespace)) $namespace = $portal;
 return Templum::templateFromString($s, $esc)->render($namespace);
}

function tpl_r($s, $namespace = Null, $esc = True) {
 if (is_array($s)) {
  foreach ($s as $k => &$v) {
   if (is_array($v) || is_string($v))
    $s[$k] = tpl_r($v, $namespace, $esc);
  }
  return $s;
 }
 elseif (is_string($s))
  return tpl($s, $namespace, $esc);
 else
  return $s;
}

// Helper functions

// vim: set fdm=marker:  "{{{1
