<?php

function wp_schedule_single_event( $timestamp, $hook, $args = array()) {
	$crons = _get_cron_array();
	$key = md5(serialize($args));
	$crons[$timestamp][$hook][$key] = array( 'schedule' => false, 'args' => $args );
	uksort( $crons, "strnatcasecmp" );
	_set_cron_array( $crons );
}

function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array()) {
	$crons = _get_cron_array();
	$schedules = wp_get_schedules();
	$key = md5(serialize($args));
	if ( !isset( $schedules[$recurrence] ) )
		return false;
	$crons[$timestamp][$hook][$key] = array( 'schedule' => $recurrence, 'args' => $args, 'interval' => $schedules[$recurrence]['interval'] );
	uksort( $crons, "strnatcasecmp" );
	_set_cron_array( $crons );
}

function wp_reschedule_event( $timestamp, $recurrence, $hook, $args = array()) {
	$crons = _get_cron_array();
	$schedules = wp_get_schedules();
	$key = md5(serialize($args));
	$interval = 0;

	// First we try to get it from the schedule
	if ( 0 == $interval )
		$interval = $schedules[$recurrence]['interval'];
	// Now we try to get it from the saved interval in case the schedule disappears
	if ( 0 == $interval )
		$interval = $crons[$timestamp][$hook][$key]['interval'];
	// Now we assume something is wrong and fail to schedule
	if ( 0 == $interval )
		return false;

	while ( $timestamp < time() + 1 )
		$timestamp += $interval;

	wp_schedule_event( $timestamp, $recurrence, $hook, $args );
}

function wp_unschedule_event( $timestamp, $hook, $args = array() ) {
	$crons = _get_cron_array();
	$key = md5(serialize($args));
	unset( $crons[$timestamp][$hook][$key] );
	if ( empty($crons[$timestamp][$hook]) )
		unset( $crons[$timestamp][$hook] );
	if ( empty($crons[$timestamp]) )
		unset( $crons[$timestamp] );
	_set_cron_array( $crons );
}

function wp_clear_scheduled_hook( $hook ) {
	$args = array_slice( func_get_args(), 1 );

	while ( $timestamp = wp_next_scheduled( $hook, $args ) )
		wp_unschedule_event( $timestamp, $hook, $args );
}

function wp_next_scheduled( $hook, $args = array() ) {
	$crons = _get_cron_array();
	$key = md5(serialize($args));
	if ( empty($crons) )
		return false;
	foreach ( $crons as $timestamp => $cron ) {
		if ( isset( $cron[$hook][$key] ) )
			return $timestamp;
	}
	return false;
}

/**
 * Send request to run cron through HTTP request that doesn't halt page loading.
 *
 * @since 2.1.0
 *
 * @return null CRON could not be spawned, because it is not needed to run.
 */
function spawn_cron() {
	$crons = _get_cron_array();

	if ( !is_array($crons) )
		return;

	$keys = array_keys( $crons );
	if ( array_shift( $keys ) > time() )
		return;

	$cron_url = get_option( 'siteurl' ) . '/wp-cron.php?check=' . wp_hash('187425');

	wp_remote_post($cron_url, array('timeout' => 0.01, 'blocking' => false));
}

function wp_cron() {
	// Prevent infinite loops caused by lack of wp-cron.php
	if ( strpos($_SERVER['REQUEST_URI'], '/wp-cron.php') !== false )
		return;

	$crons = _get_cron_array();

	if ( !is_array($crons) )
		return;

	$keys = array_keys( $crons );
	if ( isset($keys[0]) && $keys[0] > time() )
		return;

	$schedules = wp_get_schedules();
	foreach ( $crons as $timestamp => $cronhooks ) {
		if ( $timestamp > time() ) break;
		foreach ( (array) $cronhooks as $hook => $args ) {
			if ( isset($schedules[$hook]['callback']) && !call_user_func( $schedules[$hook]['callback'] ) )
				continue;
			spawn_cron();
			break 2;
		}
	}
}

function wp_get_schedules() {
	$schedules = array(
		'hourly' => array( 'interval' => 3600, 'display' => __('Once Hourly') ),
		'twicedaily' => array( 'interval' => 43200, 'display' => __('Twice Daily') ),
		'daily' => array( 'interval' => 86400, 'display' => __('Once Daily') ),
	);
	return array_merge( apply_filters( 'cron_schedules', array() ), $schedules );
}

function wp_get_schedule($hook, $args = array()) {
	$crons = _get_cron_array();
	$key = md5(serialize($args));
	if ( empty($crons) )
		return false;
	foreach ( $crons as $timestamp => $cron ) {
		if ( isset( $cron[$hook][$key] ) )
			return $cron[$hook][$key]['schedule'];
	}
	return false;
}

//
// Private functions
//

function _get_cron_array()  {
	$cron = get_option('cron');
	if ( ! is_array($cron) )
		return false;

	if ( !isset($cron['version']) )
		$cron = _upgrade_cron_array($cron);

	unset($cron['version']);

	return $cron;
}

function _set_cron_array($cron) {
	$cron['version'] = 2;
	update_option( 'cron', $cron );
}

function _upgrade_cron_array($cron) {
	if ( isset($cron['version']) && 2 == $cron['version'])
		return $cron;

	$new_cron = array();

	foreach ( (array) $cron as $timestamp => $hooks) {
		foreach ( (array) $hooks as $hook => $args ) {
			$key = md5(serialize($args['args']));
			$new_cron[$timestamp][$hook][$key] = $args;
		}
	}

	$new_cron['version'] = 2;
	update_option( 'cron', $new_cron );
	return $new_cron;
}

?>
