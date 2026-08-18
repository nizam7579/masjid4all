<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Travel prayer planner - the computation behind Sofia's "plan my solat while
 * travelling" answer.
 *
 * Replaces solat_for_travellers() in enaizi/sofia.php, which is dead twice
 * over: it hangs off fluentform/submission_inserted for form 53 and FluentForm
 * was deactivated on 2026-08-14, and it calls whatsapp_send_message(), which no
 * longer exists. Its prompt is the part worth keeping and the fiqh shape below
 * follows it.
 *
 * The important difference: that version asked the AI to work out the prayer
 * times themselves. A language model will invent plausible times, and the whole
 * point of this feature is getting solat right across a time zone change. So
 * every number here is computed - geocoding from Nominatim, prayer times from
 * Aladhan, distance by haversine - and the AI, if used at all, only writes prose
 * around facts it is handed.
 *
 * Fiqh basis, stated rather than assumed: qasar is treated as available beyond
 * two marhalah (~90km), the position commonly followed in Malaysia and the
 * wider Shafi'i context. Madhhabs differ on the threshold and on which prayers
 * may be combined, so mfa_travel_plan() returns `borderline` when the distance
 * sits close enough for the school to change the answer, and the reply says so.
 * On any long journey - certainly any flight - the schools agree, and the
 * caveat stays quiet.
 */

/** Two marhalah, in kilometres. Filterable rather than scattered as a literal. */
function mfa_travel_qasar_threshold_km() {
	return (float) apply_filters( 'mfa_travel_qasar_threshold_km', 90 );
}

/**
 * Distance band where the madhhab actually changes the ruling. Outside it the
 * schools agree and the caveat is noise.
 */
function mfa_travel_borderline_band() {
	return apply_filters( 'mfa_travel_borderline_band', array( 70, 120 ) );
}

/**
 * Forward-geocode a place name.
 *
 * Same Nominatim service the visitor-location cookie already uses, with the
 * same manners: a real User-Agent (they block the default), a long cache, and a
 * short lock so a burst of messages cannot hammer them.
 *
 * @return array|WP_Error array( lat, lng, label )
 */
