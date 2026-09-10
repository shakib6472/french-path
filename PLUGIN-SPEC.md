# French Path — Plugin Specification

This file is the single source of truth for the plugin. Code must never
contradict it. When something ships that this file does not describe, record
it here.

---

## 1. Identity

| Field | Value |
| --- | --- |
| Plugin name | French Path - Course Packages and Progressive Unlock |
| Slug / text domain | `french-path` |
| Class prefix | `French_Path_` |
| Function prefix | `french_path_` |
| Constant prefix | `FRENCH_PATH_` |
| CSS class prefix | `.french-path-` |
| Hook prefix | `french_path_` |
| Author | Shakib Shown |
| Licence | GPL-2.0-or-later |
| Requires | WordPress 6.0, PHP 7.4 |

`fic_` and `mii_` are already used by code stored in the site database and
must never be used here.

## 2. What the plugin is for

McDonald Insight International runs French Immersion College: twelve CEFR
sublevels, A1.1 to B2.4, taught in live classes. Learners buy one sublevel,
a band of sublevels, or the complete A1–B2 pathway. Whatever they buy, only
one sublevel is open at a time. A coordinator releases the next one after
assessment.

The plugin exists to hold three facts LearnDash and WooCommerce cannot hold
between them:

1. What a learner has paid for.
2. What a learner may enter today.
3. Which purchase carries the examination fee guarantee.

## 3. What the plugin deliberately does not do

- It does not decide progression. A coordinator does.
- It does not grade, schedule, or manage attendance.
- It does not hold streams. Milestone 3 adds those.
- It does not render the learner dashboard. Milestone 4 does.

## 4. Structure and conventions

One class per file, named `class-french-path-{name}.php`, lowercase and
hyphenated, resolved by the autoloader registered in `french-path.php` so it
is available during activation. Search order: `includes/`, `admin/`,
`public/`.

`French_Path` is the only singleton. Every other class is a set of static
methods with its own `init()`, called from `French_Path::init()`.

Every PHP file opens with a docblock carrying `@package French_Path`, then
`if ( ! defined( 'ABSPATH' ) ) { exit; }`. Every directory carries an
`index.php` silence guard.

Sanitise on input, escape on output, every time. Every user facing string is
translated with the `french-path` text domain. WordPress Coding Standards,
tabs for indentation. No namespaces, no Composer, no build tooling, no third
party PHP libraries.

All code and code comments are written in English.

### Files

```
french-path.php                                bootstrap, constants, autoloader registration
includes/class-french-path.php                 core singleton, autoloader, service boot
includes/class-french-path-activator.php       activation
includes/class-french-path-deactivator.php     deactivation, destroys nothing
includes/class-french-path-install.php         table schema and upgrades
includes/class-french-path-roles.php           capabilities
includes/class-french-path-package.php         package post type and lookups
includes/class-french-path-entitlement.php     the entitlements table API
includes/class-french-path-access.php          unlock, lock, LearnDash enrolment
includes/class-french-path-purchase.php        WooCommerce order transitions
includes/class-french-path-locked.php          the reason a paid sublevel is closed
includes/class-french-path-pathway.php         where a learner stands on a ladder
includes/class-french-path-settings.php        the settings option
includes/class-french-path-templates.php       template loading and theme overrides
admin/class-french-path-admin.php              meta boxes, saving, list columns
admin/class-french-path-learners.php           the learner and release screen
admin/class-french-path-admin-settings.php     the settings screen
admin/views/metabox-courses.php                ordered sublevel picker
admin/views/metabox-purchase.php               product, kind, guarantee
admin/views/metabox-visibility.php             which course pages offer it
admin/views/learners-list.php                  learners holding an entitlement
admin/views/learners-detail.php                one learner, their ladders, release controls
admin/views/settings.php                       the settings form
public/class-french-path-public.php            front end stylesheet
public/class-french-path-shortcodes.php        shortcode registry and rendering
templates/my-courses.php                       the learner's pathway
templates/enrol-card.php                       the enrol card, all three states
templates/enrol-packages.php                   the ways to buy a course
uninstall.php                                  removes options only
```

## 5. The package

A package is a `fp_package` post. It is the only thing the plugin sells. A
single sublevel is a package holding one course, so there is no separate code
path for individual purchases.

| Meta key | Meaning |
| --- | --- |
| `_french_path_courses` | Ordered array of LearnDash course IDs. The order is the ladder. |
| `_french_path_product` | WooCommerce product whose purchase grants the package. |
| `_french_path_guarantee` | `'1'` when the package carries the examination fee guarantee. |
| `_french_path_kind` | `single`, `band` or `pathway`. Presentation only. |
| `_french_path_show_on_mode` | `first` or `chosen`. How the package decides where it is offered. |
| `_french_path_show_on` | Course IDs the package is offered on, when the mode is `chosen`. |

