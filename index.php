<?php

// ----------------------------------------------------------
// iRODS Consortium Voting Booth
//
// Provides an OAuth wrapped, minimal interface into a
// GitHub-backed voting mechanism for the iRODS Consortium.
//
// Terrell Russell
// RENCI
//
// Requires:
//   Local copy of https://github.com/csphere/GithubApiWrapper-PHP
// ----------------------------------------------------------

// boilerplate
include_once "config.php";
session_start();

// returns whether the current user is logged into github via OAuth or not
function logged_in() {
    if ($_SESSION['githubuser'] != "") { return true; }
    else { return false; }
}

// prints consistent page header for the app
function votingbooth_header(){

    echo "<h2><a href='".$_SERVER['SCRIPT_NAME']."'>iRODS Consortium Voting Booth</a></h2>";
#    echo "op[$op]<br />\n";

    # check for logged in user
    if ( logged_in() ) {
        echo "Logged In: [".$_SESSION['githubuser']."] - ";
        echo "<a href='".$_SERVER['SCRIPT_NAME']."?op=logout'>Logout</a>";
    }
    else {
        echo "<a href='".$_SERVER['SCRIPT_NAME']."?op=login'>Login via GitHub</a>";
    }
    echo "<hr />\n";

    # display cookie and session information for debugging
    echo "COOKIE: ".print_r($_COOKIE, true); echo "<hr />\n";
    echo "SESSION: ".print_r($_SESSION, true); echo "<hr />\n";

}

// wraps the github API json return values as a regular php array
function callapi( $apiobj, $verb, $path, array $data = array() ) {
    return json_decode($apiobj->executeRequest( $verb, $path, $data ), true);
}

# do the operation requested
$op = print_r($_GET['op'],true);
switch ($op) {

    ##############################################################################################
    case "login":
    ##############################################################################################

        if ( logged_in() ) {
            // already logged in
            header("Location: ".$_SERVER['SCRIPT_NAME']);
        }
        else {
            // Requests access & redirects to the callback URL defined at github
            $newgithub = new GithubOAuth( GITHUB_ID, GITHUB_SECRET );
            $scope = array( 'user', 'public_repo' );
            $newgithub->requestAccessCode( $scope );
        }
        break;

    ##############################################################################################
    case "callback":
    ##############################################################################################

        // use access code to exchange for access token
        $github = new GithubOAuth( GITHUB_ID, GITHUB_SECRET );
        $github->setTokenFromCode( $_GET['code'] );

        // save access token in the session
        $_SESSION['access_token'] = $github->getToken();

        // save the authenticated user information in the session
        $response = callapi( $github, 'GET', '/user' );
        $_SESSION['githubuser'] = $response['response']['login'];
        // echo "<pre>";
        // print_r($response);
        // echo "</pre>";
        header("Location: ".$_SERVER['SCRIPT_NAME']);
        break;

    ##############################################################################################
    case "logout":
    ##############################################################################################

        # clear all session and cookie information
        session_destroy();
        unset($_SESSION);
        unset($_COOKIE);
        # redirect to front page
        header("Location: ".$_SERVER['SCRIPT_NAME']);
        break;

    ##############################################################################################
    case "showcomments":
    ##############################################################################################

        votingbooth_header();
        # construct comments URL and issue API call
        $github = new GithubOAuth( GITHUB_ID, GITHUB_SECRET );
        $issueID = intval($_GET['id']);
        $command = "/repos/".GITHUB_ACCOUNT."/".GITHUB_REPOSITORY."/issues/$issueID/comments";
        echo "command[$command]<br />";
        $response = callapi( $github, 'GET', $command );
        echo "<pre>";
        print_r($response);
        echo "</pre>";
        break;

    ##############################################################################################
    case "upvote":
    ##############################################################################################

        if( ! logged_in() ) {
            header("Location: ".$_SERVER['SCRIPT_NAME']);
        }
        votingbooth_header();
        # construct vote URL and issue API call
        $github = new GithubOAuth( GITHUB_ID, GITHUB_SECRET );
        $github->setToken($_SESSION['access_token']);
        $issueID = intval($_GET['id']);
        $command = "/repos/".GITHUB_ACCOUNT."/".GITHUB_REPOSITORY."/issues/$issueID/comments";
        echo "command[$command]<br />";
        $content = array ('body' => 'Consortium Vote: +1');
        echo "content[";
        print_r($content);
        echo "]<br />";
        $response = callapi( $github, 'POST', $command, $content );
        echo "<pre>";
        print_r($response);
        echo "</pre>";
        break;

    ##############################################################################################
    case "downvote":
    ##############################################################################################

        votingbooth_header();
        break;

    ##############################################################################################
    default:
    ##############################################################################################

        votingbooth_header();

        // get issues in the voting booth
        $github = new GithubOAuth( GITHUB_ID, GITHUB_SECRET );
        $command = "/repos/".GITHUB_ACCOUNT."/".GITHUB_REPOSITORY."/issues?labels=invotingbooth";
        $response = callapi( $github, 'GET', $command );

        foreach ($response['response'] as $i) {
            $t = new DateTime($i['created_at'], new DateTimeZone('UTC'));
            // echo $t->format("Y-m-d H:i:s");
            // echo $t->format("F j, Y, g:ia");
            echo " - ".$i['number']." ".$i['title']." (".$t->format("F j, Y, g:ia").")";
            if( $i['comments'] > 0 ) {
                echo " -- <a href='".$_SERVER['SCRIPT_NAME']."?op=showcomments&id=".$i['number']."'>Show ".$i['comments']." Comments</a>";
            }
            else {
                echo " -- No Comments";
            }
            if( logged_in() ) {
                echo " -- <a href='".$_SERVER['SCRIPT_NAME']."?op=upvote&id=".$i['number']."'>Vote for this feature</a>";
            }
            echo "<br />";
        }

    ##############################################################################################
}
