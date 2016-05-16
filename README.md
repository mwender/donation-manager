# README

Donation Manager is a WordPress plugin which handles a multi-form donation process. This plugin requires the [PODS Plugin for WordPress](http://pods.io).

__05/16/2016 - Version 1.4.3.1__

- Disabling Priority Orphan donation option for markets with an existing non-profit partner.

__05/13/2016 - Version 1.4.3__

- New Report: `Donations > Donation Reports > Orphaned Donors`.
- Refacotring: `$DMReports->callback_donation_report()` to load external fns file.
- Refactoring: Moving report JS into `lib/js/reports.orgs.js`.
- Refactoring: Renaming `lib/includes` to `lib/views`.
- Refactoring: Tabbed interface for *Donations > Donation Reports*. 
- Refactoring: Moved Shortcode class instantiation after class inclusion.
- Refactoring: Moved Donation Report class instantiation after class inclusion.
- Refactoring: Moved `$DonationManager` hooks to class construct
- Refactoring: Moved `$DMReports` hook calls inside `__construct()`.
- Moved *Donation Reports* to *Donations > Donation Reports*.
- Bugfix: Month value was not getting set during Donation Report generation.

__05/05/2016 - Version 1.4.2.1__

- Displaying ad rows for all organizations on `select-your-organization` view

__03/30/2016 - Version 1.4.2__

- Optimizing Donation Reports

__03/03/2016 - Version 1.4.1__

- Special Case: Routing all orphaned donations to College Hunks Hauling Junk
- Including `priority` status in Utilities > Test a Pickup Code report
- Search for Orphaned Donations > Providers tab

__02/22/2016 - Version 1.4.0__

- Donation Routing option for organizations (e.g. email, api, etc)
- BUG FIX: Decoding HTML Entities in email subject lines inside DonationManager::send_email()
- Initial implementation of priority pick up back links during non-profit donation process

__12/15/2015 - Version 1.3.0__

- For-profit donation leads
- PHP7 compatibility
- Changing unsubscribe link from Mandrill API link to mailto

__12/07/2015 - Version 1.2.7__

- Added "Preferred Donor Code" to donations.
- Listing "Priorty Pick Up Providers" last.
- Setting `to:noreply@pickupmydonation.com` for orphaned trans dept emails.

__10/30/2015 - Version 1.2.6__

- Orphaned Donation reporting

__10/29/2015 - Version 1.2.5__

- Added option for setting URL for Org's "Donate Now" button

__10/09/2015 - Version 1.2.4__

- Adding `priority_pickup` option for organizations.
- Added `notify_webmaster` option for `[bounced-orphan-contact]`.
- Disabling two emails: 1) missing org_id , and 2) bad link

__09/28/2015 - Version 1.2.3__

- Automatically unsubscribing Mandrill hard bounces via a Mandrill webhook

__09/28/2015 - Version 1.2.2__

- Updating `wp_mail_from` to PMD contact email
- Added `[unsubscribe-orphaned-contact]` shortcode for use on Unsubscribe page

__09/16/2015 - Version 1.2.1__

- Admin form for adding orphaned donation contacts
- Setting contact emails to lowercase

__09/10/2015 - Version 1.2.0__

- Orphaned Donation processing

__01/23/2014 - Version 1.1.0__

- Donation reports for each organization
- "All Donations" report
- Added referer URL to donations

__09/10/2014 - Version 1.0.0__

- First complete version of the plugin.
- Handles the entire donation process.