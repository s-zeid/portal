<?php
 
/* Portal
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

/* Relative or absolute path to the settings folder; default is "portal-data"
 * This should be the same on both the filesystem and in URLs.
 * Use "." for the current directory.
 */
$CONFIG_DIR = "portal-data";

// Set to True to get the $portal array when visiting the portal
// Use only for debugging purposes.
$debug = False;

////////////////////////////////////////////////////////////////////////////////

require "$CONFIG_DIR/lib/is_mobile.php";
require "$CONFIG_DIR/lib/spyc.php";
require "$CONFIG_DIR/lib/templum_php5.php";

// Configuration loading and sanitation
$portal = spyc_load_file("$CONFIG_DIR/settings.yaml");
$portal["CONFIG_DIR"] = $CONFIG_DIR;
$name = $portal["name"] = $portal["name"];
$theme = $portal["theme"] = $portal["theme"];
if (!isset($portal["banner"]))
 $portal["banner"] = array("type" => "text", "content" => $name);
$use_templum_for_banner_content = isset($portal["banner"]["content"]);
if (!in_array($portal["banner"]["type"], array("text", "image")))
 $portal["banner"]["type"] = "text";

$openid_enabled = !empty($portal["openid"]["xrds"]) &&
                  ((!empty($portal["openid"]["provider"]) &&
                    !empty($portal["openid"]["local_id"])) ||
                   (!empty($portal["openid"]["server"]) &&
                    !empty($portal["openid"]["delegate"])));

$ga_enabled = !empty($portal["google-analytics"]["account"]) &&
              !empty($portal["google-analytics"]["style"]) &&
              in_array($portal["google-analytics"]["style"],array("new","old"));

$request_uri = (!empty($_SERVER["REQUEST_URI"])) ? $_SERVER["REQUEST_URI"] : "";
$url_scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] != "off") ? 
              "https" : "http";

// Mobile device detection
$mobile = is_mobile(False, True);
$device = is_mobile(True, True);

// Template expansion for config values
if ($use_templum_for_banner_content)
 $portal["banner"]["content"] = tpl($portal["banner"]["content"]);
if (isset($portal["custom-footer-content"]))
 $portal["custom-footer-content"] = tpl($portal["custom-footer-content"]);

// Template namespace
$namespace = array();
$names = explode(",", "CONFIG_DIR,device,ga_enabled,mobile,name,"
          ."openid_enabled,portal,request_uri,url_scheme");
foreach ($names as $n) {
 $namespace[$n] = &$$n;
}

// Helper functions
function copyright_year($start = Null, $end = Null) {
 if (!$start) $start = date("Y");
 if (!$end) $end = date("Y");
 if ($start == $end) return $start;
 return $start."-".$end;
}
function htmlentitiesu8($s, $encode_twice = False) {
 if ($encode_twice) $s = htmlentitiesu8($s, False);
 return htmlentities($s, ENT_COMPAT, "UTF-8");
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

if ($debug) {
 header("Content-type: text/plain");
 print_r($portal);
 exit();
}

if (!isset($_GET["css"]) || !trim($_GET["css"]) != "") {
 /* HTML Template */
 
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
 }
 
 // Update namespace
 $names = explode(",", "_403,_404,action,highlight,minibar,narrow,orientation,"
           ."request_uri,small,target,theme");
 foreach ($names as $n) {
  $namespace[$n] = &$$n;
 }
 
 // Yadis XRDS header; needs to be sent as a proper header instead of a meta
 // tag in order to validate as HTML5
 if ($openid_enabled)
  header("X-XRDS-Location: ".rawurlencode($portal["openid"]["xrds"]));
 
 echo htmlsymbols(tpl(<<<HTML
<!--[if lt IE 7]><span class="hide" title="Put IE lt 7 in quirks mode so IE9.js will work right"></span><![endif]-->
<!DOCTYPE html>

<html>
 <head>
  <meta charset="utf-8" />
  <!--
  
   Portal
   
   Copyright (C) 2006-2011 Scott Zeid
   https://github.com/scottywz/portal
   
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
@if (stripos(rtrim(\$request_uri, "/"), ".php") == strlen(rtrim(\$request_uri, "/")) - 4):
  <base href="{{implode("/",explode("/", rtrim(\$request_uri, "/"), -1))}}/" />
@else:
  <base href="{{rtrim(\$request_uri, "/")}}/" />
@endif
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
  <meta name="generator" content="Portal by Scott Zeid; X11 License; https://github.com/scottywz/portal" />
  <!--[if lt IE 9]>
   <script type="text/javascript">var IE7_PNG_SUFFIX = ".png";</script>
   <script defer="defer" type="text/javascript" src="{{\$CONFIG_DIR}}/IE9.js"></script>
   <link rel="stylesheet" type="text/css" href="{{\$CONFIG_DIR}}/pngtrans.css" />
  <![endif]-->
  <link rel="stylesheet" type="text/css" href="{{\$url_scheme}}://fonts.googleapis.com/css?family=Ubuntu:regular,italic,bold,bolditalic" />
  <link rel="stylesheet" type="text/css" href="?css={{\$theme}}&amp;amp;.css" />
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
 if (!isset(\$site["minibar"]) || \$site["minibar"] !== False) {
  \$code = "";
  if (\$orientation == "vertical") \$code .= "<div>";
  // Link
  \$code .= "<a href=\"".htmlentitiesu8(\$site["url"], True)."\" target=\"_blank\"";
  // Highlight
  if (\$highlight == \$slug) \$code .= ' class="highlight"';
  // Site name
  \$code .= " title=\"".htmlentitiesu8(strip_tags(\$site["name"]), True);
  // Site description
  if (isset(\$site["desc"]) && trim(\$site["desc"]))
   \$code .= " &mdash; ".htmlentitiesu8(strip_tags(\$site["desc"]), True);
  // Icon
  \$code .= "\"><img src=\"\$CONFIG_DIR/icons/small/".htmlentitiesu8(\$site["icon"], True)."\"";
  \$code .= " alt=\"Icon\" /></a>";
  if (\$orientation == "vertical") \$code .= "</div>";
  echo \$code;
 }
}

]]

  </div>
