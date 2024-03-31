<?php

// This script demonstrates how to share a post on Twitter using cURL requests.

define('DEBUG_MODE', $_ENV['DEBUG_MODE'] ?? false);

if(DEBUG_MODE === true) {
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
}

class TwitterUnofficialLib
{
	public function __construct(private string $authToken, private string $ct0 = "", private string $guestToken = "")
	{
		$this->authToken = $authToken;
	}

	private function pluck($array, $key) {
		return array_map(function($v) use ($key) {
		  return is_object($v) ? $v->$key : $v[$key];
		}, $array);
	}

	private static function getCookieValue($cookieString, $cookieName) {
		$cookieValue = '';
		$cookieLines = explode("\n", $cookieString);
		foreach ($cookieLines as $cookieLine) {
			$cookieParts = explode("\t", $cookieLine);
			if (count($cookieParts) >= 7 && $cookieParts[5] == $cookieName) {
				$cookieValue = trim($cookieParts[6]);
				break;
			}
		}
		return $cookieValue;
	}

	private function curl($url, $extraHeaders = [], $extraCurlOptions = [], $withCookies = false, $cookieFile = null, $returnResponse = true) {
		try {
			$defaultHeaders = [
				"Authorization: Bearer AAAAAAAAAAAAAAAAAAAAANRILgAAAAAAnNwIzUejRCOuH5E6I8xnZz4puTs%3D1Zv7ttfk8LF81IUq16cHjhLTvJu4FA33AGWWjCpTnA",
				"User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
				"Content-type: application/json",
			];
		
			$ch = curl_init();
		
			$defaultCurlOptions = [
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				// CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $extraHeaders),
			];
		
			if($withCookies) {
				if(!$cookieFile) exit("Cookie file is required when using cookies.");
	
				$defaultCurlOptions += [
					CURLOPT_COOKIEJAR => $cookieFile,
					CURLOPT_COOKIEFILE => $cookieFile,
				];
			}
	
			curl_setopt_array($ch, $defaultCurlOptions + $extraCurlOptions);
	
			if(DEBUG_MODE === true) {
				curl_setopt($ch, CURLOPT_VERBOSE, true);
				$verbose_output = fopen('php://temp', 'w+');
				curl_setopt($ch, CURLOPT_STDERR, $verbose_output);
			}
	
			$response = curl_exec($ch);
		
			curl_close($ch);
		
			if(DEBUG_MODE === true){
				if(curl_errno($ch)) exit("Curl error: " . curl_error($ch) . " - url($url)" . "\n");

				rewind($verbose_output);
				$verbose_info = stream_get_contents($verbose_output);
				fclose($verbose_output);
				echo "<pre>";
				print_r($verbose_info);
				echo "</pre>";
			}

			if($returnResponse) return $response;

			return true;
		} catch (Exception $e) {
			throw new Exception($e->getMessage());
		}
	}

	private function getCt0Cookie(): string
	{
		$url = "https://developer.twitter.com";

		$cookieFile = tempnam(sys_get_temp_dir(), 'cookie');

		$this->curl($url, extraCurlOptions: [CURLOPT_FOLLOWLOCATION => true], withCookies: true, cookieFile: $cookieFile);

		$cookies = file_get_contents($cookieFile);
		$ct0 = self::getCookieValue($cookies, "ct0");

		return $ct0;
	}

	private function getGuestToken()
	{
		$url = "https://api.twitter.com/1.1/guest/activate.json";

		$response = $this->curl($url, extraCurlOptions: [CURLOPT_POST => true, CURLOPT_FOLLOWLOCATION => true]);

		$guestToken = json_decode($response, true)["guest_token"];

		return $guestToken;
	}

	public function sharePost($tweetText, $suppressSpamProtection = false): string
	{
		$url = "https://twitter.com/i/api/graphql/v0en1yVV-Ybeek8ClmXwYw/CreateTweet";

		$payload = [
			"variables" => [
				"tweet_text" => $tweetText,
				"dark_request" => false,
				"media" => [
					"media_entities" => [],
					"possibly_sensitive" => false
				],
				"semantic_annotation_ids" => []
			],
			"features" => [
				"communities_web_enable_tweet_community_results_fetch" => true,
				"c9s_tweet_anatomy_moderator_badge_enabled" => true,
				"tweetypie_unmention_optimization_enabled" => true,
				"responsive_web_edit_tweet_api_enabled" => true,
				"graphql_is_translatable_rweb_tweet_is_translatable_enabled" => true,
				"view_counts_everywhere_api_enabled" => true,
				"longform_notetweets_consumption_enabled" => true,
				"responsive_web_twitter_article_tweet_consumption_enabled" => true,
				"tweet_awards_web_tipping_enabled" => false,
				"longform_notetweets_rich_text_read_enabled" => true,
				"longform_notetweets_inline_media_enabled" => true,
				"rweb_video_timestamps_enabled" => true,
				"responsive_web_graphql_exclude_directive_enabled" => true,
				"verified_phone_label_enabled" => false,
				"freedom_of_speech_not_reach_fetch_enabled" => true,
				"standardized_nudges_misinfo" => true,
				"tweet_with_visibility_results_prefer_gql_limited_actions_policy_enabled" => true,
				"responsive_web_graphql_skip_user_profile_image_extensions_enabled" => false,
				"responsive_web_graphql_timeline_navigation_enabled" => true,
				"responsive_web_enhance_cards_enabled" => false
			],
			"queryId" => "v0en1yVV-Ybeek8ClmXwYw"
		];

		if($suppressSpamProtection) {
			$payload["variables"]["reply"] = [
				/* THIS PART MAKES A NICE TRICK
					*  IF WE SET in_reply_to_tweet_id TO A NEGATIVE VALUE,
					*  IT WILL BE POSTED AS A NEW TWEET EVEN IF WE KEEP tweet_text SAME
					*  SO WE CAN MAKE SPAM SOMEHOW
					*  BUT LIMITS NOT MEASURED YET
					*/
				"in_reply_to_tweet_id" => random_int(1, 1_000_000) * -1,
				"exclude_reply_user_ids" => []
			];
		}

		$extraHeaders = [
			"x-guest-token: $this->guestToken",
			"x-twitter-active-user: yes",
			"x-twitter-client-language: en",
			"x-csrf-token: $this->ct0",
		];

		$cookieList = "auth_token=$this->authToken; ct0=$this->ct0";

		$extraCurlOptions = [
			CURLOPT_COOKIE => $cookieList,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => json_encode($payload),
			CURLOPT_HEADER => 0,
			CURLOPT_SSL_VERIFYHOST => 0,
			CURLOPT_SSL_VERIFYPEER => 1,
		];

		$response = $this->curl($url, $extraHeaders, $extraCurlOptions);

		if(isset(json_decode($response, true)["errors"])) {
			throw new Exception("Error: " . implode(', ',$this->pluck(json_decode($response, true)["errors"], 'message' )));
		}

		return $response;
	}

	public function post($tweetText, $suppressSpamProtection = false)
	{
		try {
			$this->ct0 = $this->getCt0Cookie();
			$this->guestToken = $this->getGuestToken();
	
			$this->sharePost($tweetText, $suppressSpamProtection);
	
			return 'success';
		} catch (Exception $e) {
			return DEBUG_MODE ? $e->getMessage() : 'error';
		}
	}

}