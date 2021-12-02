# README

Donation Manager is a WordPress plugin which handles a multi-form donation process. This plugin requires the [PODS Plugin for WordPress](http://pods.io).

## How to Compile MJML Templates

As of February 2019, my method for compiling MJML email templates is as follows:

1. Edit the template in the MJML App.
2. Save out the template as HTML to `lib/html/`.
3. Copy the template over to `lib/templates/` and replace the `.html` extension with `.hbs`.

## Changelog

__12/02/2021 - Version 2.4.1__
- Deactivating requirement for match between originally entered zip code and pick up address zip code.

__07/29/2021 - Version 2.4.0__
- Adding SMCo Thrift and ThriftTrac logos to the footer of Transportation Department notifications.

__06/30/2021 - Version 2.3.1__
- Updating height of Unpakt.com widget to `400px` on the on-screen confirmations.

__06/21/2021 - Version 2.3.0__
- Adding Unpakt.com widgets to the on-screen confirmations.
- Updating copyright date for email footers.

__04/08/2021 - Version 2.2.2__
- BUGFIX: Ensuring `$bcc_emails` is an array before running `count()`.

__03/26/2021 - Version 2.2.1__
- BUGFIX: Ensuring array is declared before checking `array_key_exists()`.

__03/26/2021 - Version 2.2.0__
- Adding "Organization Options" meta fields with alternate "Donation Option Descriptions".

__03/25/2021 - Version 2.1.2__
- Allowing HTML in donation category descriptions.

__01/22/2021 - Version 2.1.1__
- Adding Name to subject line for zip code mismatch alert.

__01/22/2021 - Version 2.1.0__
- Checking for mismatch between entered zip code and the pick up address.

__12/09/2020 - Version 2.0.4__
- Different styling of Trans. Dept. parent "Not Set" based on `post_status`.

__12/09/2020 - Version 2.0.3__
- BUGFIX: In the Transportation Department admin listing: Correctly showing when a Transportation Department does not have a parent Organization assigned.

__07/02/2020 - Version 2.0.2__

- BUGFIX: Checking if variable is an array before performing `count()`.

__06/26/2020 - Version 2.0.1__

- Adding Realtor Ad with link to all four steps of the donation process.

__06/18/2020 - Version 2.0.0__

- Adding `[donors_in_your_area]`.
- Adding Realtor Ads.
- Adding "What led you to donate today?" option.

__04/30/2019 - Version 1.9.4__

- Adding `wp donman zipsbytransdept` report

__03/20/2019 - Version 1.9.3__

- Adding `Organization Name` to donation notification emails

__03/13/2019 - Version 1.9.2__

- Adding `DonorCompany` to all donation reports.

__03/13/2019 - Version 1.9.1__

- Adding "Company" field to donor contact details
- Adding phone number masking (i.e. (000) 000-0000) to donor phone number field.

__02/13/2019 - Version 1.9.0__

- Updating Transportation Department emails to include unsubscribe links pointing back to PickUpMyDonation.com.

__01/16/2019 - Version 1.8.1__

- Adjusting orphaned import to not process contacts which already exist in the database.
- Updated Donor Confirmation email with verbiage originally used in Freshdesk auto-responder.

__09/05/2018 - Version 1.8.0.1__

- HOTFIX: Fixing `Illegal string offset` warning during date selection screen

__11/13/2017 - Version 1.8.0__

- Automated Monthly Donor Reports for Network Members

__09/26/2017 - Version 1.7.2__

- Restoring "Current Month" Donation reports

__08/31/2017 - Version 1.7.1__

- Improved performance of DONATIONS > DONATION REPORTS view. Utilizes WP REST API.

__08/14/2017 - Version 1.7.0__

- Automated Monthy Donor Report Emails

__03/21/2017 - Version 1.6.0__

- Orphaned Pick Up Providers by-pass links
- New Feature: Set orphaned providers to show/not show on "Select Your Organization" screen

__03/01/2017 - Version 1.5.0__

- Displaying Orphaned Pick Up Providers on the "Select Your Organization" screen

__01/20/2017 - Version 1.4.9__

- Adding "Tweet/Instagram my donation" option

__12/15/2016 - Version 1.4.8.1__

- Code cleanup: setting undefined variables, checking if variables are set

__12/14/2016 - Version 1.4.8__

- Adding Donors by ZIP report

__12/14/2016 - Version 1.4.7__

- Adding Handlebars template processing
- Server-side prevention of duplicate submissions using transients
- Updating submitted form submit button text to "one moment..."
- Inlining lib/js/scripts.js

__07/15/2016 - Version 1.4.6__

- Adding WP_CLI command `wp writestats` which writes stats.json in plugin root.

__07/07/2016 - Version 1.4.5__

- Adding Donations > Orphaned Donations > Utilities > Subscribe an Email.

__06/17/2016 - Version 1.4.4__

- Adding inbound email processing.

__05/16/2016 - Version 1.4.3.1__

- Disabling Priority Orphan donation option for markets with an existing non-profit partner.
- Allow sending of trans dept emails even when donation is submitted via to an API.

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