@//Minibar
@else:
@ /* Normal mode */
  <div id="header"[[if (\$small) echo ' class="small"';]]>
   <h1>
@  /* Banner */
    <a id="title" class="{{\$portal["banner"]["type"]}}" href="{{\$request_uri}}">
@if (\$portal["banner"]["type"] == "image"):
     <img src="[[echo htmlentitiesu8((!empty(\$portal["banner"]["content"]))
                       ? \$portal["banner"]["content"]
                       : "\$CONFIG_DIR/images/banner.png", True
                      );]]" alt="{{\$name}}" />
@else:
[[echo indent(htmlsymbols((!empty(\$portal["banner"]["content"]))
               ? \$portal["banner"]["content"] : \$name), 5)."\n";]]
@endif
    </a>
@  // Banner
   </h1>
  </div>
  <div id="body"[[if (\$narrow || \$small) {
]] class="[[if (\$narrow) echo "narrow"; if (\$narrow && \$small) echo " ";
            if (\$small) echo "small";]]"[[
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
  \$code .= "<p class=\"site\">\n";
  // Link
  \$code .= " <a href=\"".htmlentitiesu8(\$site["url"], True)."\"";
  // Link target
  if (\$target) \$code .= " target=\"\$target\"";
  \$code .= ">\n";
  // Image
  \$code .= "  <span><img src=\"\$CONFIG_DIR/icons";
  if (\$small) \$code .= "/small";
  \$code .= "/".htmlentitiesu8(\$site["icon"], True)."\" alt=\" \" />";
  // Site name
  \$code .= "<strong class=\"name\">".htmlsymbols(\$site["name"])."</strong></span>";
  // Site description
  if (isset(\$site["desc"]) && trim(\$site["desc"]))
   \$code .= "<br />\n  <span class=\"desc\">".htmlsymbols(\$site["desc"])."</span>";
  // Close stuff
  \$code .= "\n </a>\n</p>";
  echo indent(\$code, 3);
  echo "\n";
 }
}

]]
@endif
  </div>
  <div id="footer" class="footer[[if (\$small) echo " small";]]">
   <p>
    <a href="https://github.com/scottywz/portal">Portal software</a>
    copyright &copy; [[echo copyright_year(2006);]] <a href="http://srwz.us/">Scott Zeid</a>.
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

} // HTML Template
else {
 /* CSS Template */
 $theme = tpl_r($portal["themes"][$_GET["css"]]);

 // Update namespace
 $names = explode(",", "theme");
 foreach ($names as $n) {
  $namespace[$n] = &$$n;
 }
 
 header("Content-type: text/css");
 
 echo tpl(<<<CSS
/* Portal
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
a:hover, .site:hover * {
 color: {{\$theme["fg"][2]}};
}
a:active, .site:active * {
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
 #header a {
  color: {{\$theme["fg"][0]}};
 }
 #title.text {
  background: {{\$theme["logo_bg"]}};
@if (\$theme["logo_bg"] != "transparent"):
  border: 1px solid {{\$theme["logo_border"]}};
@endif
  padding: 0.2em;
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
 .site:hover, #minibar a:hover {
  background: {{\$theme["link_bg_h"]}};
 }
 .site:active, #minibar a:active {
  background: {{\$theme["link_bg_a"]}};
 }
  .site a {
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
   margin-left: 52px; margin-right: 12px;
   padding-bottom: 12px;
  }
  .small .site .desc {
   margin-left: 28px; margin-right: 8px;
   padding-bottom: 8px;
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
  .mobile .site .desc {
   margin-left: 68px;
  }
  .mobile .small .site .desc {
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
 }
#action_minibar.mobile {
 font-size: 1em;
}
CSS
, $namespace, False);

} // CSS Template

?>
