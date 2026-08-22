=== Update Policy ===
Contributors: digitizer
Tags: updates, core updates, major release, automatic updates, maintenance
Requires at least: 5.5
Tested up to: 7.1
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Hold a major WordPress release back for a set number of days after your site first sees it. Security and maintenance releases are never held.

== Description ==

A major WordPress release is the moment every plugin and theme on your site meets code they have never run against. Most of them are fine. The ones that are not get fixed within days - and a site that installed the release on day one is the one that found out the hard way.

Update Policy gives your site a simple rule: **a major release waits, a security release does not.**

= What it does =

* **Holds a major release** - 7.0 to 7.1, say - for a set number of days after your site is first offered it. 30 by default; you choose.
* **Never holds security or maintenance releases** - 7.1 to 7.1.1. Those are what keeps a site safe, WordPress installs them on its own, and this plugin does not look at them.
* **Removes the held release from the update offer** rather than merely warning about it. A warning does not stop someone pressing the button; a button that is not there does.
* **Shows you the hold.** The Updates screen and the dashboard say which release is held, when your site first saw it, and when the hold ends - so a held update is never mistaken for a broken one.
* **Lets you lift it.** One click offers the release immediately, permanently for that release line. 7.1.1 arriving a week later will not ask you again.
* **Installs nothing on its own.** When the window passes, the release is simply offered again. Updating stays your decision.

= What it does not do =

* It makes no network requests. Not to WordPress.org, not to anyone. The window is counted from the day your site first saw the release, which your site already knows.
* It does not install, download, or modify any code.
* It sends no data anywhere. There is nothing to send.
* It does not touch plugin or theme updates. Only WordPress core, and only major releases.

= Multisite =

A core update is network-wide, so the policy is too. The plugin is network-activated, its settings live under Network Admin > Settings, and only a network administrator can change the hold or lift one.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate it.
3. Visit Settings > Update Policy to choose how many days a major release is held. The default is 30.

That is all. The next time a major release is offered, your site will hold it and tell you so on the Updates screen.

== Frequently Asked Questions ==

= Does this delay security updates? =

No. Security and maintenance releases - the ones with a third number, like 7.1.1 - are never held. WordPress keeps installing them automatically, exactly as it does without this plugin.

= Does it install the major release automatically when the hold ends? =

No. When the window passes, the release reappears on your Updates screen like any other. Installing it is your decision. If you have turned on automatic major updates in WordPress, those resume when the hold ends and are refused while it runs.

= I need the release now. =

Press "Offer it now anyway" in the notice on the Updates screen. The hold is lifted for that release line permanently, so the maintenance releases that follow it will not be held either.

= Why is the window counted from when my site saw the release, rather than from when it was published? =

Because your site already knows the first and would have to ask someone for the second. Every site checks for updates twice a day, so the difference is under half a day - and it keeps the plugin from ever making a network request.

= I activated the plugin while a major release was already on offer. =

It is held for the full window from now, because the plugin cannot know how long the release had been waiting. The notice says so, and "Offer it now anyway" is the answer if you want it immediately.

= Does it work on multisite? =

Yes. The plugin is network-activated, the settings are under Network Admin > Settings, and the policy applies to the whole network, which is the only thing a core update can apply to.

= Does it send any data anywhere? =

No. It makes no network requests at all.

== Changelog ==

= 1.0.0 =
* First release.
