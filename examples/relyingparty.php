<?php
/**
 * OpenID 
 * 
 * PHP Version 5.2.0+
 * 
 * @category  Auth
 * @package   OpenID
 * @author    Bill Shupp <hostmaster@shupp.org> 
 * @copyright 2009 Bill Shupp
 * @license   http://www.opensource.org/licenses/bsd-license.php FreeBSD
 * @link      http://pearopenid.googlecode.com
 */

// A tool for testing Relying Party functionality

require_once 'common/config.php';

if (!count($_GET) && !count($_POST)) {
    $contents = file_get_contents('common/rp_form.php');
    include_once 'common/wrapper.php';
    exit;
}

$identifier = null;
if (isset($_POST['identifier'])) {
    $identifier = $_POST['identifier'];
} else if (isset($_SESSION['identifier'])) {
    $identifier = $_SESSION['identifier'];
} else {
    $contents = 'No identifier available';
    include_once 'common/wrapper.php';
    exit;
}

$o = new OpenID_RelyingParty($identifier, $returnTo, $realm);
if (!empty($_POST['disable_associations'])
    || !empty($_SESSION['disable_associations'])) {

    $o->disableAssociations();
    $_SESSION['disable_associations'] = true;
}

$log = new OpenID_Observer_Log;
OpenID::attach($log);

if (isset($_POST['start'])) {


    $_SESSION['identifier'] = $identifier;
    try {
        $authRequest = $o->prepare();
    } catch (OpenID_Exception $e) {
        $contents  = "<div class='relyingparty_results'>\n";
        $contents .= "<pre>" . $e->getMessage() . "</pre>\n";
        $contents .= "</div class='relyingparty_results'>";
        include_once 'common/wrapper.php';
        exit;
    }

    // checkid_immediate
    if (!empty($_POST['checkid_immediate'])) {
        $authRequest->setMode('checkid_immediate');
    }

    // SREG
    if (!empty($_POST['sreg'])) {
        $sreg = new OpenID_Extension_SREG11(OpenID_Extension::REQUEST);
        $sreg->set('required', 'email');
        $sreg->set('optional', 'nickname,gender,dob');
        $authRequest->addExtension($sreg);
    }

    // AX
    if (!empty($_POST['ax'])) {
        $ax = new OpenID_Extension_AX(OpenID_Extension::REQUEST);
        $ax->set('type.email', 'http://axschema.org/contact/email');
        $ax->set('type.firstname', 'http://axschema.org/namePerson/first');
        $ax->set('type.lastname', 'http://axschema.org/namePerson/last');
        $ax->set('type.country', 'http://axschema.org/contact/country/home');
        $ax->set('type.language', 'http://axschema.org/pref/language');
        $ax->set('mode', 'fetch_request');
        $ax->set('required', 'email,firstname,lastname,language,country');
        $authRequest->addExtension($ax);
    }

    // UI
    if (!empty($_POST['ui'])) {
        $ui = new OpenID_Extension_UI(OpenID_Extension::REQUEST);
        $ui->set('mode', 'popup');
        $ui->set('language', 'en-US');
        $authRequest->addExtension($ui);
    }
    
    $url = $authRequest->getAuthorizeURL();
    
    if (empty($_POST['debug'])) {
        header("Location: $url");
        exit;
    }

    // Verify query before sending it (for debugging)
    $contents = "<h3>Here's what you're about to send:</h3>\n";
    $parsed   = parse_url($url);
    $qs       = array_pop($parsed);
    $qsArray  = explode('&', $qs);

    $contents .= "<b>Endpoint URL:</b> " . $parsed['scheme'] . '://';
    $contents .= $parsed['host'] . $parsed['path'];
    $contents .= "<p><b>Query String Keys / Values:</b><br><br>\n";
    $contents .= "<div class='relyingparty_results'>\n";
    $contents .= "<table border=0>";
    foreach ($qsArray as $pair) {
        list($key, $value) = explode('=', $pair);

        $contents .= "<tr><td align=left>" . urldecode($key) . '</td><td>';
        $contents .= urldecode($value) . "</td></tr>\n";
    }
    $contents .= "</table><br>";
    $contents .= "</div class='relyingparty_results'>";

    $contents .= "Proceed? <a href=\"$url\">Yes</a>";
    $contents .= " &nbsp | &nbsp <a href=\"./relyingparty.php\">No</a>\n";
    include_once 'common/wrapper.php';
    exit;
    
} else {
    if (isset($_SESSION['identifier'])) {
        $usid = $_SESSION['identifier'];
        unset($_SESSION['identifier']);
    } else {
        $usid = null;
    }

    unset($_SESSION['disable_associations']);

    if (!count($_POST)) {
        list(, $queryString) = explode('?', $_SERVER['REQUEST_URI']);
    } else {
        // I hate php sometimes
        $queryString = file_get_contents('php://input');
    }

    $message = new OpenID_Message($queryString, OpenID_Message::FORMAT_HTTP);
    $id      = $message->get('openid.claimed_id');
    $mode    = $message->get('openid.mode');
    $result  = $o->verify(new Net_URL2($returnTo . '?' . $queryString), $message);

    if ($result->success()) {
        $status  = "<tr><td>Status:</td><td><font color='green'>SUCCESS!";
        $status .= " ({$result->getAssertionMethod()})</font></td></tr>";
    } else {
        $status  = "<tr><td>Status:</td><td><font color='red'>FAIL!";
        $status .= " ({$result->getAssertionMethod()})</font></td></tr>";
    }

    $contents = "<div class='relyingparty_results'>
    <p>
    <table>
    <tr colspan=2><td><b>Results</b></td></tr>
    <tr><td>User Supplied Identifier:</td><td>$usid</td></tr>
    <tr><td>Claimed Identifier:</td><td>$id</td></tr>
    <tr><td>Mode:</td><td>$mode</td></tr>
    $status\n
    <tr colspan=2><td><p><br><b>Message Contents</b></td></tr>";

    foreach ($message->getArrayFormat() as $key => $value) {
        $contents .= "<tr><td align=left>$key</td><td>$value</td></tr>\n";
    }
    $contents .= "</table>";
    $contents .= "</div>";

    include_once 'common/wrapper.php';
}

?>
