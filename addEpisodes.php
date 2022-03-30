<?php

	function getToken() {
		// Returns False if error encountered
		
		// Spotify vars
		$authUrl = 'https://accounts.spotify.com/api/token';
		$clientId = '';
		$secretId = '';	
		
		$data = array(
			'grant_type' => 'client_credentials',
		);

		$curl = curl_init($authUrl); 

		curl_setopt_array($curl, array(
			CURLOPT_POST => true,
			CURLOPT_FAILONERROR => true,
			CURLOPT_POSTFIELDS => http_build_query($data),
			CURLOPT_USERPWD => $clientId . ':' . $secretId,
			CURLOPT_HEADER => false,
			CURLOPT_RETURNTRANSFER => true
		)); 
		
		$response = curl_exec($curl);
		
		if (curl_errno($curl)) {
			return False;
		}
		
		curl_close($curl);
		
		try {
			return json_decode($response, true)["access_token"];
		} catch (Exception $e) {
			return False;
		}
	}
	
	function getLatestEpisodes($numEpisodes, $offset) {
		// Returns False if error encountered
		
		$episodes = "https://api.spotify.com/v1/shows/2sMHk8u0FXkPgsSnaT3qEP/episodes?market=ES&limit=".$numEpisodes."&offset=".$offset;
		$token = getToken();
		
		// If token fetch failed...
		if ($token === False) {
			print("Error fetching token.");
			return False;
		} else {
			$curl = curl_init();
			
			curl_setopt_array($curl, array(
				CURLOPT_HTTPHEADER => array('Content-Type: application/json' , "Authorization: Bearer ".$token),
				CURLOPT_FAILONERROR => true,
				CURLOPT_URL => $episodes,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT => 10,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => "GET"
			));
			
			$response = curl_exec($curl);
			
			if (curl_errno($curl)) {
				return False;
			}
			
			curl_close($curl);
			
			try {
				return json_decode($response, true)["items"];
			} catch (Exception $e) {
				return False;
			}
		}
	}
		
	function getLastId($episodesFile) {
		// Returns the latest episode's ID
		// Returns empty string if not found
		$f = fopen($episodesFile, 'r');
		
		// First line will be an HTML comment containing the latest episode's id
		$firstLine = fgets($f);
		preg_match("/\s*<!--([\s\S]+?)-->\s*/", $firstLine, $latestId);
		fclose($f);
		
		if (count($latestId) > 1) {
			return $latestId[1];
		} else {
			// Id not found
			return "";
		}
	}
		
	function createLinks($episodes) {
		$result = "<!--".$episodes[0]["id"]."-->
		";
		$i = 0;
		
		foreach($episodes as $ep){			
			$result .= '<a class="episodio" href="'.$ep["external_urls"]["spotify"].'" target="_blank"  title="'.$ep["name"].'" >
							<img src="'.$ep["images"][1]["url"].'" alt="'.$ep["name"].'" >
							<p>'.$ep["name"].'</p>
						</a>
						';
		}
		
		return $result;
	}
	
	$episodesFile = "../include/episodios.html";
	$latestId = getLastId($episodesFile);
	
	// If getLasId failed...
	if ($latestId === False) {
		print("Error when fetching last Id.");
	} else {
		// Number of episodes to get in each iteration
		$numEpisodes = 3;
		$episodeFound = False;
		$offset = 0;
		// Holds the episodes that are not in the file
		$episodesToAdd = [];

		// We need to find the last episode in the array $latestEpisodes
		while (!$episodeFound) {
			// Get the last $numEpisodes episodes
			$latestEpisodes = getLatestEpisodes($numEpisodes, $offset);
			
			if ($latestEpisodes === False) {
				print("Error getting latest episodes.");
				$episodesToAdd = Array();
				break;
			}
			
			$i = 0;
			// Check whether the last episode in the file is in latestEpisodes
			foreach ($latestEpisodes as $ep) {
				if ($latestId == $ep["id"]) {
					$episodeFound = True;
					break;
				} else {
					$episodesToAdd[] = $ep;
				}
			}
			
			$offset += $numEpisodes;
			
			if (empty($latestEpisodes)) {
				// No more episodes to fetch
				$episodeFound = True;
			}
		}
		
		if (!empty($episodesToAdd)) {
			prepend(createLinks($episodesToAdd), $episodesFile);
			print("Episodes added.");
		} else {
			print("No episodes to add.");
		}
	}
	
	// CREDITS for the prepend function: https://stackoverflow.com/questions/1760525/need-to-write-at-beginning-of-file-with-php
	// Adds string to the beggining of orig_filename
	function prepend($string, $orig_filename) {
		$context = stream_context_create();
		$orig_file = fopen($orig_filename, 'r', 1, $context);

		$temp_filename = tempnam(sys_get_temp_dir(), 'php_prepend_');
		file_put_contents($temp_filename, $string);
		file_put_contents($temp_filename, $orig_file, FILE_APPEND);

		fclose($orig_file);
		unlink($orig_filename);
		rename($temp_filename, $orig_filename);
	}
	
?>
