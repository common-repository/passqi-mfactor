=== passQi mFactor ===

Contributors: passqi1, ethanblake4

Tags: security, login, passQi, two-factor authentication, authentication, multi-factor authentication, biometric authentication, Touch ID

Requires at least: 4.2

Tested up to: 4.4.2 

Stable tag: 1.0.8 

License: GPLv2

License URI: http://www.gnu.org/licenses/gpl-2.0.html


Secure two-factor authentication made simple. Lets administrators secure their sites against password attack, and users secure their content.



== Description ==

passQi mFactor adds easy to use two-factor authentication, working with the passQi smartphone app (available for iPhone in App Store, Android beta is available https://www.passqi.com/android-beta/). 

Protect your site from the dangers of compromised or hacked accounts, by requiring smartphone-based authentication.

The passQi smartphone app automates username/password login by securely relaying them from your smartphone to a site's login page within the browser. It also relays an encrypted two-factor token cookie so the site may determine that the user has possession of their smartphone. Users or administrators may also require that users authenticate with a touch device when they log in.

 passQi mFactor configuration includes installation of a public decryption; the passQi mFactor service has commercial and free non-commercial subscriptions. A certificate is obtained using a link from the passQi mFactor administration page added to the Users menu.

Installation adds a user profile option, "Require passQi to Login". If checked, the user can only login using the passQi app, which relays username and password (still required) as well as the passQi mFactor token. For how to use the passQi app, see http://www.passqi.com.

passQi mFactor leaves the primary factor of authentication, username and password, intact (albeit automated by the passQi app), while adding the second factor of the secure mFactor token, which identifies the user with an encrypted token that is only stored on their smartphone. Two channels of encryption are used to secure the delivery of credentials from  the user’s smartphone, one of which is a one-time encryption key known only to the browser session and the smartphone app, the other a site-specific public/private key pair.

Administrators may configure passQi mFactor to be required, enabled, or disabled, on a per-user basis or using bulk actions to change these settings by Role.  Privileged accounts especially should be protected with two-factor authentication as compromising them can do the most harm to your site.



== Installation ==

Install a downloaded passqi-mFactor.zip file as you would any other WordPress plugin, by uploading the zip file and then enabling it from the Plugins menu.  Or search for passQi mFactor in the plugin search and install directly.

Before your users will be able to use passQi, it is necessary to first obtain the site specific public key used for decrypting the mfa token sent at login.  Obtaining a key is free for non-commercial sites and all users involved in the.  Commercial users will pay a subscription fee.  

In order to obtain and install the public key, go to the passQi Login menu that has been added to the Users menu in the WordPress dashboard.  At the top of the page there is a "Get Public Key" section at the top of the page,

click the "Get public key" button, and a new tab will open in your browser on the passqi.com site.  Complete the form, and tap the "Get public key" button.

 At the passQi store you can determine and select what type of subscription you wish to acquire.

By default, all users are now able to enable the "Require passQi for Login" feature in their profile (if they first login with passQi, which signals WordPress that the user has this capability).

The feature may be turned on and off, and also forced for specific users by the administrator,  by setting the corresponding option in the "Users" table on the "passQi Login" page.

Most commercial subscription plans also offer support for touchQi functionality, which also allows a user or administrator to require that authentication be accompanied by a Biometric (e. g., Touch ID) local authentication at the time of login to the site.

== Frequently Asked Questions ==

= How do I report problems / ask questions =

Visit www.passqi.com/support.

= Do I need A Smartphone? =

Yes, passQi mFactor is designed to work with the passQi app.  Currently passQi 2 is available in the iPhone app store.  The Android version is
 in development and will be available shortly.

== Screenshots ==

1. The passQi mFactor administration page.
2. This is what the user sees if passQi has been configured required for an account and the username and password are entered.
3. This is what the user sees if touchQi is been required.

== Changelog ==

Initial production release

== Known Issues ==

There is a known issue with the workflow integrity checks of the passQi app being overly restrictive when using touchQi: if the user attempts login for a “logged out” page, the second bookmark load and first Touch ID local auth will not be relayed.  A subsequent bookmark click and touchQi auth will succeed.  The next release of the passQi app will correct this.



