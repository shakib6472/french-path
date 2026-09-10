=== French Path - Course Packages and Progressive Unlock ===
Contributors: shakibshown
Tags: learndash, woocommerce, lms, courses, enrolment
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sells LearnDash courses as packages, records what a learner paid for, and opens one sublevel at a time under coordinator control.

== Description ==

A school that teaches a long ladder of levels has a problem no LMS solves on
its own: a learner may pay for the whole journey at once, but should only be
able to enter one stage at a time.

French Path separates two facts that WordPress normally conflates:

* **Entitlement** — the learner has paid for this course.
* **Access** — the learner may enter it today.

Buying a twelve sublevel pathway writes twelve entitlements and opens one.
The rest stay recorded as paid for, and a coordinator releases each one after
assessment.

= How it works =

1. Create a **Package**: give it an ordered list of LearnDash courses and the
   WooCommerce product that sells it.
2. A single course is just a package holding one course, so individual and
   bundled purchases run through exactly the same code.
3. When an order is paid, every course in the package is recorded as an
   entitlement, and the first one the learner does not already have open is
   enrolled in LearnDash.
4. Locked sublevels are never enrolled. LearnDash denies them natively, so
   nothing is left open if the plugin stops running.

= Requirements =

LearnDash and WooCommerce. The plugin degrades to recording entitlements when
LearnDash is absent, and does nothing at all when WooCommerce is absent.

== Installation ==

1. Upload the `french-path` folder to `/wp-content/plugins/`.
2. Activate it through the Plugins screen.
3. Go to **French Path → Packages** and create your first package.

**Important:** leave the LearnDash *Related course* field on your package
products empty. When that field is set, the LearnDash WooCommerce integration
enrols the buyer in every listed course as soon as the order is paid, which
defeats progressive unlock.

== Frequently Asked Questions ==

= What happens to entitlements when I delete the plugin? =

They stay. The entitlements table and the package posts survive uninstall,
because deleting a plugin is not a decision to destroy records of payment.
Remove them by hand if you really want them gone.

= What happens on a refund? =

Every entitlement that order wrote becomes revoked, and LearnDash access is
removed for any course no other live order still covers. A sublevel bought
twice, once alone and once inside a pathway, survives a refund of either one.

= Can a learner lose a sublevel they already finished? =

No. Progression never closes a course. Only a refund, or a coordinator
correcting a mistake, does.

== Changelog ==

= 1.0.0 =
* Packages, entitlement records, and progressive unlock with coordinator release.