function mfa_travel_geocode( $place ) {
	$place = trim( wp_strip_all_tags( (string) $place ) );

	if ( mb_strlen( $place ) < 2 ) {
		return new WP_Error( 'mfa_travel_place', 'Place name too short.' );
	}

	$key    = 'mfa_travel_geo_' . md5( mb_strtolower( $place ) );
	$cached = get_transient( $key );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	// Nominatim asks for no more than one request a second.
	$lock = 'mfa_travel_geo_lock';
	if ( get_transient( $lock ) ) {
		usleep( 1100000 );
	}
	set_transient( $lock, 1, 2 );

	$url = 'https://nominatim.openstreetmap.org/search?' . http_build_query(
		array(
			'q'              => $place,
			'format'         => 'json',
			'limit'          => 1,
			'addressdetails' => 1,
			// Without this Nominatim answers in the local script - Jeddah comes
			// back as "جدة, محافظة جدة, ..." which is no use in an English reply.
			'accept-language' => 'en',
		)
	);

	$response = wp_remote_get(
		$url,
		array(
			'timeout'    => 12,
			'user-agent' => 'Masjid4All/1.0 (+' . home_url() . ')',
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

	if ( empty( $body[0]['lat'] ) || empty( $body[0]['lon'] ) ) {
		return new WP_Error( 'mfa_travel_geocode', 'Could not find that place.' );
	}

	$result = array(
		'lat'   => (float) $body[0]['lat'],
		'lng'   => (float) $body[0]['lon'],
		'label' => isset( $body[0]['display_name'] ) ? (string) $body[0]['display_name'] : $place,
	);

	set_transient( $key, $result, 30 * DAY_IN_SECONDS );

	return $result;
}

/**
 * Prayer times for a place on a date, from Aladhan - the same source the site's
 * prayer-times widget already uses, so a traveller sees consistent numbers.
 *
 * Aladhan also returns the location's timezone, which is what makes the time
 * zone arithmetic possible; Nominatim does not give one.
 *
 * @param string $date_ymd Y-m-d, in the location's own local date.
 * @return array|WP_Error array( times => [Fajr..Isha], timezone )
 */
function mfa_travel_prayer_times( $lat, $lng, $date_ymd, $method = 2 ) {
	$key    = 'mfa_travel_pt_' . md5( $lat . '|' . $lng . '|' . $date_ymd . '|' . $method );
	$cached = get_transient( $key );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$date = date_create_from_format( 'Y-m-d', $date_ymd );

	if ( ! $date ) {
		return new WP_Error( 'mfa_travel_date', 'Bad date.' );
	}

	$url = 'https://api.aladhan.com/v1/timings/' . $date->format( 'd-m-Y' ) . '?' . http_build_query(
		array(
			'latitude'  => $lat,
			'longitude' => $lng,
			'method'    => $method,
		)
	);

	$response = wp_remote_get( $url, array( 'timeout' => 15 ) );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

	if ( empty( $body['data']['timings'] ) ) {
		return new WP_Error( 'mfa_travel_times', 'Could not read prayer times.' );
	}

	$wanted = array( 'Fajr', 'Sunrise', 'Dhuhr', 'Asr', 'Maghrib', 'Isha' );
	$times  = array();

	foreach ( $wanted as $name ) {
		if ( isset( $body['data']['timings'][ $name ] ) ) {
			// Aladhan appends a zone marker on some responses ("05:12 (+08)").
			$times[ $name ] = trim( preg_replace( '/\s*\(.*\)$/', '', $body['data']['timings'][ $name ] ) );
		}
	}

	$result = array(
		'times'    => $times,
		'timezone' => isset( $body['data']['meta']['timezone'] ) ? (string) $body['data']['meta']['timezone'] : 'UTC',
		'date'     => $date_ymd,
	);

	set_transient( $key, $result, 7 * DAY_IN_SECONDS );

	return $result;
}

/** Great-circle distance in kilometres. */
function mfa_travel_distance_km( $lat1, $lng1, $lat2, $lng2 ) {
	$r = 6371;

	$dlat = deg2rad( $lat2 - $lat1 );
	$dlng = deg2rad( $lng2 - $lng1 );

	$a = sin( $dlat / 2 ) * sin( $dlat / 2 )
		+ cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $dlng / 2 ) * sin( $dlng / 2 );

	return $r * 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
}

/**
 * Turn a prayer clock time into a DateTimeImmutable in that place's own zone.
 */
function mfa_travel_prayer_moment( $date_ymd, $clock, $timezone ) {
	try {
		$tz = new DateTimeZone( $timezone );
	} catch ( Exception $e ) {
		$tz = new DateTimeZone( 'UTC' );
	}

	$moment = date_create_immutable_from_format( 'Y-m-d H:i', $date_ymd . ' ' . $clock, $tz );

	return $moment ?: null;
}

/**
 * Build the full plan.
 *
 * @param string $from            Departure place as typed.
 * @param string $to              Arrival place as typed.
 * @param string $depart_date     Y-m-d, local to the departure city.
 * @param string $depart_time     H:i, local to the departure city.
 * @param float  $duration_hours  Journey length.
 * @param string $mode            free text: flight, car, bus, train.
 *
 * @return array|WP_Error Facts only - no prose, nothing rounded away.
 */
function mfa_travel_plan( $from, $to, $depart_date, $depart_time, $duration_hours, $mode = '' ) {
	$origin = mfa_travel_geocode( $from );

	if ( is_wp_error( $origin ) ) {
		return new WP_Error( 'mfa_travel_from', 'I could not find *' . $from . '*. Could you give the city name again?' );
	}

	$dest = mfa_travel_geocode( $to );

	if ( is_wp_error( $dest ) ) {
		return new WP_Error( 'mfa_travel_to', 'I could not find *' . $to . '*. Could you give the city name again?' );
	}

	$distance_km = mfa_travel_distance_km( $origin['lat'], $origin['lng'], $dest['lat'], $dest['lng'] );
	$threshold   = mfa_travel_qasar_threshold_km();
	$band        = mfa_travel_borderline_band();

	$depart_times = mfa_travel_prayer_times( $origin['lat'], $origin['lng'], $depart_date );

	if ( is_wp_error( $depart_times ) ) {
		return $depart_times;
	}

	$origin_tz = $depart_times['timezone'];

	try {
		$depart_local = new DateTimeImmutable( $depart_date . ' ' . $depart_time, new DateTimeZone( $origin_tz ) );
	} catch ( Exception $e ) {
		return new WP_Error( 'mfa_travel_depart', 'I could not read that departure time.' );
	}

	$duration_minutes = (int) round( max( 0, (float) $duration_hours ) * 60 );
	$arrive_local_o   = $depart_local->modify( '+' . $duration_minutes . ' minutes' );

	// The arrival city's own clock - this is the bit travellers get wrong.
	$arrive_times = mfa_travel_prayer_times( $dest['lat'], $dest['lng'], $arrive_local_o->setTimezone( new DateTimeZone( $origin_tz ) )->format( 'Y-m-d' ) );

	if ( is_wp_error( $arrive_times ) ) {
		return $arrive_times;
	}

	$dest_tz      = $arrive_times['timezone'];
	$arrive_local = $arrive_local_o->setTimezone( new DateTimeZone( $dest_tz ) );

	// Re-read arrival-city times for the arrival city's local date, which may
	// differ from the departure city's date on a long or eastward flight.
	if ( $arrive_local->format( 'Y-m-d' ) !== $arrive_times['date'] ) {
		$corrected = mfa_travel_prayer_times( $dest['lat'], $dest['lng'], $arrive_local->format( 'Y-m-d' ) );

		if ( ! is_wp_error( $corrected ) ) {
			$arrive_times = $corrected;
		}
	}

	$offset_hours = ( $arrive_local->getOffset() - $depart_local->getOffset() ) / 3600;

	// Which prayers fall while actually in transit, judged in real time so the
	// time zone change cannot double-count or skip one.
	//
	// Both schedules are checked, not just the destination's. On the overnight
	// KL->Jeddah run, Fajr passes mid-flight by Kuala Lumpur's clock while
	// Jeddah's Fajr is still an hour after landing - checking only the arrival
	// city reported "nothing in transit" for a flight with a prayer in it. A
	// traveller reckons by where they departed from until they land, so the
	// departure city's times are the ones that usually matter in the air.
	$in_transit = array();

	$schedules = array(
		array( 'city' => mfa_travel_short_place( $origin['label'] ), 'tz' => $origin_tz, 'date' => $depart_times['date'], 'times' => $depart_times['times'] ),
	);

	// An overnight departure needs the departure city's *next* day too. Leaving
	// Kuala Lumpur at 23:30, the Fajr that passes in the air is the 11th's, not
	// the 10th's - fetching only the departure date found nothing and reported
	// a red-eye flight as having no prayer in it.
	$depart_next = $arrive_local->setTimezone( new DateTimeZone( $origin_tz ) )->format( 'Y-m-d' );

	if ( $depart_next !== $depart_times['date'] ) {
		$next = mfa_travel_prayer_times( $origin['lat'], $origin['lng'], $depart_next );

		if ( ! is_wp_error( $next ) ) {
			$schedules[] = array( 'city' => mfa_travel_short_place( $origin['label'] ), 'tz' => $origin_tz, 'date' => $next['date'], 'times' => $next['times'] );
		}
	}

	$schedules[] = array( 'city' => mfa_travel_short_place( $dest['label'] ), 'tz' => $dest_tz, 'date' => $arrive_times['date'], 'times' => $arrive_times['times'] );

	foreach ( $schedules as $schedule ) {
		foreach ( $schedule['times'] as $name => $clock ) {
			if ( 'Sunrise' === $name ) {
				continue;
			}

			$moment = mfa_travel_prayer_moment( $schedule['date'], $clock, $schedule['tz'] );

			if ( ! $moment || $moment <= $depart_local || $moment >= $arrive_local ) {
				continue;
			}

			// Keep the first sighting of a prayer - the departure city's, since
			// that schedule is listed first and is the one in force in the air.
			if ( ! isset( $in_transit[ $name ] ) ) {
				// Carry the exact schedule this was read from. The departure
				// city appears twice (its departure date and the next), so
				// matching on city name alone later picked the wrong day and
				// silently mis-answered whether the prayer could wait.
				$in_transit[ $name ] = array(
					'clock' => $moment->setTimezone( new DateTimeZone( $schedule['tz'] ) )->format( 'H:i' ),
					'city'  => $schedule['city'],
					'date'  => $schedule['date'],
					'tz'    => $schedule['tz'],
					'times' => $schedule['times'],
				);
			}
		}
	}

	$qasar = $distance_km >= $threshold;

	// Can each in-transit prayer be moved to the ground at one end or the
	// other, or does its whole window pass in the air?
	$onboard = mfa_travel_onboard_required( $in_transit, $schedules, $depart_local, $arrive_local );

	return array(
		'from'            => $origin,
		'to'              => $dest,
		'mode'            => $mode,
		'distance_km'     => round( $distance_km ),
		'qasar_allowed'   => $qasar,
		'threshold_km'    => $threshold,
		'borderline'      => ( $distance_km >= $band[0] && $distance_km <= $band[1] ),
		'depart_local'    => $depart_local->format( 'D, j M Y H:i' ),
		'depart_tz'       => $origin_tz,
		'arrive_local'    => $arrive_local->format( 'D, j M Y H:i' ),
		'arrive_tz'       => $dest_tz,
		'offset_hours'    => $offset_hours,
		'duration_hours'  => (float) $duration_hours,
		'times_from'      => $depart_times['times'],
		'times_to'        => $arrive_times['times'],
		'in_transit'      => $in_transit,
		'onboard'         => $onboard,
		'crosses_date'    => $depart_local->format( 'Y-m-d' ) !== $arrive_local->format( 'Y-m-d' ),
	);
}

/**
 * Which in-transit prayers cannot be shifted to the ground.
 *
 * A prayer only has to be performed in the air when neither end works: it
 * cannot be brought forward to before departure (jamak taqdim) and cannot be
 * held until after landing (jamak ta'khir). Fajr is the case that bites -
 * it combines with nothing, so on an overnight flight whose window falls
 * wholly between takeoff and landing there is no alternative.
 *
 * Windows, with jamak taken into account:
 *   Fajr    Fajr -> Sunrise            (no combining either way)
 *   Dhuhr   Dhuhr -> Maghrib           (ta'khir with Asr)
 *   Asr     Dhuhr -> Maghrib           (taqdim with Dhuhr)
 *   Maghrib Maghrib -> next Fajr       (ta'khir with Isha)
 *   Isha    Maghrib -> next Fajr       (taqdim with Maghrib)
 *
 * @return array prayer => reason, empty when everything can be prayed on the
 *               ground.
 */
function mfa_travel_onboard_required( $in_transit, $schedules, $depart_local, $arrive_local ) {
	if ( empty( $in_transit ) ) {
		return array();
	}

	$bounds = array(
		'Fajr'    => array( 'Fajr', 'Sunrise' ),
		'Dhuhr'   => array( 'Dhuhr', 'Maghrib' ),
		'Asr'     => array( 'Dhuhr', 'Maghrib' ),
		'Maghrib' => array( 'Maghrib', null ),
		'Isha'    => array( 'Maghrib', null ),
	);

	$onboard = array();

	foreach ( $in_transit as $prayer => $info ) {
		if ( ! isset( $bounds[ $prayer ] ) ) {
			continue;
		}

		// The schedule this prayer was actually sighted in, carried on the
		// entry itself rather than looked up by city.
		if ( empty( $info['times'] ) || empty( $info['date'] ) || empty( $info['tz'] ) ) {
			continue;
		}

		$schedule = array(
			'times' => $info['times'],
			'date'  => $info['date'],
			'tz'    => $info['tz'],
		);

		list( $earliest_key, $latest_key ) = $bounds[ $prayer ];

		$earliest = isset( $schedule['times'][ $earliest_key ] )
			? mfa_travel_prayer_moment( $schedule['date'], $schedule['times'][ $earliest_key ], $schedule['tz'] )
			: null;

		if ( null === $latest_key ) {
			// Maghrib/Isha run to the following dawn; approximate that as the
			// same schedule's Fajr a day on, which is within a minute or two.
			$latest = isset( $schedule['times']['Fajr'] )
				? mfa_travel_prayer_moment( $schedule['date'], $schedule['times']['Fajr'], $schedule['tz'] )
				: null;
			$latest = $latest ? $latest->modify( '+1 day' ) : null;
		} else {
			$latest = isset( $schedule['times'][ $latest_key ] )
				? mfa_travel_prayer_moment( $schedule['date'], $schedule['times'][ $latest_key ], $schedule['tz'] )
				: null;
		}

		if ( ! $earliest || ! $latest ) {
			continue;
		}

		$can_pray_before = ( $earliest <= $depart_local );
		$can_pray_after  = ( $latest >= $arrive_local );

		if ( ! $can_pray_before && ! $can_pray_after ) {
			$onboard[ $prayer ] = ( 'Fajr' === $prayer )
				? 'Fajr cannot be combined with any other prayer, and its whole time falls during the flight.'
				: 'Its time passes entirely during the journey, at both ends.';
		}
	}

	return $onboard;
}

/**
 * Render the plan as Sofia's reply.
 *
 * Deliberately templated rather than AI-written. Every figure here is one the
 * traveller will act on, and a model rephrasing "Asr 16:32" has nothing to gain
 * and a prayer to lose. The AI's turn comes after this, for questions.
 */
function mfa_travel_format_reply( $plan ) {
	$from_city = mfa_travel_short_place( $plan['from']['label'] );
	$to_city   = mfa_travel_short_place( $plan['to']['label'] );

	$out  = "🕋 *Solat plan for your journey*\n\n";
	$out .= "*{$from_city}* ➡️ *{$to_city}*\n";
	$out .= "Depart: {$plan['depart_local']} ({$plan['depart_tz']})\n";
	$out .= "Arrive: {$plan['arrive_local']} ({$plan['arrive_tz']})\n";
	$out .= 'Distance: ' . number_format_i18n( $plan['distance_km'] ) . " km\n";

	if ( 0 != $plan['offset_hours'] ) {
		$dir  = $plan['offset_hours'] > 0 ? 'ahead of' : 'behind';
		$out .= 'Time difference: ' . abs( $plan['offset_hours'] ) . ' hour(s) ' . $dir . " your departure city\n";
	}

	if ( $plan['crosses_date'] ) {
		$out .= "⚠️ You arrive on a different calendar day — the prayer times below are for your *arrival* date.\n";
	}

	$out .= "\n*Prayer times at " . $to_city . "* (arrival date)\n";

	foreach ( $plan['times_to'] as $name => $clock ) {
		if ( 'Sunrise' === $name ) {
			continue;
		}
		$out .= "• {$name}: {$clock}\n";
	}

	if ( ! empty( $plan['in_transit'] ) ) {
		$out .= "\n*Falls while you are travelling:*\n";

		foreach ( $plan['in_transit'] as $name => $info ) {
			$out .= "• {$name} — {$info['clock']} ({$info['city']} time)\n";
		}
	}

	$out .= "\n";

	if ( $plan['qasar_allowed'] ) {
		$out .= "✅ Your journey is about " . number_format_i18n( $plan['distance_km'] ) . " km, beyond the two-marhalah distance (~" . (int) $plan['threshold_km'] . " km), so *qasar* (shortening Zuhr, Asr and Isha to 2 raka'at) applies.\n\n";
		$out .= "You may also *jamak* (combine) Zuhr with Asr, and Maghrib with Isha — either early (taqdim, at the earlier prayer's time) or late (ta'khir, at the later one's).\n\n";

		if ( ! empty( $plan['in_transit'] ) ) {
			$names   = array_keys( $plan['in_transit'] );
			$onboard = isset( $plan['onboard'] ) ? $plan['onboard'] : array();
			$ground  = array_values( array_diff( $names, array_keys( $onboard ) ) );

			// Fajr is never combined with another prayer, so it must not be
			// swept into the jamak sentence - it is prayed in its own time,
			// before departure or after landing.
			$fajr_on_ground = in_array( 'Fajr', $ground, true );
			$combinable     = array_values( array_diff( $ground, array( 'Fajr' ) ) );

			if ( ! empty( $combinable ) ) {
				$verb = ( 1 === count( $combinable ) ) ? 'falls' : 'fall';
				$out .= mfa_travel_list_names( $combinable ) . " {$verb} during the journey. The simplest option is to combine "
					. ( 1 === count( $combinable ) ? 'it' : 'them' )
					. " before you depart, or on arrival — whichever you can perform settled and facing qiblah.\n\n";
			}

			if ( $fajr_on_ground ) {
				$out .= "Fajr falls during the journey. It is never combined with another prayer, so pray it in its own time — before you depart if it has come in, or as soon as you land while the time still holds.\n\n";
			}

			if ( ! empty( $onboard ) ) {
				$verb = ( 1 === count( $onboard ) ) ? 'needs' : 'need';
				$out .= "✈️ *" . mfa_travel_list_names( array_keys( $onboard ) ) . "* {$verb} to be prayed on board.\n";
				$out .= reset( $onboard ) . " You cannot delay it to after you land, and its time has not come in before you leave.\n\n";
				$out .= "On board: pray at your seat if you cannot stand safely, face the qiblah as you begin if you can and simply continue as the aircraft turns, and use tayammum if no water is available. The prayer is valid.\n\n";
			}
		}
	} else {
		$out .= "ℹ️ Your journey is about " . number_format_i18n( $plan['distance_km'] ) . " km, short of the two-marhalah distance (~" . (int) $plan['threshold_km'] . " km), so pray as normal — no qasar or jamak.\n\n";
	}

	if ( $plan['borderline'] ) {
		$out .= "📌 This distance is close to the threshold, and the schools of fiqh differ here. The figures above follow the two-marhalah (~90 km) position commonly used in Malaysia. Please confirm with your local religious authority.\n\n";
	}

	$out .= "Find a masjid along the way: " . home_url( '/mosque/' ) . "\n\n";
	$out .= "Safe travels, and may Allah accept your prayers. 🤲\nAsk me anything else about this journey.";

	return $out;
}

/**
 * Nominatim returns a long postal-style label; the first couple of parts are
 * what a person would call the place.
 */
function mfa_travel_short_place( $label ) {
	$parts = array_map( 'trim', explode( ',', (string) $label ) );

	if ( count( $parts ) <= 2 ) {
		return implode( ', ', $parts );
	}

	return $parts[0] . ', ' . end( $parts );
}

/**
 * "Fajr", "Fajr and Isha", "Dhuhr, Asr and Isha" - reads as a sentence rather
 * than a comma-joined list.
 */
function mfa_travel_list_names( $names ) {
	$names = array_values( array_filter( $names ) );
	$count = count( $names );

	if ( 0 === $count ) {
		return '';
	}

	if ( 1 === $count ) {
		return $names[0];
	}

	$last = array_pop( $names );

	return implode( ', ', $names ) . ' and ' . $last;
}
