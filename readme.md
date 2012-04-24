Onigi PHP SDK (v.0.7)
==========================

This repository contains the open source PHP SDK that allows you to access Onigi Platform from your PHP app. Except as otherwise noted, the Onigi PHP SDK
is licensed under the Apache Licence, Version 2.0
(http://www.apache.org/licenses/LICENSE-2.0.html)


Usage
-----

The [examples][examples] are a good place to start. The minimal you'll need to
have is:

    include '../src/onigi.php';

    $onigi = new Onigi(array(
      'appId'  => 'YOUR_APP_ID',
      'secret' => 'YOUR_APP_SECRET',
    ));

    // Get User ID
    $user = $onigi->getUser();

To make [API][API] calls:

    if ($user) {
      try {
        // Proceed knowing you have a logged in user who's authenticated.
        $user_profile = $onigi->api('/me');
      } catch (OnigiApiException $e) {
        error_log($e);
        $user = null;
      }
    }

Login or logout url will be needed depending on current user state.

    if ($user) {
      $logoutUrl = $onigi->getLogoutUrl();
    } else {
      $loginUrl = $onigi->getLoginUrl();
    }

[examples]: https://github.com/onigi/api-php/tree/master/example
[API]: http://onigi.com/api-documentation/


Report Issues/Bugs
===============
[Bugs](http://onigi.com/hubungi-kami/)

[Questions](http://onigi.com/hubungi-kami/)

