Portal
======

Copyright (C) 2006-2011 Scott Zeid  
https://github.com/scottywz/portal

Installation
------------
To install Portal:

1. Copy `index.php` and `portal-data` to your document root or whatever folder
   you want to install it in.
2. Rename `portal-data/settings.yaml.dist` to `portal-data/settings.yaml`.
3. Edit that file to change the settings and add sites.
4. Add icons to `portal-data/icons` and `portal-data/icons/small`.
   Corresponding icons in both folders should have the same file names.  Icons
   in `portal-data/icons` should be 32x32 pixels in size, and icons in
   `portal-data/icons/small` should be 16x16 in size.  If you only plan on
   using the small icons and/or minibar, you may leave out the large ones, and
   vice versa.

If you want to change the name or location of portal-data, you will also need
to update the `$CONFIG_DIR` variable in index.php accordingly.

Query string parameters
-----------------------
Portal supports the following query string parameters:

* `device={android,webos,apple,apple-tablet}` - force the mobile device type
* `minibar` - render a minibar (see below for more info and related parameters)
* `mobile` - use the mobile stylesheet
* `!mobile`, `nomobile` - do not use the mobile stylesheet
* `narrow` - force the Portal to be narrow
* `!narrow`, `wide` - force the Portal to not be narrow
* `small` - force the icons and text to be small
* `!small`, `large`, `big` - force the icons and text to be big
* `target` - use the specified value as the target attribute for site links
* `theme` - use the specified theme name (must be a key in `settings.yaml` >
            `themes`)
* `_403`, `_404` - causes Portal to render a 403 or 404 error page

OpenID support
--------------
If you use OpenID and your provider allows you to, you can use your Portal's
URL as an alias for your OpenID.  To set this up, you would look in your OpenID
provider's help pages for something that looks like "Use your own URL", and
then look for some HTML code that looks something like this:

    <link rel="openid.server" href="http://www.myopenid.com/server" />
    <link rel="openid.delegate" href="http://youraccount.myopenid.com/" />
    <link rel="openid2.local_id" href="http://youraccount.myopenid.com" />
    <link rel="openid2.provider" href="http://www.myopenid.com/server" />
    <meta http-equiv="X-XRDS-Location"
     content="http://www.myopenid.com/xrds?username=youraccount.myopenid.com" />

Then you would copy the URLs from that code and paste them into the respective
keys in `settings.yaml` > `openid`.  You can omit either `provider` and
`local_id` or `server` and `delegate` if your provider does not give you those
URLs.  However, `server` and `provider` are often the same, as are `delegate`
and `local_id`.

For example:

    openid:
        # OpenID server URL
        server:   http://www.myopenid.com/server
        # OpenID provider URL
        provider: http://www.myopenid.com/server
        # Delegate URL
        delegate: http://youraccount.myopenid.com/
        # Local ID URL
        local_id: http://youraccount.myopenid.com
        # XRDS URL
        xrds:     http://www.myopenid.com/xrds?username=youraccount.myopenid.com

Note that Portal sends the `X-XRDS-Location` URL as an actual HTTP header, and
not a meta tag, because the latter would prevent Portal from validating as
HTML5.

Minibar
-------
Portal has a "minibar" feature.  This allows you to add a bar to the top or
left (for example) of a Web page of yours that contains a miniature version of
your Portal.  Example HTML code for a horizontal minibar:

    <div id="portal-minibar" style="width: 100%; height: 23px;">
     <iframe src="http://your.portal.example/?minibar&highlight=awesome-site"
       style="width: 100%; height: 23px; border-style: none; overflow: hidden;"
       frameborder="0"></iframe>
    </div>

The `highlight` parameter is optional, and is the name of the key for the site
you want highlighted.  For example, if your sites section looks like this:

    sites:
        awesome-site:
            name: My awesome site
            icon: awesome.png
            url:  http://awesome.site.example/
            desc: There's some awesome s*** here!

and you want "My awesome site" to be highlighed, you would use
`&highlight=awesome-site`.

The default orientation is horizontal, unless you changed `minibar-orientation`
to `vertical` in settings.yaml.  You can also add `&vertical` or `&horizontal`
to the URL to change the orientation.  Here's an example for a vertical minibar
that is fixed to the left-hand side of the screen:

    <div id="portal-minibar"
      style="position: fixed; top: 0; bottom: 0; left: 0; height: 100%;">
     <iframe src="http://your.portal.example/?minibar&vertical&highlight=slug"
       style="width: 24px; height: 100%; border-style: none; overflow: hidden;"
       frameborder="0"></iframe>
    </div>
