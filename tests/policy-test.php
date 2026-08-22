<?php
require_once __DIR__ . '/bootstrap.php';
require_once dirname( __DIR__ ) . '/includes/class-update-policy-version.php';
require_once dirname( __DIR__ ) . '/includes/class-update-policy-offers.php';
require_once dirname( __DIR__ ) . '/includes/class-update-policy-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-update-policy-core.php';

/* ---- what counts as a major ---- */

up_test_eq( Update_Policy_Version::branch( '7.0.4' ), '7.0', 'a branch is the first two segments' );
up_test_eq( Update_Policy_Version::branch( '7.1' ), '7.1', 'with or without a third' );
up_test_eq( Update_Policy_Version::branch( 'nonsense' ), '', 'and unreadable input has none' );

up_test_ok( Update_Policy_Version::is_major( '7.0.4', '7.1' ), '7.0.4 to 7.1 crosses a branch' );
up_test_ok( Update_Policy_Version::is_major( '6.9', '7.0' ), 'and so does 6.9 to 7.0' );
up_test_ok( ! Update_Policy_Version::is_major( '7.0.4', '7.0.5' ), 'a fix on the same branch does not' );
up_test_ok( ! Update_Policy_Version::is_major( '7.1', '7.1' ), 'nor does standing still' );
up_test_ok( ! Update_Policy_Version::is_major( '7.1', '7.0.5' ), 'nor does going backwards' );

// Anything unreadable resolves towards not holding: a site stuck on an old
// WordPress because a version string could not be parsed would be a fault
// nobody could see.
up_test_ok( ! Update_Policy_Version::is_major( '', '7.1' ), 'an unreadable installed version holds nothing' );
up_test_ok( ! Update_Policy_Version::is_major( '7.0', '' ), 'nor does an unreadable offer' );

/* ---- the window ---- */

$day = 86400;
$t0  = 1000000;

up_test_ok( Update_Policy_Version::is_held( $t0, 30, $t0 ), 'a release seen today is held' );
up_test_ok( Update_Policy_Version::is_held( $t0, 30, $t0 + ( 29 * $day ) ), 'still held on the last day' );
up_test_ok( ! Update_Policy_Version::is_held( $t0, 30, $t0 + ( 30 * $day ) ), 'and free once the window has passed' );
up_test_eq( Update_Policy_Version::held_until( $t0, 30 ), $t0 + ( 30 * $day ), 'the end of the window is arithmetic, not a guess' );

// The setting turning the feature off and the setting being nonsense are the
// same code path on purpose.
up_test_ok( ! Update_Policy_Version::is_held( $t0, 0, $t0 ), 'zero days holds nothing' );
up_test_ok( ! Update_Policy_Version::is_held( $t0, -5, $t0 ), 'and neither does a negative window' );

// Never seen means the site has not recorded it yet, which is the one case
// where waiting is safer than installing.
up_test_ok( Update_Policy_Version::is_held( 0, 30, $t0 ), 'an unseen release is held until the next check records it' );

/* ---- filtering the offers ---- */

$updates = array(
	(object) array( 'current' => '7.0.5', 'response' => 'upgrade' ),
	(object) array( 'current' => '7.1', 'response' => 'upgrade' ),
);

$kept = Update_Policy_Offers::filter( $updates, '7.0.4', array( '7.1' => $t0 ), array(), 30, $t0 + $day );
up_test_eq( count( $kept ), 1, 'the held major is removed' );
up_test_eq( Update_Policy_Offers::version_of( array_shift( $kept ) ), '7.0.5', 'and the maintenance release in the same list survives' );

// This is the whole point of the module: security and maintenance releases are
// never touched, because they are what keeps a site alive.
$only_minor = Update_Policy_Offers::filter(
	array( (object) array( 'current' => '7.0.5' ) ),
	'7.0.4',
	array(),
	array(),
	30,
	$t0
);
up_test_eq( count( $only_minor ), 1, 'a site offered only a maintenance release is left entirely alone' );

$after = Update_Policy_Offers::filter( $updates, '7.0.4', array( '7.1' => $t0 ), array(), 30, $t0 + ( 31 * $day ) );
up_test_eq( count( $after ), 2, 'once the window passes the major comes back' );