The post type is `show_ui` but not public: it has no front end of its own.
It appears as a top level admin menu labelled **French Path** at position 56.

The client creates and edits packages without a developer. Nothing about the
twelve sublevels, the prices or the package list is hardcoded.

### Rules

- **The package post is the only authority on ladder order.** Anything that
  displays a ladder reads `French_Path_Package::get_courses()`. The `position`
  column records the rung at the time of purchase and is never trusted for
  display, because an admin who reorders a package after people have bought it
  would otherwise leave every existing learner reading the old order.
  `French_Path_Entitlement::sync_positions()` runs on package save so the
  stored column does not drift.
- When a learner holds more than one package covering a course, the one with
  the longest ladder describes their journey. Ties go to the most recent.
- More than one package may point at the same product. Every match is granted.
- A course may appear in many packages. That is how a sublevel is offered
  individually and inside a pathway at the same time.
- A package with no courses grants nothing and is skipped.

### Where a package is offered

**A package is offered only on the sublevel it opens on.** That is the
default, and it needs no configuration: `get_show_on()` returns the first
course in the ladder.

Somebody opening B1.2 came to start at B1.2. A package running A1.1 to B2.4
does contain B1.2, but buying it would put them at A1.1 - not an answer to
what they asked for. Worse, the complete pathway carries the examination fee
guarantee, and the guarantee requires the whole pathway paid: offering it on
B1.2 can leave a buyer believing they qualify for a reimbursement they can
never claim.

Setting the mode to `chosen` replaces the rule with a hand picked list of
course pages, for advertising a package ahead of where it begins.

`find_shown_on()` answers "what can somebody starting here buy" and is what
the course page uses. `find_by_course()` answers "what contains this course"
and is a different question; do not use it for display.

The client is expected to build many packages - roughly one per sensible
starting point, times the endings he wants to sell - and to price them
himself. Nothing about that list is hardcoded.

### Issues and warnings

`get_issues()` means the package will not sell, and drives the Status column.
`get_warnings()` means it works but something is worth reading, and never
affects Status. The guarantee note above is a warning, not an issue: the
client is allowed to overrule it.

## 6. The entitlement record

Table `{prefix}french_path_entitlements`.

| Column | Meaning |
| --- | --- |
| `user_id` | Learner. |
| `course_id` | LearnDash course. |
| `package_id` | Package that granted it. |
| `order_id` | Order that paid for it. |
| `position` | Index in the package ladder. |
| `status` | `entitled` or `revoked`. |
| `guarantee` | 1 when the granting package carries the guarantee. |
| `unlocked` | 1 when the learner may enter the course today. |
| `unlocked_at`, `unlocked_by` | When, and by whom. |
| `granted_at` | When the entitlement was written. |

`UNIQUE KEY (user_id, course_id, order_id)` is what makes purchase
processing idempotent. Most gateways fire both the `processing` and the
`completed` transition for the same order.

**Entitlement and access are two different facts.** Entitlement means paid
for. Unlocked means enterable. Buying the twelve sublevel pathway writes
twelve entitlement rows and unlocks one.

A course granted by more than one package has more than one row. The
unlocked flag is kept consistent across all of them: `set_unlocked()` always
writes to every live row for that learner and course, and `is_unlocked()`
returns true when any of them is set.

A row that comes back from a refund is reset to locked, so re-paying an order
opens an entry sublevel again rather than silently restoring what was open.

## 7. Access

The plugin runs its own enrolment. It does not rely on the LearnDash
WooCommerce integration, which enrols a buyer in every course attached to a
product at once — the opposite of a progressive pathway.

**A locked sublevel is not enrolled at all.** LearnDash denies it natively.
Nothing is left open if this plugin stops running.

| Gesture | Effect |
| --- | --- |
| `French_Path_Access::unlock()` | Sets the flag and calls `ld_update_course_access()`. Refuses when there is no live entitlement. |
| `French_Path_Access::lock()` | Clears the flag and removes LearnDash access. |
| `French_Path_Access::unlock_entry()` | Opens the first course in the ladder that is not already open. |
| `French_Path_Access::revoke_orphaned()` | After a refund, removes access only for courses no other live entitlement still covers. |

Progression never calls `lock()`. A completed sublevel stays open for the
rest of the pathway.

### Entry sublevel

