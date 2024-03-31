<?php

require_once 'twitter.php';

$authToken = "ABCDEF1234567890";

$twitterLib = new TwitterUnofficialLib($authToken);

$result = $twitterLib->post("This post shared from unofficial X(Twitter) library written in PHP!", suppressSpamProtection: true);

var_dump($result); // "success" | "error"