$released = Update_Policy_Offers::filter( $updates, '7.0.4', array( '7.1' => $t0 ), array( '7.1' => 1 ), 30, $t0 + $day );
up_test_eq( count( $released ), 2, 'and a branch someone released by hand is never held again' );

// A release on a branch that is already released stays released - 7.1.1 is the
// same decision as 7.1, and asking again eight days later would be a hold the
// operator already answered.
$point_one = Update_Policy_Offers::filter(
	array( (object) array( 'current' => '7.1.1' ) ),
	'7.0.4',
	array( '7.1' => $t0 ),
	array( '7.1' => 1 ),
	30,
	$t0 + $day
);
up_test_eq( count( $point_one ), 1, 'a fix on a released branch is not held' );

// Input this cannot read is passed through rather than dropped.
$odd = Update_Policy_Offers::filter( array( (object) array( 'response' => 'upgrade' ), 'nonsense' ), '7.0.4', array(), array(), 30, $t0 );
up_test_eq( count( $odd ), 2, 'an offer with no version is left where it was' );
up_test_eq( Update_Policy_Offers::filter( 'not an array', '7.0.4', array(), array(), 30, $t0 ), 'not an array', 'and a transient of the wrong shape is returned untouched' );

// Keys are preserved: WordPress and other plugins index this array.
$keyed = Update_Policy_Offers::filter(
	array( 'a' => (object) array( 'current' => '7.0.5' ), 'b' => (object) array( 'current' => '7.1' ) ),
	'7.0.4',
	array( '7.1' => $t0 ),
	array(),
	30,
	$t0
);
up_test_ok( isset( $keyed['a'] ) && ! isset( $keyed['b'] ), 'the surviving offers keep their own keys' );

/* ---- stamping ---- */

$stamps = Update_Policy_Offers::stamp( array(), $updates, '7.0.4', $t0 );
up_test_eq( $stamps, array( '7.1' => $t0 ), 'a first sighting is recorded for the major, and only the major' );

$later = Update_Policy_Offers::stamp( $stamps, $updates, '7.0.4', $t0 + ( 5 * $day ) );
up_test_eq( $later, array( '7.1' => $t0 ), 'a later check does not move it - a window that restarts is not a window' );

$two = Update_Policy_Offers::stamp( array( '7.1' => $t0 ), array( (object) array( 'current' => '7.2' ) ), '7.0.4', $t0 + $day );
up_test_eq( $two, array( '7.1' => $t0, '7.2' => $t0 + $day ), 'and a second major gets a window of its own' );

/* ---- the read applies the hold and writes nothing ---- */

$GLOBALS['wp_version']       = '7.0.4';
$GLOBALS['up_stub_options'] = array(
	'update_policy' => array( 'hold_days' => 30, 'seen' => array( '7.1' => time() ), 'released' => array() ),
);
$before = $GLOBALS['up_stub_options'];

$transient = (object) array(
	'updates'      => array(
		(object) array( 'current' => '7.1' ),
		(object) array( 'current' => '7.0.5' ),
	),
	'last_checked' => 123,
);
$filtered = Update_Policy_Core::apply_hold( $transient );

up_test_eq( count( $filtered->updates ), 1, 'the read removes the held major' );
up_test_eq( $GLOBALS['up_stub_options'], $before, 'and writes nothing at all' );
up_test_eq( count( $transient->updates ), 2, 'the object WordPress owns is left as it was' );
up_test_eq( $filtered->last_checked, 123, 'and everything else on it is carried across' );

// Nothing held: the same object comes back, not a copy, so a site with no
// policy in play is byte-for-byte what WordPress produced.
$GLOBALS['up_stub_options']['update_policy']['hold_days'] = 0;
up_test_ok( Update_Policy_Core::apply_hold( $transient ) === $transient, 'with nothing held the transient is returned untouched' );

// A transient of the wrong shape is somebody else having got there first.
up_test_eq( Update_Policy_Core::apply_hold( false ), false, 'and a transient that is not an object is left alone' );

/* ---- recording a sighting, from the stored value ---- */

$GLOBALS['up_stub_options']         = array();
$GLOBALS['up_stub_site_transients'] = array(
	'update_core' => (object) array(
		'updates' => array(
			(object) array( 'current' => '7.1' ),
			(object) array( 'current' => '7.0.5' ),
		),
	),
);

