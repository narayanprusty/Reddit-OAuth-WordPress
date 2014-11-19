<?php

session_start();

function reddit_oauth_redirect()
{
	global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header;
	require_once("../wp-load.php");
	//construct URL and redirect
	$nonce = rand();
    $redirect_uri = get_site_url() . '/wp-admin/admin-ajax.php?action=reddit_oauth_callback';
    $client_id = get_option("reddit_client_id");

    setcookie("reddit_login_nonce",$nonce);

    header("Location: " . "https://ssl.reddit.com/api/v1/authorize?client_id=". $client_id ."&response_type=code&state=". $nonce ."&redirect_uri=". $redirect_uri ."&duration=permanent&scope=identity");

	die();
}

add_action("wp_ajax_reddit_oauth_redirect", "reddit_oauth_redirect");
add_action("wp_ajax_nopriv_reddit_oauth_redirect", "reddit_oauth_redirect");

function reddit_oauth_callback()
{
	global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header;
	require_once("../wp-load.php");

	$client_id = get_option("reddit_client_id");
    $client_secret = get_option("reddit_client_secret");
    $username = get_option("reddit_username");
    $redirect_uri = get_site_url() . '/wp-admin/admin-ajax.php?action=reddit_oauth_callback';

    if(isset($_GET["error"]))
    {
    	header("Location: " . get_site_url());
    }
    else
    {
    	if($_COOKIE["reddit_login_nonce"] == $_GET["state"])
        {
        	$url = "https://ssl.reddit.com/api/v1/access_token";
            $fields = array("grant_type" => "authorization_code", "code" => $_GET["code"], "redirect_uri" => $redirect_uri);

            foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
            rtrim($fields_string, '&');

            $ch = curl_init();

            curl_setopt($ch,CURLOPT_URL, $url);
            curl_setopt($ch,CURLOPT_POST, count($fields));
            curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $client_id.":".$client_secret);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);

            $result = curl_exec($ch);
            $result = json_decode($result);

            curl_close($ch);

            $access_token = $result->access_token;
            $refresh_token = $result->refresh_token;

            reddit_login_user($access_token, $refresh_token, $username);
        }
        else
        {
            header("Location: " . get_site_url());
        }
    }

    die();
}

add_action("wp_ajax_reddit_oauth_callback", "reddit_oauth_callback");
add_action("wp_ajax_nopriv_reddit_oauth_callback", "reddit_oauth_callback");

    function reddit_login_user($access_token, $refresh_token, $username)
    {
        //lets retrieve username of the logged in user
        $user_info_url = "https://oauth.reddit.com/api/v1/me";

        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$user_info_url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: bearer ".$access_token, "User-Agent: flairbot/1.0 by ".$username));
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);


        $result = curl_exec($ch);
        $result = json_decode($result);

        if(isset($result->error))
        {
            //access token has expired. Use the refresh token to get a new access token and then make REST api calls.
            $url = "https://ssl.reddit.com/api/v1/access_token";
            $fields = array("grant_type" => "refresh_token", "refresh_token" => $refresh_token);

            foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
            rtrim($fields_string, '&');

            $ch = curl_init();

            curl_setopt($ch,CURLOPT_URL, "https://ssl.reddit.com/api/v1/access_token");
            curl_setopt($ch,CURLOPT_POST, count($fields));
            curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $client_id.":".$client_secret);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);

            $result = curl_exec($ch);
            $result = json_decode($result);

            //new access token
            $access_token = $result->access_token;

            curl_close($ch);

            $user_info_url = "https://oauth.reddit.com/api/v1/me";

            $ch = curl_init();
            curl_setopt($ch,CURLOPT_URL,$user_info_url);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: bearer ".$access_token, "User-Agent: flairbot/1.0 by ".$username));
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);


            $result = curl_exec($ch);
            curl_close($ch);

            $result = json_decode($result);
            $username = $result->name;

            if(username_exists($username))
			{
				$user_id = username_exists($username);
				wp_set_auth_cookie($user_id);
				update_user_meta($user_id, "reddit_access_token", $access_token);
				update_user_meta($user_id, "reddit_refresh_token", $refresh_token);
				header('Location: ' . get_site_url());
			}
			else
			{
				//create a new account and then login
				wp_create_user($username, rand());
				$user_id = username_exists($username);
				wp_set_auth_cookie($user_id);
				update_user_meta($user_id, "reddit_access_token", $access_token);
				update_user_meta($user_id, "reddit_refresh_token", $refresh_token);
				header('Location: ' . get_site_url());
			}
        }
        else
        {
            $user_name = $result->name;

            if(username_exists($username))
			{
				$user_id = username_exists($username);
				wp_set_auth_cookie($user_id);
				update_user_meta($user_id, "reddit_access_token", $access_token);
				update_user_meta($user_id, "reddit_refresh_token", $refresh_token);
				header('Location: ' . get_site_url());
			}
			else
			{
				//create a new account and then login
				wp_create_user($username, rand());
				$user_id = username_exists($username);
				wp_set_auth_cookie($user_id);
				update_user_meta($user_id, "reddit_access_token", $access_token);
				update_user_meta($user_id, "reddit_refresh_token", $refresh_token);
				header('Location: ' . get_site_url());
			}
        }

        curl_close($ch);   
    }

    function reddit_refresh_access_token()
    {
    	$access_token = get_user_meta(get_current_user_id(), "reddit_access_token", true);
    	$refresh_token = get_user_meta(get_current_user_id(), "reddit_refresh_token", true);
    	$client_id = get_option("reddit_client_id");	
	    $client_secret = get_option("reddit_client_secret");
    
    	$url = "https://ssl.reddit.com/api/v1/access_token";
            $fields = array("grant_type" => "refresh_token", "refresh_token" => $refresh_token);

            foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
            rtrim($fields_string, '&');

            $ch = curl_init();

            curl_setopt($ch,CURLOPT_URL, "https://ssl.reddit.com/api/v1/access_token");
            curl_setopt($ch,CURLOPT_POST, count($fields));
            curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $client_id.":".$client_secret);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);

            $result = curl_exec($ch);
            $result = json_decode($result);

            //new access token
            $access_token = $result->access_token;

            curl_close($ch);
            update_user_meta(get_current_user_id(), "reddit_access_token", $access_token);
    }