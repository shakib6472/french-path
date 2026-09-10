<?php
/**
 * The client can build a package without a developer, and is told when one
 * will not work.
 *
 * @package French_Path
 *
 * @var French_Path_Tests $t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$t->group( 'Capabilities are separated' );

French_Path_Roles::grant();

$admin = get_role( 'administrator' );

$t->ok( $admin && $admin->has_cap( French_Path_Roles::CAP_MANAGE_PACKAGES ), 'administrators may build packages' );

$editor = get_role( 'editor' );
$t->ok(
	$editor && ! $editor->has_cap( French_Path_Roles::CAP_MANAGE_PACKAGES ),
	'editors may not build packages just because they can edit posts'
);

$t->ok(
	French_Path_Roles::CAP_MANAGE_PACKAGES !== French_Path_Roles::CAP_MANAGE_LEARNERS,
	'building packages and releasing sublevels are separate powers'
);

$caps = French_Path_Roles::get_capabilities();
$t->ok( isset( $caps[ French_Path_Roles::CAP_MANAGE_LEARNERS ] ), 'the learner capability is declared' );
$t->ok( isset( $caps[ French_Path_Roles::CAP_MANAGE_PACKAGES ] ), 'the package capability is declared' );

$type = get_post_type_object( French_Path_Package::POST_TYPE );
$t->same(
	French_Path_Roles::CAP_MANAGE_PACKAGES,
	(string) $type->cap->edit_posts,
	'the package screen is gated on the package capability'
);

$t->group( 'A package says why it will not sell' );

$course  = $t->course( 'issue A1.1' );
$product = $t->product( 'issue product' );

$empty = $t->package( 'issue empty', array(), 0 );
$issues = French_Path_Package::get_issues( $empty );

$t->ok( ! French_Path_Package::is_ready( $empty ), 'an empty package is not ready' );
$t->same( 2, count( $issues ), 'an empty package reports both its problems' );
$t->ok( false !== strpos( implode( ' ', $issues ), 'No sublevels yet' ), 'it names the missing sublevels' );
$t->ok( false !== strpos( implode( ' ', $issues ), 'No product' ), 'it names the missing product' );

$no_product = $t->package( 'issue no product', array( $course ), 0 );
$t->same( 1, count( French_Path_Package::get_issues( $no_product ) ), 'a package with sublevels only lacks a product' );

$priced = $t->package( 'issue priced', array( $course ), $product );
update_post_meta( $product, '_price', '175000' );
update_post_meta( $product, '_regular_price', '175000' );

$t->ok(
	false === strpos( implode( ' ', French_Path_Package::get_issues( $priced ) ), 'no price' ),
	'a product with a price stops being reported as priceless'
);

// The one configuration that silently breaks progressive unlock.
update_post_meta( $product, '_related_course', array( $course ) );

$t->ok(
	false !== strpos( implode( ' ', French_Path_Package::get_issues( $priced ) ), 'LearnDash course attached' ),
	'a product still wired to LearnDash is reported, because it would enrol buyers in everything at once'
);

delete_post_meta( $product, '_related_course' );

// An unpublished sublevel is a real trap: the buyer pays and reaches nothing.
$draft = $t->course( 'issue draft' );
wp_update_post(
	array(
		'ID'          => $draft,
		'post_status' => 'draft',
	)
);

$with_draft = $t->package( 'issue draft package', array( $course, $draft ), $product );

$t->ok(
	false !== strpos( implode( ' ', French_Path_Package::get_issues( $with_draft ) ), 'not published' ),
	'an unpublished sublevel in the ladder is reported'
);

$t->group( 'Admin surface' );

$columns = French_Path_Admin::columns( array( 'title' => 'Title' ) );
$t->ok( isset( $columns['french_path_status'] ), 'the list table reports readiness' );

ob_start();
French_Path_Admin::column( 'french_path_status', $empty );
$broken = (string) ob_get_clean();

$t->ok( false !== strpos( $broken, 'problem' ), 'a broken package is flagged in the list' );

$ready = $t->package( 'issue ready', array( $course ), $product );

ob_start();
French_Path_Admin::column( 'french_path_status', $ready );
$fine = (string) ob_get_clean();

$t->ok( false !== strpos( $fine, 'Ready' ), 'a complete package reads as ready' );
$t->ok( French_Path_Package::is_ready( $ready ), 'and is ready' );