Update_Policy_Core::record_sightings();
$saved = $GLOBALS['up_stub_options']['update_policy'];
up_test_ok( isset( $saved['seen']['7.1'] ), 'the check records the major it was offered' );
up_test_ok( ! isset( $saved['seen']['7.0'] ), 'and records nothing for the maintenance release' );

$first = $saved['seen']['7.1'];
Update_Policy_Core::record_sightings();
up_test_eq(
	$GLOBALS['up_stub_options']['update_policy']['seen']['7.1'],
	$first,
	'a second check leaves the first sighting where it was'
);

// The stamp has to be read from the stored transient rather than through the
// module's own filter. Reading the filtered copy would hide the very major
// being held, so it would never be recorded - and never being recorded means
// held forever, the one outcome this module must not produce.
$GLOBALS['up_stub_options'] = array(
	'update_policy' => array( 'hold_days' => 30, 'seen' => array(), 'released' => array() ),
);
Update_Policy_Core::record_sightings();
up_test_ok(
	isset( $GLOBALS['up_stub_options']['update_policy']['seen']['7.1'] ),
	'a major that is currently held is still recorded, so its window can end'
);

// A module switched on while a major is already offered has missed the check
// that would have stamped it. The hold is right either way, but the notice
// would have had no dates to show - the epoch, printed as 1970 - so the stamp
// is taken on the first admin page load as well as on a check.
$GLOBALS['up_stub_options'] = array(
	'update_policy' => array( 'hold_days' => 30, 'seen' => array(), 'released' => array() ),
);
Update_Policy_Core::record_sightings();
$held_now = Update_Policy_Core::held_majors();
up_test_ok( $held_now['7.1']['seen'] > 0, 'a release already offered when the module is switched on gets a real first-seen date' );
up_test_ok(
	$held_now['7.1']['until'] > $held_now['7.1']['seen'],
	'and a hold that ends after it began rather than in 1970'
);

/* ---- what the screen is told ---- */

$now = time();
$GLOBALS['up_stub_options'] = array(
	'update_policy' => array( 'hold_days' => 30, 'seen' => array( '7.1' => $now ), 'released' => array() ),
);
$held = Update_Policy_Core::held_majors();
up_test_eq( count( $held ), 1, 'one major is held' );
up_test_eq( $held['7.1']['version'], '7.1', 'named by the version being offered' );
up_test_eq( $held['7.1']['until'], $now + ( 30 * 86400 ), 'with the date the hold ends' );

// The unattended path agrees with the visible one.
up_test_ok( ! Update_Policy_Core::allow_major_auto( true ), 'unattended major updates are refused while one is held' );

Update_Policy_Core::release( '7.1' );
up_test_eq( Update_Policy_Core::held_majors(), array(), 'releasing a branch ends the hold' );
up_test_ok( Update_Policy_Core::allow_major_auto( true ), 'and stops overriding the unattended setting' );
up_test_ok(
	true === Update_Policy_Core::allow_major_auto( true ) && false === Update_Policy_Core::allow_major_auto( false ),
	'which it only ever narrows, never widens'
);

// A release nobody is allowed to make is not made.
$GLOBALS['up_stub_options'] = array(
	'update_policy' => array( 'hold_days' => 30, 'seen' => array( '7.1' => $now ), 'released' => array() ),
);
$GLOBALS['up_stub_denied_caps'] = array( 'update_core' );
up_test_ok( ! Update_Policy_Core::release( '7.1' ), 'releasing needs the capability to install a core update' );
up_test_eq( count( Update_Policy_Core::held_majors() ), 1, 'and the hold survives the attempt' );
$GLOBALS['up_stub_denied_caps'] = array();

// On multisite the policy is network-wide, and so is the right to change it.
$GLOBALS['up_stub_multisite']   = true;
$GLOBALS['up_stub_denied_caps'] = array( 'manage_network_options' );
up_test_ok( ! Update_Policy_Settings::may_decide(), 'a site administrator on a network does not decide for the network' );
up_test_ok( ! Update_Policy_Core::release( '7.1' ), 'and cannot release a hold' );
$GLOBALS['up_stub_denied_caps'] = array();
$GLOBALS['up_stub_multisite']   = false;

exit( up_test_summary() > 0 ? 1 : 0 );