The entry sublevel is the first course in the package ladder that is not
already open for that learner. A learner who owns A1.1 and then buys the
complete pathway therefore starts on A1.2.

Milestone 3 will set the entry sublevel from the stream chosen at checkout by
hooking `french_path_entry_course`.

## 8. Purchase flow

Hooked on `woocommerce_order_status_processing`,
`woocommerce_order_status_completed` and `woocommerce_payment_complete`:

1. Load the order. Bail without a WooCommerce order or a registered user.
2. Resolve every line item to a product, variation first, then parent.
3. Find every package pointing at that product.
4. Write an entitlement row for each course in each package.
5. Open the entry sublevel of each package not already recorded in the order
   meta `_french_path_entry`.

Reversal is hooked on `woocommerce_order_status_refunded`,
`woocommerce_order_status_cancelled`, `woocommerce_order_status_failed` and
`woocommerce_order_refunded`: rows for that order become `revoked`, LearnDash
access is removed for any course no other live entitlement covers, and
`_french_path_entry` is deleted.

### Site requirement

Package products must leave the LearnDash `_related_course` field **empty**.
When that field is set, the LearnDash WooCommerce integration enrols the
buyer in every listed course the moment the order is paid, which defeats
progressive unlock. This is a configuration rule, not something the plugin
enforces.

## 9. Hooks

Actions fired:

| Hook | Arguments |
| --- | --- |
| `french_path_loaded` | — |
| `french_path_entitlement_granted` | `$user_id, $course_id, $data` |
| `french_path_entitlement_revoked` | `$user_id, $course_id, $order_id` |
| `french_path_course_unlocked` | `$user_id, $course_id, $actor_id` |
| `french_path_course_locked` | `$user_id, $course_id, $actor_id` |

Filters offered:

| Hook | Arguments |
| --- | --- |
| `french_path_package_kinds` | `$kinds` |
| `french_path_entry_course` | `$course_id, $user_id, $package_id, $courses` |
| `french_path_capability_roles` | `$roles` |
| `french_path_shortcodes` | `$shortcodes` |
| `french_path_locked_reason` | `$reason, $course_id, $user_id` |
| `french_path_template_directories` | `$directories, $name` |
| `french_path_buying_options` | `$options, $course_id` |
| `french_path_add_to_cart_url` | `$url, $package_id, $product_id` |
| `french_path_capability_roles` | `$capabilities` (capability => role slugs) |
| `french_path_package_issues` | `$issues, $package_id` |
| `french_path_package_warnings` | `$warnings, $package_id` |
| `french_path_course_label` | `$label, $course_id` |

### Naming a course

`French_Path_Shortcodes::course_label()` is what the plugin calls a course in
a button or heading. It reads the site's own `course_short_name` custom field,
so the enrol button says "Enrol in A1.1" rather than "Enrol in A1.1 —
Foundations". The field is empty on most courses, so it falls back to the post
title: a missing short name must never leave a button reading "Enrol in".

## 9a. Capabilities

Two, granted to `administrator` on activation, and deliberately separate.

| Capability | Gates |
| --- | --- |
| `french_path_manage_learners` | The learner and release screen. |
| `french_path_manage_packages` | Creating and editing packages. Every capability on the `fp_package` post type maps to it. |

Neither is `manage_options` or `edit_users`: the Milestone 3 coordinator role
must be able to release sublevels without reaching the payment settings or the
user table. It gets `french_path_manage_learners` and **not**
`french_path_manage_packages`, because a package decides which product grants
which sublevels, and that is a pricing decision.

`french_path_capability_roles` filters the whole capability-to-roles map.

## 9d. Package readiness

`French_Path_Package::get_issues()` returns plain sentences saying why a
package will not sell. An empty result means it is ready. It is shown as a
Status column in the package list and as a notice on the package edit screen,
because the client builds packages without a developer and a package that
silently does nothing has to say why.

It reports: no sublevels, an unpublished sublevel in the ladder, no product,
an unpublished product, a product with no price, and a product that still has
a LearnDash `_related_course` set - the one configuration that silently
defeats progressive unlock.

## 9b. Shortcodes

| Tag | Renders |
| --- | --- |
| `[french_path_my_courses]` | The learner's pathway: every sublevel they paid for, each marked open or locked, with the reason a locked one is closed. Attributes: `title`, `class`, `show_empty`. |
| `[french_path_enrol]` | The enrol card on a single course page. Attributes: `course`, `placement_url`, `pathway_url`, `note`, `reg_was`, `reg_now`, `class`. |

### The enrol card

One card, three states, decided per learner:

| State | Who | What it shows |
| --- | --- | --- |
| Not bought | a visitor, or a learner with no entitlement | The lowest price of any package containing the course, the registration promotion, an enrol button that opens the buying options, and the placement test link. |
| Paid, locked | bought inside a package, not released | No price at all. Status, `Step n of m`, a three rung window, a disabled button and the reason it is closed. |
| Open | released | No price. Status, the window, and a button to the first step they have not finished. |

The ladder is drawn as a **three rung window** - the rung before, the one they
are on, the one after - because a twelve sublevel pathway will not fit on a
card. `French_Path_Pathway::window()` slides it and clamps at both ends, so it
holds three wherever the learner stands. A package of one course draws no
ladder at all: "Step 1 of 1" tells nobody anything.

**A learner who has paid never sees a price again.** That is the whole point
of the card owning the price rather than the page builder: a shortcode that
only replaced the button would leave the price and the promotion sitting above
it.

### The registration fee

The registration fee is a product of its own, not part of any package, so the
plugin has to be told which one. It is chosen once on **French Path >
Settings** and never typed into a shortcode.

The card reads that product's regular and sale price. A sale price renders as
the normal fee struck through beside the promotional one and the label reads
"Registration promotion"; no sale price renders the fee plainly and the label
reads "Registration fee". The client therefore starts and ends a promotion by
adding or removing a sale price on the product, with nothing else to edit.

Resolution order, most specific first: the literal `reg_was` / `reg_now`
attributes, then the `reg_product` attribute, then the setting. With none of
them set the row does not render at all - and it never renders for a learner
who has already paid.

The card must replace the whole cream panel in the Elementor course template,
not just its button.

Templates live in `templates/` and may be overridden by copying them to
`yourtheme/french-path/`.

## 9c. Meeting a locked sublevel

A locked sublevel is not enrolled, so LearnDash refuses it and redirects a
lesson, topic or quiz URL back to its course. `French_Path_Locked` marks that
redirect with `?french-path=locked` so the course page can explain why.

The query argument is a hint only. `French_Path_Locked::current_reason()`
re-reads entitlement from the database, so nobody can produce the message by
typing the argument.

Steps are mapped to courses with `learndash_get_courses_for_step()`, never
`learndash_get_course_id()`: this site has shared course steps enabled, and
that function reads `$_GET['course_id']`. A step shared by more than one
course is treated as unresolvable and claims no reason.

## 10. Options

| Option | Meaning |
| --- | --- |
| `french_path_version` | Plugin version last activated. |
| `french_path_db_version` | Schema version the table is at. |
| `french_path_settings` | Array of settings. Keys below. |

`french_path_settings` holds:

| Key | Meaning |
| --- | --- |
| `registration_product` | The WooCommerce product the site charges as a one-off registration fee. 0 means the enrol card shows no fee at all. |

`French_Path_Settings::save()` drops any key not in `defaults()`, so the option
cannot grow fields this spec does not describe.

No other option key exists. Do not invent one.

## 11. Uninstall

`uninstall.php` deletes the two options and nothing else. The entitlements
table and the package posts survive, because deleting a plugin is not a
decision to destroy records of payment.

## 12. Not yet built

Named here so nobody assumes they exist.

- The coordinator ROLE. Milestone 3 creates it and gives it
  `french_path_manage_learners`.
- Streams and seat reservation (Milestone 3).
- The three dashboards (Milestone 4). The release screen built here is the
  admin-side version of what M4.1.3 wraps in a front end.

## 13. What this site's course page actually renders

Established by reading the live site, and load bearing for anything that
touches the single course page.

**LearnDash renders nothing there.** Elementor Pro template 2005, condition
`include/singular/sfwd-courses`, replaces the whole single course page and
contains no post-content widget, so `the_content` never runs on a course
post. Every LearnDash course template hook - `learndash-course-before`,
`ld_after_course_status_template_container`, `learndash_content`, the whole
`learndash-course-infobar-*` family and `learndash_payment_button_closed` -
is dead on this site. A live course page contains `elementor-2005` and zero
occurrences of `learndash-wrapper`, `ld-course-status` or `btn-join`.

The price on screen is a static Elementor heading, identical on all ten
courses. The enrol button is an Elementor button linking to `/register/`,
its label built from the SCF field `course_short_name`.

Therefore the plugin must never hook LearnDash's course templates, and must
not target Elementor element IDs either - those change when the client edits
the template. Anything the course page needs to show comes from a shortcode
the client places, which is what M2.4.5 builds.

`learndash_access_redirect` is the exception: it fires at `template_redirect`,
before any template, and is verified working on this site.
