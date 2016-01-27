<?php
/**
 * @package   spotify-to-vk
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2014, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 * @version   1.0.3
 */
/**
 * @param string $url
 *
 * @return int
 */
function get_file_size ($url) {
	return @(int)get_headers($url, true)['Content-Length'];
}

/**
 * @param string $string
 *
 * @return string
 */
function decode_special_chars ($string) {
	$string = preg_replace_callback("/(&#x[0-9]+;)/", function ($m) {
		return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
	}, $string);
	$string = html_entity_decode($string);
	return $string;
}

/**
 * @param string $spotify_id
 * @param string $artist
 * @param string $title
 * @param int    $duration
 * @param string $access_token
 */
function find_and_download ($spotify_id, $artist, $title, $duration, $access_token) {
	$artist = decode_special_chars(trim($artist));
	$title  = decode_special_chars(trim($title));
	/**
	 * API request
	 */
	$q      = urlencode("$artist - $title");
	$result = json_decode(
		file_get_contents("https://api.vk.com/method/audio.search?q=$q&count=20&&access_token=$access_token"),
		true
	);
	/**
	 * Error happened
	 */
	if (isset($result['error'])) {
		/**
		 * If too many requests - wait for 3 seconds and try again
		 */
		if ($result['error']['error_code'] == 6) {
			unset($q, $result);
			sleep(3);
			find_and_download($spotify_id, $artist, $title, $duration, $access_token);
		} else {
			echo "$artist - $title: {$result['error']['error_msg']}\n";
		}
		return;
	}
	$result = array_slice($result['response'], 1);
	if (!$result) {
		file_put_contents(__DIR__.'/spotify_fail.csv', "$spotify_id;$artist - $title;$duration\n", FILE_APPEND);
		echo "Failed: $artist - $title\n";
		return;
	}
	/**
	 * Normalize found tracks and calculate file size
	 */
	foreach ($result as &$r) {
		$r['title']  = decode_special_chars(trim($r['title']));
		$r['artist'] = decode_special_chars(trim($r['artist']));
		$r           = [
			'title'              => $r['title'],
			'title_levenshtein'  => levenshtein($r['title'], $title),
			'artist'             => $r['artist'],
			'artist_levenshtein' => levenshtein($r['artist'], $artist),
			'size'               => get_file_size($r['url']),
			'url'                => $r['url'],
			'duration'           => $r['duration'],
			'duration_diff'      => abs($r['duration'] - $duration),
			'not_remix'          => !preg_match('/remix/i', $title) && !preg_match('/remix/i', $r['title'])
		];
	}
	unset($r);
	/**
	 * Sort found tracks by better fit and quality
	 */
	usort($result, function ($track1, $track2) use ($artist, $title) {
		if ($track1['title_levenshtein'] != $track2['title_levenshtein']) {
			return $track1['title_levenshtein'] < $track2['title_levenshtein'] ? -1 : 1;
		}
		if ($track1['artist_levenshtein'] != $track2['artist_levenshtein']) {
			return $track1['artist_levenshtein'] < $track2['artist_levenshtein'] ? -1 : 1;
		}
		if ($track1['not_remix'] != $track2['not_remix']) {
			return $track1['not_remix'] ? -1 : 1;
		}
		if ($track1['duration_diff'] != $track2['duration_diff']) {
			return $track1['duration_diff'] < $track2['duration_diff'] ? -1 : 1;
		}
		return $track1['size'] > $track2['size'] ? -1 : 1;
	});
	if (!is_dir('spotify')) {
		mkdir('spotify');
	}
	$found = $result[0];
	unset($result);
	file_put_contents(__DIR__.'/spotify/'.str_replace('/', '|', "$artist - $title.mp3"), file_get_contents($found['url']));
	file_put_contents(__DIR__.'/spotify_success.csv', "$spotify_id;$artist - $title;$duration;$found[artist] - $found[title];$found[duration]\n", FILE_APPEND);
	echo "Succeed: $artist - $title\n";
	echo "Actually downloaded: $found[artist] - $found[title]\n";
}

$access_token = trim(file_get_contents(__DIR__.'/access_token.txt'));
preg_match_all('/([a-z0-9]{22})/Uims', file_get_contents('spotify.txt'), $spotify_ids);
$spotify_ids = $spotify_ids[1];

echo "Spotify-to-vk started. Found " . count($spotify_ids) . " track(s).\n";

foreach ($spotify_ids as $spotify_id) {

	$url =  "https://api.spotify.com/v1/tracks/$spotify_id";
	$json = json_decode(@file_get_contents($url));

	if($json === NULL){
		echo "Unable to get info from Spotify for track: " . $url . "\n";
		continue;
	}

	$artist   = $json->artists[0]->name;
	$title    = $json->name;
	$duration =  round($json->duration_ms/1000);

	echo "Got spotify info for $artist - $title ({$duration}sec). Attempting vk.com download...\n";


	find_and_download($spotify_id, $artist, $title, $duration, $access_token);
}
